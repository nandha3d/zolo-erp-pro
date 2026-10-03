<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Services\Accounting\AccountingService;
use App\Models\Accounting\ChartOfAccount;
use Illuminate\Http\Request;
use Carbon\Carbon;

class FinancialReportController extends Controller
{
    protected AccountingService $accountingService;

    public function __construct(AccountingService $accountingService)
    {
        $this->accountingService = $accountingService;
    }

    public function trialBalance(Request $request)
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        $report = $this->accountingService->getTrialBalance($startDate, $endDate);

        return view('backend.accounting.trial_balance', compact('report', 'startDate', 'endDate'));
    }

    public function profitLoss(Request $request)
    {
        $startDate = $request->input('start_date', Carbon::now()->startOfYear()->toDateString());
        $endDate = $request->input('end_date', Carbon::now()->toDateString());

        $report = $this->accountingService->getProfitAndLoss($startDate, $endDate);

        return view('backend.accounting.profit_loss', compact('report', 'startDate', 'endDate'));
    }

    public function balanceSheet(Request $request)
    {
        $asOfDate = $request->input('as_of_date', Carbon::now()->toDateString());

        $report = $this->accountingService->getBalanceSheet($asOfDate);

        return view('backend.accounting.balance_sheet', compact('report', 'asOfDate'));
    }

    public function generalLedger(Request $request)
    {
        $accounts = ChartOfAccount::where('is_active', true)->orderBy('code')->get();
        $accountId = $request->input('account_id', $accounts->first()?->id);
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        $report = null;
        if ($accountId) {
            $report = $this->accountingService->getGeneralLedger($accountId, $startDate, $endDate);
        }

        return view('backend.accounting.general_ledger', compact('accounts', 'accountId', 'report', 'startDate', 'endDate'));
    }

    public function cashFlowStatement(Request $request)
    {
        $startDate = $request->input('start_date', Carbon::now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', Carbon::now()->toDateString());

        // Cash Accounts: 1010 (Petty Cash), 1020 (Main Bank Account)
        $cashAccounts = ChartOfAccount::whereIn('code', ['1010', '1020'])->pluck('id')->toArray();

        // Operating Activities: Cash movements related to Sales, Receivables, Payables, COGS, Expenses
        $operatingDebits = \App\Models\Accounting\JournalItem::whereHas('journalEntry', function($q) use ($startDate, $endDate) {
            $q->whereBetween('entry_date', [$startDate, $endDate]);
        })->whereIn('chart_of_account_id', $cashAccounts)
          ->where('debit', '>', 0)
          ->sum('debit');

        $operatingCredits = \App\Models\Accounting\JournalItem::whereHas('journalEntry', function($q) use ($startDate, $endDate) {
            $q->whereBetween('entry_date', [$startDate, $endDate]);
        })->whereIn('chart_of_account_id', $cashAccounts)
          ->where('credit', '>', 0)
          ->sum('credit');

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

