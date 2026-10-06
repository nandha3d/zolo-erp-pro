<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\JournalItem;
use App\Models\Accounting\ChartOfAccount;
use App\Models\Customer;
use App\Models\Supplier;
use App\Models\StandardRemark;
use App\Models\DocumentSeries;
use App\Services\Accounting\AccountingService;
use App\Services\ERP\CompanyWriteGuard;
use App\Services\Platform\CompanyContextResolver;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Exception;
use Auth;

class JournalEntryController extends Controller
{
    protected AccountingService $accountingService;

    public function __construct(AccountingService $accountingService)
    {
        $this->accountingService = $accountingService;
    }

    public function index(Request $request)
    {
        $context = app(CompanyContextResolver::class)->forActor();
        $request->validate(['start_date' => 'nullable|date_format:Y-m-d', 'end_date' => 'nullable|date_format:Y-m-d|after_or_equal:start_date']);
        $query = JournalEntry::forCompany($context)->with(['creator' => fn ($q) => $q->whereIn('id', DB::table('company_user')->select('user_id')->where('company_id', $context->companyId))]);

        if ($request->filled('reference_type')) {
            $query->where('reference_type', $request->reference_type);
        }
        [$start, $end] = app(\App\Services\Accounting\FinancialReportService::class)->range($context, $request->start_date, $request->end_date);
        app(\App\Services\Accounting\FinancialReportService::class)->scopeEntries($query, $context)->whereDate('entry_date', '>=', $start)->whereDate('entry_date', '<=', $end);
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereDate('entry_date', '>=', $request->start_date)->whereDate('entry_date', '<=', $request->end_date);
        }

        $entries = $query->orderBy('id', 'desc')->paginate(25);
        $accounts = ChartOfAccount::forCompany($context)->where('is_active', true)->orderBy('code')->get();

        return view('backend.accounting.journal_entries', compact('entries', 'accounts'));
    }

    /**
     * Dedicated fast keyboard-first Optech Voucher Entry Screen (VOUCHER_ENTRY_03)
     */
    public function voucherEntry(Request $request)
    {
        $context = app(CompanyContextResolver::class)->forActor();
        $company = \App\Models\Company::findOrFail($context->companyId);
        $businessDate = CarbonImmutable::now($company->timezone)->toDateString();

        $accounts = ChartOfAccount::forCompany($context)
            ->where('is_active', true)
            ->where('allow_manual_posting', true)
            ->orderBy('code')
            ->get();

        $accounts->transform(function ($acc) {
            $bal = (float) $acc->current_balance;
            $side = $acc->isDebitNormal() ? ($bal >= 0 ? 'Dr' : 'Cr') : ($bal >= 0 ? 'Cr' : 'Dr');
            $acc->formatted_balance = number_format(abs($bal), 2) . ' ' . $side;
            return $acc;
        });

        $series = DocumentSeries::forCompany($context)
            ->where('branch_id', $context->branchId)
            ->where('financial_year_id', $context->financialYearId)
            ->whereIn('document_type', ['journal', 'sale_payment', 'purchase_payment'])
            ->get();

        $customers = Customer::forCompany($context)->where('is_active', true)->orderBy('name')->get(['id', 'name', 'city']);
        $suppliers = Supplier::forCompany($context)->where('is_active', true)->orderBy('name')->get(['id', 'name', 'city']);
        $remarks = StandardRemark::forCompany($context)->where('is_active', true)->whereIn('type', ['all', 'voucher', 'sale', 'purchase'])->get();

        return view('backend.accounting.voucher_entry', compact(
            'accounts', 'series', 'customers', 'suppliers', 'remarks', 'businessDate'
        ));
    }

    /**
     * Inline Ledger Account Creation (Alt+C / [+] button)
     */
    public function inlineAccount(Request $request)
    {
        $context = app(CompanyContextResolver::class)->forActor();
        $validated = $request->validate([
            'code' => [
                'required', 'string', 'max:50',
                Rule::unique('chart_of_accounts', 'code')->where('company_id', $context->companyId),
            ],
            'name' => 'required|string|max:255',
            'type' => 'required|in:asset,liability,equity,revenue,expense',
            'sub_type' => 'required|string|max:100',
            'control_type' => 'nullable|string|in:cash,bank,ar,ap,none',
            'opening_balance' => 'nullable|numeric|min:0',
            'description' => 'nullable|string|max:500',
        ]);

        $openingBal = (float) ($validated['opening_balance'] ?? 0);

        $account = DB::transaction(function () use ($validated, $openingBal, $context) {
            app(CompanyWriteGuard::class)->begin($context, null);
            return ChartOfAccount::forceCreate([
                'company_id' => $context->companyId,
                'code' => $validated['code'],
                'name' => $validated['name'],
                'type' => $validated['type'],
                'sub_type' => $validated['sub_type'],
                'control_type' => $validated['control_type'] ?? 'none',
                'opening_balance' => $openingBal,
                'current_balance' => $openingBal,
                'description' => $validated['description'] ?? null,
                'allow_manual_posting' => true,
                'is_system' => false,
                'is_active' => true,
            ]);
        });

        $bal = (float) $account->current_balance;
        $side = $account->isDebitNormal() ? ($bal >= 0 ? 'Dr' : 'Cr') : ($bal >= 0 ? 'Cr' : 'Dr');
        $account->formatted_balance = number_format(abs($bal), 2) . ' ' . $side;

        return response()->json([
            'success' => true,
            'data' => $account,
            'message' => 'Ledger Account created successfully.',
        ], 201);
    }

    /**
     * Next voucher number preview for a selected series
     */
    public function nextVoucherNumber(Request $request)
    {
        $context = app(CompanyContextResolver::class)->forActor();
        $code = $request->query('series_code');
        $series = DocumentSeries::forCompany($context)
            ->where('branch_id', $context->branchId)
            ->where('financial_year_id', $context->financialYearId)
            ->when($code, fn ($q) => $q->where('code', $code), fn ($q) => $q->where('is_default', true))
            ->first();

        if (!$series) {
            return response()->json(['next_number' => 'AUTO']);
        }

        $formatted = $series->prefix . str_pad((string) $series->next_number, $series->padding, '0', STR_PAD_LEFT) . $series->suffix;
        return response()->json([
            'series_id' => $series->id,
            'code' => $series->code,
            'next_number' => $formatted,
        ]);
    }

    public function show(Request $request, $id)
    {
        $context = app(CompanyContextResolver::class)->forActor();
        $entry = JournalEntry::withOwnedItems($context)->with(['creator' => fn ($q) => $q->whereIn('id', DB::table('company_user')->select('user_id')->where('company_id', $context->companyId))])->findOrFail($id);
        abort_if($entry->branch_id && (int) $entry->branch_id !== $context->branchId, 404);
        return response()->json($entry);
    }

    public function store(Request $request)
    {
        $this->validate($request, [
            'entry_date' => 'required|date_format:Y-m-d',
            'description' => 'required|string|max:500',
            'voucher_type' => 'nullable|string|max:30',
            'series_code' => 'nullable|string|max:50',
            'gst_nature' => 'nullable|string|max:50',
            'cheque_no' => 'nullable|string|max:100',
            'cheque_date' => 'nullable|date_format:Y-m-d',
            'items' => 'required|array|min:2',
            'items.*.chart_of_account_id' => 'required|integer|min:1',
            'items.*.debit' => 'nullable|numeric|min:0',
            'items.*.credit' => 'nullable|numeric|min:0',
            'items.*.memo' => 'nullable|string|max:255',
            'items.*.partner_type' => 'nullable|string|in:customer,supplier',
            'items.*.partner_id' => 'nullable|integer|min:1',
        ]);

        try {
            $context = app(CompanyContextResolver::class)->forActor();
            $entry = $this->accountingService->postJournalEntry([
                'entry_date' => $request->entry_date,
                'description' => $request->description,
                'reference_type' => $request->input('reference_type', 'manual'),
                'voucher_type' => $request->input('voucher_type'),
                'series_code' => $request->input('series_code'),
                'gst_nature' => $request->input('gst_nature'),
                'cheque_no' => $request->input('cheque_no'),
                'cheque_date' => $request->input('cheque_date'),
                'created_by' => Auth::id(),
                'idempotency_key' => $request->input('idempotency_key'),
            ], $request->items, $context);

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'data' => $entry,
                    'voucher_no' => $entry->entry_number,
                    'message' => 'Voucher [' . $entry->entry_number . '] posted successfully.',
                ], 201);
            }

            return redirect()->back()->with('message', 'Voucher [' . $entry->entry_number . '] posted successfully.');
        } catch (\Illuminate\Validation\ValidationException | \Illuminate\Auth\Access\AuthorizationException $e) {
            if ($request->expectsJson() || $request->ajax()) {
                throw $e;
            }
            throw $e;
        } catch (Exception $e) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            }
            return redirect()->back()->with('not_permitted', $e->getMessage());
        }
    }
}
