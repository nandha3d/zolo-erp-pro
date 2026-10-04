<?php

namespace App\Services\Inventory;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Compares ledger totals with the four legacy projections:
 * product_warehouse (product/warehouse/variant/batch), products, product_variants and product_batches.
 */
class InventoryReconciliationService
{
    public const TOLERANCE = 0.0001;

    /** @return list<array{level: string, product_id: int, warehouse_id: ?int, variant_id: ?int, batch_id: ?int, ledger_qty: float, projection_qty: float, difference: float, has_ledger: bool}> */
    public function differences(?int $companyId = null): array
    {
        $products = $companyId === null ? null
            : DB::table('products')->where('company_id', $companyId)->pluck('id')->map(fn ($id) => (int) $id)->all();
        $scope = fn ($query, string $column) => $products === null ? $query : $query->whereIn($column, $products);
        $lines = fn () => $scope(DB::table('stock_movement_lines'), 'product_id');
        $ledgerProducts = array_flip($lines()->distinct()->pluck('product_id')->map(fn ($id) => (int) $id)->all());

        $levels = [
            'warehouse' => [
                $lines()->groupBy('product_id', 'warehouse_id', 'variant_id', 'batch_id')
                    ->selectRaw('product_id, warehouse_id, variant_id, batch_id, SUM(qty_base) AS qty')->get(),
                $scope(DB::table('product_warehouse'), 'product_id')
                    ->groupBy('product_id', 'warehouse_id', 'variant_id', 'product_batch_id')
                    ->selectRaw('product_id, warehouse_id, variant_id, product_batch_id AS batch_id, SUM(qty) AS qty')->get(),
            ],
            'product' => [
                $lines()->groupBy('product_id')->selectRaw('product_id, SUM(qty_base) AS qty')->get(),
                $scope(DB::table('products'), 'id')->selectRaw('id AS product_id, qty')->get(),
            ],
        ];
        if (Schema::hasTable('product_variants')) {
            $levels['variant'] = [
                $lines()->whereNotNull('variant_id')->groupBy('product_id', 'variant_id')
                    ->selectRaw('product_id, variant_id, SUM(qty_base) AS qty')->get(),
                $scope(DB::table('product_variants'), 'product_id')->selectRaw('product_id, variant_id, qty')->get(),
            ];
        }
        if (Schema::hasTable('product_batches')) {
            $levels['batch'] = [
                $lines()->whereNotNull('batch_id')->groupBy('product_id', 'batch_id')
                    ->selectRaw('product_id, batch_id, SUM(qty_base) AS qty')->get(),
                $scope(DB::table('product_batches'), 'product_id')->selectRaw('product_id, id AS batch_id, qty')->get(),
            ];
        }

        $differences = [];
        foreach ($levels as $level => [$ledger, $projection]) {
            $totals = [];
            foreach ([0 => $ledger, 1 => $projection] as $side => $rows) {
                foreach ($rows as $row) {
                    $key = $this->key($level, $row);
                    $totals[$key] ??= [$row, 0.0, 0.0];
                    $totals[$key][$side + 1] += (float) $row->qty;
                }
            }
            foreach ($totals as [$row, $ledgerQty, $projectionQty]) {
                if (abs($ledgerQty - $projectionQty) <= self::TOLERANCE) {
                    continue;
                }
                $productId = (int) $row->product_id;
                $differences[] = [
                    'level' => $level,
                    'product_id' => $productId,
                    'warehouse_id' => $level === 'warehouse' ? (int) $row->warehouse_id : null,
                    'variant_id' => in_array($level, ['warehouse', 'variant'], true) ? $this->id($row->variant_id ?? null) : null,
                    'batch_id' => in_array($level, ['warehouse', 'batch'], true) ? $this->id($row->batch_id ?? null) : null,
                    'ledger_qty' => round($ledgerQty, 4),
                    'projection_qty' => round($projectionQty, 4),
                    'difference' => round($ledgerQty - $projectionQty, 4),
                    'has_ledger' => isset($ledgerProducts[$productId]),
                ];
            }
        }

        return $differences;
    }

    /**
     * Moves projections to the ledger by each difference. Products without any ledger history are
     * skipped: rebuilding them would erase stock that was never recorded (run erp:stock-opening first).
     *
     * @return int number of projection rows corrected
     */
    public function rebuild(array $differences): int
    {
        return DB::transaction(function () use ($differences) {
            $fixed = 0;
            foreach ($differences as $diff) {
                if (!$diff['has_ledger']) {
                    continue;
                }
                $delta = $diff['difference'];
                match ($diff['level']) {
                    'warehouse' => $this->rebuildWarehouseRow($diff, $delta),
                    'product' => DB::table('products')->where('id', $diff['product_id'])->increment('qty', $delta),
                    'variant' => DB::table('product_variants')->where('product_id', $diff['product_id'])
                        ->where('variant_id', $diff['variant_id'])->increment('qty', $delta),
                    'batch' => DB::table('product_batches')->where('id', $diff['batch_id'])->increment('qty', $delta),
                };
                $fixed++;
            }

            return $fixed;
        });
    }

    private function rebuildWarehouseRow(array $diff, float $delta): void
    {
        $query = DB::table('product_warehouse')->where('product_id', $diff['product_id'])->where('warehouse_id', $diff['warehouse_id']);
        foreach (['variant_id' => $diff['variant_id'], 'product_batch_id' => $diff['batch_id']] as $column => $value) {
            $value === null
                ? $query->where(fn ($q) => $q->whereNull($column)->orWhere($column, 0))
                : $query->where($column, $value);
        }
        $row = $query->orderBy('id')->lockForUpdate()->first();
        if ($row) {
            DB::table('product_warehouse')->where('id', $row->id)->increment('qty', $delta);

            return;
        }
        $company = Schema::hasColumn('product_warehouse', 'company_id')
            ? ['company_id' => DB::table('stock_movement_lines')->where('product_id', $diff['product_id'])->whereNotNull('company_id')->value('company_id')]
            : [];
        DB::table('product_warehouse')->insert($company + [
            'product_id' => $diff['product_id'], 'warehouse_id' => $diff['warehouse_id'],
            'variant_id' => $diff['variant_id'], 'product_batch_id' => $diff['batch_id'],
            'qty' => $delta, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function key(string $level, object $row): string
    {
        return implode('|', match ($level) {
            'warehouse' => [$row->product_id, $row->warehouse_id, $this->id($row->variant_id), $this->id($row->batch_id)],
            'product' => [$row->product_id],
            'variant' => [$row->product_id, $this->id($row->variant_id)],
            'batch' => [$row->product_id, $this->id($row->batch_id)],
        });
    }

    /** Legacy rows use NULL or 0 for "no variant/batch". */
    private function id(mixed $value): ?int
    {
        return $value === null || (int) $value === 0 ? null : (int) $value;
    }
}
