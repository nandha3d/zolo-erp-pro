<?php

namespace App\Http\Middleware;

use App\Services\Platform\CompanyContext;
use App\Services\Platform\CompanyContextResolver;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ResolveCompanyContext
{
    public function __construct(private CompanyContextResolver $resolver)
    {
    }

    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (!$user) {
            throw new AuthorizationException('Active user required for company access.');
        }

        $context = $this->resolver->resolve(
            $user->id,
            $this->id($request, 'X-Company-ID', 'company_id'),
            $this->id($request, 'X-Branch-ID', 'branch_id'),
            $this->id($request, 'X-Financial-Year-ID', 'financial_year_id'),
        );
        $request->attributes->set(CompanyContext::class, $context);
        try {
            return $next($request);
        } finally {
            // Context belongs to this request, including under persistent workers.
            $request->attributes->remove(CompanyContext::class);
        }
    }

    private function id(Request $request, string $header, string $sessionKey): ?int
    {
        $value = $request->header($header);
        if ($value === null && $request->hasSession()) {
            $value = $request->session()->get($sessionKey);
        }
        if ($value === null) {
            return null;
        }
        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) {
            throw ValidationException::withMessages([$sessionKey => 'Context ID must be a positive integer.']);
        }

        return $id;
    }
}
