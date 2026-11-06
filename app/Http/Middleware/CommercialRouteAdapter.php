<?php

namespace App\Http\Middleware;

use App\Http\Controllers\CommercialController;
use App\Models\Company;
use App\Models\Product;
use App\Services\Platform\CompanyContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Routes legacy web/POS mutations through the shared engine. The normal Sales/Purchase pages are the only entry UI. */
class CommercialRouteAdapter
{
    public function handle(Request $request, Closure $next)
    {
        $action = $request->route()->getActionMethod();
        $kind = str_contains($request->route()->getActionName(), 'SaleController') ? 'sale' : 'purchase';
        if (!config('commercial.enabled')) {
            if (!$request->isMethod('GET') && in_array($action, ['store', 'update', 'updateFromClient'], true)) {
                $ids = (array) $request->input('product_id', []);
                if ($kind === 'sale' && $action === 'store' && is_array($request->input('products'))) {
                    $ids = array_map(fn ($line) => $line['product_id'] ?? $line['id'] ?? null, $request->input('products'));
                }
                $ids = array_filter($ids, 'is_numeric');
                if ($ids) {
                    return DB::transaction(function () use ($request, $next, $kind, $ids) {
                        if ($context = $request->attributes->get(CompanyContext::class)) {
                            Company::whereKey($context->companyId)->lockForUpdate()->firstOrFail();
                        }
                        foreach (Product::whereKey($ids)->orderBy('id')->lockForUpdate()->get() as $product) {
                            $product->assertAllowedOnBill($kind, 'product_id');
                        }
                        return $next($request);
                    });
                }
            }
            return $next($request);
        }
        if ($request->isMethod('GET') && !in_array($action, ['paypalSuccess', 'paypalPaymentSuccess', 'stripePayment', 'duplicate'], true)) {
            return $next($request);
        }
        if (in_array($action, ['saleData', 'purchaseData'], true)) {
            return $next($request);
        }
        if ($action === 'addPayment' && !$request->filled('paying_method')) {
            $method = match ((string) $request->input('paid_by_id')) {
                '1' => 'Cash', '3' => 'Credit Card', '4' => 'Cheque', 'bank', 'Bank' => 'Bank',
                default => abort(422, 'This tender requires its reviewed company-owned settlement workflow.'),
            };
            $request->merge(['paying_method' => $method]);
        }
        if ($action === 'addPayment' && $request->filled('payment_at')) {
            $request->merge(['payment_at' => substr(normalize_to_sql_datetime($request->payment_at), 0, 10)]);
        }
        if ($action === 'deleteBySelection') {
            $request->merge(['ids' => $request->input('ids', $request->input($kind === 'sale' ? 'saleIdArray' : 'purchaseIdArray'))]);
        }
        return app(RequireSharedCommercial::class)->handle($request, fn ($request) =>
            app(ResolveCompanyContext::class)->handle($request, function ($request) use ($kind, $action) {
                $controller = app(CommercialController::class);
                return match ($action) {
                    'store' => $controller->store($request, $kind, legacy: true),
                    'update' => $controller->replace($request, $kind, (int) $request->route('sale', $request->route('purchase', $request->route('id')))),
                    'destroy' => $controller->reverse($request, $kind, (int) $request->route('sale', $request->route('purchase', $request->route('id')))),
                    'deleteBySelection' => $controller->reverseSelection($request, $kind),
                    'addPayment' => $controller->payment($request, $kind, (int) $request->input($kind.'_id')),
                    default => abort(409, 'This legacy mutation requires its reviewed shared commercial adapter.'),
                };
            }));
    }
}
