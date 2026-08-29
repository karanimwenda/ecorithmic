<?php

namespace App\Http\Controllers\ProductEnrichment;

use App\Http\Controllers\Controller;
use App\Models\ProductEnrichment\ProductAttributeValue;
use App\Models\ProductEnrichment\ReviewEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProductAttributeValueRejectionController extends Controller
{
    public function store(Request $request, ProductAttributeValue $productAttributeValue): RedirectResponse
    {
        $productAttributeValue->update(['review_status' => 'rejected']);

        ReviewEvent::create([
            'product_id' => $productAttributeValue->product_id,
            'product_attribute_value_id' => $productAttributeValue->id,
            'action' => 'reject',
            'actor_id' => $request->user()->id,
        ]);

        return redirect()->route('products.show', $productAttributeValue->product_id);
    }
}
