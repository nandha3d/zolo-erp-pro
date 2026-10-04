<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Services\Accounting\AccountingService;
use App\Models\Accounting\ChartOfAccount;
use Illuminate\Http\Request;
use Carbon\CarbonImmutable;
use App\Models\Company;
use App\Models\Accounting\JournalItem;
use App\Services\Platform\CompanyContextResolver;

class FinancialReportController extends Controller
{
    protected AccountingService $accountingService;

    public function __construct(AccountingService $accountingService)
    {
        $this->accountingService = $accountingService;
    }

    public function trialBalance(Request $request)
    {
        $context = app(CompanyContextResolver::class)->forActor();
        $timezone = Company::findOrFail($context->companyId)->timezone;
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        $report = $this->accountingService->getTrialBalance($startDate, $endDate, $context);

        return view('backend.accounting.trial_balance', compact('report', 'startDate', 'endDate'));
    }

    public function profitLoss(Request $request)
    {
        $context = app(CompanyContextResolver::class)->forActor();
        $timezone = Company::findOrFail($context->companyId)->timezone;
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        $report = $this->accountingService->getProfitAndLoss($startDate, $endDate, $context);

        return view('backend.accounting.profit_loss', compact('report', 'startDate', 'endDate'));
    }

    public function balanceSheet(Request $request)
    {
        $context = app(CompanyContextResolver::class)->forActor();
        $timezone = Company::findOrFail($context->companyId)->timezone;
        $asOfDate = $request->input('as_of_date');

        $report = $this->accountingService->getBalanceSheet($asOfDate, $context);

        return view('backend.accounting.balance_sheet', compact('report', 'asOfDate'));
    }

    public function generalLedger(Request $request)
    {
        $context = app(CompanyContextResolver::class)->forActor();
        $accounts = ChartOfAccount::forCompany($context)->where('is_active', true)->orderBy('code')->get();
        $accountId = $request->input('account_id', $accounts->first()?->id);
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        $report = null;
        if ($accountId) {
            $report = $this->accountingService->getGeneralLedger($accountId, $startDate, $endDate, $context);
        }

        return view('backend.accounting.general_ledger', compact('accounts', 'accountId', 'report', 'startDate', 'endDate'));
    }

    public function cashFlowStatement(Request $request)
    {
        $context = app(CompanyContextResolver::class)->forActor();
        $timezone = Company::findOrFail($context->companyId)->timezone;
        [$startDate, $endDate] = app(\App\Services\Accounting\FinancialReportService::class)->range($context, $request->start_date, $request->end_date);

        $cashAccounts = ChartOfAccount::forCompany($context)->whereIn('control_type', ['cash', 'bank'])->pluck('id')->toArray();

        \Illuminate\Support\Facades\Validator::make(['start_date' => $startDate, 'end_date' => $endDate], [
            'start_date' => 'required|date_format:Y-m-d',
            'end_date' => 'required|date_format:Y-m-d|after_or_equal:start_date',
        ])->validate();
        $totals = JournalItem::forCompany($context)
            ->whereHas('journalEntry', function ($q) use ($startDate, $endDate, $context) {
                app(\App\Services\Accounting\FinancialReportService::class)->scopeEntries($q, $context)
                    ->whereDate('entry_date', '>=', $startDate)->whereDate('entry_date', '<=', $endDate);
            })->whereIn('chart_of_account_id', $cashAccounts)
            ->selectRaw('COALESCE(SUM(debit), 0) AS debits, COALESCE(SUM(credit), 0) AS credits')->first();
        $operatingDebits = (float) $totals->debits;
        $operatingCredits = (float) $totals->credits;

        $netOperatingCash = $operatingDebits - $operatingCredits;
        $investingCash = 0.00; // Capital expenditure placeholder
        $financingCash = 0.00; // Debt/Equity financing placeholder
        $netCashFlow = $netOperatingCash + $investingCash + $financingCash;

        return view('backend.accounting.cash_flow_statement', compact(
            'startDate', 'endDate', 'operatingDebits', 'operatingCredits', 'netOperatingCash',
            'investingCash', 'financingCash', 'netCashFlow'
        ));
    }
}

