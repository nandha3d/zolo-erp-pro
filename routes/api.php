<?php
$operationsApi = true;
require __DIR__.'/operations.php';

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

        // Setup deliberately does not require an existing branch or financial year.
        Route::get('company-context/financial-years', [\App\Http\Controllers\CompanyFinancialYearController::class, 'index'])->name('api.v1.company.financial-years.setup');
        Route::post('company-context/financial-years', [\App\Http\Controllers\CompanyFinancialYearController::class, 'store'])->name('api.v1.company.financial-years.store');

        // User & Session
        Route::prefix('auth')->group(function () {
            Route::get('me', [AuthController::class, 'me'])->name('api.v1.auth.me');
            Route::post('logout', [AuthController::class, 'logout'])->name('api.v1.auth.logout');
        });

        // Sales & Invoicing
        Route::prefix('sales')->group(function () {
            Route::get('/', [SaleApiController::class, 'index'])->middleware('company.context')->name('api.v1.sales.index');
            Route::post('/', [SaleApiController::class, 'store'])->middleware('company.context')->name('api.v1.sales.store');
            Route::get('{id}', [SaleApiController::class, 'show'])->middleware('company.context')->name('api.v1.sales.show');
            Route::post('{id}/payments', [SaleApiController::class, 'addPayment'])->middleware('company.context')->name('api.v1.sales.add-payment');
        });

        // Purchases & Procurement
        Route::prefix('purchases')->group(function () {
            Route::get('/', [PurchaseApiController::class, 'index'])->middleware('company.context')->name('api.v1.purchases.index');
            Route::post('/', [PurchaseApiController::class, 'store'])->middleware('company.context')->name('api.v1.purchases.store');
            Route::get('{id}', [PurchaseApiController::class, 'show'])->middleware('company.context')->name('api.v1.purchases.show');
            Route::post('{id}/payments', [PurchaseApiController::class, 'addPayment'])->middleware('company.context')->name('api.v1.purchases.add-payment');
        });

        // Products & Catalog
        Route::prefix('products')->group(function () {
            Route::get('/', [ProductApiController::class, 'index'])->middleware('company.context')->name('api.v1.products.index');
            Route::get('search/{term}', [ProductApiController::class, 'search'])->middleware('company.context')->name('api.v1.products.search');
            Route::get('{id}', [ProductApiController::class, 'show'])->middleware('company.context')->name('api.v1.products.show');
        });

        // Inventory & Warehouse Logistics
        Route::prefix('inventory')->group(function () {
            Route::get('valuation', [InventoryApiController::class, 'valuation'])->middleware('company.context')->name('api.v1.inventory.valuation');
            Route::post('transfer', [InventoryApiController::class, 'transfer'])->middleware('company.context')->name('api.v1.inventory.transfer');
        });

        // Double-Entry Accounting & Financial Ledger
        Route::prefix('accounting')->group(function () {
            Route::get('chart-of-accounts', [AccountingApiController::class, 'chartOfAccounts'])->middleware('company.context')->name('api.v1.accounting.coa');
            Route::get('trial-balance', [AccountingApiController::class, 'trialBalance'])->middleware('company.context')->name('api.v1.accounting.trial-balance');
            Route::get('profit-and-loss', [AccountingApiController::class, 'profitAndLoss'])->middleware('company.context')->name('api.v1.accounting.pnl');
            Route::get('balance-sheet', [AccountingApiController::class, 'balanceSheet'])->middleware('company.context')->name('api.v1.accounting.balance-sheet');
            Route::get('general-ledger/{id}', [AccountingApiController::class, 'generalLedger'])->middleware('company.context')->name('api.v1.accounting.gl');
            Route::post('journal-entries', [AccountingApiController::class, 'storeJournalEntry'])->middleware('company.context')->name('api.v1.accounting.journal-entry.store');
            Route::middleware('company.context')->controller(\App\Http\Controllers\Accounting\VoucherController::class)->group(function () {
                Route::post('vouchers', 'store')->name('api.v1.accounting.vouchers.store');
                Route::post('journal-entries/{id}/reverse', 'reverse')->name('api.v1.accounting.journal.reverse');
                Route::get('open-items', 'openItems')->name('api.v1.accounting.open-items');
                Route::post('allocations', 'allocate')->name('api.v1.accounting.allocations.store');
                Route::post('allocations/{id}/reverse', 'reverseAllocation')->name('api.v1.accounting.allocations.reverse');
                Route::post('period', 'period')->name('api.v1.accounting.period');
                Route::get('day-book', 'book')->defaults('type', 'day')->name('api.v1.accounting.day-book');
                Route::get('cash-book', 'book')->defaults('type', 'cash')->name('api.v1.accounting.cash-book');
                Route::get('ageing', 'ageing')->name('api.v1.accounting.ageing');
                Route::get('monthly-ledger/{id}', 'monthly')->name('api.v1.accounting.monthly-ledger');
            });
        });

        // Partners (Customers & Suppliers)
        Route::get('customers', [PartnerApiController::class, 'customers'])->middleware('company.context')->name('api.v1.customers.index');
        Route::get('suppliers', [PartnerApiController::class, 'suppliers'])->middleware('company.context')->name('api.v1.suppliers.index');

        // INDUSTRY ADDON: Water Logistics
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

require __DIR__.'/compliance_api.php';
