<?php

namespace App\Http\Middleware;

use App\Services\Commercial\CommercialPermission;
use App\Services\Platform\CapabilityService;
use App\Services\Platform\CompanyContext;
use Closure;
use Illuminate\Http\Request;

class RequireCompanyPermission
{
    public function handle(Request $request, Closure $next, string $permission, string $capability)
    {
        $context = $request->attributes->get(CompanyContext::class);
        abort_unless($context instanceof CompanyContext && app(CapabilityService::class)->enabled($capability, $context), 403);
        app(CommercialPermission::class)->assert($permission, $context, $request->user()->id);
        return $next($request);
    }
}
