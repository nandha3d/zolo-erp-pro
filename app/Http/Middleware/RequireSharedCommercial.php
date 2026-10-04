<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class RequireSharedCommercial
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless(config('commercial.enabled'), 503, 'Shared commercial posting awaits Phase 5 acceptance.');
        abort_unless(class_exists(\App\Services\Accounting\AccountingPostingService::class)
            && Schema::hasTable('account_open_items') && Schema::hasTable('idempotency_keys'),
            503, 'Install the reviewed Phase 5 and Phase 6 migrations before activation.');
        return $next($request);
    }
}
