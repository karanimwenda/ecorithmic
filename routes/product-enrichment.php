<?php

use App\Http\Controllers\ProductEnrichment\BulkApprovalController;
use App\Http\Controllers\ProductEnrichment\ExportArtifactController;
use App\Http\Controllers\ProductEnrichment\ExportController;
use App\Http\Controllers\ProductEnrichment\ImportConfirmationController;
use App\Http\Controllers\ProductEnrichment\ImportController;
use App\Http\Controllers\ProductEnrichment\ProductApprovalController;
use App\Http\Controllers\ProductEnrichment\ProductAssetController;
use App\Http\Controllers\ProductEnrichment\ProductAttributeValueApprovalController;
use App\Http\Controllers\ProductEnrichment\ProductAttributeValueController;
use App\Http\Controllers\ProductEnrichment\ProductAttributeValueRegenerationController;
use App\Http\Controllers\ProductEnrichment\ProductAttributeValueRejectionController;
use App\Http\Controllers\ProductEnrichment\ProductController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    // User Story 1: Import
    Route::post('/imports', [ImportController::class, 'store'])->name('imports.store');
    Route::get('/imports/{import}', [ImportController::class, 'show'])->name('imports.show');
    Route::post('/imports/{import}/confirmation', [ImportConfirmationController::class, 'store'])->name('imports.confirmation.store');

    // Upload page
    Route::inertia('/imports/create', 'ProductEnrichment/Imports/Upload')->name('imports.create');

    // User Story 3: Review
    Route::get('/products/{product}', [ProductController::class, 'show'])->name('products.show');
    Route::patch('/product-attribute-values/{productAttributeValue}', [ProductAttributeValueController::class, 'update'])->name('product-attribute-values.update');
    Route::post('/product-attribute-values/{productAttributeValue}/approval', [ProductAttributeValueApprovalController::class, 'store'])->name('product-attribute-values.approval.store');
    Route::post('/product-attribute-values/{productAttributeValue}/rejection', [ProductAttributeValueRejectionController::class, 'store'])->name('product-attribute-values.rejection.store');
    Route::post('/product-attribute-values/{productAttributeValue}/regenerations', [ProductAttributeValueRegenerationController::class, 'store'])->name('product-attribute-values.regenerations.store');
    Route::post('/products/{product}/approval', [ProductApprovalController::class, 'store'])->name('products.approval.store');
    Route::patch('/product-assets/{productAsset}', [ProductAssetController::class, 'update'])->name('product-assets.update');

    // User Story 6: Bulk Approval
    Route::get('/bulk-approvals/create', [BulkApprovalController::class, 'create'])->name('bulk-approvals.create');
    Route::post('/bulk-approvals', [BulkApprovalController::class, 'store'])->name('bulk-approvals.store');

    // User Story 7: Export
    Route::post('/exports', [ExportController::class, 'store'])->name('exports.store');
    Route::get('/exports/{export}', [ExportController::class, 'show'])->name('exports.show');
    Route::get('/exports/{export}/artifacts/{artifact}', [ExportArtifactController::class, 'show'])->name('exports.artifacts.show');
});
