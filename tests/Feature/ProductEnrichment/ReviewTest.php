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
    $this->attribute = Attribute::where('code', 'color')->first();
    $this->otherAttribute = Attribute::where('code', 'name')->first();

    $this->pendingValue = ProductAttributeValue::factory()->create([
        'product_id' => $this->product->id,
        'attribute_id' => $this->attribute->id,
        'origin' => 'ai_research',
        'confidence_tier' => 'medium',
        'review_status' => 'pending',
        'text_value' => 'navy blue',
        'evidence_quote' => 'available in navy blue',
        'is_current' => true,
    ]);

    $this->otherPendingValue = ProductAttributeValue::factory()->create([
        'product_id' => $this->product->id,
        'attribute_id' => $this->otherAttribute->id,
        'origin' => 'manager',
        'confidence_tier' => 'high',
        'review_status' => 'pending',
        'text_value' => 'Widget Pro',
        'is_current' => true,
    ]);
});

// ─── Setup the environment ───────────────────────────────────────────────

// Uses RefreshDatabase

// ─── Run the block of code in question ─────────────────────────────────

it('shows the product review page', function () {
    $response = $this->actingAs($this->user)
        ->get('/products/'.$this->product->id);

    // ─── Make assertions ──────────────────────────────────────────────────
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('ProductEnrichment/Products/Review')
        ->has('product')
        ->has('attributeValues')
    );
});

it('approving one field does not affect other fields (US3 AC1)', function () {
    $response = $this->actingAs($this->user)
        ->post('/product-attribute-values/'.$this->pendingValue->id.'/approval');

    $response->assertRedirect();

    $this->pendingValue->refresh();
    $this->otherPendingValue->refresh();

    expect($this->pendingValue->review_status)->toBe('approved');
    expect($this->otherPendingValue->review_status)->toBe('pending'); // Not affected
});

it('edit creates a new versioned row with origin=human_edit (US3 AC3)', function () {
    $response = $this->actingAs($this->user)
        ->patch('/product-attribute-values/'.$this->pendingValue->id, [
            'value' => 'sky blue',
        ]);

    $response->assertRedirect();

    // A new row should exist with human_edit origin
    $newValue = ProductAttributeValue::where('product_id', $this->product->id)
        ->where('attribute_id', $this->attribute->id)
        ->where('origin', 'human_edit')
        ->where('is_current', true)
        ->first();

    expect($newValue)->not->toBeNull();
    expect($newValue->text_value)->toBe('sky blue');
    expect($newValue->review_status)->toBe('approved');
    expect($newValue->confidence_tier)->toBe('high');

    // Prior AI value is superseded but not deleted
    $this->pendingValue->refresh();
    expect($this->pendingValue->is_current)->toBeFalse();
});

it('approving the whole product approves all pending fields (US3 AC4)', function () {
    $thirdValue = ProductAttributeValue::factory()->create([
        'product_id' => $this->product->id,
        'attribute_id' => Attribute::where('code', 'brand')->first()->id,
        'review_status' => 'pending',
        'is_current' => true,
        'origin' => 'ai_research',
        'confidence_tier' => 'medium',
        'text_value' => 'Acme',
    ]);

    $response = $this->actingAs($this->user)
        ->post('/products/'.$this->product->id.'/approval');

    $response->assertRedirect();

    $this->pendingValue->refresh();
    $this->otherPendingValue->refresh();
    $thirdValue->refresh();

    expect($this->pendingValue->review_status)->toBe('approved');
    expect($this->otherPendingValue->review_status)->toBe('approved');
    expect($thirdValue->review_status)->toBe('approved');

    // One review event for the product-level approval
    $event = ReviewEvent::where('product_id', $this->product->id)
        ->where('action', 'approve_product')
        ->first();

    expect($event)->not->toBeNull();
});

it('reject sets review_status to rejected', function () {
    $response = $this->actingAs($this->user)
        ->post('/product-attribute-values/'.$this->pendingValue->id.'/rejection');

    $response->assertRedirect();
    $this->pendingValue->refresh();

    expect($this->pendingValue->review_status)->toBe('rejected');
});

it('records a review event for every action', function () {
    $this->actingAs($this->user)
        ->post('/product-attribute-values/'.$this->pendingValue->id.'/approval');

    $event = ReviewEvent::where('product_id', $this->product->id)
        ->where('action', 'approve')
        ->first();

    expect($event)->not->toBeNull();
    expect($event->actor_id)->toBe($this->user->id);
});
