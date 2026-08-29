<?php

namespace App\Services\ProductEnrichment;

use App\Models\ProductEnrichment\Attribute;
use Illuminate\Support\Collection;
use Rap2hpoutre\FastExcel\FastExcel;

/**
 * Parses an uploaded XLSX or CSV spreadsheet against the seeded Attribute set,
 * and matches rows to an array of extracted photo filename stems (from a ZIP
 * archive) by exact-stem equality per FR-002.
 */
class SpreadsheetImporter
{
    /** Required column in every upload. */
    private const string SKU_COLUMN = 'sku';

    /**
     * Parse a spreadsheet and return a validation summary.
     *
     * @param  string[]  $photoStems  Filename stems from the ZIP (e.g. ['SKU123', 'SKU456'])
     * @return array{
     *     rows: Collection<int, array<string, mixed>>,
     *     matched_skus: string[],
     *     unmatched_row_skus: string[],
     *     unmatched_photo_stems: string[],
     *     duplicate_skus: string[],
     *     unreadable_rows: int[],
     *     row_count: int,
     *     matched_row_count: int,
     *     unmatched_row_count: int,
     *     unmatched_photo_count: int,
     *     duplicate_sku_count: int,
     *     unreadable_file_count: int,
     * }
     */
    public function parse(string $spreadsheetPath, array $photoStems = []): array
    {
        $allowedCodes = Attribute::pluck('code')->all();

        /** @var Collection<int, array<string, mixed>> $rows */
        $rows = collect();
        $unreadableRows = [];

        $fastExcel = new FastExcel;
        $rawRows = $fastExcel->import($spreadsheetPath);

        $rowIndex = 0;
        foreach ($rawRows as $row) {
            $rowIndex++;

            // Normalize keys to lowercase snake_case
            $normalized = [];
            foreach ($row as $key => $value) {
                $normalized[strtolower(trim((string) $key))] = $value;
            }

            // Skip rows that don't have a SKU
            if (empty($normalized[self::SKU_COLUMN])) {
                $unreadableRows[] = $rowIndex;

                continue;
            }

            // Filter to only known attribute codes
            $filtered = array_intersect_key($normalized, array_flip($allowedCodes));
            $rows->push($filtered);
        }

        $rowCount = $rows->count();

        // Find duplicate SKUs within this upload
        $skus = $rows->pluck(self::SKU_COLUMN)->map(fn ($s) => trim((string) $s))->all();
        $skuCounts = array_count_values($skus);
        $duplicateSkus = array_keys(array_filter($skuCounts, fn ($c) => $c > 1));

        // Match rows to photos
        $photoStemsSet = array_flip($photoStems);
        $matchedSkus = [];
        $unmatchedRowSkus = [];

        foreach ($skus as $sku) {
            if (isset($photoStemsSet[$sku])) {
                $matchedSkus[] = $sku;
            } else {
                $unmatchedRowSkus[] = $sku;
            }
        }

        $matchedStemsSet = array_flip($matchedSkus);
        $unmatchedPhotoStems = array_values(array_filter($photoStems, fn ($stem) => ! isset($matchedStemsSet[$stem])));

        return [
            'rows' => $rows,
            'matched_skus' => $matchedSkus,
            'unmatched_row_skus' => $unmatchedRowSkus,
            'unmatched_photo_stems' => $unmatchedPhotoStems,
            'duplicate_skus' => $duplicateSkus,
            'unreadable_rows' => $unreadableRows,
            'row_count' => $rowCount,
            'matched_row_count' => count($matchedSkus),
            'unmatched_row_count' => count($unmatchedRowSkus),
            'unmatched_photo_count' => count($unmatchedPhotoStems),
            'duplicate_sku_count' => count($duplicateSkus),
            'unreadable_file_count' => count($unreadableRows),
        ];
    }

    /**
     * Extract all filename stems from a ZIP archive (without extracting contents).
     * Returns only image-extension stems (jpg, jpeg, png, webp, gif).
     *
     * @return string[]
     */
    public function extractPhotoStems(string $archivePath): array
    {
        $zip = new \ZipArchive;
        if ($zip->open($archivePath) !== true) {
            return [];
        }

        $stems = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            if ($stat === false) {
                continue;
            }
            $name = basename($stat['name']);
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], strict: true)) {
                $stems[] = pathinfo($name, PATHINFO_FILENAME);
            }
        }

        $zip->close();

        return $stems;
    }
}
