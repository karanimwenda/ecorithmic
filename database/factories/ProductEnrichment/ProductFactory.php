<?php

namespace Database\Factories\ProductEnrichment;

use App\Models\ProductEnrichment\Import;
use App\Models\ProductEnrichment\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'sku' => strtoupper($this->faker->unique()->bothify('SKU-####')),
            'first_import_id' => null,
            'last_import_id' => null,
        ];
    }

    public function withImport(Import $import): static
    {
        return $this->state([
            'first_import_id' => $import->id,
            'last_import_id' => $import->id,
        ]);
    }
}
