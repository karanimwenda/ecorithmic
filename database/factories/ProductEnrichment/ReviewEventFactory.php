<?php

namespace Database\Factories\ProductEnrichment;

use App\Models\ProductEnrichment\Product;
use App\Models\ProductEnrichment\ReviewEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReviewEvent>
 */
class ReviewEventFactory extends Factory
{
    protected $model = ReviewEvent::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'product_attribute_value_id' => null,
            'action' => $this->faker->randomElement(['approve', 'reject', 'edit', 'regenerate', 'approve_product', 'bulk_approve']),
            'actor_id' => User::factory(),
            'feedback_text' => null,
            'bulk_rule' => null,
            'created_at' => now(),
        ];
    }
}
