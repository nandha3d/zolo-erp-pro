<?php

namespace App\Http\Controllers;

use App\Models\{Payment, Purchase, Sale};
use App\Models\Accounting\JournalEntry;
use App\Services\Commercial\CommercialPermission;
use App\Services\Accounting\SettlementAccountService;
use App\Services\ERP\PaymentService;
use App\Services\Platform\CompanyContextResolver;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class BillPaymentController extends Controller
{
    public function index(Request $request, string $kind = 'sale')
    {
        // Redirect browser requests to the new centralized Payment & Due Management Command Center
        if ((!app()->environment('testing') || $request->has('simulate_browser')) && !$request->wantsJson() && !$request->has('legacy')) {
            $context = app(CompanyContextResolver::class)->forActor();
            $model = $kind === 'sale' ? Sale::class : Purchase::class;
            $search = $request->input('search');
            $billId = $request->filled('bill') ? $request->integer('bill') : null;
            if ($billId) {
                $targetBill = $context ? $model::visibleIn($context)->find($billId) : $model::find($billId);
                $search = $targetBill?->reference_no ?: (string) $billId;
            }
            return redirect()->route('dues.index', array_filter([
                'tab' => $kind === 'sale' ? 'sales' : 'purchases',
                'search' => $search,
                'bill_id' => $billId,
            ]));
        }

        $context = app(CompanyContextResolver::class)->forActor();
        $permission = $kind === 'sale' ? 'sale-payment-add' : 'purchase-payment-add';
        app(CommercialPermission::class)->assert($permission, $context, $request->user()->id);
        $request->validate(['bill' => 'nullable|integer|min:1', 'search' => 'nullable|string|max:150']);
        $model = $kind === 'sale' ? Sale::class : Purchase::class;
        $party = $kind === 'sale' ? 'customer' : 'supplier';
        $base = $model::visibleIn($context)->whereNotNull('posted_at')->whereNull('reversed_at');
        $selected = $request->filled('bill') ? (clone $base)->with($party)->findOrFail($request->integer('bill')) : null;
        $bills = (clone $base)->with($party)->whereRaw('grand_total > paid_amount + 0.00005')
            ->when($request->filled('search'), fn ($q) => $q->where(fn ($q) => $q
                ->where('reference_no', 'like', '%'.$request->search.'%')
                ->orWhereHas($party, fn ($q) => $q->where('name', 'like', '%'.$request->search.'%'))))
            ->orderBy('created_at')->orderBy('id')->paginate(25)->withQueryString();
        $payments = Payment::visibleIn($context)->with([$party === 'customer' ? 'sale.customer' : 'purchase.supplier', 'account'])
            ->whereHas($kind, fn ($q) => $q->visibleIn($context))->orderByDesc('id')->limit(50)->get();
        $accounts = app(SettlementAccountService::class)->options($context);
        $canManageAccounts = app(CompanyContextResolver::class)->canManageFinancialYears($request->user()->id, $context->companyId);
        return view('backend.accounting.bill-payments', compact('kind', 'party', 'selected', 'bills', 'payments', 'accounts', 'canManageAccounts'));
    }

    public function store(Request $request, string $kind = 'sale')
    {
        $context = app(CompanyContextResolver::class)->forActor();
        app(CommercialPermission::class)->assert($kind === 'sale' ? 'sale-payment-add' : 'purchase-payment-add', $context, $request->user()->id);
        $data = $request->validate(['bill_id' => 'required|integer|min:1', 'amount' => 'required|numeric|gt:0|max:1000000000',
            'payment_at' => 'required|date_format:Y-m-d', 'paying_method' => 'required|in:Cash,Bank,Cheque,Credit Card',
            'account_id' => 'required|integer|min:1', 'payment_note' => 'nullable|string|max:2000',
            'idempotency_key' => 'required|uuid', 'discount' => 'nullable|numeric|min:0',
            'discount_reason' => 'nullable|string|max:255', 'tds_amount' => 'nullable|numeric|min:0']);
        $bill = ($kind === 'sale' ? Sale::class : Purchase::class)::visibleIn($context)->findOrFail($data['bill_id']);
        abort_unless($bill->posted_at && !$bill->reversed_at, 409, 'Choose an active posted bill.');
        app(SettlementAccountService::class)->validateSelection($data['account_id'], $data['paying_method'], $context);
        try {
            $payment = app(PaymentService::class)->addPayment($bill, $data, $request->user()->id, $context);
        } catch (\InvalidArgumentException $error) {
            throw ValidationException::withMessages(['payment' => $error->getMessage()]);
        }
        return $request->expectsJson() ? response()->json(['data' => $payment], 201)
            : redirect()->route('bill-payments.show', $payment->id)->with('message', 'Payment recorded. Bill balance and accounts updated.');
    }

    public function show(Request $request, int $id)
    {
        $context = app(CompanyContextResolver::class)->forActor();
        $payment = Payment::visibleIn($context)->with(['sale.customer', 'purchase.supplier', 'account'])->findOrFail($id);
        $prefix = $payment->sale_id ? 'sale-payment-' : 'purchase-payment-';
        abort_unless(app(CommercialPermission::class)->allowed([$prefix.'view', $prefix.'add'], $context, $request->user()->id), 403);
        $entry = JournalEntry::forCompany($context)->where('branch_id', $context->branchId)->where('reference_type', 'payment')
            ->where('reference_id', $payment->id)->whereNull('reversal_of_id')->with('items.account')->first();
        $bill = $payment->sale_id ? $payment->sale : $payment->purchase;
        if ($entry) $entry->setAttribute('is_reversed', JournalEntry::forCompany($context)->where('reversal_of_id', $entry->id)->exists());
        $voucher = !$payment->sale_id || $request->boolean('voucher');
        $title = $payment->sale_id ? ($voucher ? 'Receipt Voucher' : 'Receipt') : 'Payment Voucher';
        return view('backend.accounting.payment-document', compact('payment', 'entry', 'bill', 'title', 'voucher'));
    }

    public function createAccount(Request $request)
    {
        $context = app(CompanyContextResolver::class)->forActor();
        $service = app(SettlementAccountService::class);
        $account = $service->create($request->all(), $context, $request->user()->id);
        return response()->json(['data' => collect($service->options($context))->firstWhere('id', $account->id)], 201);
    }
}
