<?php

namespace App\Services\Commercial;

use App\Models\Purchase;
use App\Services\ERP\CompanyWriteGuard;
use App\Services\Platform\CompanyContext;
use App\Services\Accounting\AccountingService;
use App\Services\Inventory\InventoryMovementService;
use App\Services\Inventory\StockLine;
use App\Services\Inventory\StockMovementCommand;
use Illuminate\Support\Facades\DB;

class PurchasePostingService
{
    public function post(Purchase $purchase, ?CompanyContext $context = null, ?int $actor = null): Purchase
    {
        $actor ??= auth()->id();
        $guard = app(CompanyWriteGuard::class);
        $context = $guard->context($context, $actor);
        return DB::transaction(function () use ($purchase, $context, $actor, $guard) {
            $purchase = Purchase::visibleIn($context)->lockForUpdate()->findOrFail($purchase->id);
            if ((int) $purchase->branch_id !== $context->branchId || (int) $purchase->financial_year_id !== $context->financialYearId) {
                throw new \LogicException('Legacy document posting requires a reviewed commercial migration.');
            }
            if ($purchase->reversed_at) {
                throw new \LogicException('A reversed purchase cannot be posted again.');
            }
            if ($purchase->posted_at || (int) $purchase->status === 4) {
                return $purchase;
            }
            $date = $guard->begin($context, $purchase->created_at->toDateString());
            $stock = [];
            $inventory = $transit = $expense = 0;
            // Lock all referenced products once, preserving company ownership checks for every line.
            $products = \App\Models\Product::forCompany($context)
                ->whereIn('id', $purchase->productPurchases->pluck('product_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            foreach ($purchase->productPurchases as $line) {
                if ((int) $line->company_id !== $context->companyId || !$line->qty || $line->valuation_amount === null) {
                    throw new \LogicException('Document valuation or line ownership requires review.');
                }
                $product = $products->get($line->product_id);
                if (!$product) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['product_id' => 'Select a company-owned record.']);
                }
                $value = app(CommercialPricing::class)->units((float) $line->valuation_amount);
                if (in_array($product->type, ['service', 'digital'], true)) {
                    $expense += $value;
                    continue;
                }
                $receivedValue = (int) round($value * (float) $line->recieved / (float) $line->qty);
                $inventory += $receivedValue;
                $transit += $value - $receivedValue;
                if ($line->recieved > 0) {
                    $stock[] = StockLine::fromArray([
                        'qty' => $line->recieved, 'uom_id' => $line->purchase_unit_id,
                        'unit_cost' => $receivedValue / 10000 / (float) $line->recieved,
                        'attributes' => ['commercial_line_id' => $line->id] + ($line->stock_details_json['attributes'] ?? []),
                    ] + ($line->stock_details_json ?? []) + $line->toArray());
                }
            }
            if ($stock !== []) {
                $movement = app(InventoryMovementService::class)->receive(new StockMovementCommand(
                    date: $date, lines: $stock, warehouseId: $purchase->warehouse_id,
                    sourceType: 'purchase', sourceId: $purchase->id, sourceNo: $purchase->reference_no,
                    idempotencyKey: 'purchase:'.$purchase->id, userId: $actor, context: $context,
                ));
                if (app(CommercialPricing::class)->units((float) $movement->lines->sum('value')) !== $inventory) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['landed_cost' => 'Received valuation exceeds stock ledger precision. Adjust the allocation.']);
                }
            }
            $accounts = app(PostingAccounts::class);
            $lines = [];
            $add = function (string|int $role, float $debit, float $credit, bool $party = false) use (&$lines, $accounts, $context, $purchase, $actor) {
                if (round($debit + $credit, 4) == 0) {
                    return;
                }
                $lines[] = ['chart_of_account_id' => is_int($role) ? $role : $accounts->account($role, $context, $actor),
                    'debit' => round($debit, 4), 'credit' => round($credit, 4),
                    'partner_type' => $party && $purchase->supplier_id ? 'supplier' : null,
                    'partner_id' => $party ? $purchase->supplier_id : null];
            };
            $add('inventory', $inventory / 10000, 0);
            $add('goods_in_transit', $transit / 10000, 0);
            $add('operating_expense', $expense / 10000, 0);
            if ($purchase->tax_snapshot_json) {
                foreach (['cgst', 'sgst', 'igst', 'cess'] as $component) {
                    $amount = $purchase->productPurchases->sum(fn ($line) => $line->tax_snapshot_json['input_credit_allowed'] ? ($line->tax_snapshot_json[$component] ?? 0) : 0);
                    $add('input_tax_'.$component, (float) $amount, 0);
                    if ($purchase->tax_snapshot_json['reverse_charge']) {
                        $liability = $purchase->productPurchases->sum(fn ($line) => $line->tax_snapshot_json[$component] ?? 0);
                        $blocked = $liability - $amount;
                        $add('operating_expense', (float) $blocked, 0);
                        $add('output_tax_'.$component, 0, (float) $liability);
                    }
                }
            }
            foreach ($purchase->payments()->forCompany($context)->get() as $payment) {
                $add($accounts->settlement($payment, $context, $actor), 0, (float) $payment->amount);
            }
            $add('accounts_payable', 0, (float) $purchase->grand_total - (float) $purchase->paid_amount, true);
            if ($lines !== []) {
                app(AccountingService::class)->postJournalEntry([
                    'entry_date' => $date, 'reference_type' => 'purchase', 'reference_id' => $purchase->id,
                    'reference_no' => $purchase->reference_no, 'posting_key' => 'purchase:'.$purchase->id.':v1',
                    'due_date' => $purchase->attributes_json['due_date'] ?? $date,
                    'created_by' => $actor, 'description' => 'Purchase '.$purchase->reference_no,
                ], $lines, $context);
            }
            if (config('compliance.enabled') && \Illuminate\Support\Facades\Schema::hasColumn('purchases', 'document_snapshot_json')) {
                $purchase->forceFill(['document_snapshot_json' => app(\App\Services\Documents\DocumentRenderingService::class)->capture($purchase, 'purchase', $context)]);
            }
            $purchase->forceFill(['posted_at' => now()])->save();
            app(\App\Services\Tax\GstProjectionService::class)->record($purchase, 'purchase', $purchase->productPurchases->pluck('tax_snapshot_json')->all(), $context);
            return $purchase;
        });
    }
}
