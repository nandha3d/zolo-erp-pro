<?php

namespace App\Http\Middleware;

use App\Services\Operations\OperationPosting;
use App\Services\Platform\CompanyContext;
use Closure;
use Illuminate\Http\Request;

/** Retire legacy HTTP writers, including cached module routes, without deleting historical records. */
class LegacyOperationsCutover
{
    public function handle(Request $request, Closure $next, string $area)
    {
        [$capability, $permission] = match ($area) {
            'manufacturing' => ['manufacturing.bom', 'manufacturing.read'],
            'job-work' => ['operations.job_work', 'job_work.read'],
            'projects' => ['operations.projects', 'projects.read'],
        };
        app(OperationPosting::class)->authorize($capability, $permission,
            $request->attributes->get(CompanyContext::class), $request->user()->id);
        $url = url('/operations/'.$area);
        if ($request->isMethod('GET') && !$request->expectsJson()) return redirect($url);
        return response()->json(['message' => 'Use the company-owned operations workflow. Legacy documents are retained for reviewed migration.',
            'redirect' => $url], 409);
    }
}
