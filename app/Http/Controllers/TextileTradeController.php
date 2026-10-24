<?php

namespace App\Http\Controllers;

use App\Services\Commercial\{BrokerageService, CommercialPermission, RouteSheetService};
use App\Services\JobWork\PrincipalJobService;
use App\Services\Platform\CompanyContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Brokerage register, distributor route sheet, and job work done for customers. */
class TextileTradeController extends Controller
{
    private function context(Request $request): CompanyContext { return $request->attributes->get(CompanyContext::class); }

    private function period(Request $request): array
    {
        $request->validate(['from' => 'nullable|date_format:Y-m-d', 'to' => 'nullable|date_format:Y-m-d|after_or_equal:from']);
        return [$request->input('from', now()->startOfMonth()->toDateString()), $request->input('to', now()->toDateString())];
    }

    public function brokerage(Request $request)
    {
        $context = $this->context($request); app(CommercialPermission::class)->assert('sales-index', $context, $request->user()->id);
        [$from, $to] = $this->period($request);
        $basis = $request->input('basis', 'invoice'); $agentId = $request->integer('agent_id') ?: null;
        $rows = app(BrokerageService::class)->register($context, $from, $to, $agentId, $basis);
        $agents = DB::table('agents')->where('company_id', $context->companyId)->where('is_active', true)->orderBy('name')->get(['id', 'name', 'commission_rate']);
        return $request->expectsJson() ? response()->json(['data' => $rows])
            : view('backend.trade.brokerage', ['trade' => true] + compact('context', 'from', 'to', 'basis', 'agentId', 'rows', 'agents'));
    }

    public function bookBrokerage(Request $request)
    {
        $context = $this->context($request); app(CommercialPermission::class)->assert('sales-add', $context, $request->user()->id);
        $data = $request->validate(['agent_id' => 'required|integer|min:1', 'from' => 'required|date_format:Y-m-d', 'to' => 'required|date_format:Y-m-d|after_or_equal:from',
            'business_date' => 'required|date_format:Y-m-d', 'basis' => 'required|in:invoice,collection']);
        $result = app(BrokerageService::class)->book((int) $data['agent_id'], $data['from'], $data['to'], $data['business_date'], $data['basis'], $context, $request->user()->id);
        $message = 'Brokerage booked: ₹'.number_format($result['amount'], 2).' on '.$result['invoices'].' invoices'.($result['tds'] ? ', TDS 194H ₹'.number_format($result['tds'], 2) : '').'.';
        return $request->expectsJson() ? response()->json(['data' => $result], 201) : back()->with('message', $message);
    }

    public function routes(Request $request)
    {
        $context = $this->context($request); app(CommercialPermission::class)->assert('sales-index', $context, $request->user()->id);
        $request->validate(['date' => 'nullable|date_format:Y-m-d', 'area_id' => 'nullable|integer|min:1']);
        $date = $request->input('date', now()->toDateString()); $areaId = $request->integer('area_id') ?: null;
        $sheet = app(RouteSheetService::class)->sheet($context, $date, $areaId);
        $areas = DB::table('areas')->where('company_id', $context->companyId)->where('is_active', true)->orderBy('name')->get(['id', 'name']);
        return $request->expectsJson() ? response()->json(['data' => $sheet])
            : view('backend.trade.routes', ['trade' => true] + compact('context', 'date', 'areaId', 'sheet', 'areas'));
    }

    public function customerJobs(Request $request)
    {
        $context = $this->context($request); $service = app(PrincipalJobService::class);
        $orders = $service->register($context, $request->user()->id);
        $customers = DB::table('customers')->where('company_id', $context->companyId)->orderBy('name')->limit(500)->get(['id', 'name', 'company_name']);
        $services = DB::table('products')->where('company_id', $context->companyId)->where('type', 'service')->orderBy('name')->get(['id', 'name']);
        return $request->expectsJson() ? response()->json(['data' => $orders])
            : view('backend.trade.customer_jobs', ['trade' => true] + compact('context', 'orders', 'customers', 'services'));
    }

    public function customerJob(Request $request, int $id)
    {
        $context = $this->context($request); $service = app(PrincipalJobService::class);
        $order = $service->find($id, $context, $request->user()->id);
        $movements = $service->movements($id, $context, $request->user()->id); $balance = $service->balance($id);
        $customer = DB::table('customers')->find($order->customer_id);
        if ($request->query('challan')) {
            // Our return challan (Rule 55): the principal's goods going back after processing.
            return view('backend.trade.customer_job_challan', compact('context', 'order', 'customer') + ['challan' => $request->query('challan'),
                'lines' => array_values(array_filter($movements, fn ($m) => $m->challan_no === $request->query('challan') && in_array($m->direction, ['returned', 'loss'], true)))]);
        }
        return view('backend.trade.customer_job', ['trade' => true] + compact('context', 'order', 'movements', 'balance', 'customer'));
    }

    public function customerJobAction(Request $request, string $action, ?int $id = null)
    {
        $context = $this->context($request); $actor = $request->user()->id; $service = app(PrincipalJobService::class);
        $data = $request->except('_token');
        $id = match ($action) {
            'order' => $service->order($data, $context, $actor),
            'receive' => tap($id, fn () => $service->receive($id, $data, $context, $actor)),
            'return' => tap($id, fn () => $service->returnGoods($id, $data, $context, $actor)),
            'bill' => tap($id, fn () => $service->bill($id, $data, (string) ($request->header('Idempotency-Key') ?: $request->input('idempotency_key', 'pjo-'.$id.'-'.md5(json_encode($data)))), $context, $actor)),
        };
        return $request->expectsJson() ? response()->json(['data' => ['id' => $id] + $service->balance($id)], 201)
            : redirect(url('/trade/customer-jobs/'.$id))->with('message', 'Saved.');
    }
}
