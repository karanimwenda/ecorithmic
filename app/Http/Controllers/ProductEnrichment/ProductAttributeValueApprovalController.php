<?php

namespace App\Http\Controllers\ProductEnrichment;

use App\Http\Controllers\Controller;
use App\Models\ProductEnrichment\ProductAttributeValue;
use App\Models\ProductEnrichment\ReviewEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProductAttributeValueApprovalController extends Controller
{
    public function store(Request $request, ProductAttributeValue $productAttributeValue): RedirectResponse
    {
        $productAttributeValue->update([
            'review_status' => 'approved',
            'approved_by' => $request->user()?->id,
            'approved_at' => now(),
        ]);

        // US4 conflict resolution: approving one candidate rejects all siblings
        if ($productAttributeValue->conflict_group_id) {
            ProductAttributeValue::where('conflict_group_id', $productAttributeValue->conflict_group_id)
                ->where('id', '!=', $productAttributeValue->id)
                ->update(['review_status' => 'rejected', 'is_current' => false]);
        }

        ReviewEvent::create([
            'product_id' => $productAttributeValue->product_id,
            'product_attribute_value_id' => $productAttributeValue->id,
            'action' => 'approve',
            'actor_id' => $request->user()->id,
        ]);

        return redirect()->route('products.show', $productAttributeValue->product_id);
    }
}
