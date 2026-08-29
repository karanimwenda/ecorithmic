<?php

use App\Services\ProductEnrichment\QuoteVerifier;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

// ─── Initialize fixtures ─────────────────────────────────────────────────

uses(TestCase::class);

beforeEach(function () {
    $this->verifier = new QuoteVerifier;
});

// ─── Setup the environment ───────────────────────────────────────────────

// Uses Http::fake() — no live network calls

// ─── Run the block of code in question ─────────────────────────────────

it('returns true when quote appears in page content', function () {
    Http::fake([
        'example.com/*' => Http::response(
            '<html><body>This product is made from premium materials.</body></html>',
            200,
        ),
    ]);

    $result = $this->verifier->verify('premium materials', 'https://example.com/product');

    // ─── Make assertions ──────────────────────────────────────────────────
    expect($result)->toBeTrue();
});

it('returns true for case-insensitive match', function () {
    Http::fake([
        'example.com/*' => Http::response(
            '<html><body>This product is made from PREMIUM MATERIALS.</body></html>',
            200,
        ),
    ]);

    expect($this->verifier->verify('premium materials', 'https://example.com/product'))->toBeTrue();
});

it('returns false when quote does not appear in page', function () {
    Http::fake([
        'example.com/*' => Http::response(
            '<html><body>This product has no matching content.</body></html>',
            200,
        ),
    ]);

    expect($this->verifier->verify('premium materials', 'https://example.com/product'))->toBeFalse();
});

it('returns false when source page is not accessible', function () {
    Http::fake([
        'example.com/*' => Http::response('Not found', 404),
    ]);

    expect($this->verifier->verify('premium materials', 'https://example.com/product'))->toBeFalse();
});

it('returns false when source page throws an exception', function () {
    Http::fake([
        'example.com/*' => fn () => throw new RuntimeException('Connection failed'),
    ]);

    expect($this->verifier->verify('some quote', 'https://example.com/product'))->toBeFalse();
});

it('strips html tags before matching', function () {
    Http::fake([
        'example.com/*' => Http::response(
            '<html><body><p>The <strong>weight</strong> is 500 grams.</p></body></html>',
            200,
        ),
    ]);

    expect($this->verifier->verify('weight is 500 grams', 'https://example.com/product'))->toBeTrue();
});
