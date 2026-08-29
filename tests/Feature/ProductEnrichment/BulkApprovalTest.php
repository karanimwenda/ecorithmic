<?php

use App\Models\ProductEnrichment\Attribute;
use App\Models\ProductEnrichment\Product;
use App\Models\ProductEnrichment\ProductAttributeValue;
use App\Models\ProductEnrichment\ReviewEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ─── Initialize fixtures ─────────────────────────────────────────────────

beforeEach(function () {
    $this->withoutVite();
    $this->user = User::factory()->create();
    $this->artisan('db:seed', ['--class' => 'AttributeSeeder']);

    $this->product = Product::factory()->create();

    // Create 3 high-confidence pending fields and 2 medium-confidence pending fields
    $colorAttr = Attribute::where('code', 'color')->first();
    $nameAttr = Attribute::where('code', 'name')->first();
    $brandAttr = Attribute::where('code', 'brand')->first();
    $descAttr = Attribute::where('code', 'description')->first();
    $shortDescAttr = Attribute::where('code', 'short_description')->first();

    $this->highValues = collect([
        ProductAttributeValue::factory()->create([
            'product_id' => $this->product->id, 'attribute_id' => $colorAttr->id,
            'confidence_tier' => 'high', 'review_status' => 'pending', 'is_current' => true,
            'origin' => 'manager', 'text_value' => 'blue',
        ]),
        ProductAttributeValue::factory()->create([
            'product_id' => $this->product->id, 'attribute_id' => $nameAttr->id,
            'confidence_tier' => 'high', 'review_status' => 'pending', 'is_current' => true,
            'origin' => 'manager', 'text_value' => 'Widget Pro',
        ]),
        ProductAttributeValue::factory()->create([
            'product_id' => $this->product->id, 'attribute_id' => $brandAttr->id,
            'confidence_tier' => 'high', 'review_status' => 'pending', 'is_current' => true,
            'origin' => 'manager', 'text_value' => 'Acme',
        ]),
    ]);

    $this->mediumValues = collect([
        ProductAttributeValue::factory()->create([
            'product_id' => $this->product->id, 'attribute_id' => $descAttr->id,
            'confidence_tier' => 'medium', 'review_status' => 'pending', 'is_current' => true,
            'origin' => 'ai_research', 'text_value' => 'A quality product.',
        ]),
        ProductAttributeValue::factory()->create([
            'product_id' => $this->product->id, 'attribute_id' => $shortDescAttr->id,
            'confidence_tier' => 'medium', 'review_status' => 'pending', 'is_current' => true,
            'origin' => 'ai_research', 'text_value' => 'Short desc.',
        ]),
    ]);
});

// ─── Setup the environment ───────────────────────────────────────────────

// ─── Run the block of code in question ─────────────────────────────────

it('GET /bulk-approvals/create shows the preview count for a rule (US6 AC1)', function () {
    $response = $this->actingAs($this->user)
        ->get('/bulk-approvals/create?rule[confidence_tier]=high');

    // ─── Make assertions ──────────────────────────────────────────────────
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('ProductEnrichment/BulkApprovals/Create')
        ->where('previewCount', 3)  // 3 high-confidence pending fields
    );
});

it('POST /bulk-approvals approves exactly the matched set (US6 AC2)', function () {
    $response = $this->actingAs($this->user)
        ->post('/bulk-approvals', [
            'rule' => ['confidence_tier' => 'high'],
        ]);

    $response->assertRedirect();

    // All 3 high-confidence values should now be approved
    foreach ($this->highValues as $value) {
        $value->refresh();
        expect($value->review_status)->toBe('approved');
    }

    // Medium-confidence values should be unaffected
    foreach ($this->mediumValues as $value) {
        $value->refresh();
        expect($value->review_status)->toBe('pending');
    }
});

it('bulk approval records a single review event per product (US6 AC2)', function () {
    $this->actingAs($this->user)
        ->post('/bulk-approvals', [
            'rule' => ['confidence_tier' => 'high'],
        ]);

    $events = ReviewEvent::where('product_id', $this->product->id)
        ->where('action', 'bulk_approve')
        ->get();

    expect($events)->toHaveCount(1);
    expect($events->first()->bulk_rule)->toBe(['confidence_tier' => 'high']);
});

it('bulk approval with no matching fields changes nothing', function () {
    $beforeCount = ProductAttributeValue::where('review_status', 'pending')->count();

    $this->actingAs($this->user)
        ->post('/bulk-approvals', [
            'rule' => ['confidence_tier' => 'low'],
        ]);

    $afterCount = ProductAttributeValue::where('review_status', 'pending')->count();
    expect($afterCount)->toBe($beforeCount);
});
