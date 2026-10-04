<?php

namespace App\Services\Commercial;

use App\Models\Purchase;
use App\Models\Product;
use App\Services\ERP\CompanyWriteGuard;
use App\Services\Platform\CompanyContext;
use App\Services\Accounting\AccountingService;
use App\Services\Inventory\InventoryMovementService;
use App\Services\Inventory\StockLine;
use App\Services\Inventory\StockMovementCommand;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Later physical receipt releases goods in transit; it never recognizes AP or payment again. */
class PurchaseReceiptService
{
    public function receive(Purchase $purchase, array $data, string $key, CompanyContext $context): Purchase
    {
        $guard = app(CompanyWriteGuard::class);
        $context = $guard->context($context, auth()->id());
        app(CommercialPermission::class)->assert('purchases-edit', $context, auth()->id());
        if (trim($key) === '' || strlen($key) > 150 || empty($data['items']) || !is_array($data['items'])
            || !array_is_list($data['items']) || count($data['items']) > 500) {
            throw ValidationException::withMessages(['receipt' => 'A key and receipt lines are required.']);
        }
        $hash = hash('sha256', json_encode([$purchase->id, $context->branchId, $context->financialYearId, $data], JSON_THROW_ON_ERROR));
        return DB::transaction(function () use ($purchase, $data, $key, $hash, $guard, $context) {
            \App\Models\Company::whereKey($context->companyId)->lockForUpdate()->firstOrFail();
            $purchase = Purchase::visibleIn($context)->whereKey($purchase->id)->lockForUpdate()->firstOrFail();
            $retry = DB::table('idempotency_keys')->where('company_id', $context->companyId)->where('key', $key)->first();
            if ($retry) {
                if ($retry->request_hash !== $hash || $retry->response_type !== 'receipt' || (int) $retry->response_ref !== $purchase->id) {
                    throw ValidationException::withMessages(['idempotency_key' => 'Key already used for a different receipt.']);
                }
                return $purchase;
            }
            $date = $guard->begin($context, $guard->businessDate($data));
            if (!$purchase->posted_at || $purchase->reversed_at || $date < $purchase->created_at->toDateString()) {
                throw ValidationException::withMessages(['purchase' => 'Receipt requires an active supplier bill and a valid later date.']);
            }
            $lines = $purchase->productPurchases()->forCompany($context)->lockForUpdate()->get()->keyBy('id');
            $stock = [];
            $value = 0;
            $seen = [];
            foreach ($data['items'] as $item) {
                if (!is_array($item)) {
                    throw ValidationException::withMessages(['items' => 'Each receipt line must be an object.']);
                }
                $line = $lines[$item['line_id'] ?? 0] ?? null;
                $qty = app(CommercialPricing::class)->number($item['qty'] ?? null, 'qty', 4);
                if (!$line || isset($seen[$line->id]) || $qty <= 0 || round((float) $line->recieved + $qty, 4) > (float) $line->qty) {
                    throw ValidationException::withMessages(['items' => 'Use distinct owned lines and quantities within the unreceived balance.']);
                }
                $seen[$line->id] = true;
                $product = $guard->owned(Product::class, $line->product_id, $context, 'product_id');
                if (in_array($product->type, ['service', 'digital'], true)) {
                    throw ValidationException::withMessages(['items' => 'Service lines do not receive stock.']);
                }
                $billValue = app(CommercialPricing::class)->units((float) $line->valuation_amount);
                $before = (int) round($billValue * (float) $line->recieved / (float) $line->qty);
                $after = (int) round($billValue * ((float) $line->recieved + $qty) / (float) $line->qty);
                $receivedValue = $after - $before;
                $value += $receivedValue;
                $stock[] = StockLine::fromArray(['product_id' => $line->product_id, 'qty' => $qty,
                    'unit_cost' => $receivedValue / 10000 / $qty, 'uom_id' => $line->purchase_unit_id,
                    'attributes' => ['commercial_line_id' => $line->id] + ($item['attributes'] ?? [])] + $item);
                $line->forceFill(['recieved' => round((float) $line->recieved + $qty, 4)])->save();
            }
            $movement = app(InventoryMovementService::class)->receive(new StockMovementCommand(
                date: $date, lines: $stock, warehouseId: $purchase->warehouse_id, sourceType: 'purchase', sourceId: $purchase->id,
                sourceNo: $purchase->reference_no, idempotencyKey: 'receipt:'.hash('sha256', $key), userId: auth()->id(), context: $context,
            ));
            if (app(CommercialPricing::class)->units((float) $movement->lines->sum('value')) !== $value) {
                throw ValidationException::withMessages(['valuation' => 'Receipt cost exceeds the stock ledger precision; review the quantity and allocation.']);
            }
            if ($value > 0) {
                $accounts = app(PostingAccounts::class);
                app(AccountingService::class)->postJournalEntry(['entry_date' => $date, 'reference_type' => 'purchase', 'reference_id' => $purchase->id,
                    'reference_no' => $purchase->reference_no, 'posting_key' => 'receipt:'.$movement->id.':v1',
                    'created_by' => auth()->id(), 'description' => 'Receipt of '.$purchase->reference_no], [
                    ['chart_of_account_id' => $accounts->account('inventory', $context), 'debit' => $value / 10000, 'credit' => 0],
                    ['chart_of_account_id' => $accounts->account('goods_in_transit', $context), 'debit' => 0, 'credit' => $value / 10000],
                ], $context);
            }
            DB::table('idempotency_keys')->insert(['company_id' => $context->companyId, 'key' => $key, 'request_hash' => $hash,
                'response_type' => 'receipt', 'response_ref' => $purchase->id, 'created_at' => now(), 'updated_at' => now()]);
            app(CommercialApplicationService::class)->audit('received', $purchase, $context, auth()->id(), ['movement_id' => $movement->id, 'value' => $value / 10000]);
            return $purchase->load('productPurchases');
        });
    }
}
