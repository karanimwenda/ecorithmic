<?php

namespace App\Jobs\ProductEnrichment;

use App\Models\ProductEnrichment\Attribute;
use App\Models\ProductEnrichment\Import;
use App\Models\ProductEnrichment\Product;
use App\Models\ProductEnrichment\ProductAttributeValue;
use App\Models\ProductEnrichment\Source;
use App\Services\ProductEnrichment\OpenRouterClient;
use App\Services\ProductEnrichment\QuoteVerifier;
use App\Services\ProductEnrichment\ResearchResultsCache;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;

class ResearchProduct implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 180;

    public function __construct(
        public readonly Product $product,
        public readonly ?Import $import = null,
    ) {}

    public function handle(
        OpenRouterClient $openRouter,
        QuoteVerifier $quoteVerifier,
        ResearchResultsCache $cache,
    ): void {
        // Halt if the import's cost cap has been reached
        if ($this->import?->isHaltedForCost()) {
            return;
        }

        $identifiers = $this->buildIdentifiers();

        // FR-026: check the research-results cache first
        $cachedFacts = $cache->get($identifiers);
        $costUsd = 0.0;

        if ($cachedFacts !== null) {
            $facts = $cachedFacts;
        } else {
            // Call perplexity/sonar-pro-search for grounded research
            $prompt = $this->buildResearchPrompt($identifiers);
            $schema = $this->buildResponseSchema();

            $result = $openRouter->chat(
                model: 'perplexity/sonar-pro-search',
                messages: [['role' => 'user', 'content' => $prompt]],
                responseFormat: ['type' => 'json_schema', 'json_schema' => $schema],
            );

            $costUsd = $result['cost_usd'];
            $responseData = $result['response']['choices'][0]['message']['content'] ?? '{}';
            $facts = json_decode((string) $responseData, true) ?? [];

            // Store in cache for future reprocessing
            $cache->put($identifiers, $facts);
        }

        // Process each candidate fact
        $attributes = Attribute::where('is_ai_enrichable', true)->get()->keyBy('code');

        foreach ($facts['facts'] ?? [] as $fact) {
            $attributeCode = $fact['attribute_code'] ?? null;
            $value = $fact['value'] ?? null;
            $sourceUrl = $fact['source_url'] ?? null;
            $quote = $fact['quote'] ?? null;
            $sourceTrustHint = $fact['source_trust_hint'] ?? 'allowed';

            if (! $attributeCode || ! $value || ! isset($attributes[$attributeCode])) {
                continue;
            }

            $attribute = $attributes[$attributeCode];

            // FR-008: verify the source actually describes this exact product
            // (source_trust_hint from the model signals this; 'disallowed' = similar product)
            if ($sourceTrustHint === 'disallowed') {
                continue;
            }

            // Find or create a Source record
            $source = null;
            if ($sourceUrl) {
                $source = Source::firstOrCreate(
                    ['product_id' => $this->product->id, 'url' => $sourceUrl],
                    [
                        'domain' => parse_url($sourceUrl, PHP_URL_HOST) ?? 'unknown',
                        'trust_classification' => $sourceTrustHint === 'authoritative' ? 'authoritative' : 'allowed',
                        'is_usable' => true,
                        'fetched_at' => now(),
                    ]
                );

                // FR-009: verify the quote actually appears in the source
                if ($quote && ! $quoteVerifier->verify($quote, $sourceUrl)) {
                    // Quote cannot be verified — discard this fact entirely (SC-003)
                    continue;
                }
            }

            // Derive confidence tier per FR-012 (never from AI self-reported confidence)
            $confidenceTier = $this->deriveConfidenceTier($source);

            // Supersede existing current value for this attribute
            ProductAttributeValue::where('product_id', $this->product->id)
                ->where('attribute_id', $attribute->id)
                ->where('is_current', true)
                ->update(['is_current' => false]);

            $valueData = $this->buildValueData($attribute, $value);

            ProductAttributeValue::create(array_merge($valueData, [
                'product_id' => $this->product->id,
                'attribute_id' => $attribute->id,
                'origin' => 'ai_research',
                'source_id' => $source?->id,
                'evidence_quote' => $quote,
                'confidence_tier' => $confidenceTier,
                'review_status' => 'pending',
                'is_current' => true,
            ]));
        }

        // Accumulate cost, halt remaining if cap reached
        if ($costUsd > 0 && $this->import) {
            $this->import->accumulateCost($costUsd);
        }

        // Check for conflicting sources and mark them
        $this->detectAndMarkConflicts();

        // T103: gate copy generation until both research AND vision jobs are done
        $this->dispatchCopyGenerationIfReady();
    }

    public function failed(\Throwable $exception): void
    {
        // FR-010: product must never be left in a failed state — mark it as ungrounded
        // and ensure all its research-origin fields are capped at low confidence.
        $this->product->update(['is_ungrounded' => true]);

        // Cap any pending ai_research values to low confidence (they may be absent if
        // research failed before creating any, but this is a safety net).
        ProductAttributeValue::where('product_id', $this->product->id)
            ->where('origin', 'ai_research')
            ->where('review_status', 'pending')
            ->update(['confidence_tier' => 'low']);

        // Still mark the research flag as done so copy generation can proceed
        // (the product will be processed using photo/name only — FR-010)
        $allDone = $this->product->markEnrichmentFlag('research_done', ['research_done', 'vision_done']);
        if ($allDone) {
            GenerateProductCopy::dispatch($this->product, $this->import)->onQueue('copy-generation');
        }
    }

    private function detectAndMarkConflicts(): void
    {
        // Find attributes where multiple current rows exist from different sources
        $attributes = Attribute::where('is_ai_enrichable', true)->pluck('id');

        foreach ($attributes as $attributeId) {
            $currentValues = ProductAttributeValue::where('product_id', $this->product->id)
                ->where('attribute_id', $attributeId)
                ->where('is_current', true)
                ->where('origin', 'ai_research')
                ->where('review_status', 'pending')
                ->get();

            if ($currentValues->count() > 1) {
                // Check if values actually disagree
                $uniqueValues = $currentValues->pluck('text_value')->unique();
                if ($uniqueValues->count() > 1) {
                    $conflictGroupId = (string) Str::uuid();
                    $currentValues->each(fn ($v) => $v->update([
                        'review_status' => 'conflicted',
                        'conflict_group_id' => $conflictGroupId,
                    ]));
                }
            }
        }
    }

    private function dispatchCopyGenerationIfReady(): void
    {
        // T103: only dispatch copy generation when both research AND vision are complete
        $allDone = $this->product->markEnrichmentFlag('research_done', ['research_done', 'vision_done']);
        if ($allDone) {
            GenerateProductCopy::dispatch($this->product, $this->import)->onQueue('copy-generation');
        }
    }

    /**
     * @return array<string, string>
     */
    private function buildIdentifiers(): array
    {
        $values = $this->product->currentAttributeValues()
            ->whereHas('attribute', fn ($q) => $q->whereIn('code', ['sku', 'name', 'brand', 'gtin', 'mpn']))
            ->with('attribute')
            ->get()
            ->mapWithKeys(fn ($v) => [$v->attribute->code => (string) $v->getDisplayValue()]);

        return $values->all();
    }

    /**
     * @param  array<string, string>  $identifiers
     */
    private function buildResearchPrompt(array $identifiers): string
    {
        $productDesc = collect($identifiers)
            ->map(fn ($v, $k) => "$k: $v")
            ->implode(', ');

        return 'Research the following product and extract factual attributes. '
            ."Product: $productDesc. "
            .'Return structured JSON per the schema. Only include facts you can verify from actual web sources. '
            .'For each fact, quote the exact text from the source. '
            ."Mark source_trust_hint as 'authoritative' for manufacturer/official pages, 'allowed' for reliable retailers, 'disallowed' if the source describes a different product.";
    }

    /**
     * @return array<string, mixed>
     */
    private function buildResponseSchema(): array
    {
        return [
            'name' => 'product_research',
            'schema' => [
                'type' => 'object',
                'properties' => [
                    'facts' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'attribute_code' => ['type' => 'string'],
                                'value' => ['type' => 'string'],
                                'source_url' => ['type' => 'string'],
                                'quote' => ['type' => 'string'],
                                'source_trust_hint' => ['type' => 'string', 'enum' => ['authoritative', 'allowed', 'disallowed']],
                            ],
                            'required' => ['attribute_code', 'value', 'source_url', 'quote', 'source_trust_hint'],
                        ],
                    ],
                ],
                'required' => ['facts'],
            ],
        ];
    }

    private function deriveConfidenceTier(?Source $source): string
    {
        if (! $source) {
            return 'low';
        }

        return match ($source->trust_classification) {
            'authoritative' => 'high',
            'allowed' => 'medium',
            default => 'low',
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function buildValueData(Attribute $attribute, mixed $value): array
    {
        return match ($attribute->type) {
            'price' => ['float_value' => (float) $value, 'text_value' => null],
            'boolean' => ['boolean_value' => (bool) $value, 'text_value' => null],
            'json' => ['json_value' => is_array($value) ? $value : json_decode((string) $value, true), 'text_value' => null],
            default => ['text_value' => (string) $value],
        };
    }
}
