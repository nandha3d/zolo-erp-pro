<?php

namespace App\Services\Industry;

use App\Models\Inventory\StockMovement;
use App\Models\Product;
use App\Services\Accounting\AccountingPostingService;
use App\Services\Accounting\SemanticAccountResolver;
use App\Services\ERP\CompanyWriteGuard;
use App\Services\Inventory\InventoryMovementService;
use App\Services\Inventory\StockMovementCommand;
use App\Services\Inventory\UomConversionService;
use App\Services\Operations\OperationPosting;
use App\Services\Operations\OperationStock;
use App\Services\Platform\CapabilityService;
use App\Services\Platform\CompanyContext;
use App\Support\LedgerAmount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class FmcgInventoryService
{
    public function suggest(int $productId, int $warehouseId, float $qty, string $date, CompanyContext $context, int $actor): array
    {
        app(CompanyWriteGuard::class)->context($context, $actor);
        app(CapabilityService::class)->assertEnabled('inventory.batch_expiry', $context);
        app(\App\Services\Commercial\CommercialPermission::class)->assert('sales-add', $context, $actor);
        app(CompanyWriteGuard::class)->warehouse($warehouseId, $context, $actor);
        $product = app(CompanyWriteGuard::class)->owned(Product::class, $productId, $context, 'product_id');
        validator(['qty' => $qty, 'date' => $date], ['qty' => 'required|numeric|gt:0', 'date' => 'required|date_format:Y-m-d'])->validate();
        if (!$product->is_batch) throw ValidationException::withMessages(['product_id' => 'FEFO requires a batch-tracked product.']);
        $batches = DB::table('product_warehouse as w')->join('product_batches as b', 'b.id', '=', 'w.product_batch_id')
            ->where('w.company_id', $context->companyId)->where('b.company_id', $context->companyId)
            ->where('w.product_id', $productId)->where('b.product_id', $productId)->where('w.warehouse_id', $warehouseId)
            ->where('w.qty', '>', 0)->where('b.status', 'active')->where('b.expired_date', '>=', $date)
            ->whereNull('w.variant_id')->orderBy('b.expired_date')->orderBy('b.id')
            ->select('b.id', 'b.batch_no', 'b.expired_date', 'w.qty')->get();
        $result = []; $remaining = $qty;
        foreach ($batches as $batch) {
            $picked = min($remaining, (float) $batch->qty);
            if ($picked <= 0) continue;
            $result[] = ['product_batch_id' => (int) $batch->id, 'batch_no' => $batch->batch_no,
                'expired_date' => $batch->expired_date, 'qty_base' => round($picked, 4)];
            $remaining = round($remaining - $picked, 4);
            if ($remaining <= InventoryMovementService::EPSILON) break;
        }
        if ($remaining > InventoryMovementService::EPSILON) throw ValidationException::withMessages(['qty' => 'Insufficient unexpired batch stock for FEFO picking.']);
        return $result;
    }

    public function configureScheme(array $data, CompanyContext $context, int $actor): int
    {
        app(OperationPosting::class)->authorize('sales.wholesale', 'products-edit', $context, $actor);
        $data = validator($data, ['product_id' => 'required|integer|min:1', 'name' => 'required|string|max:100',
            'buy_qty' => 'required|numeric|gt:0|max:999999999', 'free_qty' => 'required|numeric|gt:0|max:999999999',
            'valid_from' => 'required|date_format:Y-m-d', 'valid_to' => 'required|date_format:Y-m-d|after_or_equal:valid_from'])->validate();
        return DB::transaction(function () use ($data, $context, $actor) {
            app(CompanyWriteGuard::class)->begin($context, null);
            app(CompanyWriteGuard::class)->owned(Product::class, $data['product_id'], $context, 'product_id');
            $id = DB::table('sales_quantity_schemes')->insertGetId($data + ['company_id' => $context->companyId,
                'created_by' => $actor, 'created_at' => now(), 'updated_at' => now()]);
            app(OperationPosting::class)->audit('quantity_scheme', $id, $context, $actor, $data);
            return $id;
        });
    }

    /** Called within the shared commercial transaction before pricing and stock posting. */
    public function prepareSale(array $items, int $warehouse, string $date, CompanyContext $context, int $actor): array
    {
        $profile = Schema::hasTable('company_industry_settings')
            ? app(IndustryProfileService::class)->settings($context)['profile'] : 'general_trading';
        $expanded = [];
        foreach ($items as $line) {
            $expanded[] = $line;
            if (!empty($line['quantity_scheme_id'])) {
                app(CapabilityService::class)->assertEnabled('sales.wholesale', $context);
                $scheme = DB::table('sales_quantity_schemes')->where('company_id', $context->companyId)->where('product_id', $line['product_id'])
                    ->where('is_active', true)->where('valid_from', '<=', $date)->where('valid_to', '>=', $date)->find($line['quantity_scheme_id']);
                if (!$scheme) throw ValidationException::withMessages(['quantity_scheme_id' => 'Select an active company product scheme.']);
                validator($line, ['qty' => 'required|numeric|gt:0'])->validate();
                $product = Product::forCompany($context)->findOrFail($line['product_id']);
                $paidBase = app(UomConversionService::class)->toBase($product, (float) $line['qty'], isset($line['sale_unit_id']) ? (int) $line['sale_unit_id'] : null);
                $free = floor($paidBase / (float) $scheme->buy_qty) * (float) $scheme->free_qty;
                if ($free > 0) {
                    $gift = array_diff_key($line, array_flip(['quantity_scheme_id', 'tax', 'total', 'discount', 'serials', 'stock_identity_id']));
                    $gift['qty'] = $free; $gift['sale_unit_id'] = $product->unit_id; $gift['net_unit_price'] = 0;
                    $gift['attributes'] = ['quantity_scheme' => (array) $scheme, 'free_qty' => $free];
                    $expanded[] = $gift;
                }
            }
        }
        if ($profile !== 'fmcg') return $expanded;
        $result = []; $allocated = [];
        foreach ($expanded as $line) {
            $product = Product::forCompany($context)->findOrFail($line['product_id']);
            if (!$product->is_batch || !empty($line['product_batch_id']) || !empty($line['batch']) || !empty($line['variant_id'])) { $result[] = $line; continue; }
            $unit = (int) ($line['sale_unit_id'] ?? $product->unit_id);
            $base = app(UomConversionService::class)->toBase($product, (float) $line['qty'], $unit);
            // Earlier paid/free lines in the same request reserve batches for this expansion.
            $already = $allocated[$product->id] ?? 0;
            $picks = $this->suggest($product->id, $warehouse, $base + $already, $date, $context, $actor);
            $skip = $already; $remainingDiscount = LedgerAmount::units($line['discount'] ?? 0);
            foreach ($picks as $pick) {
                $qty = $pick['qty_base']; $remove = min($skip, $qty); $skip -= $remove; $qty -= $remove;
                if ($qty <= 0) continue;
                $part = $line; $part['qty'] = round((float) $line['qty'] * $qty / $base, 6); $part['product_batch_id'] = $pick['product_batch_id'];
                if (abs(app(UomConversionService::class)->toBase($product, $part['qty'], $unit) - $qty) > InventoryMovementService::EPSILON) {
                    throw ValidationException::withMessages(['qty' => 'FEFO split cannot represent this unit conversion precisely; use base units.']);
                }
                unset($part['tax'], $part['total']);
                $discount = min($remainingDiscount, (int) round(LedgerAmount::units($line['discount'] ?? 0) * $qty / $base));
                $remainingDiscount -= $discount; $part['discount'] = LedgerAmount::decimal($discount);
                $part['attributes'] = ($part['attributes'] ?? []) + ['picking' => 'FEFO', 'expiry_snapshot' => $pick['expired_date']];
                $result[] = $part;
            }
            $allocated[$product->id] = $already + $base;
        }
        return $result;
    }

    public function writeOff(array $data, string $key, CompanyContext $context, int $actor): StockMovement
    {
        return app(OperationPosting::class)->run('expiry_writeoff', StockMovement::class, 'inventory.batch_expiry', 'inventory.damage_stock',
            $data, $key, $context, $actor, function ($data, $date) use ($key, $context, $actor) {
                validator($data, ['warehouse_id' => 'required|integer|min:1', 'reason' => 'required|string|max:500', 'lines' => 'required|array|min:1|max:500'])->validate();
                app(CompanyWriteGuard::class)->warehouse($data['warehouse_id'], $context, $actor);
                $lines = array_map(fn ($line) => app(OperationStock::class)->line(array_diff_key($line, array_flip(['unit_cost'])), $context), $data['lines']);
                $movement = app(InventoryMovementService::class)->writeOffExpired(new StockMovementCommand(date: $date, lines: $lines,
                    warehouseId: $data['warehouse_id'], sourceType: 'expiry_writeoff', idempotencyKey: 'expiry:'.$context->companyId.':'.$key,
                    reason: $data['reason'], context: $context, userId: $actor));
                $value = -app(OperationStock::class)->value($movement);
                if ($value) {
                    $accounts = app(SemanticAccountResolver::class);
                    app(AccountingPostingService::class)->postJournalEntry(['entry_date' => $date, 'reference_type' => 'stock_loss', 'reference_id' => $movement->id,
                        'reference_no' => $movement->movement_no, 'description' => $data['reason'], 'created_by' => $actor],
                        [['chart_of_account_id' => $accounts->resolve('damage', $context)->id, 'debit' => LedgerAmount::decimal($value)],
                            ['chart_of_account_id' => $accounts->resolve('inventory', $context)->id, 'credit' => LedgerAmount::decimal($value)]], $context);
                }
                return $movement;
            });
    }
}
