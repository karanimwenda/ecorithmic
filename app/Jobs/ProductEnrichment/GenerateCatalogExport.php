<?php

namespace App\Jobs\ProductEnrichment;

use App\Models\ProductEnrichment\Export;
use App\Models\ProductEnrichment\Product;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Rap2hpoutre\FastExcel\FastExcel;

/**
 * Generates the catalog export: approved spreadsheet, image package, manifest (FR-023/024).
 * Catalog-wide — not scoped to any import.
 */
class GenerateCatalogExport implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 600;

    public function __construct(
        public readonly Export $export,
    ) {}

    public function handle(): void
    {
        $this->export->update(['status' => 'processing']);

        $products = Product::with([
            'currentAttributeValues.attribute',
            'currentAttributeValues.source',
        ])->get();

        $spreadsheetRows = [];
        $manifest = [];
        $includedProducts = 0;
        $excludedProducts = 0;
        $includedFields = 0;
        $excludedFields = 0;
        $excludedReasons = [];

        $imagesDir = sys_get_temp_dir().'/pe_export_images_'.$this->export->id;
        mkdir($imagesDir, 0755, true);

        foreach ($products as $product) {
            $row = ['sku' => $product->sku];
            $hasAnyApproved = false;
            $productHasApprovedAsset = false;

            $currentValues = $product->currentAttributeValues;

            foreach ($currentValues as $value) {
                $code = $value->attribute?->code;
                if (! $code) {
                    continue;
                }

                if ($value->review_status === 'approved') {
                    $row[$code] = $value->getDisplayValue();
                    $row[$code.'_source'] = $value->source->url ?? 'no_source';
                    $row[$code.'_confidence_tier'] = $value->confidence_tier;
                    $hasAnyApproved = true;
                    $includedFields++;

                    $manifest[] = [
                        'product_sku' => $product->sku,
                        'attribute' => $code,
                        'value' => $value->getDisplayValue(),
                        'source_url' => $value->source->url ?? null,
                        'confidence_tier' => $value->confidence_tier,
                        'origin' => $value->origin,
                    ];
                } elseif ($value->review_status === 'conflicted') {
                    $excludedFields++;
                    $excludedReasons['conflicted_unresolved'] = ($excludedReasons['conflicted_unresolved'] ?? 0) + 1;
                } elseif ($value->review_status === 'pending') {
                    $excludedFields++;
                    $excludedReasons['pending_review'] = ($excludedReasons['pending_review'] ?? 0) + 1;
                } elseif ($value->review_status === 'rejected') {
                    $excludedFields++;
                    $excludedReasons['rejected'] = ($excludedReasons['rejected'] ?? 0) + 1;
                }
            }

            // Copy approved image assets
            $productImagesDir = $imagesDir.'/'.$product->sku;
            foreach (['original', 'primary-ecommerce', 'social-square'] as $collection) {
                $approvedAssets = $product->getMedia($collection)
                    ->where('review_status', 'approved');

                foreach ($approvedAssets as $asset) {
                    $assetPath = $asset->getPath();
                    if (file_exists($assetPath)) {
                        if (! is_dir($productImagesDir)) {
                            mkdir($productImagesDir, 0755, true);
                        }
                        copy($assetPath, $productImagesDir.'/'.$collection.'_'.$asset->file_name);
                        $productHasApprovedAsset = true;

                        $manifest[] = [
                            'product_sku' => $product->sku,
                            'asset_collection' => $collection,
                            'file_name' => $asset->file_name,
                            'source' => 'uploaded',
                        ];
                    }
                }
            }

            if ($hasAnyApproved || $productHasApprovedAsset) {
                $spreadsheetRows[] = $row;
                $includedProducts++;
            } else {
                $excludedProducts++;
                $excludedReasons['no_approved_content'] = ($excludedReasons['no_approved_content'] ?? 0) + 1;
            }
        }

        // Write spreadsheet
        $spreadsheetPath = 'exports/export_'.$this->export->id.'_catalog.xlsx';
        $absoluteSpreadsheetPath = Storage::disk('local')->path($spreadsheetPath);
        Storage::disk('local')->makeDirectory('exports');

        if (! empty($spreadsheetRows)) {
            (new FastExcel(collect($spreadsheetRows)))->export($absoluteSpreadsheetPath);
        } else {
            // Empty export — create a file with just a header row
            (new FastExcel(collect([['sku' => 'no_approved_products']])))->export($absoluteSpreadsheetPath);
        }

        // Write manifest
        $manifestPath = 'exports/export_'.$this->export->id.'_manifest.json';
        Storage::disk('local')->put($manifestPath, (string) json_encode($manifest, JSON_PRETTY_PRINT));

        // Create images ZIP
        $imagesZipPath = 'exports/export_'.$this->export->id.'_images.zip';
        $absoluteImagesZipPath = Storage::disk('local')->path($imagesZipPath);
        $this->zipDirectory($imagesDir, $absoluteImagesZipPath);

        // Clean up temp images
        $this->rrmdir($imagesDir);

        $summary = [
            'included_products' => $includedProducts,
            'excluded_products' => $excludedProducts,
            'included_fields' => $includedFields,
            'excluded_fields' => $excludedFields,
            'excluded_reasons' => $excludedReasons,
            'message' => $includedProducts === 0
                ? 'All products were excluded from export — nothing is approved yet.'
                : null,
        ];

        $this->export->update([
            'status' => 'completed',
            'summary' => $summary,
            'spreadsheet_path' => $spreadsheetPath,
            'images_path' => $imagesZipPath,
            'manifest_path' => $manifestPath,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        $this->export->update(['status' => 'failed']);
    }

    private function zipDirectory(string $sourceDir, string $outputZip): void
    {
        $zip = new \ZipArchive;
        if ($zip->open($outputZip, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            return;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourceDir),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $file) {
            if (! $file->isDir()) {
                $filePath = $file->getRealPath();
                $relativePath = substr($filePath, strlen($sourceDir) + 1);
                $zip->addFile($filePath, $relativePath);
            }
        }

        $zip->close();
    }

    private function rrmdir(string $dir): void
    {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object !== '.' && $object !== '..') {
                    $path = $dir.'/'.$object;
                    is_dir($path) ? $this->rrmdir($path) : unlink($path);
                }
            }
            rmdir($dir);
        }
    }
}
