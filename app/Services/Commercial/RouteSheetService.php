<?php

namespace App\Services\Commercial;

use App\Services\Platform\{CapabilityService, CompanyContext};
use Illuminate\Support\Facades\DB;

/**
 * Route (beat) sheet for distributors: each area's customers with what they owe, how much is overdue, their last
 * invoice and today's billing, so the salesman knows whom to visit and what to collect. Areas are the route master.
 */
class RouteSheetService
{
    public function sheet(CompanyContext $context, string $date, ?int $areaId = null): array
    {
        app(CapabilityService::class)->assertEnabled('sales.route_distribution', $context);
        $areas = DB::table('areas')->where('company_id', $context->companyId)->where('is_active', true)
            ->when($areaId, fn ($q) => $q->where('id', $areaId))->orderBy('name')->get();
        $open = DB::table('account_open_items')->where('company_id', $context->companyId)->where('branch_id', $context->branchId)
            ->where('party_type', 'customer')->where('open_amount', '<>', 0)
            ->selectRaw('party_id, SUM(open_amount) AS outstanding, SUM(CASE WHEN due_date < ? THEN open_amount ELSE 0 END) AS overdue', [$date])
            ->groupBy('party_id')->get()->keyBy('party_id');
        $sales = DB::table('sales')->where('company_id', $context->companyId)->whereNotNull('posted_at')->whereNull('reversed_at')
            ->selectRaw('customer_id, MAX(created_at) AS last_invoice, SUM(CASE WHEN created_at BETWEEN ? AND ? THEN grand_total ELSE 0 END) AS today',
                [$date.' 00:00:00', $date.' 23:59:59'])->groupBy('customer_id')->get()->keyBy('customer_id');
        return $areas->map(function ($area) use ($context, $open, $sales) {
            $customers = DB::table('customers')->where('company_id', $context->companyId)->where('area_id', $area->id)
                ->where(fn ($q) => $q->whereNull('is_active')->orWhere('is_active', true))->orderBy('name')->get(['id', 'name', 'company_name', 'phone_number', 'city'])
                ->map(fn ($customer) => ['customer_id' => $customer->id, 'customer' => $customer->company_name ?: $customer->name,
                    'phone' => $customer->phone_number, 'city' => $customer->city,
                    'outstanding' => round((float) ($open[$customer->id]->outstanding ?? 0), 2), 'overdue' => round((float) ($open[$customer->id]->overdue ?? 0), 2),
                    'last_invoice' => isset($sales[$customer->id]) ? substr((string) $sales[$customer->id]->last_invoice, 0, 10) : null,
                    'billed_today' => round((float) ($sales[$customer->id]->today ?? 0), 2)])->all();
            return ['area_id' => $area->id, 'route' => $area->name, 'code' => $area->code, 'customers' => $customers,
                'outstanding' => round(array_sum(array_column($customers, 'outstanding')), 2), 'overdue' => round(array_sum(array_column($customers, 'overdue')), 2),
                'billed_today' => round(array_sum(array_column($customers, 'billed_today')), 2)];
        })->values()->all();
    }
}
