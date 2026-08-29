<?php

namespace Database\Factories\ProductEnrichment;

use App\Models\ProductEnrichment\Attribute;
use App\Models\ProductEnrichment\AttributeOption;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttributeOption>
 */
class AttributeOptionFactory extends Factory
{
    protected $model = AttributeOption::class;

    public function definition(): array
    {
        return [
            'attribute_id' => Attribute::factory(),
            'admin_name' => $this->faker->word(),
            'sort_order' => $this->faker->numberBetween(0, 100),
        ];
    }
}
