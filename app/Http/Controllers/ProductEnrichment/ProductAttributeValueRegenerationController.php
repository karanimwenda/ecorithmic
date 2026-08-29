<?php

namespace App\Http\Controllers\ProductEnrichment;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProductEnrichment\RegenerateFieldRequest;
use App\Jobs\ProductEnrichment\GenerateProductCopy;
use App\Models\ProductEnrichment\ProductAttributeValue;
use App\Models\ProductEnrichment\ReviewEvent;
use Illuminate\Http\RedirectResponse;

class ProductAttributeValueRegenerationController extends Controller
{
    public function store(RegenerateFieldRequest $request, ProductAttributeValue $productAttributeValue): RedirectResponse
    {
        // Precondition: must be current and not already approved (FR-022)
        if (! $productAttributeValue->is_current || $productAttributeValue->review_status === 'approved') {
            abort(422, 'Only current, non-approved fields can be regenerated.');
        }

        ReviewEvent::create([
            'product_id' => $productAttributeValue->product_id,
            'product_attribute_value_id' => $productAttributeValue->id,
            'action' => 'regenerate',
            'actor_id' => $request->user()->id,
            'feedback_text' => $request->input('feedback'),
        ]);

        // Dispatch targeted single-field regeneration with feedback
        GenerateProductCopy::dispatch(
            $productAttributeValue->product,
            null, // no import context needed for regeneration
            $productAttributeValue->id,
            $request->input('feedback'),
        )->onQueue('copy-generation');

        return redirect()->route('products.show', $productAttributeValue->product_id);
    }
}
