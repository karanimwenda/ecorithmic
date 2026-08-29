<?php

use App\Services\ProductEnrichment\ZipInspector;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    $this->inspector = new ZipInspector;
    $this->tmpDir = sys_get_temp_dir().'/zip_inspector_tests_'.uniqid();
    mkdir($this->tmpDir);
});

afterEach(function () {
    $files = glob($this->tmpDir.'/*') ?: [];
    foreach ($files as $file) {
        unlink($file);
    }
    @rmdir($this->tmpDir);
});

// ─── Initialize fixtures ────────────────────────────────────────────────────

function makeCleanZip(string $dir): string
{
    $path = $dir.'/clean.zip';
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE);
    $zip->addFromString('photo.jpg', str_repeat('x', 1000));
    $zip->close();

    return $path;
}

function makePathTraversalZip(string $dir): string
{
    $path = $dir.'/traversal.zip';
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE);
    $zip->addFromString('../escape.jpg', str_repeat('x', 100));
    $zip->close();

    return $path;
}

function makeDecompBombZip(string $dir): string
{
    // Create a file with an extreme ratio — large uncompressed, highly compressible.
    // 2MB of zeros compresses to ~2KB, producing a >100:1 ratio (BOMB_RATIO_THRESHOLD).
    $path = $dir.'/bomb.zip';
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE);
    $zip->addFromString('bomb.bin', str_repeat("\0", 2 * 1024 * 1024));
    $zip->close();

    return $path;
}

function makeEncryptedZip(string $dir): string
{
    // Create a ZIP with an encrypted entry using ZipArchive::setEncryptionName (AES-256).
    $path = $dir.'/encrypted.zip';
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE);
    $zip->setPassword('secret');
    $zip->addFromString('photo.jpg', 'encrypted content');
    $zip->setEncryptionName('photo.jpg', ZipArchive::EM_AES_256);
    $zip->close();

    return $path;
}

// ─── Setup the environment ───────────────────────────────────────────────────

// (No external setup needed — ZipInspector uses only the filesystem)

// ─── Run the block of code in question ────────────────────────────────────

it('returns no violations for a clean archive', function () {
    $zipPath = makeCleanZip($this->tmpDir);
    $violations = $this->inspector->inspect($zipPath);
    expect($violations)->toBeEmpty();
});

it('detects path-traversal entries', function () {
    $zipPath = makePathTraversalZip($this->tmpDir);
    $violations = $this->inspector->inspect($zipPath);

    // ─── Make assertions ────────────────────────────────────────────────────
    expect($violations)->not->toBeEmpty();
    expect(implode(' ', $violations))->toContain('traversal');
});

it('detects decompression bombs by ratio', function () {
    $zipPath = makeDecompBombZip($this->tmpDir);
    $violations = $this->inspector->inspect($zipPath);

    // ─── Make assertions ────────────────────────────────────────────────────
    // 2MB of zeros compresses far beyond the 100:1 threshold — a violation MUST be reported
    expect($violations)->not->toBeEmpty();
    expect(implode(' ', $violations))->toContain('bomb');
});

it('detects encrypted archives (FR-005)', function () {
    $zipPath = makeEncryptedZip($this->tmpDir);
    $violations = $this->inspector->inspect($zipPath);

    // ─── Make assertions ────────────────────────────────────────────────────
    expect($violations)->not->toBeEmpty();
    expect(implode(' ', array_map(strtolower(...), $violations)))->toContain('encrypt');
});

it('reports safe when no violations exist', function () {
    $zipPath = makeCleanZip($this->tmpDir);
    expect($this->inspector->isSafe($zipPath))->toBeTrue();
});

it('reports unsafe when violations exist', function () {
    $zipPath = makePathTraversalZip($this->tmpDir);
    expect($this->inspector->isSafe($zipPath))->toBeFalse();
});

it('returns a violation for a non-existent file', function () {
    $violations = $this->inspector->inspect('/nonexistent/path.zip');
    expect($violations)->not->toBeEmpty();
});
