<?php

namespace App\Http\Controllers;

use App\Services\Platform\CompanyContext;
use App\Services\Platform\CompanyContextResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class MaintenanceController extends Controller
{
    public function clear(Request $request)
    {
        abort_unless($request->user()->is_active && !$request->user()->is_deleted, 403);
        $companyId = $request->attributes->get(CompanyContext::class)?->companyId;
        if ($companyId === null && $request->session()->has('company_id')) {
            $companyId = filter_var($request->session()->get('company_id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            abort_if($companyId === false, 403);
            $companyId = app(CompanyContextResolver::class)->authorizedCompany($request->user()->id, $companyId)->id;
        }

        $result = Artisan::call('optimize:clear');
        abort_unless($result === 0, 500, 'Cache clear failed.');
        Log::info('erp.audit', ['action' => 'cache_clear', 'actor_id' => $request->user()->id,
            'company_id' => $companyId, 'request_id' => $request->attributes->get('erp.request_id'),
            'timestamp' => now()->toIso8601String()]);

        return response()->json(['status' => 'success', 'message' => 'Cache cleared successfully']);
    }
}
