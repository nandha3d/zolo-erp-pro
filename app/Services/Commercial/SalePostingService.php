<?php

namespace App\Services\Commercial;

use App\Models\Sale;
use App\Services\ERP\CompanyWriteGuard;
use App\Services\Platform\CompanyContext;
use App\Services\Accounting\AccountingService;
use App\Services\Inventory\InventoryMovementService;
use App\Services\Inventory\StockLine;
use App\Services\Inventory\StockMovementCommand;
use Illuminate\Support\Facades\DB;

/** Effects for an already persisted sale. The application owns document creation and pricing. */
class SalePostingService
{
    public function post(Sale $sale, ?CompanyContext $context = null, ?int $actor = null): Sale
    {
        $actor ??= auth()->id();
        $guard = app(CompanyWriteGuard::class);
        $context = $guard->context($context, $actor);
        return DB::transaction(function () use ($sale, $context, $actor, $guard) {
            $sale = Sale::visibleIn($context)->lockForUpdate()->findOrFail($sale->id);
            if ((int) $sale->branch_id !== $context->branchId || (int) $sale->financial_year_id !== $context->financialYearId) {
                throw new \LogicException('Legacy document posting requires a reviewed commercial migration.');
            }
            if ($sale->reversed_at) {
                throw new \LogicException('A reversed sale cannot be posted again.');
            }
            if ($sale->posted_at || (int) $sale->sale_status !== 1) {
                return $sale;
            }
            $date = $guard->begin($context, $sale->created_at->toDateString());
            $stock = [];
            // Lock all referenced products once, preserving company ownership checks for every line.
            $products = \App\Models\Product::forCompany($context)
                ->whereIn('id', $sale->productSales->pluck('product_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            foreach ($sale->productSales as $line) {
                if ((int) $line->company_id !== $context->companyId) {
                    throw new \LogicException('A document line belongs to another company.');
                }
                $product = $products->get($line->product_id);
                if (!$product) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['product_id' => 'Select a company-owned record.']);
                }
                if (!in_array($product->type, ['service', 'digital'], true)) {
                    $stock[] = StockLine::fromArray(['uom_id' => $line->sale_unit_id] + ($line->stock_details_json ?? []) + $line->toArray());
                }
            }
            $cost = 0;
            if ($stock !== []) {
                $movement = app(InventoryMovementService::class)->issue(new StockMovementCommand(
                    date: $date, lines: $stock, warehouseId: $sale->warehouse_id,
                    sourceType: 'sale', sourceId: $sale->id, sourceNo: $sale->reference_no,
                    idempotencyKey: 'sale:'.$sale->id, userId: $actor, context: $context,
                ));
                $cost = -(float) $movement->lines->sum('value');
            }
            $accounts = app(PostingAccounts::class);
            $lines = [];
            $add = function (string|int $role, float $debit, float $credit, bool $party = false) use (&$lines, $accounts, $context, $sale) {
                if (round($debit + $credit, 4) == 0) {
                    return;
                }
                $lines[] = ['chart_of_account_id' => is_int($role) ? $role : $accounts->account($role, $context),
                    'debit' => round($debit, 4), 'credit' => round($credit, 4),
                    'partner_type' => $party ? 'customer' : null, 'partner_id' => $party ? $sale->customer_id : null];
            };
            foreach ($sale->payments()->forCompany($context)->get() as $payment) {
                $add($accounts->settlement($payment, $context), (float) $payment->amount, 0);
            }
            $add('accounts_receivable', (float) $sale->grand_total - (float) $sale->paid_amount, 0, true);
            $add('sales_discount', (float) $sale->order_discount, 0);
            $add('sales_revenue', 0, (float) $sale->total_price - (float) $sale->total_tax);
            $add('tax_payable', 0, (float) $sale->total_tax + (float) $sale->order_tax);
            $add('shipping_income', 0, (float) $sale->shipping_cost);
            $add('cogs', $cost, 0);
            $add('inventory', 0, $cost);
            if ($lines !== []) {
                app(AccountingService::class)->postJournalEntry([
                    'entry_date' => $date, 'reference_type' => 'sale', 'reference_id' => $sale->id,
                    'reference_no' => $sale->reference_no, 'posting_key' => 'sale:'.$sale->id.':v1',
                    'due_date' => $sale->attributes_json['due_date'] ?? $date, 'created_by' => $actor,
                    'description' => 'Sale '.$sale->reference_no,
                ], $lines, $context);
            }
            $sale->forceFill(['posted_at' => now()])->save();
            return $sale;
        });
    }
}
