<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\SaleApiController;
use App\Http\Controllers\Api\V1\PurchaseApiController;
use App\Http\Controllers\Api\V1\ProductApiController;
use App\Http\Controllers\Api\V1\InventoryApiController;
use App\Http\Controllers\Api\V1\AccountingApiController;
use App\Http\Controllers\Api\V1\PartnerApiController;
use App\Http\Controllers\Api\V1\WaterLogisticsApiController;
use App\Http\Controllers\Api\V1\CafeApiController;
use App\Http\Controllers\Api\V1\RepairApiController;
use App\Http\Controllers\Api\V1\AddonApiController;

/*
|--------------------------------------------------------------------------
| API Routes - zoloERP SaaS & Industry Addon Engine (API v1)
|--------------------------------------------------------------------------
|
| Versioned RESTful API endpoints for the zoloERP Enterprise Cloud Engine.
| All endpoints return standardized JSON payloads.
| Protected routes require a Bearer token via Laravel Sanctum.
|
*/

Route::prefix('v1')->group(function () {

    // Public Authentication Endpoints
    Route::prefix('auth')->group(function () {
        Route::post('login', [AuthController::class, 'login'])->name('api.v1.auth.login');
    });

    // Public Tenant Addons Status
    Route::get('addons/status', [AddonApiController::class, 'status'])->name('api.v1.addons.status');

    // Protected ERP Endpoints (Sanctum Token Required)
    Route::middleware('auth:sanctum')->group(function () {

        // User & Session
        Route::prefix('auth')->group(function () {
            Route::get('me', [AuthController::class, 'me'])->name('api.v1.auth.me');
            Route::post('logout', [AuthController::class, 'logout'])->name('api.v1.auth.logout');
        });

        // Sales & Invoicing
        Route::prefix('sales')->group(function () {
            Route::get('/', [SaleApiController::class, 'index'])->name('api.v1.sales.index');
            Route::post('/', [SaleApiController::class, 'store'])->name('api.v1.sales.store');
            Route::get('{id}', [SaleApiController::class, 'show'])->name('api.v1.sales.show');
            Route::post('{id}/payments', [SaleApiController::class, 'addPayment'])->name('api.v1.sales.add-payment');
        });

        // Purchases & Procurement
        Route::prefix('purchases')->group(function () {
            Route::get('/', [PurchaseApiController::class, 'index'])->name('api.v1.purchases.index');
            Route::post('/', [PurchaseApiController::class, 'store'])->name('api.v1.purchases.store');
            Route::get('{id}', [PurchaseApiController::class, 'show'])->name('api.v1.purchases.show');
        });

        // Products & Catalog
        Route::prefix('products')->group(function () {
            Route::get('/', [ProductApiController::class, 'index'])->name('api.v1.products.index');
            Route::get('search/{term}', [ProductApiController::class, 'search'])->name('api.v1.products.search');
            Route::get('{id}', [ProductApiController::class, 'show'])->name('api.v1.products.show');
        });

        // Inventory & Warehouse Logistics
        Route::prefix('inventory')->group(function () {
            Route::get('valuation', [InventoryApiController::class, 'valuation'])->name('api.v1.inventory.valuation');
            Route::post('transfer', [InventoryApiController::class, 'transfer'])->name('api.v1.inventory.transfer');
        });

        // Double-Entry Accounting & Financial Ledger
        Route::prefix('accounting')->group(function () {
            Route::get('chart-of-accounts', [AccountingApiController::class, 'chartOfAccounts'])->name('api.v1.accounting.coa');
            Route::get('trial-balance', [AccountingApiController::class, 'trialBalance'])->name('api.v1.accounting.trial-balance');
            Route::get('profit-and-loss', [AccountingApiController::class, 'profitAndLoss'])->name('api.v1.accounting.pnl');
            Route::get('balance-sheet', [AccountingApiController::class, 'balanceSheet'])->name('api.v1.accounting.balance-sheet');
            Route::get('general-ledger/{id}', [AccountingApiController::class, 'generalLedger'])->name('api.v1.accounting.gl');
            Route::post('journal-entries', [AccountingApiController::class, 'storeJournalEntry'])->name('api.v1.accounting.journal-entry.store');
        });

        // Partners (Customers & Suppliers)
        Route::get('customers', [PartnerApiController::class, 'customers'])->name('api.v1.customers.index');
        Route::get('suppliers', [PartnerApiController::class, 'suppliers'])->name('api.v1.suppliers.index');

        // INDUSTRY ADDON: Water Logistics ("DK Track")
        Route::prefix('water')->group(function () {
            Route::get('routes', [WaterLogisticsApiController::class, 'routes'])->name('api.v1.water.routes');
            Route::post('trip-sheets', [WaterLogisticsApiController::class, 'storeTripSheet'])->name('api.v1.water.trip-sheets');
            Route::post('can-deliveries', [WaterLogisticsApiController::class, 'storeCanDelivery'])->name('api.v1.water.can-deliveries');
            Route::post('driver-surrender', [WaterLogisticsApiController::class, 'driverCashSurrender'])->name('api.v1.water.driver-surrender');
        });

        // INDUSTRY ADDON: Bakery & Cafe POS
        Route::prefix('cafe')->group(function () {
            Route::get('menu', [CafeApiController::class, 'menu'])->name('api.v1.cafe.menu');
            Route::post('orders', [CafeApiController::class, 'storeOrder'])->name('api.v1.cafe.orders');
            Route::post('drawer-reconcile', [CafeApiController::class, 'reconcileDrawer'])->name('api.v1.cafe.drawer-reconcile');
        });

        // INDUSTRY ADDON: Repair & RMA Service Center
        Route::prefix('repair')->group(function () {
            Route::get('jobs', [RepairApiController::class, 'jobs'])->name('api.v1.repair.jobs');
            Route::post('jobs', [RepairApiController::class, 'storeJob'])->name('api.v1.repair.store-job');
            Route::put('jobs/{id}/status', [RepairApiController::class, 'updateStatus'])->name('api.v1.repair.update-status');
        });
    });
});
