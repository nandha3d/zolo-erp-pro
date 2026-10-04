<?php

namespace App\Services\Inventory;

use App\Models\Product;
use App\Models\Product_Warehouse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

/**
 * Phase 4b shadow mode (doc 09). A legacy writer runs unchanged inside record(); every Eloquent change to
 * product_warehouse quantity or serials is captured, discarded again if its transaction or savepoint rolls
 * back, and recorded on success as one shadow movement. Shadow movements leave the projections alone, so
 * the legacy writes stay authoritative and erp:stock-reconcile shows any writer the capture misses.
 */
class LegacyStockShadow
{
    /**
     * Routed legacy stock writers (LEGACY_STOCK_SHADOW_AUDIT.md) => document model whose first created
     * instance identifies the movement source. Move a method out of this map at its authoritative cutover.
     */
    public const WRITERS = [
        'App\Http\Controllers\SaleController' => ['Sale', ['store', 'update', 'importSale', 'deleteBySelection', 'destroy']],
        'App\Http\Controllers\PurchaseController' => ['Purchase', ['store', 'update', 'importPurchase', 'deleteBySelection', 'destroy']],
        'App\Http\Controllers\ReturnController' => ['Returns', ['store', 'update', 'deleteBySelection', 'destroy']],
        'App\Http\Controllers\ReturnPurchaseController' => ['ReturnPurchase', ['store', 'update', 'deleteBySelection', 'destroy']],
        'App\Http\Controllers\AdjustmentController' => ['Adjustment', ['store', 'update', 'deleteBySelection', 'destroy']],
        'App\Http\Controllers\TransferController' => ['Transfer', ['store', 'update', 'importTransfer', 'deleteBySelection', 'destroy', 'changeStatus']],
        'App\Http\Controllers\PackingSlipController' => ['PackingSlip', ['store', 'delete']],
        'App\Http\Controllers\ProductController' => ['Product', ['store', 'updateProduct', 'importProduct']],
        'App\Http\Controllers\DamageStockController' => ['DamageStock', ['store']],
        'App\Http\Controllers\ExchangeController' => ['Exchange', ['store']],
        'App\Http\Controllers\CafeOperationsController' => [null, ['storeRawMaterial']],
        'Modules\Manufacturing\Http\Controllers\ProductionController' => ['Modules\\Manufacturing\\Entities\\Production', ['store', 'destroy']],
    ];

    private int $paused = 0;

    /** @var array{source: string, source_id: ?int, document: ?string, model: ?Model, entries: list<array>}|null */
    private ?array $scope = null;

    private ?bool $available = null;

    public function enabled(): bool
    {
        return config('inventory.legacy_ledger_mode', 'shadow') === 'shadow'
            && ($this->available ??= Schema::hasColumn('stock_movements', 'projection_mode'));
    }

    /** Runs callback without capturing, e.g. while InventoryMovementService updates projections itself. */
    public function paused(callable $callback): mixed
    {
        $this->paused++;
        try {
            return $callback();
        } finally {
            $this->paused--;
        }
    }

    /**
     * Runs a legacy stock writer in one transaction. A returned response carrying an exception, or a thrown
     * exception, rolls back every write; otherwise the captured quantity changes are recorded before commit.
     *
     * @param class-string<Model>|null $document first model of this class created by the writer becomes the source
     */
    public function record(string $source, callable $writer, ?int $sourceId = null, ?string $document = null): mixed
    {
        if ($this->scope !== null || !$this->enabled()) {
            return $writer();
        }
        $connection = DB::connection();
        $level = $connection->transactionLevel();
        $connection->beginTransaction();
        $this->scope = ['source' => $source, 'source_id' => $sourceId, 'document' => $document, 'model' => null, 'entries' => []];
        try {
            $result = $writer();
            $after = $connection->transactionLevel();
            if (is_object($result) && ($result->exception ?? null) !== null) {
                $connection->rollBack($level);
            } elseif ($after > $level + 1) {
                // The writer left a transaction open; without this wrapper it would be discarded at request end.
                Log::warning('Legacy stock writer left a transaction open; its changes were rolled back.', ['source' => $source]);
                $connection->rollBack($level);
            } elseif ($after === $level + 1) {
                $this->flush();
                $connection->commit();
            } else {
                // The writer committed this wrapper's transaction itself; record its changes separately.
                DB::transaction(fn () => $this->flush());
            }

            return $result;
        } catch (Throwable $error) {
            if ($connection->transactionLevel() > $level) {
                $connection->rollBack($level);
            }
            throw $error;
        } finally {
            $this->scope = null;
        }
    }

    /** Model event hook for product_warehouse rows. */
    public function capture(Product_Warehouse $row, string $event): void
    {
        if ($this->scope === null || $this->paused > 0) {
            return;
        }
        $original = $row->getRawOriginal();
        $current = $row->getAttributes();
        $keys = ['product_id', 'warehouse_id', 'variant_id', 'product_batch_id'];
        if (!array_key_exists('product_id', $current) && $row->getKey() !== null) {
            // Partial models such as select('id', 'qty') keep their identity only in the database row.
            $stored = (array) DB::table('product_warehouse')->where('id', $row->getKey())->first($keys);
            $original += $stored;
            $current += $stored;
        }
        $before = $event === 'created' ? null : $this->side($original);
        $after = $event === 'deleted' ? null : $this->side($current);
        $level = DB::transactionLevel();
        if ($before !== null && $after !== null && $before['key'] === $after['key']) {
            $this->entry($level, $after['key'], $after['qty'] - $before['qty'],
                array_diff($after['serials'], $before['serials']), array_diff($before['serials'], $after['serials']));
            return;
        }
        if ($before !== null) {
            $this->entry($level, $before['key'], -$before['qty'], [], $before['serials']);
        }
        if ($after !== null) {
            $this->entry($level, $after['key'], $after['qty'], $after['serials'], []);
        }
    }

    /** Model event hook: the first created document of the expected class identifies the source. */
    public function created(Model $model): void
    {
        if ($this->scope !== null && $this->scope['model'] === null && $this->scope['document'] !== null
            && $model instanceof $this->scope['document']) {
            $this->scope['model'] = $model;
        }
    }

    /** Changes made inside a rolled-back transaction or savepoint never happened. */
    public function rolledBack(int $level): void
    {
        if ($this->scope !== null) {
            $this->scope['entries'] = array_values(array_filter($this->scope['entries'], fn ($entry) => $entry['level'] <= $level));
        }
    }

    /** A committed savepoint's changes belong to its parent transaction from now on. */
    public function committed(int $level): void
    {
        if ($this->scope !== null) {
            foreach ($this->scope['entries'] as &$entry) {
                $entry['level'] = min($entry['level'], $level);
            }
        }
    }

    private function side(array $attributes): ?array
    {
        if (empty($attributes['product_id']) || empty($attributes['warehouse_id'])) {
            return null;
        }
        $id = fn ($key) => (int) ($attributes[$key] ?? 0) ?: null;

        return [
            'key' => implode('|', [(int) $attributes['product_id'], (int) $attributes['warehouse_id'], $id('variant_id'), $id('product_batch_id')]),
            'qty' => (float) ($attributes['qty'] ?? 0),
            'serials' => StockLine::parseSerials($attributes['imei_number'] ?? ''),
        ];
    }

    private function entry(int $level, string $key, float $qty, array $added, array $removed): void
    {
        if (abs($qty) > InventoryMovementService::EPSILON || $added !== [] || $removed !== []) {
            $this->scope['entries'][] = compact('level', 'key', 'qty', 'added', 'removed');
        }
    }

    private function flush(): void
    {
        $totals = [];
        foreach ($this->scope['entries'] as $entry) {
            $total = &$totals[$entry['key']];
            $total ??= ['qty' => 0.0, 'serials' => []];
            $total['qty'] += $entry['qty'];
            foreach ($entry['added'] as $serial) {
                $total['serials'][$serial] = ($total['serials'][$serial] ?? 0) + 1;
            }
            foreach ($entry['removed'] as $serial) {
                $total['serials'][$serial] = ($total['serials'][$serial] ?? 0) - 1;
            }
            unset($total);
        }
        $totals = array_filter($totals, fn ($total) => abs($total['qty']) > InventoryMovementService::EPSILON);
        if ($totals === []) {
            return;
        }

        $inbound = min(array_column($totals, 'qty')) > 0;
        $outbound = max(array_column($totals, 'qty')) < 0;
        $warnings = [];
        $lines = [];
        foreach ($totals as $key => $total) {
            [$productId, $warehouseId, $variantId, $batchId] = array_map(fn ($part) => $part === '' ? null : (int) $part, explode('|', $key));
            $serials = array_keys(array_filter($total['serials'], fn ($net) => $total['qty'] > 0 ? $net > 0 : $net < 0));
            if ($serials !== [] && abs(abs($total['qty']) - count($serials)) > InventoryMovementService::EPSILON) {
                $warnings[] = "Serial changes of product {$productId} in warehouse {$warehouseId} do not match its quantity change.";
                $serials = [];
            }
            $lines[] = new StockLine(
                productId: $productId,
                qty: $inbound || $outbound ? abs($total['qty']) : $total['qty'],
                warehouseId: $warehouseId,
                variantId: $variantId,
                batchId: $batchId,
                // Legacy purchases and returns keep their cost on the product master.
                unitCost: $inbound ? max(0.0, (float) Product::whereKey($productId)->value('cost')) : null,
                serials: array_map('strval', $serials),
            );
        }

        $model = $this->scope['model'];
        $command = new StockMovementCommand(
            date: now()->toDateString(),
            lines: $lines,
            sourceType: 'legacy:'.$this->scope['source'],
            sourceId: $model?->getKey() ?? $this->scope['source_id'],
            sourceNo: $model?->getAttribute('reference_no'),
            idempotencyKey: 'legacy:'.Str::uuid(),
            reason: $warnings === [] ? 'Legacy writer shadow record' : implode(' ', $warnings),
            userId: auth()->id(),
            shadow: true,
        );
        $movements = app(InventoryMovementService::class);
        try {
            // A savepoint keeps a failed shadow record from undoing the legacy writer's own changes.
            DB::transaction(fn () => match (true) {
                $inbound => $movements->receive($command),
                $outbound => $movements->issue($command),
                default => $movements->adjust($command),
            });
        } catch (Throwable $error) {
            Log::warning('Legacy stock change was not recorded in the stock ledger.', [
                'source' => $this->scope['source'], 'source_id' => $command->sourceId, 'error' => $error->getMessage(),
            ]);
        }
    }
}
