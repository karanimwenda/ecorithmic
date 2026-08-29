<?php

namespace App\Http\Controllers\ProductEnrichment;

use App\Http\Controllers\Controller;
use App\Models\ProductEnrichment\Export;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportArtifactController extends Controller
{
    public function show(Export $export, string $artifact): StreamedResponse|HttpResponse
    {
        if ($export->status !== 'completed') {
            abort(404, 'Export is not yet complete.');
        }

        $pathColumn = match ($artifact) {
            'spreadsheet' => $export->spreadsheet_path,
            'images' => $export->images_path,
            'manifest' => $export->manifest_path,
            default => null,
        };

        if (! $pathColumn || ! Storage::disk('local')->exists($pathColumn)) {
            abort(404, 'Artifact not found.');
        }

        $mimeType = match ($artifact) {
            'spreadsheet' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'images' => 'application/zip',
            'manifest' => 'application/json',
            default => 'application/octet-stream',
        };

        return Storage::disk('local')->download($pathColumn, $artifact.'_export_'.$export->id, [
            'Content-Type' => $mimeType,
        ]);
    }
}
