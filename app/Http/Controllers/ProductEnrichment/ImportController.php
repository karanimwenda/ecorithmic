<?php

namespace App\Http\Controllers\ProductEnrichment;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProductEnrichment\StoreImportRequest;
use App\Models\ProductEnrichment\Import;
use App\Services\ProductEnrichment\SpreadsheetImporter;
use App\Services\ProductEnrichment\ZipInspector;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ImportController extends Controller
{
    public function __construct(
        private readonly ZipInspector $zipInspector,
        private readonly SpreadsheetImporter $spreadsheetImporter,
    ) {}

    public function store(StoreImportRequest $request): RedirectResponse
    {
        // Store files
        $spreadsheetPath = (string) $request->file('spreadsheet')->store('imports/spreadsheets', 'local');
        $archivePath = (string) $request->file('archive')->store('imports/archives', 'local');

        $absoluteArchivePath = Storage::disk('local')->path($archivePath);
        $absoluteSpreadsheetPath = Storage::disk('local')->path($spreadsheetPath);

        // Safety-check the archive first (FR-005)
        $archiveViolations = $this->zipInspector->inspect($absoluteArchivePath);
        if (! empty($archiveViolations)) {
            // Clean up stored files
            Storage::disk('local')->delete([$spreadsheetPath, $archivePath]);

            return redirect()->back()->withErrors([
                'archive' => 'Archive failed safety inspection: '.implode('; ', $archiveViolations),
            ]);
        }

        // Extract photo stems for matching
        $photoStems = $this->spreadsheetImporter->extractPhotoStems($absoluteArchivePath);

        // Parse spreadsheet and match rows to photos
        $parsed = $this->spreadsheetImporter->parse($absoluteSpreadsheetPath, $photoStems);

        // Determine if the upload should be auto-rejected (FR-004)
        $threshold = (float) config('product-enrichment.unmatched_row_threshold', 0.5);
        $rowCount = $parsed['row_count'];
        $unmatchedRatio = $rowCount > 0 ? $parsed['unmatched_row_count'] / $rowCount : 0;
        $autoRejected = $unmatchedRatio > $threshold;

        // Estimate processing cost (research.md §1 pricing: sonar-pro per-request + tokens estimate)
        // Conservative estimate: $0.018 per product (sonar call fee) + $0.02 copy + $0.01 vision
        $estimatedCostUsd = $rowCount * 0.05;

        $validationReport = [
            'matched' => $parsed['matched_row_count'],
            'unmatched_rows' => $parsed['unmatched_row_skus'],
            'unmatched_photos' => $parsed['unmatched_photo_stems'],
            'duplicate_skus' => $parsed['duplicate_skus'],
            'unreadable_rows' => $parsed['unreadable_rows'],
        ];

        $import = Import::create([
            'spreadsheet_path' => $spreadsheetPath,
            'spreadsheet_original_filename' => $request->file('spreadsheet')->getClientOriginalName(),
            'archive_path' => $archivePath,
            'row_count' => $parsed['row_count'],
            'matched_row_count' => $parsed['matched_row_count'],
            'unmatched_row_count' => $parsed['unmatched_row_count'],
            'unmatched_photo_count' => $parsed['unmatched_photo_count'],
            'duplicate_sku_count' => $parsed['duplicate_sku_count'],
            'unreadable_file_count' => $parsed['unreadable_file_count'],
            'estimated_cost_usd' => $estimatedCostUsd,
            'processing_cost_usd' => 0,
            'cost_cap_usd' => $request->input('cost_cap_usd'),
            'status' => $autoRejected ? 'rejected' : 'pending_validation',
            'validation_report' => $validationReport,
        ]);

        return redirect()->route('imports.show', $import);
    }

    public function show(Import $import): Response
    {
        return Inertia::render('ProductEnrichment/Imports/ValidationReport', [
            'importData' => [
                'id' => $import->id,
                'status' => $import->status,
                'validation_report' => $import->validation_report,
                'estimated_cost_usd' => $import->estimated_cost_usd,
                'processing_cost_usd' => $import->processing_cost_usd,
                'cost_cap_usd' => $import->cost_cap_usd,
                'row_count' => $import->row_count,
                'matched_row_count' => $import->matched_row_count,
                'unmatched_row_count' => $import->unmatched_row_count,
                'unmatched_photo_count' => $import->unmatched_photo_count,
                'duplicate_sku_count' => $import->duplicate_sku_count,
                'unreadable_file_count' => $import->unreadable_file_count,
                'confirmed_at' => $import->confirmed_at,
                'created_at' => $import->created_at,
            ],
        ]);
    }
}
