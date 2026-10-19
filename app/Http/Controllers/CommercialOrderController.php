<?php

namespace App\Http\Controllers;

use App\Models\Purchase;
use App\Models\SalesOrder;
use App\Services\Commercial\CommercialPermission;
use App\Services\Commercial\LegacyCommercialCommand;
use App\Services\Commercial\OrderLifecycleService;
use App\Services\Platform\CompanyContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Services\Commercial\CommercialDraftService;

class CommercialOrderController extends Controller
{
    private function context(Request $request, string $kind, bool $write = false): CompanyContext
    {
        $context = $request->attributes->get(CompanyContext::class);
        abort_unless($context && Schema::hasTable('commercial_order_items'), 503, 'Order lifecycle is not installed.');
        app(CommercialPermission::class)->assert(($kind === 'sale' ? 'sales-' : 'purchases-').($write ? 'add' : 'index'), $context, $request->user()->id);
        return $context;
    }

    public function index(Request $request, string $kind)
    {
        $context = $this->context($request, $kind);
        $request->validate(['party_id' => 'nullable|integer|min:1', 'status' => 'nullable|in:pending,all']);
        $query = $kind === 'sale' ? SalesOrder::visibleIn($context)->with('customer') : Purchase::visibleIn($context)->where('status', 4)->with('supplier');
        if ($request->filled('party_id')) $query->where($kind === 'sale' ? 'customer_id' : 'supplier_id', $request->integer('party_id'));
        if ($request->input('status', 'pending') !== 'all') {
            if ($kind === 'sale') $query->whereIn('status', ['open', 'partial']);
            else $query->whereNull('reversed_at')->whereNull('attributes_json->fulfilled_at');
        }
        $rows = $query->orderByDesc('id')->paginate(25);
        // Compute remaining quantities from authoritative allocations, not status labels or product totals.
        $rows->setCollection($rows->getCollection()->map(function ($order) use ($kind, $context) {
            $details = app(OrderLifecycleService::class)->remaining($kind, $order->id, $context);
            return ['id' => $order->id, 'reference_no' => $order->reference_no,
                'party' => $kind === 'sale' ? $order->customer?->name : $order->supplier?->name,
                'status' => $kind === 'sale' ? $order->status : ($order->attributes_json['order_status'] ?? ($order->reversed_at ? 'cancelled' : ($details['items'] ? 'open' : 'review required'))),
                'remaining_delivery_base' => array_sum(array_column($details['items'], 'remaining_delivery_base')),
                'remaining_billing_base' => array_sum(array_column($details['items'], 'remaining_billing_base')),
                'reversed' => (bool) $order->reversed_at];
        }));
        return response()->json($rows);
    }

    public function show(Request $request, string $kind, int $id)
    {
        $context = $this->context($request, $kind);
        return response()->json(['data' => app(OrderLifecycleService::class)->remaining($kind, $id, $context)]);
    }

    public function storeSale(Request $request)
    {
        $context = $this->context($request, 'sale', true);
        $data = $request->has('items') ? $request->all() : app(LegacyCommercialCommand::class)->data($request, false, $context);
        $key = $request->header('Idempotency-Key', $request->input('idempotency_key', ''));
        abort_unless(is_string($key), 422);
        $order = DB::transaction(function () use ($data, $key, $context, $request) {
            $drafts = app(CommercialDraftService::class);
            if ($request->filled('draft_id')) $drafts->assertPostable('sale', $request->integer('draft_id'), $context, $request->user()->id);
            $order = app(OrderLifecycleService::class)->createSale($data, $key, $context, $request->user()->id);
            if ($request->filled('draft_id')) $drafts->query('sale', $context, $request->user()->id)->where('id', $request->integer('draft_id'))->delete();
            return $order;
        });
        return response()->json(['success' => true, 'data' => $order], 201);
    }

    public function cancel(Request $request, string $kind, int $id)
    {
        $context = $this->context($request, $kind);
        $data = $request->validate(['reason' => 'required|string|min:3|max:500', 'business_date' => 'required|date_format:Y-m-d']);
        return response()->json(['data' => app(OrderLifecycleService::class)->cancelRemainder($kind, $id, $data['reason'], $data['business_date'], $context, $request->user()->id)]);
    }

    public function amend(Request $request, string $kind, int $id)
    {
        $context = $this->context($request, $kind);
        $key = $request->header('Idempotency-Key', $request->input('idempotency_key', ''));
        abort_unless(is_string($key), 422);
        return response()->json(['data' => app(OrderLifecycleService::class)->amend($kind, $id, $request->except('_token', '_method', 'idempotency_key'), $key, $context, $request->user()->id)]);
    }
}
