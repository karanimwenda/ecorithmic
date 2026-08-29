<?php

namespace App\Http\Controllers\ProductEnrichment;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProductEnrichment\EditAttributeValueRequest;
use App\Models\ProductEnrichment\ProductAttributeValue;
use App\Models\ProductEnrichment\ReviewEvent;
use Illuminate\Http\RedirectResponse;

class ProductAttributeValueController extends Controller
{
    public function update(EditAttributeValueRequest $request, ProductAttributeValue $productAttributeValue): RedirectResponse
    {
        $product = $productAttributeValue->product;
        $attribute = $productAttributeValue->attribute;

        $value = $request->input('value');

        // Supersede the prior row (never deleted)
        $productAttributeValue->update(['is_current' => false]);

        // Build typed value columns
        $valueData = match ($attribute?->type) {
            'price' => ['float_value' => (float) $value],
            'boolean' => ['boolean_value' => (bool) $value],
            'json' => ['json_value' => is_array($value) ? $value : json_decode((string) $value, true)],
            default => ['text_value' => (string) $value],
        };

        $newValue = ProductAttributeValue::create(array_merge($valueData, [
            'product_id' => $product->id,
            'attribute_id' => $attribute->id,
            'origin' => 'human_edit',
            'confidence_tier' => 'high',
            'review_status' => 'approved',
            'is_current' => true,
            'previous_value_id' => $productAttributeValue->id,
            'approved_by' => $request->user()?->id,
            'approved_at' => now(),
        ]));

        ReviewEvent::create([
            'product_id' => $product->id,
            'product_attribute_value_id' => $newValue->id,
            'action' => 'edit',
            'actor_id' => $request->user()->id,
        ]);

        return redirect()->route('products.show', $product);
    }
}
