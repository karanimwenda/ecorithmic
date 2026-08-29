<?php

namespace Database\Factories\ProductEnrichment;

use App\Models\ProductEnrichment\Product;
use App\Models\ProductEnrichment\Source;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Source>
 */
class SourceFactory extends Factory
{
    protected $model = Source::class;

    public function definition(): array
    {
        $domain = $this->faker->domainName();

        return [
            'product_id' => Product::factory(),
            'url' => 'https://'.$domain.'/'.$this->faker->slug(),
            'domain' => $domain,
            'trust_classification' => 'allowed',
            'is_usable' => true,
            'fetched_at' => now(),
        ];
    }

    public function authoritative(): static
    {
        return $this->state(['trust_classification' => 'authoritative', 'is_usable' => true]);
    }

    public function disallowed(): static
    {
        return $this->state(['trust_classification' => 'disallowed', 'is_usable' => false]);
    }

    public function unusable(): static
    {
        return $this->state(['is_usable' => false]);
    }
}
