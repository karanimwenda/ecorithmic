<?php

namespace App\Http\Controllers\ProductEnrichment;

use App\Http\Controllers\Controller;
use App\Models\ProductEnrichment\Product;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function show(Request $request, Product $product): Response
    {
        $product->load(['sources']);

        // Group attribute values by attribute code for the review UI
        $attributeValues = $product->currentAttributeValues()
            ->with(['attribute', 'source', 'previousValue'])
            ->get()
            /** @phpstan-ignore argument.type */
            ->groupBy(fn ($v) => $v->attribute?->code)
            /** @phpstan-ignore argument.type, return.type, return.type */
            ->map(fn ($group) => $group->map(fn ($v) => [
                'id' => $v->id,
                'attribute_code' => $v->attribute?->code,
                'attribute_name' => $v->attribute?->name,
                'attribute_type' => $v->attribute?->type,
                'display_value' => $v->getDisplayValue(),
                'origin' => $v->origin,
                'confidence_tier' => $v->confidence_tier,
                'review_status' => $v->review_status,
                'conflict_group_id' => $v->conflict_group_id,
                'evidence_quote' => $v->evidence_quote,
                'source' => $v->source ? [
                    'url' => $v->source->url,
                    'domain' => $v->source->domain,
                    'trust_classification' => $v->source->trust_classification,
                ] : null,
                'history' => $v->versions()->where('is_current', false)->get()->map(fn ($h) => [
                    'id' => $h->id,
                    'display_value' => $h->getDisplayValue(),
                    'origin' => $h->origin,
                    'confidence_tier' => $h->confidence_tier,
                    'review_status' => $h->review_status,
                    'created_at' => $h->created_at,
                ]),
            ]));

        $assets = $product->getMedia()->map(fn ($m) => [
            'id' => $m->id,
            'collection_name' => $m->collection_name,
            'file_name' => $m->file_name,
            'url' => $m->getUrl(),
            'review_status' => $m->review_status ?? 'pending',
            'quality_flags' => $m->quality_flags ?? [],
            'is_primary' => $m->is_primary ?? false,
        ]);

        return Inertia::render('ProductEnrichment/Products/Review', [
            'product' => [
                'id' => $product->id,
                'sku' => $product->sku,
                'created_at' => $product->created_at,
                'updated_at' => $product->updated_at,
            ],
            'attributeValues' => $attributeValues,
            'assets' => $assets,
        ]);
    }
}
