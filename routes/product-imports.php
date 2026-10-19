<?php

use App\Http\Controllers\ReviewedProductImportController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'active', 'legacy.company', 'common'])->group(function () {
    Route::get('products/import/review', [ReviewedProductImportController::class, 'index'])->name('products.import.index');
    Route::get('products/import/template', [ReviewedProductImportController::class, 'template'])->name('products.import.template');
    Route::post('products/import/{id}/commit', [ReviewedProductImportController::class, 'commit'])->whereNumber('id')->name('products.import.commit');
});
