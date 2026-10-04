<?php

namespace App\Services\ERP;

use App\Models\Product;
use App\Models\Product_Warehouse;
use App\Models\Warehouse;
use App\Models\Adjustment;
use App\Models\ProductAdjustment;
use App\Models\Transfer;
use App\Models\ProductTransfer;
use App\Services\Accounting\AccountingService;
use App\Services\Inventory\InventoryMovementService;
use App\Services\Inventory\StockLine;
use App\Services\Inventory\StockMovementCommand;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use App\Services\Platform\CompanyContext;

class InventoryService
{
    protected AccountingService $accountingService;

    public function __construct(AccountingService $accountingService)
    {
        $this->accountingService = $accountingService;
    }

    /**
     * Get real-time stock levels with valuation for a warehouse or all warehouses.
     */
    public function getStockValuation(?int $warehouseId = null, ?CompanyContext $context = null): array
    {
        $query = $context === null ? Product_Warehouse::with(['product', 'warehouse']) : Product_Warehouse::visibleIn($context)->with([
            'product' => fn ($q) => $q->forCompany($context),
            'warehouse' => fn ($q) => $q->forCompany($context)->where('branch_id', $context->branchId),
        ]);
        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }

        $records = $query->get();

        $totalItems = 0;
        $totalQty = 0.0;
        $totalAssetValue = 0.0;
        $totalRetailValue = 0.0;

        $items = [];
        foreach ($records as $pw) {
            if (!$pw->product) continue;

            $qty = (float) $pw->qty;
            $cost = (float) $pw->product->cost;
            $price = (float) ($pw->price ?: $pw->product->price);

            $assetVal = $qty * $cost;
            $retailVal = $qty * $price;

            $totalItems++;
            $totalQty += $qty;
            $totalAssetValue += $assetVal;
            $totalRetailValue += $retailVal;

            $items[] = [
                'product_id' => $pw->product_id,
                'product_name' => $pw->product->name,
                'product_code' => $pw->product->code,
                'warehouse_id' => $pw->warehouse_id,
                'warehouse_name' => $pw->warehouse->name ?? 'N/A',
                'qty' => $qty,
                'unit_cost' => $cost,
                'unit_price' => $price,
                'asset_value' => round($assetVal, 2),
                'retail_value' => round($retailVal, 2),
                'potential_profit' => round($retailVal - $assetVal, 2),
            ];
        }

        return [
            'warehouse_id' => $warehouseId,
            'total_sku_count' => $totalItems,
            'total_quantity' => $totalQty,
            'total_inventory_asset_value' => round($totalAssetValue, 2),
            'total_potential_retail_value' => round($totalRetailValue, 2),
            'items' => $items,
        ];
    }

    /**
     * Transfer stock between warehouses.
     */
    public function transferStock(array $data, ?int $userId = null, ?CompanyContext $context = null): Transfer
    {
        $userId = $userId ?: auth()->id();
        $guard = app(CompanyWriteGuard::class);
        $context = $guard->context($context, $userId);

        $fromId = (int) $data['from_warehouse_id'];
        $toId = (int) $data['to_warehouse_id'];

        if ($fromId === $toId) {
            throw new InvalidArgumentException("Source and Destination warehouses must be different.");
        }

        if (empty($data['items'])) {
            throw new InvalidArgumentException("Transfer must include at least one product item.");
        }

        return DB::transaction(function () use ($data, $fromId, $toId, $userId, $context, $guard) {
            $date = $guard->begin($context, $guard->businessDate($data));
            $guard->rejectUnscopedReferences($data);
            $guard->warehouse($fromId, $context, $userId);
            $guard->warehouse($toId, $context, $userId, false);
            foreach ($data['items'] as &$line) {
                if (!is_numeric($line['qty'] ?? null) || !is_finite((float) $line['qty']) || (float) $line['qty'] <= 0
                    || !is_numeric($line['net_unit_cost'] ?? null) || !is_finite((float) $line['net_unit_cost']) || (float) $line['net_unit_cost'] < 0) {
                    throw new InvalidArgumentException('Transfer quantity must be positive and unit cost must be nonnegative.');
                }
                $guard->product($line, $context, 'purchase_unit_id');
            }
            unset($line);
            $numbers = app(\App\Services\Platform\DocumentNumberService::class);
            $reservation = $numbers->reserve('transfer', $context, $date, $userId);
            $referenceNo = $reservation->formatted_number;

            $itemCount = 0;
            $totalQty = 0.0;
            $totalCost = 0.0;

            foreach ($data['items'] as $item) {
                $qty = (float) $item['qty'];
                $unitCost = (float) $item['net_unit_cost'];
                $itemCount++;
                $totalQty += $qty;
                $totalCost += ($qty * $unitCost);
            }

            $transfer = (new Transfer)->forceFill([
                'company_id' => $context->companyId,
                'created_at' => $date,
                'reference_no' => $referenceNo,
                'user_id' => $userId,
                'status' => $data['status'] ?? 1, // 1 = Completed
                'from_warehouse_id' => $fromId,
                'to_warehouse_id' => $toId,
                'item' => $itemCount,
                'total_qty' => $totalQty,
                'total_tax' => 0,
                'total_cost' => $totalCost,
                'shipping_cost' => (float) ($data['shipping_cost'] ?? 0),
                'grand_total' => $totalCost + (float) ($data['shipping_cost'] ?? 0),
                'note' => $data['note'] ?? null,
            ]);
            $transfer->save();
            $numbers->assign($reservation, $transfer);

            foreach ($data['items'] as $item) {
                $productId = (int) $item['product_id'];
                $qty = (float) $item['qty'];

                (new ProductTransfer)->forceFill([
                    'company_id' => $context->companyId,
                    'product_batch_id' => $item['product_batch_id'],
                    'variant_id' => $item['variant_id'],
                    'transfer_id' => $transfer->id,
                    'product_id' => $productId,
                    'qty' => $qty,
                    'purchase_unit_id' => $item['purchase_unit_id'] ?? 1,
                    'net_unit_cost' => (float) $item['net_unit_cost'],
                    'tax_rate' => 0,
                    'tax' => 0,
                    'total' => $qty * (float) $item['net_unit_cost'],
                ])->save();
            }

            // A completed transfer moves stock out of the source and into the destination at unchanged cost.
            if (($data['status'] ?? 1) == 1) {
                app(InventoryMovementService::class)->transfer(new StockMovementCommand(
                    date: $date,
                    lines: array_map(fn ($item) => StockLine::fromArray(
                        ['uom_id' => $item['purchase_unit_id'] ?? null, 'unit_cost' => null] + $item,
                    ), $data['items']),
                    warehouseId: $fromId,
                    toWarehouseId: $toId,
                    sourceType: 'transfer',
                    sourceId: $transfer->id,
                    sourceNo: $referenceNo,
                    idempotencyKey: 'transfer:'.$transfer->id,
                    userId: $userId,
                    context: $context,
                ));
            }

            return $transfer->load(['fromWarehouse', 'toWarehouse', 'productTransfers']);
        });
    }
}
