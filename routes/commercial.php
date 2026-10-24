<?php

use App\Http\Controllers\CommercialController;
use App\Http\Middleware\RequireSharedCommercial;
use Illuminate\Support\Facades\Route;

Route::get('dispatch-register', [\App\Http\Controllers\DispatchRegisterController::class, 'index'])
    ->middleware(['auth', 'company.context', 'common'])->name('dispatch-register.index');

// Draft tabs of the document pages that post through their own controllers (returns, challans, goods receipts, quotations).
Route::prefix('documents')->middleware(['auth', 'company.context'])->where(['kind' => 'sreturn|preturn|dc|grn|quotation', 'id' => '[0-9]+'])->group(function () {
    Route::get('{kind}/drafts', [CommercialController::class, 'drafts']);
    Route::post('{kind}/drafts', [CommercialController::class, 'draft']);
    Route::get('{kind}/drafts/{id}', [CommercialController::class, 'showDraft']);
    Route::delete('{kind}/drafts/{id}', [CommercialController::class, 'destroyDraft']);
});

Route::prefix('commercial')->middleware(['auth', 'company.context', RequireSharedCommercial::class])
    ->where(['kind' => 'sale|purchase', 'resource' => 'parties|products|agents|areas|bill-sundries|sale-types|purchase-types|remarks|series', 'id' => '[0-9]+'])->group(function () {
        Route::post('{kind}', [CommercialController::class, 'store']);
        Route::post('{kind}/preview', [CommercialController::class, 'preview']);
        Route::get('{kind}/search/{resource}', [CommercialController::class, 'search']);
        Route::get('{kind}/party/{id}', [CommercialController::class, 'party']);
        Route::get('{kind}/previous-rates', [CommercialController::class, 'previousRates']);
        Route::get('{kind}/material-sources', [CommercialController::class, 'materialSources']);
        Route::get('{kind}/clone/{id}', [CommercialController::class, 'cloneDocument']);
        Route::get('{kind}/drafts', [CommercialController::class, 'drafts']);
        Route::post('{kind}/drafts', [CommercialController::class, 'draft']);
        Route::get('{kind}/drafts/{id}', [CommercialController::class, 'showDraft']);
        Route::delete('{kind}/drafts/{id}', [CommercialController::class, 'destroyDraft']);
        Route::post('{kind}/masters/{resource}', [CommercialController::class, 'inlineMaster']);
        Route::post('{kind}/{id}/reverse', [CommercialController::class, 'reverse']);
        Route::get('{kind}/{id}/reverse', [CommercialController::class, 'reversalForm']);
        Route::put('{kind}/{id}', [CommercialController::class, 'replace']);
        Route::post('{kind}/{id}/payments', [CommercialController::class, 'payment']);
        Route::post('purchase/{id}/receipts', [CommercialController::class, 'receive']);
    });

// Textile trade: brokerage register, distributor route sheet, job work done for customers (principal-owned material).
Route::prefix('trade')->middleware(['auth', 'company.context', RequireSharedCommercial::class, 'common'])->group(function () {
    Route::get('brokerage', [\App\Http\Controllers\TextileTradeController::class, 'brokerage']);
    Route::post('brokerage', [\App\Http\Controllers\TextileTradeController::class, 'bookBrokerage']);
    Route::get('routes', [\App\Http\Controllers\TextileTradeController::class, 'routes']);
    Route::get('customer-jobs', [\App\Http\Controllers\TextileTradeController::class, 'customerJobs']);
    Route::get('customer-jobs/{id}', [\App\Http\Controllers\TextileTradeController::class, 'customerJob'])->whereNumber('id');
    Route::post('customer-jobs/{action}/{id?}', [\App\Http\Controllers\TextileTradeController::class, 'customerJobAction'])
        ->where(['action' => 'order|receive|return|bill', 'id' => '[0-9]+']);
});
