<?php

namespace App\Services\Accounting;

use App\Models\Accounting\ChartOfAccount;
use App\Models\Accounting\JournalItem;
use App\Models\Accounting\AccountOpenItem;
use App\Models\Accounting\AccountAllocation;
use App\Models\Accounting\JournalEntry;
use App\Models\Company;
use App\Services\Platform\CompanyContext;
use App\Services\Platform\CompanyContextResolver;
use App\Support\LedgerAmount;
use Illuminate\Support\Facades\DB;

class LedgerReconciliationService
{
    /** Company-wide reconciliation requires company administration, independent of the selected branch. */
    public function reconcile(CompanyContext $context, bool $rebuild = false, ?int $actor = null): array
    {
        $resolver = app(CompanyContextResolver::class);
        $actor ??= auth()->id();
        $context = $resolver->forActor($context, $actor);
        if (!$resolver->canManageFinancialYears($actor, $context->companyId)) {
            throw new \Illuminate\Auth\Access\AuthorizationException('Company administrator required for ledger reconciliation.');
        }
        return DB::transaction(function () use ($context, $rebuild) {
            Company::whereKey($context->companyId)->lockForUpdate()->firstOrFail();
            $issues = [];
            $accounts = ChartOfAccount::forCompany($context)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            foreach (JournalEntry::forCompany($context)->where('status', 'posted')->cursor() as $entry) {
                $debit = $credit = 0;
                $items = JournalItem::where('journal_entry_id', $entry->id)->get();
                foreach ($items as $item) {
                    if ((int) $item->company_id !== $context->companyId || !$accounts->has($item->chart_of_account_id)) {
                        $issues[] = ['journal_entry_id' => $entry->id, 'issue' => 'Journal line ownership mismatch.'];
                        continue;
                    }
                    $d = LedgerAmount::units($item->debit);
                    $c = LedgerAmount::units($item->credit);
                    $debit += $d;
                    $credit += $c;
                    if ($d < 0 || $c < 0 || ($d && $c) || (!$d && !$c)) {
                        $issues[] = ['journal_entry_id' => $entry->id, 'issue' => 'Invalid journal line amounts.'];
                    }
                }
                if ($items->count() < 2 || $debit !== $credit || $debit !== LedgerAmount::units($entry->total_debit)
                    || $credit !== LedgerAmount::units($entry->total_credit)) {
                    $issues[] = ['journal_entry_id' => $entry->id, 'issue' => 'Journal totals do not balance or match its lines.'];
                }
            }
            // Equal AR/AP totals alone cannot detect two compensating open-amount errors.
            $openItems = AccountOpenItem::forCompany($context)->get()->keyBy('id');
            $effects = [];
            foreach (AccountAllocation::forCompany($context)->cursor() as $allocation) {
                $positive = $openItems->get($allocation->debit_open_item_id);
                $negative = $openItems->get($allocation->credit_open_item_id);
                if (!$positive || !$negative || $positive->account_id !== $negative->account_id
                    || $positive->branch_id !== $negative->branch_id || $positive->party_type !== $negative->party_type
                    || (int) $positive->party_id !== (int) $negative->party_id) {
                    $issues[] = ['allocation_id' => $allocation->id, 'issue' => 'Allocation ownership or party mismatch.'];
                    continue;
                }
                $amount = LedgerAmount::units($allocation->allocated_amount) * ($allocation->reversal_of_id ? -1 : 1);
                $effects[$positive->id] = ($effects[$positive->id] ?? 0) - $amount;
                $effects[$negative->id] = ($effects[$negative->id] ?? 0) + $amount;
            }
            foreach ($openItems as $item) {
                $line = JournalItem::forCompany($context)->where('journal_entry_id', $item->journal_entry_id)->find($item->journal_item_id);
                $account = $accounts->get($item->account_id);
                $original = $line ? LedgerAmount::units($line->debit) - LedgerAmount::units($line->credit) : null;
                if ($account?->control_type === 'ap' && $original !== null) {
                    $original = -$original;
                }
                if (!$line || !$account || !in_array($account->control_type, ['ar', 'ap'], true)
                    || (int) $line->chart_of_account_id !== (int) $item->account_id
                    || $original !== LedgerAmount::units($item->original_amount)
                    || $original + ($effects[$item->id] ?? 0) !== LedgerAmount::units($item->open_amount)) {
                    $issues[] = ['open_item_id' => $item->id, 'issue' => 'Open item differs from journal or allocation history.'];
                }
            }
            $rows = [];
            $trialDifference = 0;
            foreach ($accounts as $account) {
                $sum = 0;
                foreach (JournalItem::forCompany($context)->where('chart_of_account_id', $account->id)
                    ->whereHas('journalEntry', fn ($q) => $q->forCompany($context)->where('status', 'posted'))->cursor() as $item) {
                    $sum += LedgerAmount::units($item->debit) - LedgerAmount::units($item->credit);
                }
                $ledger = LedgerAmount::units($account->opening_balance) + ($account->isDebitNormal() ? $sum : -$sum);
                $trialDifference += $account->isDebitNormal() ? $ledger : -$ledger;
                $cached = LedgerAmount::units($account->current_balance);
                $open = null;
                if (in_array($account->control_type, ['ar', 'ap'], true)) {
                    $open = 0;
                    foreach (AccountOpenItem::forCompany($context)->where('account_id', $account->id)->cursor() as $item) {
                        $open += LedgerAmount::units($item->open_amount);
                    }
                }
                if ($rebuild && !$issues && $cached !== $ledger) {
                    $account->forceFill(['current_balance' => LedgerAmount::decimal($ledger)])->save();
                }
                $rows[] = ['account_id' => $account->id, 'code' => $account->code, 'control_type' => $account->control_type,
                    'ledger_balance' => LedgerAmount::decimal($ledger), 'cached_balance' => LedgerAmount::decimal($cached),
                    'cache_difference' => LedgerAmount::decimal($cached - $ledger),
                    'open_amount' => $open === null ? null : LedgerAmount::decimal($open),
                    'control_difference' => $open === null ? null : LedgerAmount::decimal($ledger - $open)];
            }
            return ['company_id' => $context->companyId, 'rebuilt' => $rebuild && !$issues, 'accounts' => $rows, 'integrity_issues' => $issues,
                'trial_balance_difference' => LedgerAmount::decimal($trialDifference),
                'is_reconciled' => !$issues && $trialDifference === 0 && collect($rows)->every(fn ($row) => ($rebuild || $row['cache_difference'] === '0.0000')
                    && ($row['control_difference'] === null || $row['control_difference'] === '0.0000'))];
        });
    }
}
