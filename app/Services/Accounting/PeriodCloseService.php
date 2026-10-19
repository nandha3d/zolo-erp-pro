<?php

namespace App\Services\Accounting;

use App\Models\Accounting\ChartOfAccount;
use App\Models\Accounting\FiscalYear;
use App\Models\Accounting\JournalEntry;
use App\Models\Company;
use App\Services\Platform\CompanyContext;
use App\Services\Platform\CompanyContextResolver;
use App\Support\LedgerAmount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PeriodCloseService
{
    public function change(CompanyContext $context, string $action, ?string $date, string $reason): FiscalYear
    {
        $resolver = app(CompanyContextResolver::class);
        $context = $resolver->forActor($context);
        if (!$resolver->canManageFinancialYears(auth()->id(), $context->companyId)) {
            throw new \Illuminate\Auth\Access\AuthorizationException('Company administrator required for period controls.');
        }
        Validator::make(['action' => $action, 'date' => $date, 'reason' => trim($reason)], [
            'action' => 'required|in:lock,unlock,close', 'date' => 'nullable|date_format:Y-m-d|required_if:action,lock',
            'reason' => 'required|string|max:500',
        ])->validate();
        return DB::transaction(function () use ($context, $action, $date, $reason) {
            Company::whereKey($context->companyId)->lockForUpdate()->firstOrFail();
            $year = FiscalYear::where('company_id', $context->companyId)->whereKey($context->financialYearId)->lockForUpdate()->firstOrFail();
            if ($date && ($date < $year->start_date->toDateString() || $date > $year->end_date->toDateString())) {
                throw new \InvalidArgumentException('Lock date must fall inside the selected financial year.');
            }
            if ($action === 'lock' && ($year->is_closed || ($year->lock_date && $date < $year->lock_date->toDateString()))) {
                throw new \InvalidArgumentException('Use an audited unlock to reopen a closed period or move its lock backwards.');
            }
            if ($action === 'close' && $year->is_closed) {
                throw new \InvalidArgumentException('This financial year is already closed.');
            }
            if ($action === 'close' && !app(LedgerReconciliationService::class)->reconcile($context)['is_reconciled']) {
                throw new \InvalidArgumentException('Reconcile account balances and open items before closing the year.');
            }
            $previous = $year->lock_date?->toDateString();
            $wasClosed = (bool) $year->is_closed;
            if ($action === 'close') {
                // The closing entry is dated the year end, which an earlier lock may already cover.
                $year->forceFill(['lock_date' => null])->save();
                $this->postClosingEntries($year, $context, $reason);
            }
            $year->forceFill([
                'lock_date' => $action === 'unlock' ? null : ($action === 'close' ? $year->end_date->toDateString() : $date),
                'status' => $action === 'close' ? 'closed' : 'open', 'is_closed' => $action === 'close',
                'closed_at' => $action === 'close' ? now() : null, 'closed_by' => $action === 'close' ? auth()->id() : null,
            ])->save();
            if ($action === 'unlock' && $wasClosed) {
                // Reopening hands the profit back to the income and expense accounts; closing again posts a fresh transfer.
                $this->reverseClosingEntries($year, $context, $reason);
            }
            DB::table('accounting_period_events')->insert([
                'company_id' => $context->companyId, 'financial_year_id' => $year->id, 'actor_id' => auth()->id(),
                'action' => $action, 'previous_lock_date' => $previous, 'lock_date' => $year->lock_date?->toDateString(),
                'reason' => $reason, 'created_at' => now(),
            ]);
            return $year;
        });
    }

    /**
     * Moves every income and expense balance up to the year end into Retained Earnings, one journal per branch, so the
     * next year starts with clean income accounts and the profit sits in equity. Reports leave these entries out of
     * profit and loss and the trial balance; the balance sheet keeps them.
     */
    private function postClosingEntries(FiscalYear $year, CompanyContext $context, string $reason): void
    {
        $end = $year->end_date->toDateString();
        $accounts = ChartOfAccount::forCompany($context)->whereIn('type', ['revenue', 'expense'])->get()->keyBy('id');
        if ($accounts->isEmpty()) {
            return;
        }
        $branches = DB::table('company_branches')->where('company_id', $context->companyId)->orderBy('id')->get(['id', 'code']);
        $sums = DB::table('journal_items')->join('journal_entries', 'journal_entries.id', '=', 'journal_items.journal_entry_id')
            ->where('journal_entries.company_id', $context->companyId)->where('journal_entries.status', 'posted')
            ->whereDate('journal_entries.entry_date', '<=', $end)->whereIn('journal_items.chart_of_account_id', $accounts->keys())
            ->groupBy('journal_entries.branch_id', 'journal_items.chart_of_account_id')
            ->get(['journal_entries.branch_id', 'journal_items.chart_of_account_id',
                DB::raw('SUM(journal_items.debit) as debit'), DB::raw('SUM(journal_items.credit) as credit')]);
        $main = $branches->firstWhere('code', 'MAIN')?->id;
        foreach ($branches as $branch) {
            // Debit-side units per account: undated legacy lines and account opening amounts belong to MAIN.
            $balances = [];
            foreach ($sums->filter(fn ($row) => (int) ($row->branch_id ?? $main) === (int) $branch->id) as $row) {
                $balances[$row->chart_of_account_id] = ($balances[$row->chart_of_account_id] ?? 0)
                    + LedgerAmount::units($row->debit) - LedgerAmount::units($row->credit);
            }
            if ((int) $branch->id === (int) $main) {
                foreach ($accounts as $account) {
                    $opening = LedgerAmount::units($account->opening_balance ?? 0);
                    $balances[$account->id] = ($balances[$account->id] ?? 0) + ($account->isDebitNormal() ? $opening : -$opening);
                }
            }
            $lines = [];
            $net = 0;
            foreach (array_filter($balances) as $accountId => $units) {
                $lines[] = ['chart_of_account_id' => $accountId, 'memo' => 'Year-end close',
                    $units > 0 ? 'credit' : 'debit' => LedgerAmount::decimal(abs($units))];
                $net += $units;
            }
            if ($lines === []) {
                continue;
            }
            // $net is the debit-side total of income and expense: negative means a profit, credited to equity.
            if ($net !== 0) {
                $retained = app(SemanticAccountResolver::class)->resolve('retained_earnings', $context);
                $lines[] = ['chart_of_account_id' => $retained->id, 'memo' => ($net < 0 ? 'Profit' : 'Loss').' for '.$year->name,
                    $net < 0 ? 'credit' : 'debit' => LedgerAmount::decimal(abs($net))];
            }
            $version = JournalEntry::forCompany($context)->where('reference_type', 'year_close')->where('reference_id', $year->id)
                ->where('branch_id', $branch->id)->count() + 1;
            app(AccountingPostingService::class)->postJournalEntry([
                'entry_date' => $end, 'reference_type' => 'year_close', 'reference_id' => $year->id,
                'reference_no' => $year->name, 'description' => 'Year-end close '.$year->name.': '.$reason,
                'posting_key' => 'year_close:'.$year->id.':'.$branch->id.':v'.$version,
            ], $lines, new CompanyContext($context->companyId, (int) $branch->id, $year->id));
        }
    }

    private function reverseClosingEntries(FiscalYear $year, CompanyContext $context, string $reason): void
    {
        $entries = JournalEntry::forCompany($context)->where('reference_type', 'year_close')->where('reference_id', $year->id)
            ->where('status', 'posted')->whereNotIn('id', JournalEntry::forCompany($context)->whereNotNull('reversal_of_id')->select('reversal_of_id'))
            ->orderBy('id')->get();
        foreach ($entries as $entry) {
            app(AccountingPostingService::class)->reverse($entry->id, $year->end_date->toDateString(), 'Year reopened: '.$reason,
                new CompanyContext($context->companyId, (int) $entry->branch_id, $year->id));
        }
    }
}
