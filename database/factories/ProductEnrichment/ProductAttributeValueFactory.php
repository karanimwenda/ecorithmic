<?php

namespace Database\Factories\ProductEnrichment;

use App\Models\ProductEnrichment\Attribute;
use App\Models\ProductEnrichment\Product;
use App\Models\ProductEnrichment\ProductAttributeValue;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ProductAttributeValue>
 */
class ProductAttributeValueFactory extends Factory
{
    protected $model = ProductAttributeValue::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'attribute_id' => Attribute::factory(),
            'locale' => null,
            'text_value' => $this->faker->sentence(),
            'integer_value' => null,
            'float_value' => null,
            'boolean_value' => null,
            'json_value' => null,
            'origin' => 'manager',
            'source_id' => null,
            'evidence_quote' => null,
            'confidence_tier' => 'high',
            'review_status' => 'pending',
            'conflict_group_id' => null,
            'is_current' => true,
            'previous_value_id' => null,
            'regeneration_feedback' => null,
            'approved_by' => null,
            'approved_at' => null,
        ];
    }

    public function manager(): static
    {
        return $this->state(['origin' => 'manager', 'confidence_tier' => 'high']);
    }

    public function aiResearch(): static
    {
        return $this->state(['origin' => 'ai_research', 'confidence_tier' => 'medium']);
    }

    public function aiVision(): static
    {
        return $this->state(['origin' => 'ai_vision', 'confidence_tier' => 'low']);
    }

    public function aiGeneratedCopy(): static
    {
        return $this->state(['origin' => 'ai_generated_copy', 'confidence_tier' => 'low']);
    }

    public function humanEdit(): static
    {
        return $this->state(['origin' => 'human_edit', 'confidence_tier' => 'high', 'review_status' => 'approved']);
    }

    public function approved(): static
    {
        return $this->state(['review_status' => 'approved', 'approved_at' => now()]);
    }

    public function rejected(): static
    {
        return $this->state(['review_status' => 'rejected']);
    }

    public function conflicted(): static
    {
        return $this->state(['review_status' => 'conflicted', 'conflict_group_id' => (string) Str::uuid()]);
    }

    public function superseded(): static
    {
        return $this->state(['is_current' => false]);
    }
}
