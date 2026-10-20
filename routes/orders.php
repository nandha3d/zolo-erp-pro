<?php

use App\Http\Controllers\CommercialOrderController;
use App\Http\Middleware\RequireSharedCommercial;
use Illuminate\Support\Facades\Route;

Route::prefix('commercial/orders')->middleware(['auth', 'company.context', RequireSharedCommercial::class])
    ->where(['kind' => 'sale|purchase', 'id' => '[0-9]+'])->group(function () {
        Route::get('{kind}', [CommercialOrderController::class, 'index'])->name('commercial.orders.index');
        Route::post('sale', [CommercialOrderController::class, 'storeSale'])->name('commercial.orders.sale.store');
        Route::get('{kind}/{id}', [CommercialOrderController::class, 'show'])->name('commercial.orders.show');
        Route::put('{kind}/{id}', [CommercialOrderController::class, 'amend'])->name('commercial.orders.amend');
        Route::post('{kind}/{id}/cancel', [CommercialOrderController::class, 'cancel'])->name('commercial.orders.cancel');
    });
