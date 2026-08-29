<?php

namespace App\Jobs\ProductEnrichment;

use App\Models\ProductEnrichment\Attribute;
use App\Models\ProductEnrichment\Import;
use App\Models\ProductEnrichment\Product;
use App\Models\ProductEnrichment\ProductAttributeValue;
use App\Services\ProductEnrichment\OpenRouterClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Generates descriptive copy for a product using only its already-established facts (FR-013).
 * Supports feedback-driven single-field regeneration (FR-022/US5).
 */
class GenerateProductCopy implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    /** Copy field codes this job is responsible for generating. */
    private const array COPY_FIELDS = [
        'short_description',
        'description',
        'bullet_points',
        'seo_title',
        'seo_summary',
    ];

    public function __construct(
        public readonly Product $product,
        public readonly ?Import $import = null,
        public readonly ?int $targetValueId = null,
        public readonly ?string $feedbackText = null,
    ) {}

    public function handle(OpenRouterClient $openRouter): void
    {
        if ($this->import?->isHaltedForCost()) {
            return;
        }

        // Collect established facts (non-copy fields that are approved or pending)
        $establishedFacts = $this->product->currentAttributeValues()
            ->with('attribute')
            ->whereHas('attribute', fn ($q) => $q->whereNotIn('code', self::COPY_FIELDS))
            ->get()
            ->filter(fn ($v) => in_array($v->review_status, ['approved', 'pending'], strict: true))
            ->mapWithKeys(fn ($v) => [$v->attribute->code => $v->getDisplayValue()])
            ->all();

        if (empty($establishedFacts)) {
            return; // Nothing to base copy on
        }

        // Determine which fields to generate
        $fieldsToGenerate = $this->targetValueId !== null
            ? $this->getSingleFieldCode()
            : self::COPY_FIELDS;

        if (empty($fieldsToGenerate)) {
            return;
        }

        $factsContext = collect($establishedFacts)
            ->map(fn ($v, $k) => "$k: $v")
            ->implode("\n");

        $feedbackInstruction = $this->feedbackText
            ? "\n\nFeedback for regeneration: {$this->feedbackText}"
            : '';

        $prompt = "Generate product copy using ONLY these established facts:\n{$factsContext}"
            ."{$feedbackInstruction}\n\n"
            .'Do NOT introduce any fact not listed above (no dimensions, materials, certifications, '
            .'warranty, safety claims, or country of origin unless explicitly listed). '
            .'Return structured JSON per the schema.';

        $schema = $this->buildCopySchema($fieldsToGenerate);

        $result = $openRouter->chat(
            model: 'ibm-granite/granite-4.1-8b',
            messages: [['role' => 'user', 'content' => $prompt]],
            responseFormat: ['type' => 'json_schema', 'json_schema' => $schema],
        );

        $costUsd = $result['cost_usd'];
        $responseData = $result['response']['choices'][0]['message']['content'] ?? '{}';
        $data = json_decode((string) $responseData, true) ?? [];

        $attributes = Attribute::whereIn('code', self::COPY_FIELDS)->get()->keyBy('code');

        foreach ($fieldsToGenerate as $fieldCode) {
            if (! isset($data[$fieldCode]) || ! isset($attributes[$fieldCode])) {
                continue;
            }

            $attribute = $attributes[$fieldCode];
            $value = $data[$fieldCode];

            // For regeneration: only affect the target field
            if ($this->targetValueId !== null) {
                $targetValue = ProductAttributeValue::find($this->targetValueId);
                if (! $targetValue || $targetValue->review_status === 'approved') {
                    continue; // FR-022: never overwrite approved
                }

                $targetValue->update(['is_current' => false]);

                ProductAttributeValue::create([
                    'product_id' => $this->product->id,
                    'attribute_id' => $attribute->id,
                    'text_value' => is_array($value) ? null : (string) $value,
                    'json_value' => is_array($value) ? $value : null,
                    'origin' => 'ai_generated_copy',
                    'confidence_tier' => 'low',
                    'review_status' => 'pending',
                    'is_current' => true,
                    'previous_value_id' => $targetValue->id,
                    'regeneration_feedback' => $this->feedbackText,
                ]);
            } else {
                // Initial generation: supersede any existing copy value
                ProductAttributeValue::where('product_id', $this->product->id)
                    ->where('attribute_id', $attribute->id)
                    ->where('is_current', true)
                    ->update(['is_current' => false]);

                ProductAttributeValue::create([
                    'product_id' => $this->product->id,
                    'attribute_id' => $attribute->id,
                    'text_value' => is_array($value) ? null : (string) $value,
                    'json_value' => is_array($value) ? $value : null,
                    'origin' => 'ai_generated_copy',
                    'confidence_tier' => 'low',
                    'review_status' => 'pending',
                    'is_current' => true,
                ]);
            }
        }

        if ($costUsd > 0 && $this->import) {
            $this->import->accumulateCost($costUsd);
        }
    }

    public function failed(\Throwable $exception): void
    {
        // FR-010: non-blocking failure — product remains usable without copy fields
    }

    /**
     * @return string[]
     */
    private function getSingleFieldCode(): array
    {
        if (! $this->targetValueId) {
            return [];
        }

        $value = ProductAttributeValue::with('attribute')->find($this->targetValueId);

        return $value?->attribute?->code ? [$value->attribute->code] : [];
    }

    /**
     * @param  string[]  $fields
     * @return array<string, mixed>
     */
    private function buildCopySchema(array $fields): array
    {
        $properties = [];
        foreach ($fields as $field) {
            if ($field === 'bullet_points') {
                $properties[$field] = ['type' => 'array', 'items' => ['type' => 'string']];
            } else {
                $properties[$field] = ['type' => 'string'];
            }
        }

        return [
            'name' => 'product_copy',
            'schema' => [
                'type' => 'object',
                'properties' => $properties,
                'required' => $fields,
            ],
        ];
    }
}
