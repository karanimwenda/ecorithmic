<?php

namespace App\Http\Controllers\ProductEnrichment;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProductEnrichment\BulkApprovalRuleRequest;
use App\Models\ProductEnrichment\ProductAttributeValue;
use App\Models\ProductEnrichment\ReviewEvent;
use App\Services\ProductEnrichment\BulkApprovalRuleMatcher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class BulkApprovalController extends Controller
{
    public function __construct(
        private readonly BulkApprovalRuleMatcher $matcher,
    ) {}

    public function create(Request $request): Response
    {
        $rule = $request->input('rule', []);
        $previewCount = ! empty($rule) ? $this->matcher->count((array) $rule) : null;

        return Inertia::render('ProductEnrichment/BulkApprovals/Create', [
            'rule' => $rule,
            'previewCount' => $previewCount,
        ]);
    }

    public function store(BulkApprovalRuleRequest $request): RedirectResponse
    {
        $rule = (array) $request->input('rule');
        $now = now();
        $userId = $request->user()?->id;

        DB::transaction(function () use ($rule, $now, $userId) {
            $affectedIds = $this->matcher->query($rule)->pluck('id')->all();

            if (empty($affectedIds)) {
                return;
            }

            ProductAttributeValue::whereIn('id', $affectedIds)
                ->update([
                    'review_status' => 'approved',
                    'approved_by' => $userId,
                    'approved_at' => $now,
                ]);

            // One review event per product touched (grouped)
            $productIds = ProductAttributeValue::whereIn('id', $affectedIds)
                ->distinct()
                ->pluck('product_id');

            foreach ($productIds as $productId) {
                ReviewEvent::create([
                    'product_id' => $productId,
                    'product_attribute_value_id' => null,
                    'action' => 'bulk_approve',
                    'actor_id' => $userId,
                    'bulk_rule' => $rule,
                ]);
            }
        });

        return redirect()->route('bulk-approvals.create');
    }
}
