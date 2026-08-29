<?php

namespace App\Http\Controllers\ProductEnrichment;

use App\Http\Controllers\Controller;
use App\Models\ProductEnrichment\Product;
use App\Models\ProductEnrichment\ProductAttributeValue;
use App\Models\ProductEnrichment\ReviewEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductApprovalController extends Controller
{
    public function store(Request $request, Product $product): RedirectResponse
    {
        DB::transaction(function () use ($request, $product) {
            $now = now();
            $userId = $request->user()?->id;

            // Approve every currently-pending field in one action (FR-020)
            ProductAttributeValue::where('product_id', $product->id)
                ->where('is_current', true)
                ->where('review_status', 'pending')
                ->update([
                    'review_status' => 'approved',
                    'approved_by' => $userId,
                    'approved_at' => $now,
                ]);

            ReviewEvent::create([
                'product_id' => $product->id,
                'product_attribute_value_id' => null,
                'action' => 'approve_product',
                'actor_id' => $userId,
            ]);
        });

        return redirect()->route('products.show', $product);
    }
}
