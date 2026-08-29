<?php

use App\Services\ProductEnrichment\ResearchResultsCache;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

// ─── Initialize fixtures ─────────────────────────────────────────────────

uses(TestCase::class);

beforeEach(function () {
    $this->cache = new ResearchResultsCache;
    $this->identifiers = ['sku' => 'SKU123', 'name' => 'Widget Pro', 'brand' => 'Acme'];
});

// ─── Setup the environment ───────────────────────────────────────────────

// Uses Cache facade — no real Redis needed in tests (uses array driver)

// ─── Run the block of code in question ─────────────────────────────────

it('returns null on a cache miss', function () {
    $result = $this->cache->get($this->identifiers);

    // ─── Make assertions ──────────────────────────────────────────────────
    expect($result)->toBeNull();
});

it('stores and retrieves research facts', function () {
    $facts = ['facts' => [['attribute_code' => 'color', 'value' => 'blue', 'source_url' => 'http://x.com', 'quote' => 'blue', 'source_trust_hint' => 'allowed']]];

    $this->cache->put($this->identifiers, $facts);
    $result = $this->cache->get($this->identifiers);

    expect($result)->toBe($facts);
});

it('uses a stable key regardless of identifier order', function () {
    $facts = ['facts' => []];
    $this->cache->put($this->identifiers, $facts);

    // Same identifiers in different order should hit the same cache key
    $reordered = ['brand' => 'Acme', 'sku' => 'SKU123', 'name' => 'Widget Pro'];
    $result = $this->cache->get($reordered);

    expect($result)->toBe($facts);
});

it('returns null after forgetting the key', function () {
    $facts = ['facts' => []];
    $this->cache->put($this->identifiers, $facts);
    $this->cache->forget($this->identifiers);

    expect($this->cache->get($this->identifiers))->toBeNull();
});

it('ignores non-identifier keys', function () {
    $facts = ['facts' => []];
    $this->cache->put($this->identifiers, $facts);

    // Adding extra keys that aren't in the stable set should still hit
    $withExtra = array_merge($this->identifiers, ['locale' => 'en', 'random' => 'value']);
    $result = $this->cache->get($withExtra);

    expect($result)->toBe($facts);
});
