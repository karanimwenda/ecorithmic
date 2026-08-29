<?php

namespace App\Http\Controllers\ProductEnrichment;

use App\Http\Controllers\Controller;
use App\Jobs\ProductEnrichment\AnalyzeProductPhoto;
use App\Jobs\ProductEnrichment\ResearchProduct;
use App\Models\ProductEnrichment\Attribute;
use App\Models\ProductEnrichment\Import;
use App\Models\ProductEnrichment\Product;
use App\Models\ProductEnrichment\ProductAttributeValue;
use App\Services\ProductEnrichment\SpreadsheetImporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ImportConfirmationController extends Controller
{
    public function __construct(
        private readonly SpreadsheetImporter $spreadsheetImporter,
    ) {}

    public function store(Request $request, Import $import): RedirectResponse
    {
        if ($import->status !== 'pending_validation') {
            return redirect()->route('imports.show', $import)
                ->withErrors(['import' => 'This import has already been confirmed or rejected.']);
        }

        $absoluteSpreadsheetPath = Storage::disk('local')->path($import->spreadsheet_path);
        $absoluteArchivePath = $import->archive_path
            ? Storage::disk('local')->path($import->archive_path)
            : null;

        $photoStems = $absoluteArchivePath
            ? $this->spreadsheetImporter->extractPhotoStems($absoluteArchivePath)
            : [];

        $parsed = $this->spreadsheetImporter->parse($absoluteSpreadsheetPath, $photoStems);

        // Load all attributes keyed by code for fast lookup
        $attributes = Attribute::all()->keyBy('code');

        DB::transaction(function () use ($import, $parsed, $attributes, $absoluteArchivePath, $request) {
            $import->update([
                'status' => 'confirmed',
                'confirmed_by' => $request->user()?->id,
                'confirmed_at' => now(),
            ]);

            // Extract archive to temp dir for file access
            $tempDir = null;
            if ($absoluteArchivePath) {
                $tempDir = sys_get_temp_dir().'/pe_import_'.$import->id.'_'.uniqid();
                mkdir($tempDir, 0755, true);
                $zip = new \ZipArchive;
                if ($zip->open($absoluteArchivePath) === true) {
                    $zip->extractTo($tempDir);
                    $zip->close();
                }
            }

            foreach ($parsed['rows'] as $rowData) {
                $sku = trim((string) ($rowData['sku'] ?? ''));
                if (empty($sku)) {
                    continue;
                }

                // FR-006: update existing product, don't duplicate
                $product = Product::updateOrCreate(
                    ['sku' => $sku],
                    [
                        'last_import_id' => $import->id,
                    ]
                );

                if (! $product->first_import_id) {
                    $product->update(['first_import_id' => $import->id]);
                }

                // Seed manager-supplied attribute values
                foreach ($rowData as $code => $value) {
                    if (! isset($attributes[$code]) || $value === null || $value === '') {
                        continue;
                    }

                    $attribute = $attributes[$code];

                    // Supersede existing current value for this attribute
                    ProductAttributeValue::where('product_id', $product->id)
                        ->where('attribute_id', $attribute->id)
                        ->where('is_current', true)
                        ->update(['is_current' => false]);

                    $valueData = $this->buildValueData($attribute, $value);

                    ProductAttributeValue::create(array_merge($valueData, [
                        'product_id' => $product->id,
                        'attribute_id' => $attribute->id,
                        'origin' => 'manager',
                        'confidence_tier' => 'high',
                        'review_status' => 'approved',
                        'is_current' => true,
                    ]));
                }

                // FR-002: set is_primary on the matched original photo (first/only original)
                if ($tempDir) {
                    $this->attachPrimaryPhoto($product, $sku, $tempDir, $import);
                }
            }

            // Clean up temp dir
            if ($tempDir && is_dir($tempDir)) {
                $this->rrmdir($tempDir);
            }

            $import->update(['status' => 'processing']);
        });

        // Dispatch enrichment jobs for each newly imported/updated product
        foreach ($import->fresh()->products as $product) {
            ResearchProduct::dispatch($product, $import)->onQueue('research');
            AnalyzeProductPhoto::dispatch($product, $import)->onQueue('research');
        }

        return redirect()->route('imports.show', $import);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildValueData(Attribute $attribute, mixed $value): array
    {
        return match ($attribute->type) {
            'price' => ['float_value' => (float) $value],
            'boolean' => ['boolean_value' => (bool) $value],
            'json' => ['json_value' => is_array($value) ? $value : json_decode((string) $value, true)],
            default => ['text_value' => (string) $value],
        };
    }

    private function attachPrimaryPhoto(Product $product, string $sku, string $tempDir, Import $import): void
    {
        $extensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        foreach ($extensions as $ext) {
            $photoPath = $tempDir.'/'.$sku.'.'.$ext;
            if (file_exists($photoPath)) {
                // Only set primary if there's no existing primary for this product
                $hasExistingPrimary = $product->getMedia('original')->where('is_primary', true)->isNotEmpty();

                $media = $product->addMedia($photoPath)
                    ->preservingOriginal()
                    ->toMediaCollection('original');

                if (! $hasExistingPrimary) {
                    $media->update(['is_primary' => true]);
                }

                break;
            }
        }
    }

    private function rrmdir(string $dir): void
    {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object !== '.' && $object !== '..') {
                    $path = $dir.'/'.$object;
                    if (is_dir($path)) {
                        $this->rrmdir($path);
                    } else {
                        unlink($path);
                    }
                }
            }
            rmdir($dir);
        }
    }
}
