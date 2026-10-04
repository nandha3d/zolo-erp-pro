<?php

namespace App\Services\Inventory;

use App\Models\Inventory\StockMovement;

/** @internal Mutable state for one movement posting inside InventoryMovementService. */
final class PostingRun
{
    public int $lineNo = 0;

    /** @var list<string> */
    public array $warnings = [];

    /** @var array<int, array{qty: float, value: float}> running ledger balance per product */
    public array $valuation = [];

    public function __construct(
        public readonly StockMovement $movement,
        public readonly ?int $companyId,
        public readonly bool $updateProjections,
        public readonly string $outStatus,
        public readonly bool $shadow = false,
        public readonly string $purpose = 'ordinary',
    ) {
    }
}
