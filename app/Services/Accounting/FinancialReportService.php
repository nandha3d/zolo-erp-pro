<?php

namespace App\Services\Accounting;

use App\Models\Accounting\ChartOfAccount;
use Illuminate\Support\Facades\DB;
use App\Models\Accounting\AccountAllocation;
use App\Models\Accounting\AccountOpenItem;
use App\Models\Accounting\FiscalYear;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\JournalItem;
use App\Services\Platform\CompanyContext;
use App\Services\ERP\CompanyWriteGuard;
use App\Support\LedgerAmount;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class FinancialReportService
{
    /** Carry prior posted activity into the selected period without changing historical FY dates. */
    public function openingBalance(ChartOfAccount $account, CompanyContext $context, string $start): float
    {
        $main = \App\Models\CompanyBranch::whereKey($context->branchId)->value('code') === 'MAIN';
        $amount = $main ? LedgerAmount::units($account->opening_balance) : 0;
        foreach (JournalItem::forCompany($context)->where('chart_of_account_id', $account->id)
            ->whereHas('journalEntry', fn ($q) => $q->forCompany($context)->where('status', 'posted')
                ->where(fn ($q) => $q->where('branch_id', $context->branchId)->when($main, fn ($q) => $q->orWhereNull('branch_id')))
                ->where(fn ($q) => $q->whereNull('financial_year_id')->orWhereIn('financial_year_id', FiscalYear::where('company_id', $context->companyId)->select('id')))
                ->whereDate('entry_date', '<', $start))->cursor() as $item) {
            $change = LedgerAmount::units($item->debit) - LedgerAmount::units($item->credit);
            $amount += $account->isDebitNormal() ? $change : -$change;
        }
        return (float) LedgerAmount::decimal($amount);
    }
    public function range(CompanyContext $context, ?string $start = null, ?string $end = null): array
    {
        $year = FiscalYear::where('company_id', $context->companyId)->findOrFail($context->financialYearId);
        $start ??= $year->start_date->toDateString();
        $end ??= $year->end_date->toDateString();
        Validator::make(['start' => $start, 'end' => $end], [
            'start' => 'required|date_format:Y-m-d', 'end' => 'required|date_format:Y-m-d|after_or_equal:start',
        ])->validate();
        if ($start < $year->start_date->toDateString() || $end > $year->end_date->toDateString()) {
            throw ValidationException::withMessages(['date' => 'Report dates must fall inside the selected financial year.']);
        }
        return [$start, $end];
    }

    /** Undated legacy headers belong to MAIN only, until reviewed opening/history migration. */
    public function scopeEntries(Builder $query, CompanyContext $context): Builder
    {
        $main = \App\Models\CompanyBranch::whereKey($context->branchId)->value('code') === 'MAIN';
        return $query->forCompany($context)->where('status', 'posted')
            ->where(fn ($q) => $q->where('branch_id', $context->branchId)->when($main, fn ($q) => $q->orWhereNull('branch_id')))
            ->where(fn ($q) => $q->where('financial_year_id', $context->financialYearId)->orWhereNull('financial_year_id'));
    }

    public function lines(CompanyContext $context, string $start, string $end): Builder
    {
        return JournalItem::forCompany($context)->whereHas('account', fn ($q) => $q->forCompany($context))
            ->whereHas('journalEntry', fn ($q) => $this->scopeEntries($q, $context)->whereDate('entry_date', '>=', $start)->whereDate('entry_date', '<=', $end));
    }

    public function dayBook(CompanyContext $context, ?string $start = null, ?string $end = null, bool $cashOnly = false): array
    {
        $context = app(CompanyWriteGuard::class)->context($context, auth()->id());
        [$start, $end] = $this->range($context, $start, $end);
        $query = $this->scopeEntries(JournalEntry::withOwnedItems($context), $context)->whereDate('entry_date', '>=', $start)->whereDate('entry_date', '<=', $end);
        if ($cashOnly) {
            $query->whereHas('items', fn ($q) => $q->forCompany($context)->whereHas('account', fn ($q) => $q->forCompany($context)->whereIn('control_type', ['cash', 'bank'])));
        }
        $entries = $query->orderBy('entry_date')->orderBy('id')->paginate(50)->withQueryString();
        if ($cashOnly) {
            foreach ($entries as $entry) {
                $debit = $credit = 0;
                foreach ($entry->items as $line) {
                    if (in_array($line->account->control_type, ['cash', 'bank'], true)) {
                        $debit += LedgerAmount::units($line->debit);
                        $credit += LedgerAmount::units($line->credit);
                    }
                }
                $entry->cash_debit = LedgerAmount::decimal($debit);
                $entry->cash_credit = LedgerAmount::decimal($credit);
            }
        }
        return ['start_date' => $start, 'end_date' => $end, 'entries' => $entries];
    }

    /** Historical ageing reconstructs allocations as of the report date, including reversal dates. */
    public function ageing(CompanyContext $context, string $partyType, ?string $asOf = null): array
    {
        $context = app(CompanyWriteGuard::class)->context($context, auth()->id());
        if (!in_array($partyType, ['customer', 'supplier'], true)) {
            throw new \InvalidArgumentException('Ageing requires customers or suppliers.');
        }
        [, $asOf] = $this->range($context, null, $asOf);
        $items = AccountOpenItem::forCompany($context)->where('branch_id', $context->branchId)
            ->where('party_type', $partyType)->whereDate('document_date', '<=', $asOf)->orderBy('due_date')->get();
        $allocations = AccountAllocation::forCompany($context)->whereDate('allocation_date', '<=', $asOf)
            ->where(fn ($q) => $q->whereIn('debit_open_item_id', $items->pluck('id'))->orWhereIn('credit_open_item_id', $items->pluck('id')))->get();
        $effects = [];
        foreach ($allocations as $allocation) {
            $amount = LedgerAmount::units($allocation->allocated_amount) * ($allocation->reversal_of_id ? -1 : 1);
            $effects[$allocation->debit_open_item_id] = ($effects[$allocation->debit_open_item_id] ?? 0) - $amount;
            $effects[$allocation->credit_open_item_id] = ($effects[$allocation->credit_open_item_id] ?? 0) + $amount;
        }
        $totals = ['0-30' => 0, '31-60' => 0, '60+' => 0, 'credits' => 0];
        $rows = [];
        foreach ($items as $item) {
            $amount = LedgerAmount::units($item->original_amount) + ($effects[$item->id] ?? 0);
            if (!$amount) {
                continue;
            }
            $days = max(0, (int) CarbonImmutable::parse($item->due_date)->diffInDays(CarbonImmutable::parse($asOf), false));
            $bucket = $amount < 0 ? 'credits' : ($days <= 30 ? '0-30' : ($days <= 60 ? '31-60' : '60+'));
            $totals[$bucket] += $amount;
            $rows[] = ['item' => $item, 'open_amount' => LedgerAmount::decimal($amount), 'days' => $days, 'bucket' => $bucket];
        }
        return ['as_of_date' => $asOf, 'party_type' => $partyType, 'rows' => $rows, 'totals' => array_map(LedgerAmount::decimal(...), $totals)];
    }

    public function monthlyLedger(int $accountId, CompanyContext $context): array
    {
        $context = app(CompanyWriteGuard::class)->context($context, auth()->id());
        $account = \App\Models\Accounting\ChartOfAccount::forCompany($context)->findOrFail($accountId);
        [$start, $end] = $this->range($context);
        $items = $this->lines($context, $start, $end)->where('chart_of_account_id', $accountId)->with('journalEntry')->get();
        $rows = [];
        for ($month = CarbonImmutable::parse($start)->startOfMonth(); $month->toDateString() <= $end; $month = $month->addMonth()) {
            $from = max($start, $month->toDateString());
            $to = min($end, $month->endOfMonth()->toDateString());
            $debit = $credit = 0;
            foreach ($items as $item) {
                $date = $item->journalEntry->entry_date->toDateString();
                if ($date >= $from && $date <= $to) {
                    $debit += LedgerAmount::units($item->debit);
                    $credit += LedgerAmount::units($item->credit);
                }
            }
            $rows[] = ['label' => $month->format('M Y'), 'start_date' => $from, 'end_date' => $to,
                'debit' => LedgerAmount::decimal($debit), 'credit' => LedgerAmount::decimal($credit)];
        }
        return ['account' => $account, 'months' => $rows];
    }
    /** Historical opening-balance semantics remain unchanged; every activity row and header is owned. */
    private function accountActivity(CompanyContext $context, ?string $startDate, ?string $endDate): Builder
    {
        return ChartOfAccount::forCompany($context)->with([
            'journalItems' => fn ($q) => $q->forCompany($context)
                ->whereHas('journalEntry', function ($q) use ($context, $startDate, $endDate) {
                    app(FinancialReportService::class)->scopeEntries($q, $context);
                    if ($startDate) {
                        $q->whereDate('entry_date', '>=', $startDate);
                    }
                    if ($endDate) {
                        $q->whereDate('entry_date', '<=', $endDate);
                    }
                }),
        ]);
    }

    // ==========================================
    // FINANCIAL STATEMENTS & REPORT GENERATION
    // ==========================================

    /**
     * Generate Trial Balance.
     * Checks mathematical equality: Sum(Debit) == Sum(Credit).
     */
    public function getTrialBalance(?string $startDate = null, ?string $endDate = null, ?CompanyContext $context = null): array
    {
        $context = app(CompanyWriteGuard::class)->context($context, auth()->id());
        [$startDate, $endDate] = app(FinancialReportService::class)->range($context, $startDate, $endDate);
        return DB::transaction(function () use ($startDate, $endDate, $context) {
            $accounts = $this->accountActivity($context, $startDate, $endDate)->orderBy('code')->get();

            $rows = [];
            $totalDebit = 0.0;
            $totalCredit = 0.0;

            foreach ($accounts as $account) {
                $debit = (float) $account->journalItems->sum('debit');
                $credit = (float) $account->journalItems->sum('credit');

                // Skip zero activity accounts if both debit & credit are zero and opening is zero
                $opening = $this->openingBalance($account, $context, $startDate);
                if ($debit == 0 && $credit == 0 && $opening == 0) {
                    continue;
                }

                $netDebit = 0.0;
                $netCredit = 0.0;

                if ($account->isDebitNormal()) {
                    $netBalance = $opening + ($debit - $credit);
                    if ($netBalance >= 0) {
                        $netDebit = $netBalance;
                    } else {
                        $netCredit = abs($netBalance);
                    }
                } else {
                    $netBalance = $opening + ($credit - $debit);
                    if ($netBalance >= 0) {
                        $netCredit = $netBalance;
                    } else {
                        $netDebit = abs($netBalance);
                    }
                }

                $totalDebit += $netDebit;
                $totalCredit += $netCredit;

                $rows[] = [
                    'id' => $account->id,
                    'code' => $account->code,
                    'name' => $account->name,
                    'type' => $account->type,
                    'sub_type' => $account->sub_type,
                    'debit' => $netDebit,
                    'credit' => $netCredit,
                ];
            }

            return [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'accounts' => $rows,
                'total_debit' => round($totalDebit, 4),
                'total_credit' => round($totalCredit, 4),
                'is_balanced' => round($totalDebit - $totalCredit, 4) === 0.0,
                'difference' => round(abs($totalDebit - $totalCredit), 4),
            ];
        });
    }

    /**
     * Generate Profit and Loss (Income Statement).
     * Net Income = Revenue - COGS - Operating Expenses
     */
    public function getProfitAndLoss(?string $startDate = null, ?string $endDate = null, ?CompanyContext $context = null): array
    {
        $context = app(CompanyWriteGuard::class)->context($context, auth()->id());
        [$startDate, $endDate] = app(FinancialReportService::class)->range($context, $startDate, $endDate);
        return DB::transaction(function () use ($startDate, $endDate, $context) {
            $accounts = $this->accountActivity($context, $startDate, $endDate)->whereIn('type', ['revenue', 'expense'])->orderBy('code')->get();

            $revenues = [];
            $cogs = [];
            $expenses = [];

            $totalRevenue = 0.0;
            $totalCogs = 0.0;
            $totalOperatingExpense = 0.0;

            foreach ($accounts as $acc) {
                $debits = (float) $acc->journalItems->sum('debit');
                $credits = (float) $acc->journalItems->sum('credit');

                if ($acc->type === 'revenue') {
                    // Credit normal
                    $amount = ($credits - $debits);
                    if ($amount != 0) {
                        $revenues[] = ['code' => $acc->code, 'name' => $acc->name, 'amount' => round($amount, 4)];
                        $totalRevenue += $amount;
                    }
                } elseif ($acc->sub_type === 'cogs') {
                    // Debit normal
                    $amount = ($debits - $credits);
                    if ($amount != 0) {
                        $cogs[] = ['code' => $acc->code, 'name' => $acc->name, 'amount' => round($amount, 4)];
                        $totalCogs += $amount;
                    }
                } else {
                    // Operating expense
                    $amount = ($debits - $credits);
                    if ($amount != 0) {
                        $expenses[] = ['code' => $acc->code, 'name' => $acc->name, 'amount' => round($amount, 4)];
                        $totalOperatingExpense += $amount;
                    }
                }
            }

            $grossProfit = $totalRevenue - $totalCogs;
            $netIncome = $grossProfit - $totalOperatingExpense;

            return [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'revenues' => $revenues,
                'total_revenue' => round($totalRevenue, 4),
                'cogs' => $cogs,
                'total_cogs' => round($totalCogs, 4),
                'gross_profit' => round($grossProfit, 4),
                'operating_expenses' => $expenses,
                'total_operating_expenses' => round($totalOperatingExpense, 4),
                'net_income' => round($netIncome, 4),
            ];
        });
    }

    /**
     * Generate Balance Sheet.
     * Accounting equation: Assets = Liabilities + Equity (including current period Net Income)
     */
    public function getBalanceSheet(?string $asOfDate = null, ?CompanyContext $context = null): array
    {
        $context = app(CompanyWriteGuard::class)->context($context, auth()->id());
        [$startDate, $asOfDate] = app(FinancialReportService::class)->range($context, null, $asOfDate);
        return DB::transaction(function () use ($startDate, $asOfDate, $context) {

            $accounts = $this->accountActivity($context, $startDate, $asOfDate)->whereIn('type', ['asset', 'liability', 'equity'])->orderBy('code')->get();

            $assets = [];
            $liabilities = [];
            $equities = [];

            $totalAssets = 0.0;
            $totalLiabilities = 0.0;
            $totalEquity = 0.0;

            foreach ($accounts as $acc) {
                $debits = (float) $acc->journalItems->sum('debit');
                $credits = (float) $acc->journalItems->sum('credit');

                if ($acc->type === 'asset') {
                    $balance = $this->openingBalance($acc, $context, $startDate) + ($debits - $credits);
                    if ($balance != 0) {
                        $assets[] = ['code' => $acc->code, 'name' => $acc->name, 'sub_type' => $acc->sub_type, 'balance' => round($balance, 4)];
                        $totalAssets += $balance;
                    }
                } elseif ($acc->type === 'liability') {
                    $balance = $this->openingBalance($acc, $context, $startDate) + ($credits - $debits);
                    if ($balance != 0) {
                        $liabilities[] = ['code' => $acc->code, 'name' => $acc->name, 'sub_type' => $acc->sub_type, 'balance' => round($balance, 4)];
                        $totalLiabilities += $balance;
                    }
                } elseif ($acc->type === 'equity') {
                    $balance = $this->openingBalance($acc, $context, $startDate) + ($credits - $debits);
                    if ($balance != 0) {
                        $equities[] = ['code' => $acc->code, 'name' => $acc->name, 'sub_type' => $acc->sub_type, 'balance' => round($balance, 4)];
                        $totalEquity += $balance;
                    }
                }
            }

            // Add current period Net Income to Equity for dynamic balance
            $pnl = $this->getProfitAndLoss($startDate, $asOfDate, $context);
            $currentEarnings = $pnl['net_income'];
            $priorEarnings = 0.0;
            foreach (ChartOfAccount::forCompany($context)->whereIn('type', ['revenue', 'expense'])->get() as $account) {
                $balance = $this->openingBalance($account, $context, $startDate);
                $priorEarnings += $account->type === 'revenue' ? $balance : -$balance;
            }
            if ($priorEarnings != 0) {
                $equities[] = ['code' => 'PRIOR-EARNINGS', 'name' => 'Prior Period Earnings', 'sub_type' => 'equity', 'balance' => round($priorEarnings, 4)];
                $totalEquity += $priorEarnings;
            }

            $equities[] = [
                'code' => 'CURRENT-EARNINGS',
                'name' => 'Current Year Earnings (Net Income)',
                'sub_type' => 'equity',
                'balance' => $currentEarnings,
            ];
            $totalEquity += $currentEarnings;

            $totalLiabEquity = $totalLiabilities + $totalEquity;
            $isBalanced = abs($totalAssets - $totalLiabEquity) < 0.0001;

            return [
                'as_of_date' => $asOfDate,
                'assets' => $assets,
                'total_assets' => round($totalAssets, 4),
                'liabilities' => $liabilities,
                'total_liabilities' => round($totalLiabilities, 4),
                'equity' => $equities,
                'total_equity' => round($totalEquity, 4),
                'total_liabilities_and_equity' => round($totalLiabEquity, 4),
                'is_balanced' => $isBalanced,
                'difference' => round(abs($totalAssets - $totalLiabEquity), 4),
            ];
        });
    }

    /**
     * General Ledger Statement for a single Account.
     */
    public function getGeneralLedger(int $accountId, ?string $startDate = null, ?string $endDate = null, ?CompanyContext $context = null): array
    {
        $context = app(CompanyWriteGuard::class)->context($context, auth()->id());
        [$startDate, $endDate] = app(FinancialReportService::class)->range($context, $startDate, $endDate);
        return DB::transaction(function () use ($accountId, $startDate, $endDate, $context) {
            $account = ChartOfAccount::forCompany($context)->findOrFail($accountId);

            $query = JournalItem::forCompany($context)->with(['journalEntry' => fn ($q) => $q->forCompany($context)])
                ->where('chart_of_account_id', $accountId)
                ->whereHas('journalEntry', function ($q) use ($context, $startDate, $endDate) {
                    app(FinancialReportService::class)->scopeEntries($q, $context);
                    if ($startDate) {
                        $q->whereDate('entry_date', '>=', $startDate);
                    }
                    if ($endDate) {
                        $q->whereDate('entry_date', '<=', $endDate);
                    }
                });

            $items = $query->orderBy(
                JournalEntry::forCompany($context)->select('entry_date')->whereColumn('journal_entries.id', 'journal_items.journal_entry_id')
            )->orderBy('journal_entry_id')->orderBy('id')->get();

            $runningBalance = $this->openingBalance($account, $context, $startDate);
            $openingBalance = $runningBalance;
            $rows = [];

            foreach ($items as $item) {
                $debit = (float) $item->debit;
                $credit = (float) $item->credit;

                if ($account->isDebitNormal()) {
                    $runningBalance += ($debit - $credit);
                } else {
                    $runningBalance += ($credit - $debit);
                }

                $rows[] = [
                    'date' => $item->journalEntry->entry_date->format('Y-m-d'),
                    'entry_number' => $item->journalEntry->entry_number,
                    'reference_type' => $item->journalEntry->reference_type,
                    'reference_no' => $item->journalEntry->reference_no,
                    'memo' => $item->memo ?: $item->journalEntry->description,
                    'debit' => round($debit, 4),
                    'credit' => round($credit, 4),
                    'running_balance' => round($runningBalance, 4),
                ];
            }

            return [
                'account' => $account,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'opening_balance' => $openingBalance,
                'closing_balance' => round($runningBalance, 4),
                'total_debit' => round((float)$items->sum('debit'), 4),
                'total_credit' => round((float)$items->sum('credit'), 4),
                'transactions' => $rows,
            ];
        });
    }
}
