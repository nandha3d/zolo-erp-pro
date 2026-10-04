<?php

use App\Http\Controllers\WorkspaceController;
use Illuminate\Support\Facades\Route;

Route::prefix('workspace')->middleware(['web', 'auth'])->group(function () {
    Route::get('/', [WorkspaceController::class, 'index'])->middleware('company.context');
    Route::post('context', [WorkspaceController::class, 'select']);
    Route::get('setup', [WorkspaceController::class, 'setup']);
    Route::post('setup/{section}', [WorkspaceController::class, 'save'])->where('section', 'company|branch|warehouse');
});
Route::prefix('api/v1')->middleware(['api', 'auth:sanctum'])->group(function () {
    Route::get('companies', [WorkspaceController::class, 'companies']);
    Route::get('workspace', [WorkspaceController::class, 'index'])->middleware('company.context');
    Route::get('company-context/setup', [WorkspaceController::class, 'setup']);
    Route::put('company-context/setup/{section}', [WorkspaceController::class, 'save'])->where('section', 'company|branch|warehouse');
});
