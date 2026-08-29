<?php

use App\Jobs\ProductEnrichment\GenerateProductCopy;
use App\Models\ProductEnrichment\Attribute;
use App\Models\ProductEnrichment\Product;
use App\Models\ProductEnrichment\ProductAttributeValue;
use App\Models\ProductEnrichment\ReviewEvent;
use App\Models\User;
use App\Services\ProductEnrichment\OpenRouterClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

// ─── Initialize fixtures ─────────────────────────────────────────────────

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->artisan('db:seed', ['--class' => 'AttributeSeeder']);

    $this->product = Product::factory()->create();
    $this->attribute = Attribute::where('code', 'short_description')->first();

    $this->pendingCopyValue = ProductAttributeValue::factory()->create([
        'product_id' => $this->product->id,
        'attribute_id' => $this->attribute->id,
        'origin' => 'ai_generated_copy',
        'confidence_tier' => 'low',
        'review_status' => 'pending',
        'is_current' => true,
        'text_value' => 'A lengthy description that needs shortening.',
    ]);

    $this->approvedValue = ProductAttributeValue::factory()->create([
        'product_id' => $this->product->id,
        'attribute_id' => Attribute::where('code', 'name')->first()->id,
        'origin' => 'manager',
        'confidence_tier' => 'high',
        'review_status' => 'approved',
        'is_current' => true,
        'text_value' => 'Widget Pro',
    ]);
});

// ─── Setup the environment ───────────────────────────────────────────────

// ─── Run the block of code in question ─────────────────────────────────

it('regeneration request records a review event with feedback', function () {
    Queue::fake();

    $response = $this->actingAs($this->user)
        ->post('/product-attribute-values/'.$this->pendingCopyValue->id.'/regenerations', [
            'feedback' => 'make it shorter',
        ]);

    // ─── Make assertions ──────────────────────────────────────────────────
    $response->assertRedirect();

    $event = ReviewEvent::where('product_id', $this->product->id)
        ->where('action', 'regenerate')
        ->first();

    expect($event)->not->toBeNull();
    expect($event->feedback_text)->toBe('make it shorter');
});

it('regeneration produces a new version retaining the prior (US5 AC1)', function () {
    Http::fake([
        'openrouter.ai/*' => Http::response([
            'choices' => [['message' => ['content' => json_encode([
                'short_description' => 'Short.',
            ])]]],
            'usage' => ['prompt_tokens' => 50, 'completion_tokens' => 10],
        ]),
    ]);

    $job = new GenerateProductCopy(
        $this->product,
        null,
        $this->pendingCopyValue->id,
        'make it shorter',
    );
    $job->handle(new OpenRouterClient);

    // A new version should exist
    $newVersion = ProductAttributeValue::where('product_id', $this->product->id)
        ->where('attribute_id', $this->attribute->id)
        ->where('is_current', true)
        ->where('previous_value_id', $this->pendingCopyValue->id)
        ->first();

    expect($newVersion)->not->toBeNull();
    expect($newVersion->regeneration_feedback)->toBe('make it shorter');

    // Prior version is retained (not deleted)
    expect(ProductAttributeValue::find($this->pendingCopyValue->id))->not->toBeNull();
});

it('regeneration never touches an already-approved field (US5 AC2)', function () {
    Http::fake([
        'openrouter.ai/*' => Http::response([
            'choices' => [['message' => ['content' => json_encode([
                'short_description' => 'New copy.',
            ])]]],
            'usage' => ['prompt_tokens' => 50, 'completion_tokens' => 10],
        ]),
    ]);

    $approvedValueBefore = $this->approvedValue->text_value;

    $job = new GenerateProductCopy(
        $this->product,
        null,
        $this->pendingCopyValue->id,
        'make it shorter',
    );
    $job->handle(new OpenRouterClient);

    $this->approvedValue->refresh();
    expect($this->approvedValue->text_value)->toBe($approvedValueBefore);
    expect($this->approvedValue->review_status)->toBe('approved');
});

it('cannot regenerate an already-approved field (precondition check)', function () {
    Queue::fake();

    $response = $this->actingAs($this->user)
        ->post('/product-attribute-values/'.$this->approvedValue->id.'/regenerations', [
            'feedback' => 'change it',
        ]);

    $response->assertStatus(422);
    Queue::assertNothingPushed();
});
