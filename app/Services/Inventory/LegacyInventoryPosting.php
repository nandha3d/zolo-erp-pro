<?php

namespace App\Services\Inventory;

use App\Models\Inventory\StockMovement;
use App\Models\Product;
use App\Services\Platform\CompanyContext;
use Illuminate\Database\Eloquent\Model;

/** Document attribution and pre-cutover compatibility for the remaining legacy stock writers. */
class LegacyInventoryPosting
{
    private bool $historicalReversal = false;

    public function __construct(private readonly InventoryMovementService $movements)
    {
    }

    /** @param list<StockLine> $lines */
    public function post(Model $document, string $type, array $lines, int $warehouseId, ?int $toWarehouseId = null): ?StockMovement
    {
        if ($lines === []) {
            return null;
        }

        return $this->movements->{$type}(new StockMovementCommand(
            date: ($document->created_at ?? now())->toDateString(),
            lines: $lines,
            warehouseId: $warehouseId,
            toWarehouseId: $toWarehouseId,
            sourceType: 'legacy:'.$document->getTable().($this->historicalReversal ? ':historical-reversal' : ''),
            sourceId: (int) $document->getKey(),
            sourceNo: $document->reference_no,
            userId: auth()->id() ?? $document->user_id,
            context: app()->bound('request') ? request()->attributes->get(CompanyContext::class) : null,
        ));
    }

    /**
     * Reverse applied movements at their original quantities, identities and cost. Historical documents
     * without applied movements use their persisted lines; opening/shadow history is never reversed.
     * The caller holds the document lock inside the transaction containing its update/delete.
     */
    public function reverse(Model $document, callable $historicalReversal): void
    {
        $query = StockMovement::where('source_type', 'legacy:'.$document->getTable())
            ->where('source_id', $document->getKey())->where('projection_mode', 'applied')
            ->where('movement_type', '!=', 'reversal');
        $history = $query->orderByDesc('id')->lockForUpdate()->get();
        $context = app()->bound('request') ? request()->attributes->get(CompanyContext::class) : null;
        if ($context !== null && $history->contains(fn ($movement) => (int) $movement->company_id !== $context->companyId)) {
            throw new StockPolicyException('The stock document does not belong to the active company.');
        }
        if ($history->isEmpty()) {
            $this->historicalReversal = true;
            try {
                $historicalReversal();
            } finally {
                $this->historicalReversal = false;
            }
            return;
        }
        foreach ($history->where('status', 'posted') as $movement) {
            $this->movements->reverse($movement, 'Legacy document updated or deleted', auth()->id());
        }
    }

    /**
     * Stock lines for received purchase lines. Quantity is what was physically received, in the purchase unit;
     * cost is the entered net unit cost. Lines received at zero (ordered/pending) have no stock effect.
     *
     * @param iterable<Model> $lines ProductPurchase rows
     * @return list<StockLine>
     */
    public function purchaseLines(iterable $lines): array
    {
        $stock = [];
        foreach ($lines as $line) {
            if ((float) $line->recieved <= 0) {
                continue;
            }
            $stock[] = StockLine::fromArray([
                'product_id' => $line->product_id, 'qty' => $line->recieved, 'variant_id' => $line->variant_id,
                'product_batch_id' => $line->product_batch_id, 'imei_number' => $line->imei_number,
                'uom_id' => $line->purchase_unit_id, 'unit_cost' => $line->net_unit_cost,
            ]);
        }

        return $stock;
    }

    /**
     * Stock lines for sale-like rows (sales, sale returns): combos expand to components, services and
     * digital goods have no stock.
     *
     * @param iterable<Model> $lines Product_Sale or ProductReturn rows
     * @return list<StockLine>
     */
    public function documentSaleLines(iterable $lines): array
    {
        $stock = [];
        foreach ($lines as $line) {
            array_push($stock, ...$this->saleLines($line));
        }

        return $stock;
    }

    /**
     * Stock lines for purchase return rows, in the purchase unit.
     *
     * @param iterable<Model> $lines PurchaseProductReturn rows
     * @return list<StockLine>
     */
    public function purchaseReturnLines(iterable $lines): array
    {
        $stock = [];
        foreach ($lines as $line) {
            if ((int) $line->purchase_unit_id === 0) {
                continue; // 'n/a' unit: a service line without stock
            }
            $stock[] = StockLine::fromArray([
                'product_id' => $line->product_id, 'qty' => $line->qty, 'variant_id' => $line->variant_id,
                'product_batch_id' => $line->product_batch_id, 'imei_number' => $line->imei_number,
                'uom_id' => $line->purchase_unit_id,
            ]);
        }

        return $stock;
    }

    /** Expand a combo at packing time. Subsequent reversals use the recorded components. */
    public function saleLines(Model $line): array
    {
        $product = Product::findOrFail($line->product_id);
        if ($product->type === 'service' || $product->type === 'digital') {
            return [];
        }
        if ($product->type !== 'combo') {
            return [StockLine::fromArray([
                'product_id' => $product->id, 'qty' => $line->qty,
                'variant_id' => $line->variant_id, 'product_batch_id' => $line->product_batch_id,
                'imei_number' => $line->imei_number, 'uom_id' => $line->sale_unit_id,
            ])];
        }
        $variants = explode(',', (string) $product->variant_list);
        $quantities = explode(',', (string) $product->qty_list);
        $lines = [];
        foreach (explode(',', $product->product_list) as $index => $productId) {
            $lines[] = StockLine::fromArray([
                'product_id' => $productId, 'qty' => $line->qty * $quantities[$index],
                'variant_id' => $variants[$index] ?? null,
            ]);
        }

        return $lines;
    }
}
