<?php

namespace App\Services\Accounting;

use App\Models\Accounting\AccountOpenItem;
use App\Models\Accounting\CollectionEvent;
use App\Models\Accounting\CollectionRecord;
use App\Models\Accounting\JournalEntry;
use App\Services\ERP\CompanyWriteGuard;
use App\Services\Platform\CompanyContext;
use App\Support\LedgerAmount;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/** Operational collection planning only. All financial effects remain with the posted voucher owner. */
class CollectionService
{
    public function save(array $input, CompanyContext $context, ?int $id = null): CollectionRecord
    {
        $guard = app(CompanyWriteGuard::class);
        $context = $guard->context($context, auth()->id());
        AccountingAccess::assert($context);
        $data = Validator::make($input, [
            'open_item_id' => 'required|integer|min:1', 'kind' => 'required|in:follow_up,pdc,reminder',
            'status' => 'required|in:planned,completed,cancelled,presented,cleared,bounced',
            'action_date' => 'required|date_format:Y-m-d', 'notes' => 'required|string|max:2000',
            'amount' => 'nullable|numeric|gt:0|max:9999999999', 'cheque_no' => 'nullable|string|max:100',
            'bank_name' => 'nullable|string|max:150', 'receipt_entry_id' => 'nullable|integer|min:1',
            'version' => 'nullable|integer|min:1', 'idempotency_key' => 'required|string|max:100',
        ])->validate();
        if (isset($data['amount'])) {
            try {
                if (!preg_match('/^\d+(?:\.\d{1,4})?$/D', (string) $data['amount']) || LedgerAmount::units($data['amount']) <= 0) {
                    throw new \InvalidArgumentException('Unsupported amount precision.');
                }
            }
            catch (\InvalidArgumentException $error) {
                throw ValidationException::withMessages(['amount' => 'Enter a decimal amount with at most four decimal places.']);
            }
        }
        if ($data['kind'] === 'reminder' && !in_array($data['status'], ['planned', 'cancelled'], true)) {
            throw ValidationException::withMessages(['status' => 'Reminders can only be planned or cancelled. No messages are sent.']);
        }
        if ($data['kind'] === 'follow_up' && !in_array($data['status'], ['planned', 'completed', 'cancelled'], true)) {
            throw ValidationException::withMessages(['status' => 'Choose planned, completed or cancelled for follow-up.']);
        }
        if ($data['kind'] === 'pdc' && (empty($data['cheque_no']) || empty($data['bank_name']) || empty($data['amount'])
            || !in_array($data['status'], ['planned', 'presented', 'cleared', 'bounced', 'cancelled'], true))) {
            throw ValidationException::withMessages(['cheque_no' => 'PDC requires cheque number, bank, positive amount and a PDC status.']);
        }
        if ($data['kind'] !== 'pdc' && (!empty($data['receipt_entry_id']) || !empty($data['amount']) || !empty($data['cheque_no']) || !empty($data['bank_name']))) {
            throw ValidationException::withMessages(['kind' => 'Cheque and receipt fields are only available for PDC records.']);
        }
        foreach (['amount', 'cheque_no', 'bank_name', 'receipt_entry_id', 'version'] as $field) { $data[$field] ??= null; }
        if ($data['amount'] !== null) { $data['amount'] = LedgerAmount::decimal(LedgerAmount::units($data['amount'])); }
        $hash = hash('sha256', json_encode([$id, $data], JSON_THROW_ON_ERROR));
        return DB::transaction(function () use ($context, $guard, $id, $data, $hash) {
            // The same company lock as voucher posting prevents receipt links racing financial reversals.
            \App\Models\Company::whereKey($context->companyId)->lockForUpdate()->firstOrFail();
            $guard->owned(\App\Models\CompanyBranch::class, $context->branchId, $context, 'branch_id');
            $item = AccountOpenItem::forCompany($context)->where('branch_id', $context->branchId)
                ->where('party_type', 'customer')->whereKey($data['open_item_id'])->lockForUpdate()->first();
            if (!$item || !$item->party_id || LedgerAmount::units($item->original_amount) <= 0) {
                throw ValidationException::withMessages(['open_item_id' => 'Select a customer debit item in this company and branch.']);
            }
            $guard->owned(\App\Models\Customer::class, $item->party_id, $context, 'party_id');
            $record = $id ? CollectionRecord::forCompany($context)->where('branch_id', $context->branchId)->lockForUpdate()->findOrFail($id) : new CollectionRecord;
            $retry = CollectionEvent::forCompany($context)->where('idempotency_key', $data['idempotency_key'])->first();
            if ($retry) {
                if ($retry->payload_hash !== $hash) { throw ValidationException::withMessages(['idempotency_key' => 'Retry key already identifies different collection data.']); }
                return CollectionRecord::forCompany($context)->where('branch_id', $context->branchId)->findOrFail($retry->collection_record_id);
            }
            if ($id && ((int) $data['version'] !== (int) $record->version || (int) $record->open_item_id !== $item->id || $record->kind !== $data['kind'])) {
                throw ValidationException::withMessages(['version' => 'Collection changed. Reload before updating the same source and kind.']);
            }
            if ($data['status'] === 'cleared') {
                $receipt = JournalEntry::forCompany($context)->where('branch_id', $context->branchId)->where('status', 'posted')
                    ->where('voucher_type', 'receipt')->whereKey($data['receipt_entry_id'])->first();
                if (!$receipt || $receipt->entry_date->toDateString() < $data['action_date']
                    || JournalEntry::forCompany($context)->where('reversal_of_id', $receipt->id)->exists()) {
                    throw ValidationException::withMessages(['receipt_entry_id' => 'Link an unreversed posted receipt dated on or after the cheque date in this branch.']);
                }
                $receiptAmount = 0;
                foreach (AccountOpenItem::forCompany($context)->where('branch_id', $context->branchId)->where('journal_entry_id', $receipt->id)
                    ->where('party_type', 'customer')->where('party_id', $item->party_id)->where('account_id', $item->account_id)->get() as $receiptItem) {
                    $receiptAmount -= LedgerAmount::units($receiptItem->original_amount);
                }
                $linkedAmount = 0;
                foreach (CollectionRecord::forCompany($context)->where('branch_id', $context->branchId)->where('status', 'cleared')
                    ->whereIn('open_item_id', AccountOpenItem::forCompany($context)->where('branch_id', $context->branchId)
                        ->where('party_type', 'customer')->where('party_id', $item->party_id)->where('account_id', $item->account_id)->select('id'))
                    ->where('receipt_entry_id', $receipt->id)->when($id, fn ($q) => $q->where('id', '!=', $id))->get() as $linked) {
                    $linkedAmount += LedgerAmount::units($linked->amount);
                }
                if (LedgerAmount::units($data['amount']) + $linkedAmount > $receiptAmount) {
                    throw ValidationException::withMessages(['receipt_entry_id' => 'Cleared PDC amounts exceed the same-party receipt amount.']);
                }
            } elseif (!empty($data['receipt_entry_id'])) {
                throw ValidationException::withMessages(['receipt_entry_id' => 'Only cleared PDC records may link a receipt.']);
            }
            $record->forceFill(collect($data)->except(['idempotency_key', 'version'])->all());
            if (!$id) { $record->forceFill(['company_id' => $context->companyId, 'branch_id' => $context->branchId, 'created_by' => auth()->id(), 'version' => 1]); }
            else { $record->version++; }
            $record->save();
            (new CollectionEvent)->forceFill(['company_id' => $context->companyId, 'collection_record_id' => $record->id,
                'version' => $record->version, 'actor_id' => auth()->id(), 'idempotency_key' => $data['idempotency_key'],
                'payload_hash' => $hash, 'snapshot' => $record->toArray(), 'created_at' => now()])->save();
            return $record;
        });
    }

    public function preview(array $input, CompanyContext $context): array
    {
        $context = app(CompanyWriteGuard::class)->context($context, auth()->id());
        $data = Validator::make($input, [
            'open_item_id' => 'required|integer|min:1', 'as_of_date' => 'required|date_format:Y-m-d',
            'interest_rate' => ['required', 'numeric', 'min:0', 'max:100', 'regex:/^\d{1,3}(?:\.\d{1,4})?$/D'], 'day_basis' => 'required|in:360,365,366',
            'discount_rate' => ['required', 'numeric', 'min:0', 'max:100', 'regex:/^\d{1,3}(?:\.\d{1,4})?$/D'], 'discount_eligible_until' => 'required|date_format:Y-m-d',
        ])->validate();
        $item = AccountOpenItem::forCompany($context)->where('branch_id', $context->branchId)->where('party_type', 'customer')->findOrFail($data['open_item_id']);
        $report = app(FinancialReportService::class)->ageing($context, 'customer', $data['as_of_date'], ['party_id' => $item->party_id]);
        $row = collect($report['rows'])->first(fn ($row) => $row['item']->id === $item->id);
        $principal = max(0, LedgerAmount::units($row['open_amount'] ?? '0'));
        $days = max(0, (int) CarbonImmutable::parse($item->due_date)->diffInDays(CarbonImmutable::parse($data['as_of_date']), false));
        $eligible = $data['as_of_date'] <= $data['discount_eligible_until'] && $data['as_of_date'] >= $item->document_date->toDateString();
        // Brick Math is already installed by the framework; preserve ledger units even at the decimal(18,4) limit.
        $interest = \Brick\Math\BigDecimal::of($principal)->multipliedBy((string) $data['interest_rate'])->multipliedBy($days)
            ->dividedBy(100 * (int) $data['day_basis'], 0, \Brick\Math\RoundingMode::HALF_UP);
        $discount = $eligible ? \Brick\Math\BigDecimal::of($principal)->multipliedBy((string) $data['discount_rate'])
            ->dividedBy(100, 0, \Brick\Math\RoundingMode::HALF_UP) : \Brick\Math\BigDecimal::zero();
        if ($interest->compareTo('999999999999999999') > 0 || $discount->compareTo('999999999999999999') > 0) {
            throw ValidationException::withMessages(['interest_rate' => 'Preview exceeds ledger precision. Use a smaller rate or date range.']);
        }
        return ['open_item_id' => $item->id, 'as_of_date' => $data['as_of_date'], 'principal' => LedgerAmount::decimal($principal),
            'days' => $days, 'day_basis' => (int) $data['day_basis'], 'interest_rate' => $data['interest_rate'],
            'interest_preview' => LedgerAmount::decimal($interest->toInt()),
            'discount_rate' => $data['discount_rate'], 'discount_eligible_until' => $data['discount_eligible_until'], 'discount_eligible' => $eligible,
            'discount_preview' => LedgerAmount::decimal($discount->toInt()),
            'effects' => 'Preview only. No journal, payment, allocation or notification is created.'];
    }
}
