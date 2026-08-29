<?php

namespace Database\Factories\ProductEnrichment;

use App\Models\ProductEnrichment\Attribute;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attribute>
 */
class AttributeFactory extends Factory
{
    protected $model = Attribute::class;

    public function definition(): array
    {
        return [
            'code' => $this->faker->unique()->slug(2),
            'name' => $this->faker->words(2, true),
            'type' => $this->faker->randomElement(['text', 'textarea', 'price', 'boolean', 'select', 'json']),
            'is_required' => false,
            'is_unique' => false,
            'is_ai_enrichable' => true,
            'validation' => null,
            'position' => $this->faker->numberBetween(0, 100),
        ];
    }

    public function text(): static
    {
        return $this->state(['type' => 'text', 'is_ai_enrichable' => true]);
    }

    public function select(): static
    {
        return $this->state(['type' => 'select', 'is_ai_enrichable' => true]);
    }

    public function nonEnrichable(): static
    {
        return $this->state(['is_ai_enrichable' => false]);
    }
}
