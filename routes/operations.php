<?php

use App\Http\Controllers\OperationsController;
use App\Http\Middleware\RequireOperations;
use Illuminate\Support\Facades\Route;

$api = $operationsApi ?? false;
Route::prefix($api ? 'v1/operations' : 'operations')->name($api ? 'api.operations.' : 'operations.')
    ->middleware([$api ? 'auth:sanctum' : 'auth', 'company.context', RequireOperations::class])->group(function () {
        Route::get('{area}', [OperationsController::class, 'hub'])->where('area', 'manufacturing|job-work|profiles|projects|stock')->name('hub');
        Route::get('{kind}/{id}', [OperationsController::class, 'detail'])->where('kind', 'production|job-work|project')->whereNumber('id')->name('detail');
        Route::post('{action}', [OperationsController::class, 'action'])->where('action', 'bom|production-plan|job-order|project|expiry-writeoff');
        Route::post('{action}/{id}', [OperationsController::class, 'action'])->where('action', 'production-complete|production-reverse|job-dispatch|job-receive|job-bill|job-reverse-dispatch|job-reverse-receipt|project-quotation|project-allocate|project-dispatch|project-install|project-commission|project-service|project-link')->whereNumber('id');
        Route::post('configure/{action}', [OperationsController::class, 'configure'])->where('action', 'profile|process|scheme');
        Route::match(['GET', 'POST'], 'product/{id}/attributes', [OperationsController::class, 'attributes'])->whereNumber('id');
        Route::get('inventory/fefo', [OperationsController::class, 'fefo']);
        Route::get('inventory/pieces', [OperationsController::class, 'pieces']);
        Route::get('dispatch/{id}/print', [OperationsController::class, 'materialDocument'])->whereNumber('id');
    });
