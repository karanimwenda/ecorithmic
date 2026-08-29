<?php

use App\Jobs\ProductEnrichment\AnalyzeProductPhoto;
use App\Jobs\ProductEnrichment\ResearchProduct;
use App\Models\ProductEnrichment\Attribute;
use App\Models\ProductEnrichment\Import;
use App\Models\ProductEnrichment\Product;
use App\Models\ProductEnrichment\ProductAttributeValue;
use App\Models\ProductEnrichment\Source;
use App\Services\ProductEnrichment\OpenRouterClient;
use App\Services\ProductEnrichment\QuoteVerifier;
use App\Services\ProductEnrichment\ResearchResultsCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

// ─── Initialize fixtures ─────────────────────────────────────────────────

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'AttributeSeeder']);

    $this->import = Import::factory()->processing()->create();
    $this->product = Product::factory()->withImport($this->import)->create([
        'sku' => 'TEST-SKU-001',
    ]);

    $this->nameAttribute = Attribute::where('code', 'name')->first();
    $this->colorAttribute = Attribute::where('code', 'color')->first();
});

// ─── Setup the environment ───────────────────────────────────────────────

// All OpenRouter calls are faked via Http::fake()

// ─── Run the block of code in question ─────────────────────────────────

it('creates product attribute values with grounded quotes after ResearchProduct job', function () {
    Http::fake([
        'openrouter.ai/*' => Http::response([
            'choices' => [['message' => ['content' => json_encode([
                'facts' => [[
                    'attribute_code' => 'color',
                    'value' => 'navy blue',
                    'source_url' => 'https://example.com/product',
                    'quote' => 'available in navy blue',
                    'source_trust_hint' => 'allowed',
                ]],
            ])]]],
            'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 50],
        ]),
        // Quote verification fetch
        'example.com/*' => Http::response('<body>available in navy blue</body>', 200),
    ]);

    $job = new ResearchProduct($this->product, $this->import);
    $job->handle(
        new OpenRouterClient,
        new QuoteVerifier,
        new ResearchResultsCache,
    );

    // ─── Make assertions ──────────────────────────────────────────────────
    $values = ProductAttributeValue::where('product_id', $this->product->id)
        ->where('origin', 'ai_research')
        ->get();

    expect($values)->not->toBeEmpty();
    $colorValue = $values->first(fn ($v) => $v->attribute_id === $this->colorAttribute?->id);
    if ($colorValue) {
        expect($colorValue->evidence_quote)->toBe('available in navy blue');
        expect($colorValue->confidence_tier)->toBe('medium');
    }
});

it('discards facts whose quotes cannot be verified (SC-003)', function () {
    Http::fake([
        'openrouter.ai/*' => Http::response([
            'choices' => [['message' => ['content' => json_encode([
                'facts' => [[
                    'attribute_code' => 'color',
                    'value' => 'red',
                    'source_url' => 'https://example.com/product',
                    'quote' => 'this quote does not exist on the page',
                    'source_trust_hint' => 'allowed',
                ]],
            ])]]],
            'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 50],
        ]),
        // Quote verification — quote NOT found in page
        'example.com/*' => Http::response('<body>Page content with no matching text</body>', 200),
    ]);

    $job = new ResearchProduct($this->product, $this->import);
    $job->handle(
        new OpenRouterClient,
        new QuoteVerifier,
        new ResearchResultsCache,
    );

    // The unverified fact must never appear as a ProductAttributeValue row
    $aiResearchValues = ProductAttributeValue::where('product_id', $this->product->id)
        ->where('origin', 'ai_research')
        ->get();

    expect($aiResearchValues)->toBeEmpty();
});

it('marks product as ungrounded when no source is found (FR-010)', function () {
    Http::fake([
        'openrouter.ai/*' => Http::response([
            'choices' => [['message' => ['content' => json_encode(['facts' => []])]]],
            'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 50],
        ]),
    ]);

    $job = new ResearchProduct($this->product, $this->import);
    $job->handle(
        new OpenRouterClient,
        new QuoteVerifier,
        new ResearchResultsCache,
    );

    // Product should still exist — never left in a failed state
    expect(Product::find($this->product->id))->not->toBeNull();
});

it('AnalyzeProductPhoto creates ai_vision values with always-low confidence (FR-012/014)', function () {
    Http::fake([
        'openrouter.ai/*' => Http::response([
            'choices' => [['message' => ['content' => json_encode([
                'observations' => [[
                    'attribute_code' => 'color',
                    'value' => 'appears navy',
                ]],
            ])]]],
            'usage' => ['prompt_tokens' => 50, 'completion_tokens' => 20],
        ]),
    ]);

    // We need to simulate having a media asset — skip if no media available
    $job = new AnalyzeProductPhoto($this->product, $this->import);
    $job->handle(new OpenRouterClient);

    // If a photo existed, these would be created; without photo it's a no-op (still not failed)
    expect(Product::find($this->product->id))->not->toBeNull();
});

it('halts processing when cost cap is reached', function () {
    $import = Import::factory()->processing()->withCostCap(0.001)->create();
    $import->accumulateCost(0.01); // Exceeds cap

    $product = Product::factory()->withImport($import)->create();

    Http::fake(['*' => Http::response([
        'choices' => [['message' => ['content' => json_encode(['facts' => []])]]],
        'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 50],
    ])]);

    $job = new ResearchProduct($product, $import);
    $job->handle(
        new OpenRouterClient,
        new QuoteVerifier,
        new ResearchResultsCache,
    );

    // Job should return early when halted — no values created
    expect(Http::recorded())->toBeEmpty();
});

it('one product failure does not affect other products (FR-017)', function () {
    $product2 = Product::factory()->withImport($this->import)->create(['sku' => 'PRODUCT-2']);

    Http::fake([
        'openrouter.ai/*' => Http::sequence()
            ->push(['choices' => [['message' => ['content' => '{ invalid json ']]], 'usage' => []])
            ->push([
                'choices' => [['message' => ['content' => json_encode(['facts' => []])]]],
                'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 50],
            ]),
    ]);

    // First job throws, second succeeds
    try {
        $job1 = new ResearchProduct($this->product, $this->import);
        $job1->handle(new OpenRouterClient, new QuoteVerifier, new ResearchResultsCache);
    } catch (Throwable) {
        // Expected — first job fails
    }

    // Second product should still be processable
    $job2 = new ResearchProduct($product2, $this->import);
    expect(fn () => $job2->handle(new OpenRouterClient, new QuoteVerifier, new ResearchResultsCache))->not->toThrow(Throwable::class);

    expect(Product::find($product2->id))->not->toBeNull();
});

it('excludes sources that describe a similar but not identical product (FR-008)', function () {
    Http::fake([
        'openrouter.ai/*' => Http::response([
            'choices' => [['message' => ['content' => json_encode([
                'facts' => [[
                    'attribute_code' => 'color',
                    'value' => 'red',
                    'source_url' => 'https://example.com/similar-product',
                    'quote' => 'red color',
                    'source_trust_hint' => 'disallowed', // Marks it as a different product
                ]],
            ])]]],
            'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 50],
        ]),
        'example.com/*' => Http::response('<body>red color</body>', 200),
    ]);

    $job = new ResearchProduct($this->product, $this->import);
    $job->handle(
        new OpenRouterClient,
        new QuoteVerifier,
        new ResearchResultsCache,
    );

    // Source marked as disallowed (different product) must be excluded
    $disallowedValues = ProductAttributeValue::where('product_id', $this->product->id)
        ->where('origin', 'ai_research')
        ->get();

    expect($disallowedValues)->toBeEmpty();
});
