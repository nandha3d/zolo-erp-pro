<?php

namespace App\Services\Accounting;

use App\Models\Accounting\AccountAllocation;
use App\Models\Accounting\AccountOpenItem;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\ChartOfAccount;
use App\Services\ERP\CompanyWriteGuard;
use App\Services\Platform\CompanyContext;
use App\Support\LedgerAmount;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/** Open amounts have the normal-balance sign of their AR/AP account. Allocations offset opposite signs. */
class OpenItemService
{
    public function record(JournalEntry $entry, array $header): void
    {
        foreach ($entry->items as $line) {
            $account = ChartOfAccount::where('company_id', $entry->company_id)->findOrFail($line->chart_of_account_id);
            if (!in_array($account->control_type, ['ar', 'ap'], true)) {
                continue;
            }
            $amount = LedgerAmount::units($line->debit) - LedgerAmount::units($line->credit);
            if ($account->control_type === 'ap') {
                $amount = -$amount;
            }
            (new AccountOpenItem)->forceFill([
                'company_id' => $entry->company_id, 'branch_id' => $entry->branch_id,
                'financial_year_id' => $entry->financial_year_id, 'journal_item_id' => $line->id,
                'journal_entry_id' => $entry->id, 'account_id' => $account->id,
                'party_type' => $line->partner_type, 'party_id' => $line->partner_id ?: null,
                'source_type' => $entry->reference_type, 'source_id' => $entry->reference_id,
                'document_no' => $entry->reference_no ?: $entry->entry_number,
                'document_date' => $entry->entry_date->toDateString(),
                'due_date' => $header['due_date'] ?? $entry->entry_date->toDateString(),
                'original_amount' => LedgerAmount::decimal($amount), 'open_amount' => LedgerAmount::decimal($amount),
                'reference_mode' => $header['reference_mode'] ?? 'new_reference',
            ])->save();
        }
    }

    public function allocate(int $firstId, int $secondId, mixed $amount, string $date, string $key, CompanyContext $context, ?int $journalId = null, ?int $actor = null): AccountAllocation
    {
        $guard = app(CompanyWriteGuard::class);
        $actor ??= auth()->id();
        $context = $guard->context($context, $actor);
        $units = LedgerAmount::units($amount);
        if ($units <= 0 || $key === '' || strlen($key) > 150 || $firstId === $secondId) {
            throw new InvalidArgumentException('Allocation requires two distinct items, a positive amount and a bounded key.');
        }
        return DB::transaction(function () use ($guard, $context, $firstId, $secondId, $units, $date, $key, $journalId, $actor) {
            $guard->begin($context, $date);
            $items = AccountOpenItem::forCompany($context)->where('branch_id', $context->branchId)
                ->whereIn('id', [$firstId, $secondId])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            if ($items->count() !== 2) {
                throw new InvalidArgumentException('Allocation items must belong to the selected company and branch.');
            }
            $first = $items[$firstId];
            $second = $items[$secondId];
            if ($first->account_id !== $second->account_id || $first->party_type !== $second->party_type
                || (int) $first->party_id !== (int) $second->party_id) {
                throw new InvalidArgumentException('Allocation items must have the same account and party.');
            }
            $positive = LedgerAmount::units($first->original_amount) > 0 ? $first : $second;
            $negative = $positive->id === $first->id ? $second : $first;
            if (LedgerAmount::units($positive->original_amount) <= 0 || LedgerAmount::units($negative->original_amount) >= 0) {
                throw new InvalidArgumentException('Allocation requires opposite open-item signs.');
            }
            $existing = AccountAllocation::forCompany($context)->where('idempotency_key', $key)->first();
            if ($existing) {
                if ($existing->debit_open_item_id !== $positive->id || $existing->credit_open_item_id !== $negative->id
                    || LedgerAmount::units($existing->allocated_amount) !== $units || $existing->allocation_date->toDateString() !== $date
                    || (int) $existing->journal_entry_id !== (int) $journalId || $existing->reversal_of_id) {
                    throw new InvalidArgumentException('Allocation key was already used for different effects.');
                }
                return $existing;
            }
            if ($date < $positive->document_date->toDateString() || $date < $negative->document_date->toDateString()) {
                throw new InvalidArgumentException('Allocation date cannot precede either document.');
            }
            if ($journalId !== null) {
                JournalEntry::forCompany($context)->where('branch_id', $context->branchId)->whereKey($journalId)->firstOrFail();
            }
            if ($units > LedgerAmount::units($positive->open_amount) || $units > -LedgerAmount::units($negative->open_amount)) {
                throw new InvalidArgumentException('Allocation exceeds the remaining open amount.');
            }
            $allocation = (new AccountAllocation)->forceFill([
                'company_id' => $context->companyId, 'allocation_no' => 'ALLOC-'.$positive->id.'-'.$negative->id.'-'.substr(hash('sha256', $key), 0, 12),
                'idempotency_key' => $key, 'debit_open_item_id' => $positive->id, 'credit_open_item_id' => $negative->id,
                'journal_entry_id' => $journalId, 'allocated_amount' => LedgerAmount::decimal($units),
                'allocation_date' => $date, 'created_by' => $actor,
            ]);
            $allocation->save();
            $this->change($positive, -$units);
            $this->change($negative, $units);
            if (!$journalId || JournalEntry::findOrFail($journalId)->reference_type !== 'reversal') {
                $this->syncDocument($positive);
                $this->syncDocument($negative);
            }
            return $allocation;
        });
    }

    public function reverseAllocation(int $id, string $date, string $reason, CompanyContext $context): AccountAllocation
    {
        $guard = app(CompanyWriteGuard::class);
        $context = $guard->context($context, auth()->id());
        if (trim($reason) === '') {
            throw new InvalidArgumentException('An allocation reversal reason is required.');
        }
        return DB::transaction(function () use ($guard, $context, $id, $date, $reason) {
            $guard->begin($context, $date);
            $allocation = AccountAllocation::forCompany($context)->whereKey($id)->lockForUpdate()->firstOrFail();
            if ($allocation->reversal_of_id || $date < $allocation->allocation_date->toDateString()) {
                throw new InvalidArgumentException('Invalid allocation reversal or date.');
            }
            $items = AccountOpenItem::forCompany($context)->where('branch_id', $context->branchId)
                ->whereIn('id', [$allocation->debit_open_item_id, $allocation->credit_open_item_id])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            if ($items->count() !== 2) {
                throw new InvalidArgumentException('Allocation reversal is outside the selected branch.');
            }
            $existing = AccountAllocation::forCompany($context)->where('reversal_of_id', $id)->first();
            if ($existing) {
                return $existing;
            }
            $reversal = (new AccountAllocation)->forceFill([
                'company_id' => $context->companyId, 'allocation_no' => 'ALLOC-REV-'.$id,
                'idempotency_key' => 'allocation-reversal:'.$id,
                'debit_open_item_id' => $allocation->debit_open_item_id, 'credit_open_item_id' => $allocation->credit_open_item_id,
                'journal_entry_id' => $allocation->journal_entry_id, 'allocated_amount' => $allocation->allocated_amount,
                'allocation_date' => $date, 'reversal_of_id' => $id, 'reversal_reason' => $reason, 'created_by' => auth()->id(),
            ]);
            $reversal->save();
            $units = LedgerAmount::units($allocation->allocated_amount);
            $this->change($items[$allocation->debit_open_item_id], $units);
            $this->change($items[$allocation->credit_open_item_id], -$units);
            $this->syncDocument($items[$allocation->debit_open_item_id]);
            $this->syncDocument($items[$allocation->credit_open_item_id]);
            return $reversal;
        });
    }

    public function settlePayment(JournalEntry $entry, \App\Models\Payment $payment, CompanyContext $context): void
    {
        $type = $payment->sale_id ? 'sale' : 'purchase';
        $sourceId = $payment->sale_id ?: $payment->purchase_id;
        foreach (AccountOpenItem::forCompany($context)->where('journal_entry_id', $entry->id)->get() as $paymentItem) {
            $invoice = AccountOpenItem::forCompany($context)->where('branch_id', $context->branchId)
                ->where('source_type', $type)->where('source_id', $sourceId)->where('account_id', $paymentItem->account_id)
                ->where('original_amount', '>', 0)->sole();
            $this->allocate($invoice->id, $paymentItem->id, (float) $payment->amount, $entry->entry_date->toDateString(), 'payment:'.$payment->id.':'.$paymentItem->id, $context, $entry->id, $entry->created_by);
        }
    }

    private function change(AccountOpenItem $item, int $change): void
    {
        $amount = LedgerAmount::units($item->open_amount) + $change;
        $item->forceFill(['open_amount' => LedgerAmount::decimal($amount), 'status' => $amount === 0 ? 'settled' : 'open'])->save();
    }

    private function syncDocument(AccountOpenItem $item): void
    {
        if (!in_array($item->source_type, ['sale', 'purchase'], true) || LedgerAmount::units($item->original_amount) <= 0) {
            return;
        }
        $model = $item->source_type === 'sale' ? \App\Models\Sale::class : \App\Models\Purchase::class;
        $source = $model::where('company_id', $item->company_id)->whereKey($item->source_id)->lockForUpdate()->firstOrFail();
        $paid = LedgerAmount::units((float) $source->grand_total) - LedgerAmount::units($item->open_amount);
        $source->update(['paid_amount' => LedgerAmount::decimal($paid), 'payment_status' => LedgerAmount::units($item->open_amount) === 0 ? 4 : ($paid > 0 ? 3 : 2)]);
    }
}
