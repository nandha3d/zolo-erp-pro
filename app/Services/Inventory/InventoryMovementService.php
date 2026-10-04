<?php

namespace App\Services\Inventory;

use App\Models\Inventory\StockDimension;
use App\Models\Inventory\StockIdentity;
use App\Models\Inventory\StockMovement;
use App\Models\Inventory\StockMovementLine;
use App\Models\Product;
use App\Models\Product_Warehouse;
use App\Services\Platform\CompanyContext;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

/**
 * The only writer of stock history (doc 09). Each call runs in one transaction that locks the
 * affected products (sorted, so concurrent postings serialize without deadlock), validates
 * batch/serial/piece identities and the negative-stock policy, persists cost/value at posting
 * time and updates the legacy quantity projections.
 */
class InventoryMovementService
{
    public const EPSILON = 0.00005;

    private array $columns = [];

    public function __construct(
        private readonly UomConversionService $uom,
        private readonly InventoryPolicy $policy,
    ) {
    }

    public function receive(StockMovementCommand $command): StockMovement
    {
        return $this->post('receipt', $command);
    }

    public function issue(StockMovementCommand $command): StockMovement
    {
        return $this->post('issue', $command);
    }

    public function transfer(StockMovementCommand $command): StockMovement
    {
        return $this->post('transfer', $command);
    }

    /** Line quantities are signed: positive found/added, negative lost/removed. */
    public function adjust(StockMovementCommand $command): StockMovement
    {
        return $this->post('adjustment', $command);
    }

    /** Records signed quantities that already exist in the projections; projections are not changed. */
    public function opening(StockMovementCommand $command): StockMovement
    {
        return $this->post('opening', $command);
    }

    /** Posts the exact negation of a movement at its original cost and marks it reversed. */
    public function reverse(StockMovement|int $movement, string $reason, ?int $userId = null, ?string $date = null): StockMovement
    {
        if (trim($reason) === '') {
            throw new InvalidArgumentException('A stock reversal needs a reason.');
        }

        return DB::transaction(function () use ($movement, $reason, $userId, $date) {
            $original = StockMovement::whereKey($movement instanceof StockMovement ? $movement->id : $movement)
                ->lockForUpdate()->firstOrFail();
            if ($original->movement_type === 'reversal') {
                throw new InvalidArgumentException('A reversal cannot itself be reversed; post a new movement instead.');
            }
            if ($original->status !== 'posted') {
                throw new InvalidArgumentException("Stock movement {$original->movement_no} is already reversed.");
            }
            $lines = $original->lines()->get();
            $products = $this->lockProducts($lines->pluck('product_id')->all(), $original->company_id);

            $reversal = $this->createHeader('reversal', [
                'company_id' => $original->company_id,
                'branch_id' => $original->branch_id,
                'financial_year_id' => $original->financial_year_id,
                'movement_date' => Carbon::parse($date ?? now())->toDateString(),
                'source_type' => $original->source_type,
                'source_id' => $original->source_id,
                'source_no' => $original->source_no,
                'warehouse_from_id' => $original->warehouse_to_id,
                'warehouse_to_id' => $original->warehouse_from_id,
                'reversal_of_id' => $original->id,
                'idempotency_key' => 'reversal:'.$original->id,
                'reason' => $reason,
                'created_by' => $userId,
            ]);
            // An opening only described existing projections, so its reversal does not change them either.
            $run = new PostingRun($reversal, $original->company_id, $original->movement_type !== 'opening', 'removed');

            // Remove stock before restoring it so identities never appear in two places.
            foreach ($lines->sortBy(fn ($line) => [(float) $line->qty_base > 0 ? 0 : 1, $line->line_no]) as $line) {
                $this->postLine($run, $products[$line->product_id], [
                    'warehouse_id' => (int) $line->warehouse_id,
                    'qty' => -(float) $line->qty_base,
                    'unit_cost' => (float) $line->unit_cost,
                    'variant_id' => $line->variant_id,
                    'batch_id' => $line->batch_id,
                    'identity' => $line->stock_identity_id ? StockIdentity::find($line->stock_identity_id) : null,
                    'uom_id' => $line->uom_id,
                    'uom_qty' => $line->uom_qty === null ? null : -(float) $line->uom_qty,
                    'attributes' => $line->attributes_json ?? [],
                ]);
            }
            $original->status = 'reversed';
            $original->save();

            return $this->finish($run);
        });
    }

    private function post(string $type, StockMovementCommand $command): StockMovement
    {
        return DB::transaction(function () use ($type, $command) {
            if ($command->idempotencyKey !== null && ($existing = $this->replay($type, $command->idempotencyKey))) {
                return $existing;
            }
            $from = $command->warehouseId;
            $warehouseIds = [];
            if ($type === 'transfer') {
                if ($from === null || $command->toWarehouseId === null || $from === $command->toWarehouseId) {
                    throw new InvalidArgumentException('A transfer needs different source and destination warehouses.');
                }
                $warehouseIds = [$from, $command->toWarehouseId];
            }
            foreach ($command->lines as $line) {
                $warehouseId = $line->warehouseId ?? $from;
                if ($warehouseId === null || ($type === 'transfer' && $warehouseId !== $from)) {
                    throw new InvalidArgumentException('Every stock line needs a warehouse; transfer lines use the transfer source.');
                }
                $warehouseIds[] = $warehouseId;
            }
            $companyId = $this->companyFor($command->context, array_values(array_unique($warehouseIds)));
            $products = $this->lockProducts(array_map(fn (StockLine $line) => $line->productId, $command->lines), $companyId);

            $movement = $this->createHeader($type, [
                'company_id' => $companyId,
                'branch_id' => $command->context?->branchId,
                'financial_year_id' => $command->context?->financialYearId,
                'movement_date' => Carbon::parse($command->date)->toDateString(),
                'source_type' => $command->sourceType,
                'source_id' => $command->sourceId,
                'source_no' => $command->sourceNo,
                'warehouse_from_id' => in_array($type, ['issue', 'transfer'], true) ? $from : null,
                'warehouse_to_id' => $type === 'transfer' ? $command->toWarehouseId : ($type === 'issue' ? null : $from),
                'idempotency_key' => $command->idempotencyKey,
                'reason' => $command->reason,
                'created_by' => $command->userId,
            ]);
            $run = new PostingRun($movement, $companyId, $type !== 'opening', $type === 'issue' ? StockIdentity::ISSUED : 'removed');

            foreach ($command->lines as $line) {
                $product = $products[$line->productId];
                foreach ($this->expand($type, $command, $line, $product, $run) as $posting) {
                    $this->postLine($run, $product, $posting);
                }
            }

            return $this->finish($run);
        });
    }

    private function replay(string $type, string $key): ?StockMovement
    {
        $existing = StockMovement::where('idempotency_key', $key)->first();
        if ($existing && $existing->movement_type !== $type) {
            throw new InvalidArgumentException("Idempotency key {$key} was already used for a {$existing->movement_type} movement.");
        }

        return $existing?->load('lines');
    }

    private function createHeader(string $type, array $attributes): StockMovement
    {
        $movement = StockMovement::create($attributes + [
            'movement_type' => $type,
            'status' => 'posted',
            'posted_at' => now(),
        ]);
        // Internal ledger reference; commercial documents keep their own series numbers.
        $movement->movement_no = sprintf('SM-%08d', $movement->id);
        $movement->save();

        return $movement;
    }

    private function finish(PostingRun $run): StockMovement
    {
        if ($run->warnings !== []) {
            $run->movement->warnings_json = $run->warnings;
            $run->movement->save();
        }

        return $run->movement->load('lines');
    }

    /** @return iterable<array<string, mixed>> postings in execution order */
    private function expand(string $type, StockMovementCommand $command, StockLine $line, Product $product, PostingRun $run): iterable
    {
        $qty = $this->uom->toBase($product, $line->qty, $line->uomId);
        // Openings mirror legacy projections, which may already be negative.
        $signed = $type === 'adjustment' || $type === 'opening';
        if (abs($qty) <= self::EPSILON || (!$signed && $qty < 0)) {
            throw new InvalidArgumentException("Stock quantity for product {$product->id} must be ".($signed ? 'nonzero.' : 'positive.'));
        }
        $direction = $type === 'issue' || ($signed && $qty < 0) ? -1 : 1;
        $qty = abs($qty);
        $outbound = $type === 'transfer' || $direction < 0;
        // Entered cost is per entered unit; the ledger stores cost per base unit.
        $unitCost = $line->unitCost === null ? null : round($line->unitCost * abs($line->qty) / $qty, 6);
        $warehouseId = $line->warehouseId ?? $command->warehouseId;

        $this->assertVariant($product, $line->variantId, $type);
        $batchId = $this->resolveBatch($type, $product, $line, $outbound, $run);

        foreach ($this->identityChunks($type, $product, $line, $qty, $outbound, $batchId, $run) as [$chunk, $identity]) {
            $uomQty = $line->uomId === null ? null : abs($line->qty) * $chunk / $qty;
            $shared = [
                'variant_id' => $line->variantId,
                'batch_id' => $batchId,
                'identity' => $identity,
                'uom_id' => $line->uomId,
                'attributes' => $line->attributes,
            ];
            if ($type === 'transfer') {
                $cost = $this->averageCost($run, $product);
                yield ['warehouse_id' => $command->warehouseId, 'qty' => -$chunk, 'uom_qty' => $uomQty === null ? null : -$uomQty, 'unit_cost' => $cost] + $shared;
                yield ['warehouse_id' => $command->toWarehouseId, 'qty' => $chunk, 'uom_qty' => $uomQty, 'unit_cost' => $cost] + $shared;
            } else {
                yield [
                    'warehouse_id' => $warehouseId,
                    'qty' => $direction * $chunk,
                    'uom_qty' => $uomQty === null ? null : $direction * $uomQty,
                    'unit_cost' => $direction > 0 ? $unitCost : null,
                ] + $shared;
            }
        }
    }

    private function postLine(PostingRun $run, Product $product, array $posting): void
    {
        $qty = round((float) $posting['qty'], 4);
        $cost = $posting['unit_cost'] ?? $this->averageCost($run, $product);
        $run->valuation[$product->id] ??= $this->ledgerBalance($product->id);

        if ($posting['identity'] !== null) {
            $this->moveIdentity($run, $posting['identity'], (int) $posting['warehouse_id'], $qty);
        }
        if ($run->updateProjections) {
            $this->applyProjections($run, $product, $posting, $qty);
        }
        $value = round($qty * $cost, 4);
        StockMovementLine::create([
            'stock_movement_id' => $run->movement->id,
            'company_id' => $run->companyId,
            'line_no' => ++$run->lineNo,
            'product_id' => $product->id,
            'variant_id' => $posting['variant_id'],
            'warehouse_id' => $posting['warehouse_id'],
            'uom_id' => $posting['uom_id'],
            'uom_qty' => $posting['uom_qty'] === null ? null : round($posting['uom_qty'], 4),
            'qty_base' => $qty,
            'unit_cost' => $cost,
            'value' => $value,
            'batch_id' => $posting['batch_id'],
            'stock_identity_id' => $posting['identity']?->id,
            'attributes_json' => $posting['attributes'] ?: null,
        ]);
        $run->valuation[$product->id]['qty'] += $qty;
        $run->valuation[$product->id]['value'] += $value;
    }

    /** Weighted average of the product's ledger value, or the product master cost when there is no positive balance. */
    private function averageCost(PostingRun $run, Product $product): float
    {
        if ($this->policy->valuation($run->companyId) === 'standard') {
            return round((float) $product->cost, 6);
        }
        $balance = $run->valuation[$product->id] ??= $this->ledgerBalance($product->id);

        return $balance['qty'] > self::EPSILON && $balance['value'] > 0
            ? round($balance['value'] / $balance['qty'], 6)
            : round((float) $product->cost, 6);
    }

    private function ledgerBalance(int $productId): array
    {
        $row = DB::table('stock_movement_lines')->where('product_id', $productId)
            ->selectRaw('COALESCE(SUM(qty_base), 0) AS qty, COALESCE(SUM(value), 0) AS value')->first();

        return ['qty' => (float) $row->qty, 'value' => (float) $row->value];
    }

    private function applyProjections(PostingRun $run, Product $product, array $posting, float $qty): void
    {
        $warehouseId = (int) $posting['warehouse_id'];
        $row = $this->warehouseRow($run, $product->id, $warehouseId, $posting['variant_id'], $posting['batch_id']);
        $after = (float) $row->qty + $qty;
        if ($qty < 0 && $after < -self::EPSILON) {
            $message = sprintf('Insufficient stock for product %d in warehouse %d: %s available, %s requested.',
                $product->id, $warehouseId, round((float) $row->qty, 4) + 0, -$qty);
            match ($this->policy->negativeStock($run->companyId)) {
                'block' => throw new StockPolicyException($message),
                'warn' => $run->warnings[] = $message,
                default => null,
            };
        }
        $row->qty = $after;
        $row->save();

        DB::table('products')->where('id', $product->id)->increment('qty', $qty);
        if ($posting['variant_id'] !== null) {
            DB::table('product_variants')->where('product_id', $product->id)
                ->where('variant_id', $posting['variant_id'])->increment('qty', $qty);
        }
        if ($posting['batch_id'] !== null) {
            DB::table('product_batches')->where('id', $posting['batch_id'])->increment('qty', $qty);
        }
    }

    private function warehouseRow(PostingRun $run, int $productId, int $warehouseId, ?int $variantId, ?int $batchId): Product_Warehouse
    {
        $query = Product_Warehouse::where('product_id', $productId)->where('warehouse_id', $warehouseId);
        foreach (['variant_id' => $variantId, 'product_batch_id' => $batchId] as $column => $value) {
            $value === null
                ? $query->where(fn ($q) => $q->whereNull($column)->orWhere($column, 0))
                : $query->where($column, $value);
        }
        // Ambiguous or foreign projection rows must be reconciled before any posting touches them.
        $rows = $query->orderBy('id')->lockForUpdate()->get();
        $foreign = $run->companyId !== null && $this->hasColumn('product_warehouse', 'company_id')
            && $rows->contains(fn ($row) => (int) $row->company_id !== $run->companyId);
        if ($rows->count() > 1 || $foreign) {
            throw new StockPolicyException("Stock ownership or identity of product {$productId} in warehouse {$warehouseId} requires reconciliation.");
        }
        if ($row = $rows->first()) {
            return $row;
        }
        $row = (new Product_Warehouse)->forceFill([
            'product_id' => $productId, 'warehouse_id' => $warehouseId,
            'variant_id' => $variantId, 'product_batch_id' => $batchId, 'qty' => 0,
        ]);
        if ($this->hasColumn('product_warehouse', 'company_id')) {
            $row->company_id = $run->companyId;
        }

        return $row;
    }

    private function assertVariant(Product $product, ?int $variantId, string $type): void
    {
        if ($variantId === null) {
            if ($product->is_variant && $type !== 'opening') {
                throw new StockPolicyException("Product {$product->id} has variants; specify the variant.");
            }

            return;
        }
        if (!DB::table('product_variants')->where('product_id', $product->id)->where('variant_id', $variantId)->exists()) {
            throw new InvalidArgumentException("Variant {$variantId} does not belong to product {$product->id}.");
        }
    }

    private function resolveBatch(string $type, Product $product, StockLine $line, bool $outbound, PostingRun $run): ?int
    {
        if ($line->batchId !== null) {
            $batch = DB::table('product_batches')->where('id', $line->batchId)->first();
        } elseif ($line->batch !== null) {
            $batchNo = trim((string) ($line->batch['batch_no'] ?? ''));
            if ($batchNo === '') {
                throw new InvalidArgumentException("A batch for product {$product->id} needs a batch number.");
            }
            $batch = DB::table('product_batches')->where('product_id', $product->id)->where('batch_no', $batchNo)->first();
            if (!$batch && !$outbound) {
                $batch = $this->createBatch($product, $batchNo, $line->batch, $run);
            }
            if (!$batch) {
                throw new StockPolicyException("Batch {$batchNo} of product {$product->id} does not exist.");
            }
        } else {
            if ($product->is_batch && $type !== 'opening') {
                throw new StockPolicyException("Product {$product->id} is batch-tracked; specify the batch.");
            }

            return null;
        }
        if (!$batch || (int) $batch->product_id !== $product->id) {
            throw new InvalidArgumentException("Batch {$line->batchId} does not belong to product {$product->id}.");
        }
        $date = $run->movement->movement_date->toDateString();
        if ($type === 'issue' && $batch->expired_date !== null && substr((string) $batch->expired_date, 0, 10) < $date) {
            $message = "Batch {$batch->batch_no} of product {$product->id} expired on ".substr((string) $batch->expired_date, 0, 10).'.';
            match ($this->policy->expiredBatch($run->companyId)) {
                'block' => throw new StockPolicyException($message),
                'warn' => $run->warnings[] = $message,
                default => null,
            };
        }

        return (int) $batch->id;
    }

    private function createBatch(Product $product, string $batchNo, array $data, PostingRun $run): object
    {
        if (empty($data['expired_date']) || strtotime($data['expired_date']) === false) {
            throw new InvalidArgumentException("New batch {$batchNo} needs an expiry date.");
        }
        $id = DB::table('product_batches')->insertGetId([
            'product_id' => $product->id,
            'batch_no' => $batchNo,
            'expired_date' => Carbon::parse($data['expired_date'])->toDateString(),
            'mfg_date' => empty($data['mfg_date']) ? null : Carbon::parse($data['mfg_date'])->toDateString(),
            'mrp' => isset($data['mrp']) ? (float) $data['mrp'] : null,
            'company_id' => $run->companyId,
            'qty' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('product_batches')->where('id', $id)->first();
    }

    /** @return list<array{0: float, 1: ?StockIdentity}> */
    private function identityChunks(string $type, Product $product, StockLine $line, float $qty, bool $outbound, ?int $batchId, PostingRun $run): array
    {
        if ($line->serials !== []) {
            $serials = array_values(array_unique($line->serials));
            if (count($serials) !== count($line->serials)) {
                throw new StockPolicyException("Duplicate serial numbers for product {$product->id}.");
            }
            if (abs($qty - count($serials)) > self::EPSILON) {
                throw new StockPolicyException("Product {$product->id} needs exactly {$qty} serial numbers; ".count($serials).' given.');
            }

            return array_map(function (string $serial) use ($product, $outbound, $batchId, $run) {
                $identity = StockIdentity::where('product_id', $product->id)->where('identity_type', StockIdentity::SERIAL)
                    ->where('identity_no', $serial)->lockForUpdate()->first();
                if (!$identity && $outbound) {
                    throw new StockPolicyException("Serial {$serial} of product {$product->id} is not in stock.");
                }

                return [1.0, $identity ?? StockIdentity::create([
                    'company_id' => $run->companyId, 'product_id' => $product->id,
                    'identity_type' => StockIdentity::SERIAL, 'identity_no' => $serial,
                    'batch_id' => $batchId, 'status' => 'new',
                ])];
            }, $serials);
        }
        if ($line->identityId !== null) {
            $identity = StockIdentity::where('product_id', $product->id)->lockForUpdate()->find($line->identityId);
            if (!$identity) {
                throw new InvalidArgumentException("Stock identity {$line->identityId} does not belong to product {$product->id}.");
            }
            $whole = $identity->identity_type === StockIdentity::SERIAL ? 1.0 : $this->identityBalance($identity->id);
            if (($type === 'transfer' || $identity->identity_type === StockIdentity::SERIAL) && abs($qty - $whole) > self::EPSILON) {
                throw new StockPolicyException("Stock identity {$identity->identity_no} must be moved whole ({$whole}).");
            }

            return [[$qty, $identity]];
        }
        if ($line->dimensions !== null) {
            if ($outbound) {
                throw new InvalidArgumentException('Dimensions describe a new piece on receipt; move existing pieces by stock_identity_id.');
            }

            return [[$qty, $this->createPiece($product, $line->dimensions, $batchId, $run)]];
        }
        if ($product->is_imei && $type !== 'opening') {
            throw new StockPolicyException("Product {$product->id} is serial-tracked; provide its serial numbers.");
        }

        return [[$qty, null]];
    }

    private function createPiece(Product $product, array $dimensions, ?int $batchId, PostingRun $run): StockIdentity
    {
        $number = trim((string) ($dimensions['identity_no'] ?? ''))
            ?: sprintf('%s-%d', $run->movement->movement_no, $run->lineNo + 1);
        if (StockIdentity::where('product_id', $product->id)->where('identity_type', StockIdentity::PIECE)->where('identity_no', $number)->exists()) {
            throw new StockPolicyException("Piece {$number} of product {$product->id} already exists.");
        }
        $identity = StockIdentity::create([
            'company_id' => $run->companyId, 'product_id' => $product->id,
            'identity_type' => StockIdentity::PIECE, 'identity_no' => $number,
            'batch_id' => $batchId, 'status' => 'new',
        ]);
        $size = fn ($key) => isset($dimensions[$key]) && is_numeric($dimensions[$key]) ? (float) $dimensions[$key] : null;
        $pieces = max(1, (int) ($dimensions['pieces'] ?? 1));
        [$length, $width, $thickness] = [$size('length'), $size('width'), $size('thickness')];
        StockDimension::create([
            'stock_identity_id' => $identity->id,
            'length' => $length, 'width' => $width, 'thickness' => $thickness,
            'dimension_uom' => $dimensions['dimension_uom'] ?? null,
            'pieces' => $pieces,
            'computed_volume' => $length !== null && $width !== null && $thickness !== null
                ? round($length * $width * $thickness * $pieces, 6) : null,
            'volume_uom' => $dimensions['volume_uom'] ?? null,
            'grade' => $dimensions['grade'] ?? null,
        ]);

        return $identity;
    }

    /** One location per identity: outbound requires it in stock here; inbound requires it not in stock elsewhere. */
    private function moveIdentity(PostingRun $run, StockIdentity $identity, int $warehouseId, float $qty): void
    {
        $identity = StockIdentity::lockForUpdate()->findOrFail($identity->id);
        $label = ucfirst($identity->identity_type).' '.$identity->identity_no;
        $inStockHere = $identity->status === StockIdentity::IN_STOCK && (int) $identity->warehouse_id === $warehouseId;
        if ($qty < 0) {
            if (!$inStockHere) {
                throw new StockPolicyException("{$label} is not in stock in warehouse {$warehouseId}.");
            }
            $remaining = $identity->identity_type === StockIdentity::SERIAL ? 1.0 : $this->identityBalance($identity->id);
            if (-$qty > $remaining + self::EPSILON) {
                throw new StockPolicyException("{$label} has only {$remaining} remaining.");
            }
            if ($remaining + $qty <= self::EPSILON) {
                $identity->status = $run->outStatus;
                $identity->warehouse_id = null;
            }
        } else {
            if ($identity->status === StockIdentity::IN_STOCK && ($identity->identity_type === StockIdentity::SERIAL || !$inStockHere)) {
                throw new StockPolicyException("{$label} is already in stock in warehouse {$identity->warehouse_id}.");
            }
            $identity->status = StockIdentity::IN_STOCK;
            $identity->warehouse_id = $warehouseId;
        }
        $identity->last_movement_id = $run->movement->id;
        $identity->save();
    }

    private function identityBalance(int $identityId): float
    {
        return (float) DB::table('stock_movement_lines')->where('stock_identity_id', $identityId)->sum('qty_base');
    }

    /** @return array<int, Product> locked products keyed by ID */
    private function lockProducts(array $ids, ?int $companyId): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        sort($ids);
        $products = Product::whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        if ($products->count() !== count($ids)) {
            throw new InvalidArgumentException('Unknown product in stock movement.');
        }
        if ($companyId !== null && $this->hasColumn('products', 'company_id')
            && $products->contains(fn ($product) => $product->company_id !== null && (int) $product->company_id !== $companyId)) {
            throw new InvalidArgumentException('A product does not belong to the stock movement company.');
        }

        return $products->all();
    }

    private function companyFor(?CompanyContext $context, array $warehouseIds): ?int
    {
        $warehouses = DB::table('warehouses')->whereIn('id', $warehouseIds)->get();
        if ($warehouses->count() !== count($warehouseIds)) {
            throw new InvalidArgumentException('Unknown warehouse in stock movement.');
        }
        $owners = $this->hasColumn('warehouses', 'company_id')
            ? $warehouses->pluck('company_id')->filter(fn ($id) => $id !== null)->map(fn ($id) => (int) $id)->unique()->values()
            : collect();
        if ($context !== null) {
            if ($owners->contains(fn ($id) => $id !== $context->companyId)) {
                throw new InvalidArgumentException('A warehouse does not belong to the active company.');
            }

            return $context->companyId;
        }
        if ($owners->count() > 1) {
            throw new InvalidArgumentException('Stock movement warehouses belong to different companies.');
        }

        return $owners->first();
    }

    private function hasColumn(string $table, string $column): bool
    {
        return $this->columns[$table.'.'.$column] ??= Schema::hasColumn($table, $column);
    }
}
