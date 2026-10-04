<?php

namespace App\Console\Commands;

use App\Services\Inventory\InventoryMovementService;
use App\Services\Inventory\StockLine;
use App\Services\Inventory\StockMovementCommand;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Seeds the ledger from current projections: one opening movement per product_warehouse row,
 * so that ledger == projections at cutover. Idempotent per row (key opening:pw:{id}).
 * Run with stock writers paused, after backfill and before any service writes to the ledger.
 */
class StockOpening extends Command
{
    protected $signature = 'erp:stock-opening
        {--date= : Opening movement date (default today)}
        {--dry-run : Report what would be recorded without writing}';

    protected $description = 'Record current warehouse stock quantities as opening stock movements';

    public function handle(InventoryMovementService $movements): int
    {
        $date = Carbon::parse($this->option('date') ?: now())->toDateString();
        $dryRun = (bool) $this->option('dry-run');
        $counts = ['recorded' => 0, 'already_recorded' => 0, 'has_history' => 0, 'failed' => 0];
        $failures = [];

        DB::table('product_warehouse')->where(fn ($q) => $q->where('qty', '>', 0.00005)->orWhere('qty', '<', -0.00005))
            ->orderBy('id')->chunkById(500, function ($rows) use ($movements, $date, $dryRun, &$counts, &$failures) {
                foreach ($rows as $row) {
                    $key = 'opening:pw:'.$row->id;
                    if (DB::table('stock_movements')->where('idempotency_key', $key)->exists()) {
                        $counts['already_recorded']++;
                        continue;
                    }
                    $variantId = (int) ($row->variant_id ?? 0) ?: null;
                    $batchId = (int) ($row->product_batch_id ?? 0) ?: null;
                    $history = DB::table('stock_movement_lines')->where('product_id', $row->product_id)
                        ->where('warehouse_id', $row->warehouse_id);
                    $variantId === null ? $history->whereNull('variant_id') : $history->where('variant_id', $variantId);
                    $batchId === null ? $history->whereNull('batch_id') : $history->where('batch_id', $batchId);
                    if ($history->exists()) {
                        $counts['has_history']++;
                        continue;
                    }
                    if ($dryRun) {
                        $counts['recorded']++;
                        continue;
                    }
                    try {
                        $movements->opening(new StockMovementCommand(
                            date: $date,
                            lines: [$this->stockLine($row, $variantId, $batchId)],
                            warehouseId: (int) $row->warehouse_id,
                            sourceType: 'product_warehouse',
                            sourceId: (int) $row->id,
                            idempotencyKey: $key,
                            reason: 'Opening stock from existing warehouse quantity',
                        ));
                        $counts['recorded']++;
                    } catch (Throwable $error) {
                        $counts['failed']++;
                        $failures[] = [$row->id, $row->product_id, $row->warehouse_id, $error->getMessage()];
                    }
                }
            });

        $this->table(['Result', 'Rows'], collect($counts)->map(fn ($n, $k) => [$k, $n])->values()->all());
        if ($dryRun) {
            $this->info('Dry run: nothing was written.');
        }
        if ($failures !== []) {
            $this->table(['product_warehouse', 'Product', 'Warehouse', 'Error'], $failures);
        }

        return $failures === [] ? self::SUCCESS : self::FAILURE;
    }

    private function stockLine(object $row, ?int $variantId, ?int $batchId): StockLine
    {
        $product = DB::table('products')->where('id', $row->product_id)->first();
        $qty = round((float) $row->qty, 4);
        $serials = StockLine::fromArray(['product_id' => $row->product_id, 'imei_number' => (string) ($row->imei_number ?? '')])->serials;
        // Serials are imported only when they account for every unit; otherwise the quantity stays anonymous.
        $serials = $qty > 0 && count($serials) === (int) $qty && abs($qty - (int) $qty) < 0.00005 ? $serials : [];

        return new StockLine(
            productId: (int) $row->product_id,
            qty: $qty,
            warehouseId: (int) $row->warehouse_id,
            variantId: $variantId,
            batchId: $batchId,
            unitCost: max(0.0, (float) ($product->cost ?? 0)),
            serials: $serials,
        );
    }
}
