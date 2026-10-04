<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RequireOperations
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless(config('operations.enabled'), 403, 'Operations await company and shared-engine acceptance.');
        return $next($request);
    }
}
