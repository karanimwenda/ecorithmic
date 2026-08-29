<?php

use Database\Factories\ProductEnrichment\ProductAttributeValueFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// ─── Initialize fixtures ─────────────────────────────────────────────────

// ─── Setup the environment ───────────────────────────────────────────────

uses(TestCase::class, RefreshDatabase::class);

// ─── Run the block of code in question ─────────────────────────────────

it('assigns high confidence to manager-origin values', function () {
    // Manager-supplied value → always high (FR-012)
    $value = ProductAttributeValueFactory::new()->manager()->make();

    // ─── Make assertions ──────────────────────────────────────────────────
    expect($value->confidence_tier)->toBe('high');
    expect($value->origin)->toBe('manager');
});

it('assigns high confidence to authoritative source values', function () {
    $value = ProductAttributeValueFactory::new()->aiResearch()->make([
        'confidence_tier' => 'high',
    ]);

    expect($value->confidence_tier)->toBe('high');
});

it('assigns medium confidence to ai_research with verified quote from allowed source', function () {
    $value = ProductAttributeValueFactory::new()->aiResearch()->make([
        'confidence_tier' => 'medium',
        'evidence_quote' => 'The weight is 500g.',
    ]);

    expect($value->confidence_tier)->toBe('medium');
    expect($value->evidence_quote)->not->toBeNull();
});

it('assigns low confidence to ai_vision origin always', function () {
    // FR-012/014: ai_vision ALWAYS gets low confidence — never derived from model output
    $value = ProductAttributeValueFactory::new()->aiVision()->make();

    expect($value->confidence_tier)->toBe('low');
    expect($value->origin)->toBe('ai_vision');
});

it('assigns low confidence to ai_generated_copy', function () {
    $value = ProductAttributeValueFactory::new()->aiGeneratedCopy()->make();

    expect($value->confidence_tier)->toBe('low');
    expect($value->origin)->toBe('ai_generated_copy');
});

it('assigns low confidence to conflicted values', function () {
    $value = ProductAttributeValueFactory::new()->conflicted()->make([
        'confidence_tier' => 'low',
    ]);

    expect($value->review_status)->toBe('conflicted');
    expect($value->confidence_tier)->toBe('low');
});

it('human_edit origin is always high confidence and approved', function () {
    $value = ProductAttributeValueFactory::new()->humanEdit()->make();

    expect($value->origin)->toBe('human_edit');
    expect($value->confidence_tier)->toBe('high');
    expect($value->review_status)->toBe('approved');
});
