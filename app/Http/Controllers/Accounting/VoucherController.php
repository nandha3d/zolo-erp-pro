<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\AccountOpenItem;
use App\Models\Accounting\ChartOfAccount;
use App\Models\Accounting\FiscalYear;
use App\Models\Accounting\JournalEntry;
use App\Services\Accounting\AccountingAccess;
use App\Services\Accounting\AccountingPostingService;
use App\Services\Accounting\FinancialReportService;
use App\Services\Accounting\OpenItemService;
use App\Services\Accounting\PeriodCloseService;
use App\Services\Accounting\VoucherService;
use App\Services\ERP\CompanyWriteGuard;
use App\Services\Platform\CompanyContextResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VoucherController extends Controller
{
    public function index(Request $request)
    {
        $context = app(CompanyContextResolver::class)->forActor();
        $accounts = ChartOfAccount::forCompany($context)->where('is_active', true)->whereDoesntHave('children')->orderBy('code')->get();
        $customers = \App\Models\Customer::forCompany($context)->orderBy('name')->get(['id', 'name']);
        $suppliers = \App\Models\Supplier::forCompany($context)->orderBy('name')->get(['id', 'name']);
        $openItems = AccountOpenItem::forCompany($context)->where('branch_id', $context->branchId)->where('status', 'open')->orderBy('document_date')->get();
        $entries = app(FinancialReportService::class)->dayBook($context)['entries'];
        $year = FiscalYear::where('company_id', $context->companyId)->findOrFail($context->financialYearId);
        $canManage = app(CompanyContextResolver::class)->canManageFinancialYears($request->user()->id, $context->companyId);
        $legacyAccounts = \App\Models\Account::forCompany($context)->orderBy('name')->get();
        $canPost = true;
        try {
            AccountingAccess::assert($context);
        } catch (\Illuminate\Auth\Access\AuthorizationException) {
            $canPost = false;
        }
        $periodEvents = DB::table('accounting_period_events')->where('company_id', $context->companyId)
            ->where('financial_year_id', $context->financialYearId)->orderByDesc('id')->limit(10)->get();
        return view('backend.accounting.vouchers', compact('accounts', 'customers', 'suppliers', 'openItems', 'entries', 'year', 'canManage', 'canPost', 'legacyAccounts', 'periodEvents'));
    }

    public function store(Request $request)
    {
        return $this->effect($request, fn () => app(VoucherService::class)->post($request->all(), app(CompanyContextResolver::class)->forActor()), 'Voucher posted.');
    }

    public function reverse(Request $request, int $id)
    {
        $request->validate(['entry_date' => 'required|date_format:Y-m-d', 'reason' => 'required|string|max:500']);
        $context = app(CompanyContextResolver::class)->forActor();
        AccountingAccess::assert($context, 'accounting.journal.reverse');
        return $this->effect($request, fn () => app(AccountingPostingService::class)->reverse($id, $request->entry_date, $request->reason, $context), 'Journal reversal posted.');
    }

    public function openItems(Request $request)
    {
        $context = app(CompanyContextResolver::class)->forActor();
        $request->validate(['party_type' => 'nullable|in:customer,supplier', 'party_id' => 'nullable|integer|min:1']);
        return response()->json(AccountOpenItem::forCompany($context)->where('branch_id', $context->branchId)
            ->when($request->filled('party_type'), fn ($q) => $q->where('party_type', $request->party_type))
            ->when($request->filled('party_id'), fn ($q) => $q->where('party_id', $request->party_id))
            ->orderBy('id')->paginate(50));
    }

    public function allocate(Request $request)
    {
        $request->validate(['first_item_id' => 'required|integer|min:1', 'second_item_id' => 'required|integer|min:1',
            'amount' => 'required|numeric|gt:0', 'allocation_date' => 'required|date_format:Y-m-d', 'idempotency_key' => 'required|string|max:100']);
        $context = app(CompanyContextResolver::class)->forActor();
        AccountingAccess::assert($context);
        return $this->effect($request, fn () => app(OpenItemService::class)->allocate($request->first_item_id, $request->second_item_id,
            $request->amount, $request->allocation_date, 'user:'.$request->idempotency_key, $context), 'Open items allocated.');
    }

    public function reverseAllocation(Request $request, int $id)
    {
        $request->validate(['allocation_date' => 'required|date_format:Y-m-d', 'reason' => 'required|string|max:500']);
        $context = app(CompanyContextResolver::class)->forActor();
        AccountingAccess::assert($context, 'accounting.journal.reverse');
        return $this->effect($request, fn () => app(OpenItemService::class)->reverseAllocation($id, $request->allocation_date, $request->reason, $context), 'Allocation reversal posted.');
    }

    public function period(Request $request)
    {
        $request->validate(['action' => 'required|in:lock,unlock,close', 'lock_date' => 'nullable|date_format:Y-m-d', 'reason' => 'required|string|max:500']);
        return $this->effect($request, fn () => app(PeriodCloseService::class)->change(app(CompanyContextResolver::class)->forActor(),
            $request->action, $request->lock_date, $request->reason), 'Financial period updated.');
    }

    public function linkAccount(Request $request, int $id)
    {
        $request->validate(['chart_of_account_id' => 'required|integer|min:1']);
        $context = app(CompanyContextResolver::class)->forActor();
        abort_unless(app(CompanyContextResolver::class)->canManageFinancialYears($request->user()->id, $context->companyId), 403);
        return $this->effect($request, fn () => DB::transaction(function () use ($context, $id, $request) {
            $guard = app(CompanyWriteGuard::class);
            $guard->begin($context, null);
            $account = $guard->owned(\App\Models\Account::class, $id, $context, 'account_id');
            $chart = $guard->owned(ChartOfAccount::class, $request->chart_of_account_id, $context, 'chart_of_account_id');
            if (!$chart->is_active || !in_array($chart->control_type, ['cash', 'bank'], true)) {
                throw new \InvalidArgumentException('Link an active company cash or bank account.');
            }
            $account->forceFill(['chart_of_account_id' => $chart->id])->save();
            return $account;
        }), 'Settlement account linked.');
    }

    public function book(Request $request, string $type = 'day')
    {
        $request->validate(['start_date' => 'nullable|date_format:Y-m-d', 'end_date' => 'nullable|date_format:Y-m-d']);
        $context = app(CompanyContextResolver::class)->forActor();
        $report = app(FinancialReportService::class)->dayBook($context, $request->start_date, $request->end_date, $type === 'cash');
        if ($request->expectsJson()) {
            return response()->json($report);
        }
        return view('backend.accounting.book', compact('report', 'type'));
    }

    public function ageing(Request $request)
    {
        $request->validate(['party_type' => 'nullable|in:customer,supplier', 'as_of_date' => 'nullable|date_format:Y-m-d']);
        $report = app(FinancialReportService::class)->ageing(app(CompanyContextResolver::class)->forActor(), $request->input('party_type', 'customer'), $request->as_of_date);
        return $request->expectsJson() ? response()->json($report) : view('backend.accounting.ageing', compact('report'));
    }

    public function monthly(Request $request, int $id)
    {
        $report = app(FinancialReportService::class)->monthlyLedger($id, app(CompanyContextResolver::class)->forActor());
        return $request->expectsJson() ? response()->json($report) : view('backend.accounting.monthly_ledger', compact('report'));
    }

    private function effect(Request $request, callable $action, string $message)
    {
        try {
            $result = $action();
        } catch (\InvalidArgumentException $error) {
            throw ValidationException::withMessages(['accounting' => $error->getMessage()]);
        }
        return $request->expectsJson() ? response()->json(['message' => $message, 'data' => $result], 200)
            : redirect()->back()->with('message', $message);
    }
}
