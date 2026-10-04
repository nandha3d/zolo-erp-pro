<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class RequireCompliance
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless(config('compliance.enabled') && config('commercial.enabled'), 503, 'Compliance awaits reviewed Phase 5–8 acceptance.');
        abort_unless(Schema::hasTable('gst_transaction_projections') && Schema::hasTable('document_dispatch_logs'), 503, 'Install reviewed compliance migrations before activation.');
        return $next($request);
    }
}
