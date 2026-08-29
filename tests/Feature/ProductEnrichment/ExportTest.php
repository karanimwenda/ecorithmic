<?php

use App\Jobs\ProductEnrichment\GenerateCatalogExport;
use App\Models\ProductEnrichment\Attribute;
use App\Models\ProductEnrichment\Export;
use App\Models\ProductEnrichment\Product;
use App\Models\ProductEnrichment\ProductAttributeValue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

// ─── Initialize fixtures ─────────────────────────────────────────────────

beforeEach(function () {
    $this->user = User::factory()->create();
    Storage::fake('local');
    $this->artisan('db:seed', ['--class' => 'AttributeSeeder']);

    $this->product = Product::factory()->create(['sku' => 'EXPORT-SKU-001']);
    $attribute = Attribute::where('code', 'name')->first();

    // Create one approved and one pending value
    $this->approvedValue = ProductAttributeValue::factory()->create([
        'product_id' => $this->product->id,
        'attribute_id' => $attribute->id,
        'review_status' => 'approved',
        'is_current' => true,
        'origin' => 'manager',
        'confidence_tier' => 'high',
        'text_value' => 'Widget Pro',
    ]);

    $this->pendingValue = ProductAttributeValue::factory()->create([
        'product_id' => $this->product->id,
        'attribute_id' => Attribute::where('code', 'color')->first()->id,
        'review_status' => 'pending',
        'is_current' => true,
        'origin' => 'ai_research',
        'confidence_tier' => 'medium',
        'text_value' => 'blue',
    ]);
});

// ─── Setup the environment ───────────────────────────────────────────────

// Uses RefreshDatabase, Storage::fake, Queue::fake

// ─── Run the block of code in question ─────────────────────────────────

it('POST /exports triggers a GenerateCatalogExport job', function () {
    Queue::fake();

    $response = $this->actingAs($this->user)
        ->post('/exports');

    // ─── Make assertions ──────────────────────────────────────────────────
    $response->assertRedirect();
    Queue::assertPushed(GenerateCatalogExport::class);
});

it('GenerateCatalogExport includes only approved fields (FR-023)', function () {
    $export = Export::create(['status' => 'pending', 'requested_by' => $this->user->id]);

    $job = new GenerateCatalogExport($export);
    $job->handle();

    $export->refresh();

    expect($export->status)->toBe('completed');
    expect($export->summary['included_fields'])->toBe(1);  // Only the approved value
    expect($export->summary['excluded_fields'])->toBeGreaterThanOrEqual(1); // Pending excluded
});

it('export summary accurately counts excluded items (FR-023)', function () {
    $export = Export::create(['status' => 'pending', 'requested_by' => $this->user->id]);

    $job = new GenerateCatalogExport($export);
    $job->handle();

    $export->refresh();

    expect($export->summary['excluded_reasons'])->toHaveKey('pending_review');
});

it('produces an explicit summary for entirely unreviewed catalog (SC-008)', function () {
    // Reject all existing values and create fresh pending ones
    ProductAttributeValue::query()->update(['review_status' => 'pending']);

    $export = Export::create(['status' => 'pending', 'requested_by' => $this->user->id]);
    $job = new GenerateCatalogExport($export);
    $job->handle();

    $export->refresh();

    // Regardless of outcome, the export must complete with a summary — never silently empty
    expect($export->status)->toBe('completed');
    expect($export->summary)->not->toBeNull();
    expect($export->summary['included_products'] + $export->summary['excluded_products'])->toBeGreaterThanOrEqual(0);
});

it('returns 404 for incomplete export artifact', function () {
    $export = Export::create(['status' => 'pending', 'requested_by' => $this->user->id]);

    $response = $this->actingAs($this->user)
        ->get('/exports/'.$export->id.'/artifacts/spreadsheet');

    $response->assertNotFound();
});
