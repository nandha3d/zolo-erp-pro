<?php

use App\Http\Controllers\InventoryLotReportController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'company.context', 'common'])->get('inventory-reports/stock-by-lot', [InventoryLotReportController::class, 'index'])
    ->name('inventory-reports.stock-by-lot');
