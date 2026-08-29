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
 * Analyzes the product's primary photo using a vision-capable model (Gemini Flash Lite).
 * Creates ProductAttributeValue rows with origin=ai_vision and confidence_tier=low (FR-012/014).
 */
class AnalyzeProductPhoto implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 120;

    public function __construct(
        public readonly Product $product,
        public readonly ?Import $import = null,
    ) {}

    public function handle(OpenRouterClient $openRouter): void
    {
        if ($this->import?->isHaltedForCost()) {
            return;
        }

        // Get the primary photo URL
        $primaryAsset = $this->product->getMedia('original')
            ->where('is_primary', true)
            ->first();

        if (! $primaryAsset) {
            // No photo — nothing to analyze, but product still completes (FR-010)
            return;
        }

        $photoUrl = $primaryAsset->getUrl();

        $attributes = Attribute::where('is_ai_enrichable', true)->get()->keyBy('code');

        $prompt = 'Analyze this product photo and describe only what is visually observable. '
            .'Use conservative language (e.g., "appears navy", not "is navy"). '
            .'Only describe visible attributes. Return structured JSON per the schema.';

        $schema = [
            'name' => 'vision_attributes',
            'schema' => [
                'type' => 'object',
                'properties' => [
                    'observations' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'attribute_code' => ['type' => 'string'],
                                'value' => ['type' => 'string'],
                            ],
                            'required' => ['attribute_code', 'value'],
                        ],
                    ],
                ],
                'required' => ['observations'],
            ],
        ];

        $result = $openRouter->chat(
            model: 'google/gemini-2.5-flash-lite',
            messages: [['role' => 'user', 'content' => $prompt]],
            responseFormat: ['type' => 'json_schema', 'json_schema' => $schema],
            imageAttachment: ['url' => $photoUrl, 'detail' => 'auto'],
        );

        $costUsd = $result['cost_usd'];
        $responseData = $result['response']['choices'][0]['message']['content'] ?? '{}';
        $data = json_decode((string) $responseData, true) ?? [];

        foreach ($data['observations'] ?? [] as $obs) {
            $attributeCode = $obs['attribute_code'] ?? null;
            $value = $obs['value'] ?? null;

            if (! $attributeCode || ! $value || ! isset($attributes[$attributeCode])) {
                continue;
            }

            $attribute = $attributes[$attributeCode];

            // FR-012/014: ai_vision rows are ALWAYS confidence_tier=low
            // Never skip existing manager-supplied or ai_research values — only create if absent
            $existingCurrent = ProductAttributeValue::where('product_id', $this->product->id)
                ->where('attribute_id', $attribute->id)
                ->where('is_current', true)
                ->whereIn('origin', ['manager', 'ai_research'])
                ->exists();

            if ($existingCurrent) {
                continue; // Don't overwrite higher-confidence values
            }

            ProductAttributeValue::updateOrCreate(
                [
                    'product_id' => $this->product->id,
                    'attribute_id' => $attribute->id,
                    'origin' => 'ai_vision',
                    'is_current' => true,
                ],
                [
                    'text_value' => (string) $value,
                    'confidence_tier' => 'low', // ALWAYS low for vision (FR-012/014)
                    'review_status' => 'pending',
                ]
            );
        }

        if ($costUsd > 0 && $this->import) {
            $this->import->accumulateCost($costUsd);
        }
    }

    public function failed(\Throwable $exception): void
    {
        // FR-010: product must never be left failed — AnalyzeProductPhoto failure is non-blocking
    }
}
