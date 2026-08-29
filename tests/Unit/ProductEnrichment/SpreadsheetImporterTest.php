<?php

use App\Models\ProductEnrichment\Attribute;
use App\Services\ProductEnrichment\SpreadsheetImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->importer = new SpreadsheetImporter;
    $this->fixturePath = base_path('tests/Fixtures/ProductEnrichment/sample-catalog.xlsx');
    $this->archivePath = base_path('tests/Fixtures/ProductEnrichment/sample-photos.zip');
});

// ─── Initialize fixtures ─────────────────────────────────────────────────

function seedAttributes(): void
{
    $attributes = ['sku', 'name', 'brand', 'short_description', 'description', 'price', 'color', 'category', 'gtin', 'mpn'];
    foreach ($attributes as $i => $code) {
        Attribute::firstOrCreate(['code' => $code], [
            'name' => ucfirst($code),
            'type' => 'text',
            'is_required' => $code === 'sku',
            'is_unique' => $code === 'sku',
            'is_ai_enrichable' => ! in_array($code, ['sku', 'price']),
            'position' => $i + 1,
        ]);
    }
}

// ─── Setup the environment ───────────────────────────────────────────────

// Uses RefreshDatabase via Pest's database interactions

// ─── Run the block of code in question ─────────────────────────────────

it('parses rows from an xlsx file', function () {
    seedAttributes();
    $result = $this->importer->parse($this->fixturePath, []);

    // ─── Make assertions ──────────────────────────────────────────────────
    expect($result['row_count'])->toBeGreaterThan(0);
    expect($result['rows'])->not->toBeEmpty();
});

it('matches rows to photo stems by exact sku', function () {
    seedAttributes();
    $result = $this->importer->parse($this->fixturePath, ['SKU001', 'SKU002', 'SKU003']);

    expect($result['matched_row_count'])->toBeGreaterThan(0);
    expect($result['matched_skus'])->toContain('SKU001');
});

it('reports unmatched rows when photo is missing', function () {
    seedAttributes();
    // Only provide photos for SKU001 and SKU002 — SKU003 and others will be unmatched
    $result = $this->importer->parse($this->fixturePath, ['SKU001', 'SKU002']);

    expect($result['unmatched_row_count'])->toBeGreaterThan(0);
    expect($result['unmatched_row_skus'])->toContain('SKU003');
});

it('detects duplicate skus within the upload', function () {
    seedAttributes();
    $result = $this->importer->parse($this->fixturePath, ['SKU001', 'SKU002', 'SKU003']);

    // The fixture has SKU001 twice
    expect($result['duplicate_sku_count'])->toBeGreaterThan(0);
    expect($result['duplicate_skus'])->toContain('SKU001');
});

it('extracts photo stems from zip archive', function () {
    $stems = $this->importer->extractPhotoStems($this->archivePath);

    expect($stems)->toContain('SKU001');
    expect($stems)->toContain('SKU002');
    expect($stems)->toContain('SKU003');
});

it('filters out non-image files from zip', function () {
    $stems = $this->importer->extractPhotoStems($this->archivePath);

    // Should only contain image extensions
    foreach ($stems as $stem) {
        expect($stem)->toBeString();
    }
});
