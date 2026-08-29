<?php

namespace App\Http\Controllers\ProductEnrichment;

use App\Http\Controllers\Controller;
use App\Jobs\ProductEnrichment\GenerateCatalogExport;
use App\Models\ProductEnrichment\Export;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ExportController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $export = Export::create([
            'status' => 'pending',
            'requested_by' => $request->user()?->id,
            'summary' => null,
        ]);

        GenerateCatalogExport::dispatch($export)->onQueue('default');

        return redirect()->route('exports.show', $export);
    }

    public function show(Export $export): Response
    {
        return Inertia::render('ProductEnrichment/Exports/Show', [
            'exportData' => [
                'id' => $export->id,
                'status' => $export->status,
                'summary' => $export->summary,
                'spreadsheet_url' => $export->status === 'completed' && $export->spreadsheet_path
                    ? route('exports.artifacts.show', [$export, 'spreadsheet'])
                    : null,
                'images_url' => $export->status === 'completed' && $export->images_path
                    ? route('exports.artifacts.show', [$export, 'images'])
                    : null,
                'manifest_url' => $export->status === 'completed' && $export->manifest_path
                    ? route('exports.artifacts.show', [$export, 'manifest'])
                    : null,
                'created_at' => $export->created_at,
            ],
        ]);
    }
}
