<?php

namespace App\Services\Accounting;

use App\Models\Accounting\ChartOfAccount;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\JournalItem;
use App\Models\Accounting\AccountOpenItem;
use App\Models\Accounting\AccountAllocation;
use App\Services\ERP\CompanyWriteGuard;
use App\Services\Platform\CompanyContext;
use App\Services\Platform\DocumentNumberService;
use App\Support\LedgerAmount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

/** The only writer for posted journals, account projections and their open items. */
class AccountingPostingService
{
    public function postJournalEntry(array $header, array $items, ?CompanyContext $context = null): JournalEntry
    {
        $guard = app(CompanyWriteGuard::class);
        $actor = auth()->id() ?: ($header['created_by'] ?? null);
        $context = $guard->context($context, $actor);
        Validator::make($header, [
            'posting_key' => 'nullable|string|max:150', 'idempotency_key' => 'nullable|string|max:150',
            'description' => 'nullable|string|max:2000', 'reference_type' => 'nullable|string|max:100',
            'reference_no' => 'nullable|string|max:100', 'due_date' => 'nullable|date_format:Y-m-d|after_or_equal:entry_date',
            'cheque_no' => 'nullable|string|max:100', 'cheque_date' => 'nullable|date_format:Y-m-d',
            'reference_mode' => 'nullable|in:new_reference,against_reference,advance,on_account',
        ])->validate();
        $debits = $credits = 0;
        $lines = [];
        foreach ($items as $item) {
            Validator::make($item, ['chart_of_account_id' => 'required|integer|min:1',
                'partner_type' => 'nullable|string|max:20', 'partner_id' => 'nullable|integer|min:1',
                'memo' => 'nullable|string|max:255'])->validate();
            $debit = LedgerAmount::units($item['debit'] ?? 0);
            $credit = LedgerAmount::units($item['credit'] ?? 0);
            if ($debit < 0 || $credit < 0 || ($debit && $credit)) {
                throw new InvalidArgumentException('Journal amounts must be nonnegative and on one side per line.');
            }
            if (!$debit && !$credit) {
                continue;
            }
            $lines[] = [
                'chart_of_account_id' => (int) $item['chart_of_account_id'],
                'debit' => LedgerAmount::decimal($debit), 'credit' => LedgerAmount::decimal($credit),
                'memo' => $item['memo'] ?? null, 'partner_type' => $item['partner_type'] ?? null,
                'partner_id' => empty($item['partner_id']) ? null : (int) $item['partner_id'],
            ];
            $debits += $debit;
            $credits += $credit;
            LedgerAmount::decimal($debits);
            LedgerAmount::decimal($credits);
        }
        if ($debits !== $credits || count($lines) < 2) {
            throw new InvalidArgumentException('Double-entry unbalanced: a journal requires at least two lines with equal debit and credit totals.');
        }
        $type = $header['reference_type'] ?? 'manual';
        $key = $header['posting_key'] ?? (!empty($header['reference_id']) ? $type.':'.$header['reference_id'].':v1' : null);
        $hash = hash('sha256', json_encode([
            $context->branchId, $context->financialYearId, $header['entry_date'] ?? null,
            $type, $header['reference_id'] ?? null, $header['description'] ?? 'Journal Entry',
            $header['voucher_type'] ?? null, $header['cheque_no'] ?? null, $header['cheque_date'] ?? null,
            $header['due_date'] ?? null, $header['reference_mode'] ?? 'new_reference', $header['allocations'] ?? [], $lines,
        ], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($header, $context, $guard, $actor, $lines, $debits, $credits, $type, $key, $hash) {
            \App\Models\Company::whereKey($context->companyId)->lockForUpdate()->firstOrFail();
            $this->validateReference($header, $context, $guard, $actor);
            if ($key || !empty($header['idempotency_key'])) {
                $existing = JournalEntry::forCompany($context)->where(function ($q) use ($key, $header) {
                    if ($key) {
                        $q->where('posting_key', $key);
                    }
                    if (!empty($header['idempotency_key'])) {
                        $q->orWhere('idempotency_key', $header['idempotency_key']);
                    }
                })->get();
                if ($existing->isNotEmpty()) {
                    if ($existing->count() !== 1 || $existing->first()->request_hash !== $hash
                        || (int) $existing->first()->branch_id !== $context->branchId) {
                        throw new InvalidArgumentException('Posting key was already used for different journal effects.');
                    }
                    return $existing->first()->load('items');
                }
            }
            if (in_array($type, ['sale', 'purchase'], true)
                && JournalEntry::forCompany($context)->where('reference_type', $type)->where('reference_id', $header['reference_id'])
                    ->whereNull('posting_key')->where('status', 'posted')->exists()) {
                throw new InvalidArgumentException('Legacy source journal requires reviewed posting-key migration before reposting.');
            }
            $date = $guard->begin($context, $header['entry_date'] ?? null);
            $accounts = [];
            foreach ($lines as $line) {
                $account = $guard->owned(ChartOfAccount::class, $line['chart_of_account_id'], $context, 'items.chart_of_account_id');
                if (!$account->is_active || $account->children()->exists() || ($type === 'manual' && !$account->allow_manual_posting)) {
                    throw new InvalidArgumentException('Account is inactive or does not allow manual posting.');
                }
                $accounts[$account->id] = $account;
                if ($line['partner_id']) {
                    $model = match ($line['partner_type']) {
                        'customer' => \App\Models\Customer::class, 'supplier' => \App\Models\Supplier::class,
                        default => throw new InvalidArgumentException('Partner type requires a company-owned posting path.'),
                    };
                    $guard->owned($model, $line['partner_id'], $context, 'items.partner_id');
                } elseif ($line['partner_type']) {
                    throw new InvalidArgumentException('Journal partner type and ID must be supplied together.');
                }
                if (in_array($account->control_type, ['ar', 'ap'], true)) {
                    $expected = $account->control_type === 'ar' ? 'customer' : 'supplier';
                    if (($line['partner_type'] && $line['partner_type'] !== $expected)
                        || (in_array($type, ['manual', 'voucher'], true) && !$line['partner_id'])) {
                        throw new InvalidArgumentException('Control-account postings require their customer or supplier.');
                    }
                }
            }
            $numbers = app(DocumentNumberService::class);
            $reservation = $numbers->reserve('journal', $context, $date, $actor);
            $entry = (new JournalEntry)->forceFill([
                'company_id' => $context->companyId, 'branch_id' => $context->branchId,
                'financial_year_id' => $context->financialYearId, 'document_series_id' => $reservation->series_id,
                'entry_number' => $reservation->formatted_number, 'entry_date' => $date,
                'reference_type' => $type, 'reference_id' => $header['reference_id'] ?? null,
                'reference_no' => $header['reference_no'] ?? null, 'description' => $header['description'] ?? 'Journal Entry',
                'status' => 'draft', 'total_debit' => LedgerAmount::decimal($debits), 'total_credit' => LedgerAmount::decimal($credits),
                'created_by' => $actor, 'posting_key' => $key, 'idempotency_key' => $header['idempotency_key'] ?? null,
                'request_hash' => $hash, 'reversal_of_id' => $header['reversal_of_id'] ?? null,
                'void_reason' => $header['void_reason'] ?? null, 'voucher_type' => $header['voucher_type'] ?? null,
                'cheque_no' => $header['cheque_no'] ?? null, 'cheque_date' => $header['cheque_date'] ?? null,
            ]);
            $entry->save();
            $numbers->assign($reservation, $entry);
            foreach ($lines as $line) {
                (new JournalItem)->forceFill($line + ['company_id' => $context->companyId, 'journal_entry_id' => $entry->id])->save();
                $account = $accounts[$line['chart_of_account_id']];
                $change = LedgerAmount::units($line['debit']) - LedgerAmount::units($line['credit']);
                if (!$account->isDebitNormal()) {
                    $change = -$change;
                }
                $account->current_balance = LedgerAmount::decimal(LedgerAmount::units($account->current_balance) + $change);
                $account->save();
            }
            $entry->forceFill(['status' => 'posted', 'posted_at' => now()])->save();
            app(OpenItemService::class)->record($entry->load('items'), $header);
            if ($type === 'payment') {
                app(OpenItemService::class)->settlePayment($entry, \App\Models\Payment::forCompany($context)->findOrFail($header['reference_id']), $context);
            }
            return $entry;
        });
    }

    private function validateReference(array $header, CompanyContext $context, CompanyWriteGuard $guard, int $actor): void
    {
        $type = $header['reference_type'] ?? 'manual';
        if (in_array($type, ['manual', 'voucher'], true)) {
            if (!empty($header['reference_id']) || !empty($header['reversal_of_id'])) {
                throw new InvalidArgumentException('Manual vouchers cannot impersonate source documents.');
            }
            return;
        }
        $model = match ($type) {
            'sale' => \App\Models\Sale::class, 'purchase' => \App\Models\Purchase::class,
            'payment' => \App\Models\Payment::class, 'expense' => \App\Models\Expense::class,
            'reversal' => JournalEntry::class,
            'sale_credit_note', 'sale_debit_note' => \App\Models\Returns::class,
            'purchase_credit_note', 'purchase_debit_note' => \App\Models\ReturnPurchase::class,
            'damage' => \App\Models\DamageStock::class,
            default => throw new InvalidArgumentException('This source requires its company-owned accounting path.'),
        };
        if (empty($header['reference_id'])) {
            throw new InvalidArgumentException('A source document ID is required.');
        }
        if (in_array($type, ['sale', 'purchase', 'payment'], true)) {
            $model::visibleIn($context)->whereKey($header['reference_id'])->lockForUpdate()->firstOrFail();
        } else {
            $source = $guard->owned($model, $header['reference_id'], $context, 'reference_id');
            if (in_array($type, ['sale_credit_note', 'sale_debit_note', 'purchase_credit_note', 'purchase_debit_note', 'damage'], true)
                && ((int) $source->branch_id !== $context->branchId || (int) $source->financial_year_id !== $context->financialYearId)) {
                throw new InvalidArgumentException('Adjustment source must belong to the posting branch and financial year.');
            }
            if ($type === 'expense' && $source->warehouse_id) {
                $guard->warehouse($source->warehouse_id, $context, $actor);
            }
            if ($type === 'reversal' && ((int) $source->branch_id !== $context->branchId || $source->status !== 'posted'
                || $source->reversal_of_id || (int) ($header['reversal_of_id'] ?? 0) !== $source->id)) {
                throw new InvalidArgumentException('Reversal must identify a posted original journal in this branch.');
            }
        }
    }

    public function reverse(int $entryId, string $date, string $reason, CompanyContext $context): JournalEntry
    {
        $guard = app(CompanyWriteGuard::class);
        $context = $guard->context($context, auth()->id());
        AccountingAccess::assert($context, 'accounting.journal.reverse');
        if (trim($reason) === '' || strlen($reason) > 500) {
            throw new InvalidArgumentException('A reversal reason of at most 500 characters is required.');
        }
        return DB::transaction(function () use ($guard, $context, $entryId, $date, $reason) {
            $guard->begin($context, $date);
            $original = JournalEntry::withOwnedItems($context)->where('branch_id', $context->branchId)->whereKey($entryId)->lockForUpdate()->firstOrFail();
            if ($original->status !== 'posted' || $original->reversal_of_id || $date < $original->entry_date->toDateString()) {
                throw new InvalidArgumentException('Only original posted journals can be reversed on or after their posting date.');
            }
            $existing = JournalEntry::forCompany($context)->where('reversal_of_id', $entryId)->first();
            if ($existing) {
                return $existing;
            }
            if ($original->items->count() !== JournalItem::where('journal_entry_id', $entryId)->count()) {
                throw new InvalidArgumentException('Journal contains corrupt or foreign lines; review it before reversal.');
            }
            $openItems = app(OpenItemService::class);
            $ids = AccountOpenItem::forCompany($context)->where('journal_entry_id', $entryId)->pluck('id');
            $allocations = AccountAllocation::forCompany($context)->whereNull('reversal_of_id')->where(function ($q) use ($ids) {
                $q->whereIn('debit_open_item_id', $ids)->orWhereIn('credit_open_item_id', $ids);
            })->orderBy('id')->get();
            foreach ($allocations as $allocation) {
                $openItems->reverseAllocation($allocation->id, $date, $reason, $context);
            }
            $reversal = $this->postJournalEntry([
                'entry_date' => $date, 'reference_type' => 'reversal', 'reference_id' => $entryId,
                'reference_no' => $original->entry_number, 'description' => 'Reversal: '.$reason,
                'posting_key' => 'reversal:'.$entryId.':v1', 'reversal_of_id' => $entryId, 'void_reason' => $reason,
            ], $original->items->map(fn ($line) => [
                'chart_of_account_id' => $line->chart_of_account_id, 'debit' => $line->credit, 'credit' => $line->debit,
                'memo' => $line->memo, 'partner_type' => $line->partner_type, 'partner_id' => $line->partner_id,
            ])->all(), $context);
            foreach (AccountOpenItem::forCompany($context)->where('journal_entry_id', $reversal->id)->get() as $reversedItem) {
                $source = AccountOpenItem::forCompany($context)->where('journal_entry_id', $entryId)
                    ->where('account_id', $reversedItem->account_id)->where('party_type', $reversedItem->party_type)
                    ->where('party_id', $reversedItem->party_id)->where('open_amount', $this->opposite($reversedItem->open_amount))->firstOrFail();
                $openItems->allocate($source->id, $reversedItem->id, LedgerAmount::decimal(abs(LedgerAmount::units($reversedItem->open_amount))),
                    $date, 'journal-reversal:'.$reversedItem->id, $context, $reversal->id);
            }
            return $reversal;
        });
    }

    private function opposite(string $amount): string
    {
        return LedgerAmount::decimal(-LedgerAmount::units($amount));
    }
}
