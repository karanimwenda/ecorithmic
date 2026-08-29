<?php

namespace App\Services\ProductEnrichment;

use App\Models\ProductEnrichment\ProductAttributeValue;
use Illuminate\Database\Eloquent\Builder;

/**
 * Translates a bulk-approval rule array into a ProductAttributeValue query
 * scoped to pending fields (FR-021).
 */
class BulkApprovalRuleMatcher
{
    /**
     * @param  array{confidence_tier?: string, category?: string, attribute_code?: string}  $rule
     * @return Builder<ProductAttributeValue>
     */
    public function query(array $rule): Builder
    {
        $query = ProductAttributeValue::query()
            ->where('is_current', true)
            ->where('review_status', 'pending');

        if (! empty($rule['confidence_tier'])) {
            $query->where('confidence_tier', $rule['confidence_tier']);
        }

        if (! empty($rule['attribute_code'])) {
            $query->whereHas('attribute', fn (Builder $q) => $q->where('code', $rule['attribute_code']));
        }

        if (! empty($rule['category'])) {
            // category filter: find product_ids that have an approved category value matching
            $query->whereHas('product', function (Builder $q) use ($rule) {
                $q->whereHas('currentAttributeValues', function (Builder $q2) use ($rule) {
                    $q2->whereHas('attribute', fn (Builder $q3) => $q3->where('code', 'category'))
                        ->where('text_value', $rule['category'])
                        ->where('review_status', 'approved');
                });
            });
        }

        return $query;
    }

    /**
     * @param  array{confidence_tier?: string, category?: string, attribute_code?: string}  $rule
     */
    public function count(array $rule): int
    {
        return $this->query($rule)->count();
    }
}
