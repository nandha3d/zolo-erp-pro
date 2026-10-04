<?php

namespace App\Http\Middleware;

use App\Services\Platform\CapabilityService;
use App\Services\Platform\CompanyContext;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;

class RequireCapability
{
    public function handle(Request $request, Closure $next, string $key)
    {
        $context = $request->attributes->get(CompanyContext::class);
        if (!$context instanceof CompanyContext) {
            throw new AuthorizationException('Authorized company context required.');
        }
        app(CapabilityService::class)->assertEnabled($key, $context);
        return $next($request);
    }
}
