<?php

namespace App\Http\Controllers;

use App\Models\{Company, Customer, Expense, Payment, Purchase, Sale, Supplier, Warehouse};
use App\Services\Accounting\SettlementAccountService;
use App\Services\Commercial\CommercialPermission;
use App\Services\ERP\PaymentService;
use App\Services\Platform\CompanyContext;
use App\Services\Platform\CompanyContextResolver;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DueManagementController extends Controller
{
    /**
     * Centralized Due Management Workspace (Due Clearance Hub)
     */
    public function index(Request $request)
    {
        $context = app(CompanyContextResolver::class)->forActor();
        $companyId = $context->companyId;

        $tab = $request->input('tab', 'sales');
        if (!in_array($tab, ['sales', 'purchases', 'expenses'], true)) {
            $tab = 'sales';
        }

        $search = trim((string) $request->input('search', ''));
        $partyId = $request->filled('party_id') ? (int) $request->party_id : null;
        $billId = $request->filled('bill_id') ? (int) $request->bill_id : ($request->filled('bill') ? (int) $request->bill : null);
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $warehouseId = $request->filled('warehouse_id') ? (int) $request->warehouse_id : null;
        $dueFilter = $request->input('due_filter', 'all'); // 'all', 'overdue', 'unpaid', 'partial'
        
        // View mode persistence: remember last clicked view mode in session
        if ($request->has('view')) {
            $viewMode = $request->input('view') === 'party_wise' ? 'party_wise' : 'bill_wise';
            session(['dues_view_mode' => $viewMode]);
        } else {
            $viewMode = session('dues_view_mode', 'bill_wise');
        }

        $today = Carbon::today();
        $rows = collect();

        $totalDue = 0.0;
        $totalOverdue = 0.0;
        $uniqueParties = collect();

        if ($tab === 'sales') {
            $query = Sale::visibleIn($context)
                ->with(['customer', 'warehouse'])
                ->whereNotNull('posted_at')
                ->whereNull('reversed_at')
                ->whereRaw('grand_total > paid_amount + 0.009');

            if ($billId) {
                $query->where('id', $billId);
            }
            if ($partyId) {
                $query->where('customer_id', $partyId);
            }
            if ($startDate) {
                $query->whereDate('created_at', '>=', $startDate);
            }
            if ($endDate) {
                $query->whereDate('created_at', '<=', $endDate);
            }
            if ($warehouseId) {
                $query->where('warehouse_id', $warehouseId);
            }
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('reference_no', 'like', "%{$search}%")
                      ->orWhereHas('customer', function ($cq) use ($search) {
                          $cq->where('name', 'like', "%{$search}%")
                             ->orWhere('phone_number', 'like', "%{$search}%");
                      });
                    if (is_numeric($search)) {
                        $q->orWhere('id', (int) $search);
                    }
                });
            }

            $sales = $query->orderBy('customer_id', 'asc')->orderBy('created_at', 'asc')->orderBy('id', 'asc')->get();

            foreach ($sales as $sale) {
                $returned = (float) DB::table('returns')
                    ->where('sale_id', $sale->id)
                    ->sum(DB::raw('grand_total / coalesce(exchange_rate, 1)'));

                $billed = (float) $sale->grand_total;
                $paid = (float) ($sale->paid_amount ?? 0);
                $due = max(0.0, $billed - $returned - $paid);

                if ($due <= 0.009) {
                    continue;
                }

                $creditDays = (int) ($sale->credit_days ?? $sale->customer?->credit_days ?? 30);
                $invoiceDate = Carbon::parse($sale->created_at);
                $dueDate = (clone $invoiceDate)->addDays($creditDays);
                $isOverdue = $today->greaterThan($dueDate);
                $daysOverdue = $isOverdue ? $today->diffInDays($dueDate) : 0;
                $daysLeft = !$isOverdue ? $dueDate->diffInDays($today) : 0;

                if ($dueFilter === 'overdue' && !$isOverdue) continue;
                if ($dueFilter === 'unpaid' && $paid > 0.009) continue;
                if ($dueFilter === 'partial' && $paid <= 0.009) continue;

                $totalDue += $due;
                if ($isOverdue) {
                    $totalOverdue += $due;
                }
                if ($sale->customer_id) {
                    $uniqueParties->put($sale->customer_id, true);
                }

                $sale->billed_amt = $billed;
                $sale->returned_amt = $returned;
                $sale->paid_amt = $paid;
                $sale->due_amt = $due;
                $sale->due_date_formatted = $dueDate->format('d-m-Y');
                $sale->due_date_raw = $dueDate->format('Y-m-d');
                $sale->is_overdue = $isOverdue;
                $sale->days_overdue = $daysOverdue;
                $sale->days_left = $daysLeft;
                $sale->party_id = $sale->customer_id;
                $sale->party_name = $sale->customer?->name ?? 'Walk-in Customer';
                $sale->party_phone = $sale->customer?->phone_number ?? '';
                $sale->party_city = $sale->customer?->city ?? '';
                $sale->warehouse_title = $sale->warehouse?->name ?? '—';
                $sale->doc_type = 'Sale Invoice';

                $rows->push($sale);
            }
        } elseif ($tab === 'purchases') {
            $query = Purchase::visibleIn($context)
                ->with(['supplier', 'warehouse'])
                ->whereNotNull('posted_at')
                ->whereNull('reversed_at')
                ->whereRaw('grand_total > paid_amount + 0.009');

            if ($billId) {
                $query->where('id', $billId);
            }
            if ($partyId) {
                $query->where('supplier_id', $partyId);
            }
            if ($startDate) {
                $query->whereDate('created_at', '>=', $startDate);
            }
            if ($endDate) {
                $query->whereDate('created_at', '<=', $endDate);
            }
            if ($warehouseId) {
                $query->where('warehouse_id', $warehouseId);
            }
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('reference_no', 'like', "%{$search}%")
                      ->orWhere('supplier_invoice_no', 'like', "%{$search}%")
                      ->orWhereHas('supplier', function ($sq) use ($search) {
                          $sq->where('name', 'like', "%{$search}%")
                             ->orWhere('phone_number', 'like', "%{$search}%");
                      });
                    if (is_numeric($search)) {
                        $q->orWhere('id', (int) $search);
                    }
                });
            }

            $purchases = $query->orderBy('supplier_id', 'asc')->orderBy('created_at', 'asc')->orderBy('id', 'asc')->get();

            foreach ($purchases as $purchase) {
                $returned = (float) DB::table('return_purchases')
                    ->where('purchase_id', $purchase->id)
                    ->sum('grand_total');

                $billed = (float) $purchase->grand_total;
                $paid = (float) ($purchase->paid_amount ?? 0);
                $due = max(0.0, $billed - $returned - $paid);

                if ($due <= 0.009) {
                    continue;
                }

                $creditDays = (int) ($purchase->supplier?->credit_days ?? 30);
                $invoiceDate = Carbon::parse($purchase->created_at);
                $dueDate = (clone $invoiceDate)->addDays($creditDays);
                $isOverdue = $today->greaterThan($dueDate);
                $daysOverdue = $isOverdue ? $today->diffInDays($dueDate) : 0;
                $daysLeft = !$isOverdue ? $dueDate->diffInDays($today) : 0;

                if ($dueFilter === 'overdue' && !$isOverdue) continue;
                if ($dueFilter === 'unpaid' && $paid > 0.009) continue;
                if ($dueFilter === 'partial' && $paid <= 0.009) continue;

                $totalDue += $due;
                if ($isOverdue) {
                    $totalOverdue += $due;
                }
                if ($purchase->supplier_id) {
                    $uniqueParties->put($purchase->supplier_id, true);
                }

                $purchase->billed_amt = $billed;
                $purchase->returned_amt = $returned;
                $purchase->paid_amt = $paid;
                $purchase->due_amt = $due;
                $purchase->due_date_formatted = $dueDate->format('d-m-Y');
                $purchase->due_date_raw = $dueDate->format('Y-m-d');
                $purchase->is_overdue = $isOverdue;
                $purchase->days_overdue = $daysOverdue;
                $purchase->days_left = $daysLeft;
                $purchase->party_id = $purchase->supplier_id;
                $purchase->party_name = $purchase->supplier?->name ?? 'Unknown Supplier';
                $purchase->party_phone = $purchase->supplier?->phone_number ?? '';
                $purchase->party_city = $purchase->supplier?->city ?? '';
                $purchase->warehouse_title = $purchase->warehouse?->name ?? '—';
                $purchase->doc_type = 'Purchase Bill';

                $rows->push($purchase);
            }
        } else {
            // Expenses & Other Dues
            $query = Expense::where('company_id', $companyId)
                ->with(['expenseCategory', 'warehouse'])
                ->whereNull('account_id');

            if ($billId) {
                $query->where('id', $billId);
            }
            if ($startDate) {
                $query->whereDate('created_at', '>=', $startDate);
            }
            if ($endDate) {
                $query->whereDate('created_at', '<=', $endDate);
            }
            if ($warehouseId) {
                $query->where('warehouse_id', $warehouseId);
            }
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('reference_no', 'like', "%{$search}%")
                      ->orWhere('note', 'like', "%{$search}%");
                    if (is_numeric($search)) {
                        $q->orWhere('id', (int) $search);
                    }
                });
            }

            $expenses = $query->orderBy('created_at', 'desc')->get();

            foreach ($expenses as $expense) {
                $amount = (float) $expense->amount;
                $totalDue += $amount;

                $expense->billed_amt = $amount;
                $expense->returned_amt = 0;
                $expense->paid_amt = 0;
                $expense->due_amt = $amount;
                $expense->due_date_formatted = Carbon::parse($expense->created_at)->format('d-m-Y');
                $expense->due_date_raw = Carbon::parse($expense->created_at)->format('Y-m-d');
                $expense->is_overdue = false;
                $expense->days_overdue = 0;
                $expense->days_left = 0;
                $expense->party_id = $expense->id;
                $expense->party_name = $expense->expenseCategory?->name ?? 'Operating Expense';
                $expense->party_phone = '';
                $expense->party_city = '';
                $expense->warehouse_title = $expense->warehouse?->name ?? '—';
                $expense->doc_type = 'Expense Voucher';

                $rows->push($expense);
            }
        }

        // If party-wise view is requested, group by party
        $partyGroups = collect();
        if ($viewMode === 'party_wise') {
            $partyGroups = $rows->groupBy('party_name')->map(function ($items, $name) {
                // Ensure date-wise ordering (oldest bill first for FIFO display)
                $sortedBills = $items->sortBy('created_at')->values();
                return (object) [
                    'party_name' => $name,
                    'party_phone' => $items->first()->party_phone,
                    'party_city' => $items->first()->party_city,
                    'party_id' => $items->first()->party_id,
                    'bill_count' => $items->count(),
                    'total_billed' => $items->sum('billed_amt'),
                    'total_paid' => $items->sum('paid_amt'),
                    'total_due' => $items->sum('due_amt'),
                    'has_overdue' => $items->contains('is_overdue', true),
                    'bills' => $sortedBills->map(fn($b) => [
                        'id' => $b->id,
                        'ref' => $b->reference_no,
                        'date' => date('d-m-Y', strtotime($b->created_at)),
                        'billed' => (float)$b->billed_amt,
                        'paid' => (float)$b->paid_amt,
                        'due' => (float)$b->due_amt,
                        'due_date' => $b->due_date_formatted,
                        'is_overdue' => (bool)$b->is_overdue,
                        'days_overdue' => (int)($b->days_overdue ?? 0),
                        'days_left' => (int)($b->days_left ?? 0),
                    ])->values()->all(),
                    'items' => $items,
                ];
            })->values();
        }

        // Active settlement accounts (cash & bank leaf accounts)
        $accounts = app(SettlementAccountService::class)->options($context);

        $customers = Customer::where('is_active', true)
            ->where(fn($q) => $q->where('company_id', $companyId)->orWhereNull('company_id'))
            ->orderBy('name')
            ->get(['id', 'name', 'phone_number']);

        $suppliers = Supplier::where('is_active', true)
            ->where(fn($q) => $q->where('company_id', $companyId)->orWhereNull('company_id'))
            ->orderBy('name')
            ->get(['id', 'name', 'phone_number']);

        $warehouses = Warehouse::where('is_active', true)
            ->where('company_id', $companyId)
            ->orderBy('name')
            ->get(['id', 'name']);

        $company = Company::find($companyId);
        $generalSetting = cache()->get('general_setting') ?? DB::table('general_settings')->first();

        // Historical settlement payments & receipts register for current company & tab
        $historicalQuery = Payment::visibleIn($context)
            ->with(['account'])
            ->orderByDesc('payment_at')
            ->orderByDesc('id');

        if ($tab === 'sales') {
            $historicalQuery->whereNotNull('sale_id')->where('sale_id', '>', 0)->with('sale.customer');
        } elseif ($tab === 'purchases') {
            $historicalQuery->whereNotNull('purchase_id')->where('purchase_id', '>', 0)->with('purchase.supplier');
        } else {
            $historicalQuery->whereNull('sale_id')->whereNull('purchase_id');
        }

        $historicalPayments = $historicalQuery->limit(25)->get();

        return view('backend.dues.index', compact(
            'rows',
            'partyGroups',
            'tab',
            'viewMode',
            'dueFilter',
            'search',
            'partyId',
            'startDate',
            'endDate',
            'warehouseId',
            'totalDue',
            'totalOverdue',
            'uniqueParties',
            'accounts',
            'customers',
            'suppliers',
            'warehouses',
            'company',
            'generalSetting',
            'historicalPayments'
        ));
    }

    /**
     * Settle a single due document
     */
    public function settle(Request $request): JsonResponse
    {
        $context = app(CompanyContextResolver::class)->forActor();

        $data = $request->validate([
            'kind' => 'required|in:sale,purchase,expense',
            'bill_id' => 'required|integer|min:1',
            'amount' => 'required|numeric|gt:0|max:1000000000',
            'payment_at' => 'required|date_format:Y-m-d',
            'paying_method' => 'required|in:Cash,Bank,Cheque,Credit Card,UPI',
            'account_id' => 'required|integer|min:1',
            'payment_note' => 'nullable|string|max:2000',
            'discount' => 'nullable|numeric|min:0',
            'discount_reason' => 'nullable|string|max:255',
            'tds_amount' => 'nullable|numeric|min:0',
            'idempotency_key' => 'nullable|string',
        ]);

        if (empty($data['idempotency_key'])) {
            $data['idempotency_key'] = (string) Str::uuid();
        }

        if ($data['kind'] === 'sale' || $data['kind'] === 'purchase') {
            $permission = $data['kind'] === 'sale' ? 'sale-payment-add' : 'purchase-payment-add';
            app(CommercialPermission::class)->assert($permission, $context, $request->user()->id);

            $model = $data['kind'] === 'sale' ? Sale::class : Purchase::class;
            $bill = $model::visibleIn($context)->findOrFail($data['bill_id']);
            abort_unless($bill->posted_at && !$bill->reversed_at, 409, 'Choose an active posted bill.');

            app(SettlementAccountService::class)->validateSelection($data['account_id'], $data['paying_method'], $context);

            try {
                $payment = app(PaymentService::class)->addPayment($bill, $data, $request->user()->id, $context);
            } catch (\InvalidArgumentException $e) {
                throw ValidationException::withMessages(['payment' => $e->getMessage()]);
            }

            $bill->refresh();
            $newDue = max(0.0, (float)$bill->grand_total - (float)$bill->paid_amount);

            return response()->json([
                'success' => true,
                'message' => 'Settlement recorded successfully.',
                'payment_id' => $payment->id,
                'voucher_url' => route('bill-payments.show', $payment->id),
                'bill_id' => $bill->id,
                'paid_amount' => (float)$bill->paid_amount,
                'remaining_due' => $newDue,
                'is_fully_paid' => $newDue <= 0.009,
            ]);
        } else {
            // Expense settlement
            $expense = Expense::where('company_id', $context->companyId)->findOrFail($data['bill_id']);
            $expense->account_id = $data['account_id'];
            $expense->save();

            return response()->json([
                'success' => true,
                'message' => 'Expense settlement recorded.',
                'bill_id' => $expense->id,
                'remaining_due' => 0,
                'is_fully_paid' => true,
            ]);
        }
    }

    /**
     * Batch settle multiple due bills for a single party
     */
    public function batchSettle(Request $request): JsonResponse
    {
        $context = app(CompanyContextResolver::class)->forActor();

        $data = $request->validate([
            'kind' => 'required|in:sale,purchase',
            'party_id' => 'required|integer|min:1',
            'bill_ids' => 'required|array|min:1',
            'bill_ids.*' => 'integer|min:1',
            'total_amount' => 'required|numeric|gt:0|max:1000000000',
            'payment_at' => 'required|date_format:Y-m-d',
            'paying_method' => 'required|in:Cash,Bank,Cheque,Credit Card,UPI',
            'account_id' => 'required|integer|min:1',
            'payment_note' => 'nullable|string|max:2000',
        ]);

        $permission = $data['kind'] === 'sale' ? 'sale-payment-add' : 'purchase-payment-add';
        app(CommercialPermission::class)->assert($permission, $context, $request->user()->id);
        app(SettlementAccountService::class)->validateSelection($data['account_id'], $data['paying_method'], $context);

        $model = $data['kind'] === 'sale' ? Sale::class : Purchase::class;
        $partyCol = $data['kind'] === 'sale' ? 'customer_id' : 'supplier_id';

        $bills = $model::visibleIn($context)
            ->where($partyCol, $data['party_id'])
            ->whereIn('id', $data['bill_ids'])
            ->whereNotNull('posted_at')
            ->whereNull('reversed_at')
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        if ($bills->isEmpty()) {
            throw ValidationException::withMessages(['bills' => 'No active bills found for settlement.']);
        }

        $remainingPool = (float) $data['total_amount'];
        $settled = [];

        DB::transaction(function () use ($bills, &$remainingPool, $data, $context, $request, &$settled) {
            foreach ($bills as $bill) {
                if ($remainingPool <= 0.009) {
                    break;
                }

                $billed = (float) $bill->grand_total;
                $paid = (float) ($bill->paid_amount ?? 0);
                $due = max(0.0, $billed - $paid);

                if ($due <= 0.009) {
                    continue;
                }

                $alloc = min($remainingPool, $due);
                $paymentPayload = [
                    'amount' => $alloc,
                    'payment_at' => $data['payment_at'],
                    'paying_method' => $data['paying_method'],
                    'account_id' => $data['account_id'],
                    'payment_note' => $data['payment_note'] ? "{$data['payment_note']} (Batch)" : 'Batch settlement',
                    'idempotency_key' => (string) Str::uuid(),
                ];

                $payment = app(PaymentService::class)->addPayment($bill, $paymentPayload, $request->user()->id, $context);
                $remainingPool -= $alloc;

                $bill->refresh();
                $settled[] = [
                    'bill_id' => $bill->id,
                    'allocated' => $alloc,
                    'remaining_due' => max(0.0, (float)$bill->grand_total - (float)$bill->paid_amount),
                    'payment_id' => $payment->id,
                ];
            }
        });

        $model = $data['kind'] === 'sale' ? Sale::class : Purchase::class;
        $partyCol = $data['kind'] === 'sale' ? 'customer_id' : 'supplier_id';
        $remainingBills = $model::visibleIn($context)
            ->where($partyCol, $data['party_id'])
            ->whereNotNull('posted_at')
            ->whereNull('reversed_at')
            ->whereRaw('grand_total > paid_amount + 0.009')
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $updatedPartyDue = (float) $remainingBills->sum(fn($b) => max(0.0, (float)$b->grand_total - (float)$b->paid_amount));

        return response()->json([
            'success' => true,
            'message' => count($settled) . ' bill(s) processed with FIFO allocation.',
            'party_id' => $data['party_id'],
            'settled' => $settled,
            'new_party_due' => $updatedPartyDue,
            'open_bills_count' => $remainingBills->count(),
            'remaining_unallocated' => $remainingPool,
        ]);
    }
}

