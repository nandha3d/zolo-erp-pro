<?php

use App\Http\Controllers\ProductAliasController;
use Illuminate\Support\Facades\Route;

Route::prefix('product-aliases')->middleware(['auth', 'active', 'company.context', 'common'])->group(function () {
    Route::get('/', [ProductAliasController::class, 'index'])->name('product-aliases.index');
    Route::post('/', [ProductAliasController::class, 'store'])->name('product-aliases.store');
    Route::delete('/{id}', [ProductAliasController::class, 'destroy'])->whereNumber('id')->name('product-aliases.destroy');
});
