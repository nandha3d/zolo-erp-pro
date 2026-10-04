<?php

use App\Http\Controllers\ComplianceController;
use App\Http\Middleware\{RequireCompliance, RequireSharedCommercial};
use Illuminate\Support\Facades\Route;

Route::prefix('v1/compliance')->middleware(['auth:sanctum', 'company.context', RequireSharedCommercial::class, RequireCompliance::class])
    ->where(['kind' => 'sale|purchase', 'documentKind' => 'sale|purchase|sale_note|purchase_note', 'id' => '[0-9]+'])->group(function () {
        Route::post('setup/{resource}', [ComplianceController::class, 'saveSetup']);
        Route::post('gst/lookup', [ComplianceController::class, 'lookup']);
        Route::get('gst/report', [ComplianceController::class, 'report']);
        Route::get('gst/export', [ComplianceController::class, 'export']);
        Route::get('returns', [ComplianceController::class, 'notes']);
        Route::post('{kind}/{id}/notes', [ComplianceController::class, 'createNote']);
        Route::post('{kind}/notes/{id}/approve', [ComplianceController::class, 'approve']);
        Route::get('documents/{documentKind}/{id}', [ComplianceController::class, 'document']);
        Route::get('documents/{documentKind}/{id}/print', [ComplianceController::class, 'printDocument']);
        Route::post('documents/{documentKind}/{id}/dispatch', [ComplianceController::class, 'dispatch']);
        Route::post('dispatch/{id}/retry', [ComplianceController::class, 'retry']);
        Route::post('stock-loss', [ComplianceController::class, 'loss']);
        Route::post('exchange/{id}', [ComplianceController::class, 'exchange']);
    });
