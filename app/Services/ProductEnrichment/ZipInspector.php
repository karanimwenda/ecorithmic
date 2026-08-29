<?php

namespace App\Services\ProductEnrichment;

use ZipArchive;

/**
 * Performs pre-extraction safety checks on a ZIP archive per FR-005 and research.md §6.
 * Rejects: encryption, path-traversal entries, and decompression bombs.
 * Never extracts or executes anything until all checks pass.
 */
class ZipInspector
{
    /** Maximum ratio of uncompressed to compressed size (decompression bomb guard). */
    private const int BOMB_RATIO_THRESHOLD = 100;

    /** Maximum total uncompressed size: 1 GB. */
    private const int MAX_UNCOMPRESSED_BYTES = 1_073_741_824;

    /**
     * Inspect the archive at $path. Returns an array of violation strings.
     * An empty array means the archive is safe to extract.
     *
     * @return array<int, string>
     */
    public function inspect(string $path): array
    {
        $zip = new ZipArchive;
        $result = $zip->open($path);

        if ($result !== true) {
            return ['Unable to open archive (error code: '.$result.')'];
        }

        // Check archive-level encryption via entry inspection
        if ($zip->numFiles > 0 && $this->isArchiveEncrypted($zip)) {
            $zip->close();

            return ['Archive is encrypted and cannot be processed'];
        }

        $violations = [];
        $totalUncompressed = 0;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);

            if ($stat === false) {
                continue;
            }

            $name = $stat['name'];

            // Path-traversal guard: normalize and check it resolves within root
            if ($this->hasPathTraversal($name)) {
                $violations[] = 'Path-traversal entry: '.$name;

                continue;
            }

            // Per-entry encryption check
            $entryInfo = $zip->statIndex($i, ZipArchive::FL_UNCHANGED);
            if ($entryInfo !== false && $entryInfo['encryption_method'] !== 0) {
                $violations[] = 'Encrypted entry: '.$name;

                continue;
            }

            // Decompression bomb: per-entry ratio
            $compressedSize = (int) $stat['comp_size'];
            $uncompressedSize = (int) $stat['size'];

            if ($compressedSize > 0) {
                $ratio = $uncompressedSize / $compressedSize;
                if ($ratio > self::BOMB_RATIO_THRESHOLD) {
                    $violations[] = sprintf(
                        'Decompression bomb: entry "%s" has a %d:1 compression ratio',
                        $name,
                        (int) $ratio,
                    );
                }
            }

            $totalUncompressed += $uncompressedSize;
        }

        // Decompression bomb: total size guard
        if ($totalUncompressed > self::MAX_UNCOMPRESSED_BYTES) {
            $violations[] = sprintf(
                'Archive total uncompressed size (%d bytes) exceeds the 1GB limit',
                $totalUncompressed,
            );
        }

        $zip->close();

        return $violations;
    }

    /** Whether the archive is safe to extract (no violations). */
    public function isSafe(string $path): bool
    {
        return $this->inspect($path) === [];
    }

    private function hasPathTraversal(string $name): bool
    {
        // Normalize the entry name and check if it would escape the root
        $normalized = str_replace('\\', '/', $name);
        $parts = explode('/', $normalized);

        $depth = 0;
        foreach ($parts as $part) {
            if ($part === '..') {
                $depth--;
                if ($depth < 0) {
                    return true;
                }
            } elseif ($part !== '' && $part !== '.') {
                $depth++;
            }
        }

        return false;
    }

    private function isArchiveEncrypted(ZipArchive $zip): bool
    {
        // Try to stat the first entry — if the archive requires a password to read,
        // statIndex will return false or the entry will have encryption_method set.
        $stat = $zip->statIndex(0, ZipArchive::FL_UNCHANGED);
        if ($stat === false) {
            return false;
        }

        return $stat['encryption_method'] !== 0;
    }
}
