<?php

namespace App\Http\Controllers\ProductEnrichment;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProductEnrichment\UpdateProductAssetRequest;
use App\Models\ProductEnrichment\ProductAsset;
use Illuminate\Http\RedirectResponse;

class ProductAssetController extends Controller
{
    public function update(UpdateProductAssetRequest $request, ProductAsset $productAsset): RedirectResponse
    {
        // Only original-collection assets can be "primary" (FR-002)
        if ($productAsset->collection_name !== 'original') {
            abort(422, 'Only original-collection assets can be set as primary.');
        }

        if ($request->boolean('is_primary')) {
            // Un-set primary on all sibling original assets for this product
            ProductAsset::where('model_type', $productAsset->model_type)
                ->where('model_id', $productAsset->model_id)
                ->where('collection_name', 'original')
                ->where('id', '!=', $productAsset->id)
                ->update(['is_primary' => false]);

            $productAsset->update(['is_primary' => true]);
        } else {
            $productAsset->update(['is_primary' => false]);
        }

        return redirect()->route('products.show', $productAsset->model_id);
    }
}
