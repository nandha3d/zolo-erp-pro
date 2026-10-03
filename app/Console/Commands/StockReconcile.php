<?php

namespace App\Console\Commands;

use App\Services\Inventory\InventoryReconciliationService;
use Illuminate\Console\Command;

class StockReconcile extends Command
{
    protected $signature = 'erp:stock-reconcile
        {--company= : Limit to one company ID}
        {--rebuild : Move projections to the ledger for products that have ledger history}
        {--force : Rebuild without the confirmation prompt}
        {--limit=200 : Maximum difference rows to print}';

    protected $description = 'Compare the stock movement ledger with product, warehouse, variant and batch quantities';

    public function handle(InventoryReconciliationService $reconciliation): int
    {
        $company = $this->option('company') !== null ? (int) $this->option('company') : null;
        $differences = $reconciliation->differences($company);
        $this->report($differences);
        if ($differences === []) {
            return self::SUCCESS;
        }
        if (!$this->option('rebuild')) {
            return self::FAILURE;
        }

        $withoutLedger = count(array_filter($differences, fn ($diff) => !$diff['has_ledger']));
        if ($withoutLedger > 0) {
            $this->warn("{$withoutLedger} differences belong to products without ledger history and will be skipped; run erp:stock-opening first.");
        }
        if (!$this->option('force') && !$this->confirm('Overwrite projection quantities from the ledger? Pause stock writers first.')) {
            $this->info('Rebuild cancelled; nothing was changed.');

            return self::FAILURE;
        }
        $fixed = $reconciliation->rebuild($differences);
        $this->info("Corrected {$fixed} projection rows.");
        $remaining = $reconciliation->differences($company);
        $this->report($remaining);

        return $remaining === [] ? self::SUCCESS : self::FAILURE;
    }

    private function report(array $differences): void
    {
        if ($differences === []) {
            $this->info('Stock ledger and projections reconcile.');

            return;
        }
        $this->error(count($differences).' stock differences found.');
        $limit = max(1, (int) $this->option('limit'));
        $this->table(
            ['Level', 'Product', 'Warehouse', 'Variant', 'Batch', 'Ledger', 'Projection', 'Difference', 'Ledger history'],
            array_map(fn ($diff) => [
                $diff['level'], $diff['product_id'], $diff['warehouse_id'] ?? '-', $diff['variant_id'] ?? '-', $diff['batch_id'] ?? '-',
                $diff['ledger_qty'], $diff['projection_qty'], $diff['difference'], $diff['has_ledger'] ? 'yes' : 'no',
            ], array_slice($differences, 0, $limit)),
        );
        if (count($differences) > $limit) {
            $this->line('... '.(count($differences) - $limit).' more not shown.');
        }
    }
}
