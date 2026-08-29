<?php

namespace Database\Factories\ProductEnrichment;

use App\Models\ProductEnrichment\Product;
use App\Models\ProductEnrichment\ProductAsset;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ProductAsset>
 *
 * Creates fixture media rows for tests without needing the full upload pipeline.
 */
class ProductAssetFactory extends Factory
{
    protected $model = ProductAsset::class;

    public function definition(): array
    {
        return [
            'model_type' => Product::class,
            'model_id' => Product::factory(),
            'uuid' => Str::uuid()->toString(),
            'collection_name' => 'original',
            'name' => $this->faker->slug(2),
            'file_name' => $this->faker->slug(2).'.jpg',
            'mime_type' => 'image/jpeg',
            'disk' => 'local',
            'conversions_disk' => 'local',
            'size' => $this->faker->numberBetween(100000, 5000000),
            'manipulations' => [],
            'custom_properties' => [],
            'generated_conversions' => [],
            'responsive_images' => [],
            'review_status' => 'pending',
            'quality_flags' => null,
            'is_primary' => false,
            'order_column' => 1,
        ];
    }

    public function original(): static
    {
        return $this->state(['collection_name' => 'original']);
    }

    public function primaryEcommerce(): static
    {
        return $this->state(['collection_name' => 'primary-ecommerce']);
    }

    public function socialSquare(): static
    {
        return $this->state(['collection_name' => 'social-square']);
    }

    public function primary(): static
    {
        return $this->state(['is_primary' => true, 'collection_name' => 'original']);
    }

    public function approved(): static
    {
        return $this->state(['review_status' => 'approved']);
    }

    /** @param array<string, mixed> $flags */
    public function withQualityFlags(array $flags): static
    {
        return $this->state(['quality_flags' => $flags]);
    }
}
