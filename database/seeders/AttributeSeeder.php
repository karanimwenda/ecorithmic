<?php

namespace Database\Seeders;

use App\Models\ProductEnrichment\Attribute;
use App\Models\ProductEnrichment\AttributeOption;
use Illuminate\Database\Seeder;

class AttributeSeeder extends Seeder
{
    public function run(): void
    {
        $attributes = [
            ['code' => 'sku', 'name' => 'SKU', 'type' => 'text', 'is_required' => true, 'is_unique' => true, 'is_ai_enrichable' => false, 'position' => 1],
            ['code' => 'name', 'name' => 'Name', 'type' => 'text', 'is_required' => true, 'is_unique' => false, 'is_ai_enrichable' => true, 'position' => 2],
            ['code' => 'brand', 'name' => 'Brand', 'type' => 'select', 'is_required' => false, 'is_unique' => false, 'is_ai_enrichable' => true, 'position' => 3],
            ['code' => 'gtin', 'name' => 'GTIN', 'type' => 'text', 'is_required' => false, 'is_unique' => false, 'is_ai_enrichable' => false, 'position' => 4],
            ['code' => 'mpn', 'name' => 'MPN', 'type' => 'text', 'is_required' => false, 'is_unique' => false, 'is_ai_enrichable' => false, 'position' => 5],
            ['code' => 'short_description', 'name' => 'Short Description', 'type' => 'textarea', 'is_required' => false, 'is_unique' => false, 'is_ai_enrichable' => true, 'position' => 6],
            ['code' => 'description', 'name' => 'Description', 'type' => 'textarea', 'is_required' => false, 'is_unique' => false, 'is_ai_enrichable' => true, 'position' => 7],
            ['code' => 'bullet_points', 'name' => 'Bullet Points', 'type' => 'json', 'is_required' => false, 'is_unique' => false, 'is_ai_enrichable' => true, 'position' => 8],
            ['code' => 'seo_title', 'name' => 'SEO Title', 'type' => 'text', 'is_required' => false, 'is_unique' => false, 'is_ai_enrichable' => true, 'position' => 9],
            ['code' => 'seo_summary', 'name' => 'SEO Summary', 'type' => 'textarea', 'is_required' => false, 'is_unique' => false, 'is_ai_enrichable' => true, 'position' => 10],
            ['code' => 'price', 'name' => 'Price', 'type' => 'price', 'is_required' => false, 'is_unique' => false, 'is_ai_enrichable' => false, 'position' => 11],
            ['code' => 'color', 'name' => 'Color', 'type' => 'select', 'is_required' => false, 'is_unique' => false, 'is_ai_enrichable' => true, 'position' => 12],
            ['code' => 'category', 'name' => 'Category', 'type' => 'select', 'is_required' => false, 'is_unique' => false, 'is_ai_enrichable' => true, 'position' => 13],
        ];

        foreach ($attributes as $data) {
            Attribute::firstOrCreate(['code' => $data['code']], $data);
        }

        // Seed AttributeOption rows for select-type attributes
        $colorAttribute = Attribute::where('code', 'color')->first();
        if ($colorAttribute) {
            $colors = ['Red', 'Blue', 'Green', 'Black', 'White', 'Navy', 'Grey', 'Yellow', 'Pink', 'Purple', 'Orange', 'Brown'];
            foreach ($colors as $index => $color) {
                AttributeOption::firstOrCreate(
                    ['attribute_id' => $colorAttribute->id, 'admin_name' => $color],
                    ['sort_order' => $index]
                );
            }
        }

        $categoryAttribute = Attribute::where('code', 'category')->first();
        if ($categoryAttribute) {
            $categories = ['Electronics', 'Clothing', 'Home & Garden', 'Sports', 'Toys', 'Books', 'Automotive', 'Health', 'Beauty', 'Food'];
            foreach ($categories as $index => $category) {
                AttributeOption::firstOrCreate(
                    ['attribute_id' => $categoryAttribute->id, 'admin_name' => $category],
                    ['sort_order' => $index]
                );
            }
        }

        $brandAttribute = Attribute::where('code', 'brand')->first();
        if ($brandAttribute) {
            $brands = ['Generic', 'Apple', 'Samsung', 'Sony', 'Nike', 'Adidas'];
            foreach ($brands as $index => $brand) {
                AttributeOption::firstOrCreate(
                    ['attribute_id' => $brandAttribute->id, 'admin_name' => $brand],
                    ['sort_order' => $index]
                );
            }
        }
    }
}
