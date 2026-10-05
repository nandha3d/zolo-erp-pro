<?php

namespace App\Http\Middleware;

use App\Models\Company;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/**
 * Company context for the original business screens. It is the same trusted resolution as the API
 * (membership, branch grant, financial year), but an installation that has never run the company
 * foundation gets an actionable message instead of silently running without isolation.
 */
class ResolveLegacyCompanyContext extends ResolveCompanyContext
{
    public function handle(Request $request, Closure $next)
    {
        if (!Schema::hasTable('companies') || !Company::query()->exists()) {
            $message = 'Company foundation is not initialised. An administrator must run "php artisan erp:backfill-company-context" (see the company backfill runbook) before business screens can be used.';

            return $request->expectsJson() ? response()->json(['message' => $message], 409) : response($message, 409);
        }

        return parent::handle($request, $next);
    }
}
