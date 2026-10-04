<?php

namespace App\Services\Accounting;

use App\Models\Accounting\ChartOfAccount;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\AccountOpenItem;
use App\Services\ERP\CompanyWriteGuard;
use App\Services\Platform\CompanyContext;
use App\Support\LedgerAmount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class VoucherService
{
    public function post(array $data, CompanyContext $context): JournalEntry
    {
        $guard = app(CompanyWriteGuard::class);
        $context = $guard->context($context, auth()->id());
        AccountingAccess::assert($context);
        $data = Validator::make($data, [
            'voucher_type' => 'required|in:contra,payment,receipt,journal',
            'entry_date' => 'required|date_format:Y-m-d', 'description' => 'required|string|max:2000',
            'idempotency_key' => 'required|string|max:100',
            'reference_mode' => 'required|in:new_reference,against_reference,advance,on_account',
            'due_date' => 'nullable|date_format:Y-m-d|after_or_equal:entry_date',
            'cheque_no' => 'nullable|string|max:100', 'cheque_date' => 'nullable|date_format:Y-m-d|required_with:cheque_no',
            'items' => 'required|array|min:2|max:100', 'items.*.chart_of_account_id' => 'required|integer|min:1',
            'items.*.debit' => 'nullable|numeric|min:0', 'items.*.credit' => 'nullable|numeric|min:0',
            'items.*.partner_type' => 'nullable|in:customer,supplier', 'items.*.partner_id' => 'nullable|integer|min:1',
            'items.*.memo' => 'nullable|string|max:255',
            'allocations' => 'nullable|array|max:100|required_if:reference_mode,against_reference',
            'allocations.*.open_item_id' => 'required|integer|min:1', 'allocations.*.line_index' => 'required|integer|min:0',
            'allocations.*.amount' => 'required|numeric|gt:0',
        ])->validate();
        return DB::transaction(function () use ($context, $guard, $data) {
            $guard->begin($context, $data['entry_date']);
            $cashDebits = $cashCredits = 0;
            $settlementIds = [];
            foreach ($data['items'] as $line) {
                $account = $guard->owned(ChartOfAccount::class, $line['chart_of_account_id'], $context, 'items.chart_of_account_id');
                $settlement = in_array($account->control_type, ['cash', 'bank'], true);
                $control = in_array($account->control_type, ['ar', 'ap'], true);
                if (!$account->allow_manual_posting && !$control) {
                    throw new \InvalidArgumentException('Account does not allow voucher posting.');
                }
                if ($data['voucher_type'] === 'contra' && !$settlement) {
                    throw new \InvalidArgumentException('Contra vouchers transfer between cash and bank accounts.');
                }
                if ($settlement) {
                    $settlementIds[$account->id] = true;
                    $cashDebits += LedgerAmount::units($line['debit'] ?? 0);
                    $cashCredits += LedgerAmount::units($line['credit'] ?? 0);
                }
            }
            if ($data['voucher_type'] === 'contra' && count($settlementIds) < 2) {
                throw new \InvalidArgumentException('Contra requires two distinct cash or bank accounts.');
            }
            if (($data['voucher_type'] === 'payment' && ($cashCredits <= 0 || $cashDebits > 0))
                || ($data['voucher_type'] === 'receipt' && ($cashDebits <= 0 || $cashCredits > 0))) {
                throw new \InvalidArgumentException('Payment credits cash/bank; Receipt debits cash/bank.');
            }
            if ($data['reference_mode'] !== 'against_reference' && !empty($data['allocations'])) {
                throw new \InvalidArgumentException('Allocations require Against Reference mode.');
            }
            $entry = app(AccountingPostingService::class)->postJournalEntry([
                'reference_type' => 'voucher', 'posting_key' => 'voucher:'.$data['idempotency_key'],
                'idempotency_key' => 'voucher:'.$data['idempotency_key'],
                'entry_date' => $data['entry_date'], 'description' => $data['description'],
                'voucher_type' => $data['voucher_type'], 'reference_mode' => $data['reference_mode'],
                'due_date' => $data['due_date'] ?? null, 'cheque_no' => $data['cheque_no'] ?? null,
                'cheque_date' => $data['cheque_date'] ?? null, 'allocations' => $data['allocations'] ?? [],
            ], $data['items'], $context);
            // Line indexes are preserved: zero lines are refused in vouchers to prevent allocation ambiguity.
            if ($entry->items->count() !== count($data['items'])) {
                throw new \InvalidArgumentException('Every voucher line must have a nonzero amount.');
            }
            foreach ($data['allocations'] ?? [] as $index => $allocation) {
                $line = $entry->items->values()->get($allocation['line_index']);
                $item = $line ? AccountOpenItem::forCompany($context)->where('journal_item_id', $line->id)->first() : null;
                if (!$item) {
                    throw new \InvalidArgumentException('Allocation line must identify an AR/AP open item.');
                }
                app(OpenItemService::class)->allocate($allocation['open_item_id'], $item->id, $allocation['amount'],
                    $data['entry_date'], 'voucher:'.$entry->id.':allocation:'.$index, $context, $entry->id);
            }
            return $entry->load('items');
        });
    }
}
