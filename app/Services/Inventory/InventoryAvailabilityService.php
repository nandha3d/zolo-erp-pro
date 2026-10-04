<?php

namespace App\Services\Inventory;

use App\Models\Product;
use Illuminate\Support\Facades\DB;

/**
 * on_hand: the transactional projection (what postings lock and check).
 * ledger_on_hand: the movement history total; differs only until opening/reconciliation is clean.
 * reserved: base quantity on Pending (2) and Processing (5) sales, which have not issued stock yet.
 */
class InventoryAvailabilityService
{
    public const RESERVING_SALE_STATUSES = [2, 5];

    public function __construct(private readonly UomConversionService $uom)
    {
    }

    public function forProduct(int $productId, ?int $warehouseId = null, ?int $variantId = null, ?int $batchId = null): array
    {
        $product = Product::findOrFail($productId);
        $filter = function ($query, string $warehouse, string $variant, string $batch) use ($warehouseId, $variantId, $batchId) {
            $warehouseId !== null && $query->where($warehouse, $warehouseId);
            $variantId !== null && $query->where($variant, $variantId);
            $batchId !== null && $query->where($batch, $batchId);

            return $query;
        };

        $onHand = (float) $filter(DB::table('product_warehouse')->where('product_id', $productId),
            'warehouse_id', 'variant_id', 'product_batch_id')->sum('qty');
        $ledger = (float) $filter(DB::table('stock_movement_lines')->where('product_id', $productId),
            'warehouse_id', 'variant_id', 'batch_id')->sum('qty_base');

        $reserved = 0.0;
        $pending = $filter(DB::table('product_sales')->join('sales', 'sales.id', '=', 'product_sales.sale_id')
            ->where('product_sales.product_id', $productId)
            ->whereIn('sales.sale_status', self::RESERVING_SALE_STATUSES)
            ->whereNull('sales.deleted_at'), 'sales.warehouse_id', 'product_sales.variant_id', 'product_sales.product_batch_id')
            ->groupBy('product_sales.sale_unit_id')
            ->selectRaw('product_sales.sale_unit_id AS unit_id, SUM(product_sales.qty) AS qty')->get();
        foreach ($pending as $row) {
            $reserved += $this->uom->toBase($product, (float) $row->qty, $row->unit_id ? (int) $row->unit_id : null);
        }

        return [
            'product_id' => $productId,
            'warehouse_id' => $warehouseId,
            'variant_id' => $variantId,
            'batch_id' => $batchId,
            'on_hand' => round($onHand, 4),
            'ledger_on_hand' => round($ledger, 4),
            'reserved' => round($reserved, 4),
            'available' => round($onHand - $reserved, 4),
        ];
    }
}
