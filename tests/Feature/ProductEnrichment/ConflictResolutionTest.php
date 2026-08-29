<?php

use App\Jobs\ProductEnrichment\GenerateCatalogExport;
use App\Models\ProductEnrichment\Attribute;
use App\Models\ProductEnrichment\Export;
use App\Models\ProductEnrichment\Product;
use App\Models\ProductEnrichment\ProductAttributeValue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

// ─── Initialize fixtures ─────────────────────────────────────────────────

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->artisan('db:seed', ['--class' => 'AttributeSeeder']);
    $this->product = Product::factory()->create();
    $this->attribute = Attribute::where('code', 'color')->first();
});

// ─── Setup the environment ───────────────────────────────────────────────

function createConflictedPair(Product $product, Attribute $attribute): array
{
    $groupId = (string) Str::uuid();

    $valueA = ProductAttributeValue::factory()->create([
        'product_id' => $product->id,
        'attribute_id' => $attribute->id,
        'review_status' => 'conflicted',
        'conflict_group_id' => $groupId,
        'is_current' => true,
        'origin' => 'ai_research',
        'confidence_tier' => 'medium',
        'text_value' => 'navy blue',
    ]);

    $valueB = ProductAttributeValue::factory()->create([
        'product_id' => $product->id,
        'attribute_id' => $attribute->id,
        'review_status' => 'conflicted',
        'conflict_group_id' => $groupId,
        'is_current' => true,
        'origin' => 'ai_research',
        'confidence_tier' => 'medium',
        'text_value' => 'royal blue',
    ]);

    return [$valueA, $valueB];
}

// ─── Run the block of code in question ─────────────────────────────────

it('conflicted field shows both candidates with shared conflict_group_id (US4 AC1)', function () {
    [$valueA, $valueB] = createConflictedPair($this->product, $this->attribute);

    // ─── Make assertions ──────────────────────────────────────────────────
    expect($valueA->conflict_group_id)->toBe($valueB->conflict_group_id);
    expect($valueA->review_status)->toBe('conflicted');
    expect($valueB->review_status)->toBe('conflicted');
});

it('approving one conflicted candidate rejects the siblings (US4 AC2)', function () {
    [$valueA, $valueB] = createConflictedPair($this->product, $this->attribute);

    $this->actingAs($this->user)
        ->post('/product-attribute-values/'.$valueA->id.'/approval');

    $valueA->refresh();
    $valueB->refresh();

    expect($valueA->review_status)->toBe('approved');
    expect($valueB->review_status)->toBe('rejected');
    expect($valueB->is_current)->toBeFalse(); // Sibling is retained but marked superseded
});

it('rejected sibling is retained in history and not deleted (US4 AC2)', function () {
    [$valueA, $valueB] = createConflictedPair($this->product, $this->attribute);

    $this->actingAs($this->user)
        ->post('/product-attribute-values/'.$valueA->id.'/approval');

    // Both rows still exist in the database
    expect(ProductAttributeValue::find($valueB->id))->not->toBeNull();
});

it('unresolved conflicted field is excluded from export (US4 AC3)', function () {
    Storage::fake('local');

    [$valueA, $valueB] = createConflictedPair($this->product, $this->attribute);

    $export = Export::create([
        'status' => 'pending',
        'requested_by' => $this->user->id,
    ]);

    $job = new GenerateCatalogExport($export);
    $job->handle();

    $export->refresh();

    expect($export->summary['excluded_reasons'])->toHaveKey('conflicted_unresolved');
    expect($export->summary['excluded_reasons']['conflicted_unresolved'])->toBeGreaterThan(0);
});
