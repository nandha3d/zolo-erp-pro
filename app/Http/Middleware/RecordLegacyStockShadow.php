<?php

namespace App\Http\Middleware;

use App\Services\Inventory\LegacyStockShadow;
use Closure;
use Illuminate\Http\Request;

/**
 * Wraps a legacy stock-writing request in one transaction and records its stock changes as a shadow movement.
 * Usage: legacy.stock:{document class basename}[,any] — "any" also wraps GET/HEAD requests that write stock.
 */
class RecordLegacyStockShadow
{
    public function __construct(private readonly LegacyStockShadow $shadow)
    {
    }

    public function handle(Request $request, Closure $next, ?string $document = null, ?string $methods = null): mixed
    {
        if ($request->isMethodSafe() && $methods !== 'any') {
            return $next($request);
        }
        $route = $request->route();
        $source = $route?->getName() ?? str_replace('App\\Http\\Controllers\\', '', (string) $route?->getActionName());
        // Resource routes name the document parameter after the resource ({sale}, {purchase}, ...).
        $id = collect($route?->parameters() ?? [])->map(fn ($value) => filter_var($value, FILTER_VALIDATE_INT))->filter()->first();
        $class = $document === null ? null : (class_exists($document) ? $document : 'App\\Models\\'.$document);

        return $this->shadow->record($source, fn () => $next($request), $id, $class);
    }
}
