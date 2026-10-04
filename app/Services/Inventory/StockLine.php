<?php

namespace App\Services\Inventory;

use InvalidArgumentException;

/**
 * One requested stock line. Quantity is in $uomId (or the product base unit when null);
 * $unitCost is per entered unit. Adjustment lines are signed; all others are positive.
 */
final readonly class StockLine
{
    /**
     * @param list<string> $serials serial numbers, one per base unit
     * @param array{batch_no:string, expired_date?:string, mfg_date?:string, mrp?:float}|null $batch find-or-create batch on receipt
     * @param array<string, mixed>|null $dimensions creates a dimensioned piece on receipt
     */
    public function __construct(
        public int $productId,
        public float $qty,
        public ?int $warehouseId = null,
        public ?int $variantId = null,
        public ?int $batchId = null,
        public ?float $unitCost = null,
        public ?int $uomId = null,
        public array $serials = [],
        public ?array $batch = null,
        public ?int $identityId = null,
        public ?array $dimensions = null,
        public array $attributes = [],
    ) {
        if ($productId < 1 || !is_finite($qty) || ($unitCost !== null && (!is_finite($unitCost) || $unitCost < 0))) {
            throw new InvalidArgumentException('Stock lines need a product, a finite quantity and a nonnegative unit cost.');
        }
    }

    /** Accepts legacy-style line arrays; imei_number may be a comma-separated list. */
    public static function fromArray(array $line): self
    {
        $serials = $line['serials'] ?? ($line['imei_number'] ?? []);
        if (is_string($serials)) {
            $serials = explode(',', $serials);
        }
        $int = fn ($key) => isset($line[$key]) && $line[$key] !== '' && (int) $line[$key] !== 0 ? (int) $line[$key] : null;

        return new self(
            productId: (int) ($line['product_id'] ?? 0),
            qty: (float) ($line['qty'] ?? 0),
            warehouseId: $int('warehouse_id'),
            variantId: $int('variant_id'),
            batchId: $int('product_batch_id') ?? $int('batch_id'),
            unitCost: isset($line['unit_cost']) ? (float) $line['unit_cost'] : null,
            uomId: $int('uom_id'),
            // Legacy forms post the literal "null" for lines without IMEI numbers.
            serials: array_values(array_filter(array_map('trim', (array) $serials), fn ($serial) => $serial !== '' && $serial !== 'null')),
            batch: $line['batch'] ?? null,
            identityId: $int('stock_identity_id'),
            dimensions: $line['dimensions'] ?? null,
            attributes: $line['attributes'] ?? [],
        );
    }
}
