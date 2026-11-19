<?php
namespace App\Services\Industry;

use App\Models\{Product, Warehouse};
use App\Services\ERP\CompanyWriteGuard;
use App\Services\Operations\OperationPosting;
use App\Services\Platform\CompanyContext;
use Carbon\CarbonImmutable;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SimplifiedStockService
{
    public function __construct(
        private readonly OperationPosting $posting,
        private readonly CompanyWriteGuard $guard,
    ) {}

    /**
     * Query simplified business-centric stock flow:
     * Purchased -> Given to Job Work -> Returned after Job Work -> Final Available Stock
     */
    public function simplifiedStock(array $filters, CompanyContext $context, int $actor): array
    {
        $this->posting->authorize('core.inventory', 'products-index', $context, $actor);
        if (!empty($filters['product_id'])) {
            $this->guard->owned(Product::class, (int) $filters['product_id'], $context, 'product_id');
        }
        if (!empty($filters['warehouse_id'])) {
            $this->guard->warehouse((int) $filters['warehouse_id'], $context, $actor);
        }

        $from = $filters['from'];
        $to = CarbonImmutable::parse($filters['to'])->addDay()->toDateString();
        $groupBy = $filters['group_by'] ?? 'simplified';
        $byLot = ($groupBy === 'lot') || (!empty($filters['lot']));

        // Categorize warehouses safely checking column existence
        $hasQuarantine = Schema::hasColumn('warehouses', 'is_quarantine');
        $hasExtJwo = Schema::hasColumn('warehouses', 'external_job_order_id');

        $whColumns = ['id', 'name'];
        if ($hasQuarantine) $whColumns[] = 'is_quarantine';
        if ($hasExtJwo) $whColumns[] = 'external_job_order_id';

        $warehouses = DB::table('warehouses')
            ->where('company_id', $context->companyId)
            ->where('branch_id', $context->branchId)
            ->where('is_active', 1)
            ->get($whColumns);

        $internalWhIds = $warehouses->filter(fn ($w) => empty($w->is_quarantine ?? null) && empty($w->external_job_order_id ?? null))->pluck('id')->all();
        $quarantineWhIds = $hasQuarantine ? $warehouses->filter(fn ($w) => !empty($w->is_quarantine))->pluck('id')->all() : [];
        $externalWhIds = $hasExtJwo ? $warehouses->filter(fn ($w) => !empty($w->external_job_order_id))->pluck('id')->all() : [];

        // 1. Stock Movements for Warehouse Balances & Direct Purchases
        $lotCol = $byLot ? "COALESCE(b.batch_no, '')" : "''";
        
        $movements = DB::table('stock_movement_lines as l')
            ->join('stock_movements as m', 'm.id', '=', 'l.stock_movement_id')
            ->leftJoin('product_batches as b', 'b.id', '=', 'l.batch_id')
            ->where('l.company_id', $context->companyId)
            ->where('m.company_id', $context->companyId)
            ->where('m.projection_mode', 'applied')
            ->whereIn('m.status', ['posted', 'reversed']);

        if (!empty($filters['product_id'])) {
            $movements->where('l.product_id', (int) $filters['product_id']);
        }
        if (!empty($filters['warehouse_id'])) {
            $movements->where('l.warehouse_id', (int) $filters['warehouse_id']);
        }
        if ($byLot && isset($filters['lot']) && $filters['lot'] !== '') {
            $lotFilter = $filters['lot'] === '__unbatched__' ? '' : $filters['lot'];
            $movements->whereRaw("COALESCE(b.batch_no, '') = ?", [$lotFilter]);
        }

        $movementsQuery = clone $movements;
        $movementRows = $movementsQuery
            ->groupBy('l.product_id')
            ->groupByRaw($lotCol)
            ->selectRaw("l.product_id, {$lotCol} AS lot")
            // Purchased inward into internal warehouses within date range
            ->selectRaw("SUM(CASE WHEN m.movement_date >= ? AND m.movement_date < ? AND l.qty_base > 0 
                AND (m.movement_type = 'purchase' OR m.source_type = 'purchase') 
                THEN l.qty_base ELSE 0 END) AS purchased_qty", [$from, $to])
            // Current in godown (internal warehouses, all time)
            ->selectRaw("SUM(CASE WHEN " . ($internalWhIds ? "l.warehouse_id IN (" . implode(',', $internalWhIds) . ")" : "0=1") . " 
                THEN l.qty_base ELSE 0 END) AS in_godown_qty")
            // Current in quarantine
            ->selectRaw("SUM(CASE WHEN " . ($quarantineWhIds ? "l.warehouse_id IN (" . implode(',', $quarantineWhIds) . ")" : "0=1") . " 
                THEN l.qty_base ELSE 0 END) AS in_quarantine_qty")
            // Current at external job work warehouses
            ->selectRaw("SUM(CASE WHEN " . ($externalWhIds ? "l.warehouse_id IN (" . implode(',', $externalWhIds) . ")" : "0=1") . " 
                THEN l.qty_base ELSE 0 END) AS at_job_worker_qty")
            ->get();

        // 2. Job Work Dispatches (Given to Job Work)
        $dispatches = DB::table('job_work_dispatch_lines as dl')
            ->join('job_work_dispatches as d', 'd.id', '=', 'dl.dispatch_id')
            ->where('d.company_id', $context->companyId)
            ->where('d.branch_id', $context->branchId)
            ->whereNull('d.reversed_at')
            ->where('d.business_date', '>=', $from)
            ->where('d.business_date', '<', $to);

        if (!empty($filters['product_id'])) {
            $dispatches->where('dl.product_id', (int) $filters['product_id']);
        }

        $dispatchRows = $dispatches
            ->groupBy('dl.product_id')
            ->select('dl.product_id', DB::raw('SUM(dl.qty_base) as dispatched_qty'))
            ->get()
            ->keyBy('product_id');

        // 3. Job Work Receipts & Stock Name Changes
        $receipts = DB::table('job_work_receipt_lines as rl')
            ->join('job_work_receipts as r', 'r.id', '=', 'rl.receipt_id')
            ->join('job_work_dispatch_lines as dl', 'dl.id', '=', 'rl.dispatch_line_id')
            ->where('r.company_id', $context->companyId)
            ->where('r.branch_id', $context->branchId)
            ->whereNull('r.reversed_at')
            ->where('r.business_date', '>=', $from)
            ->where('r.business_date', '<', $to);

        if (!empty($filters['product_id'])) {
            $receipts->where('dl.product_id', (int) $filters['product_id']);
        }

        $receiptRows = $receipts
            ->groupBy('dl.product_id')
            ->select('dl.product_id', DB::raw('SUM(rl.accepted_qty) as returned_qty'))
            ->get()
            ->keyBy('product_id');

        // 4. Traceability & Renamed Stock Notes
        $notes = [];
        $conversionReceipts = DB::table('job_work_receipts')
            ->where('company_id', $context->companyId)
            ->where('branch_id', $context->branchId)
            ->whereNull('reversed_at')
            ->whereNotNull('service_purchase_id')
            ->where('details_json', 'like', '%"stock_name_change"%')
            ->get(['reference_no', 'details_json']);

        $convertedIn = [];
        $convertedOut = [];

        foreach ($conversionReceipts as $receipt) {
            $change = json_decode($receipt->details_json ?? '{}', true)['stock_name_change'] ?? null;
            if (!$change || ($change['date'] ?? '') > $filters['to']) continue;
            foreach ($change['items'] ?? [] as $item) {
                $ref = ' (' . $receipt->reference_no . ' / ' . ($change['purchase_no'] ?? '') . ')';
                $qtyFormatted = rtrim(rtrim(number_format((float) $item['qty'], 4, '.', ''), '0'), '.');
                $fromPid = (int) $item['from_product_id'];
                $toPid = (int) $item['to_product_id'];

                $notes[$fromPid][] = "{$qtyFormatted} returned as {$item['to_name']}{$ref}";
                $notes[$toPid][] = "{$qtyFormatted} received from {$item['from_name']}{$ref}";

                $convertedOut[$fromPid] = ($convertedOut[$fromPid] ?? 0) + (float) $item['qty'];
                $convertedIn[$toPid] = ($convertedIn[$toPid] ?? 0) + (float) $item['qty'];
            }
        }

        // 5. Merge all distinct products touched
        $productIds = collect()
            ->merge($movementRows->pluck('product_id'))
            ->merge($dispatchRows->pluck('product_id'))
            ->merge($receiptRows->pluck('product_id'))
            ->merge(array_keys($convertedIn))
            ->merge(array_keys($convertedOut))
            ->unique()
            ->filter();

        if (!empty($filters['product_id'])) {
            $productIds = $productIds->filter(fn ($id) => (int) $id === (int) $filters['product_id']);
        }

        $products = Product::whereIn('products.id', $productIds)
            ->leftJoin('units', 'units.id', '=', 'products.unit_id')
            ->get(['products.id', 'products.name', 'products.code', 'units.unit_code', 'units.unit_name'])
            ->keyBy('id');

        $rows = [];
        $movementByProduct = $movementRows->groupBy('product_id');

        foreach ($productIds as $pid) {
            $prod = $products->get($pid);
            if (!$prod) continue;

            $mRows = $movementByProduct->get($pid, collect());
            $purchased = (float) $mRows->sum('purchased_qty');
            $inGodown = (float) $mRows->sum('in_godown_qty');
            $inQuarantine = (float) $mRows->sum('in_quarantine_qty');
            $atJobWorker = (float) $mRows->sum('at_job_worker_qty');

            $dispatched = (float) ($dispatchRows->get($pid)?->dispatched_qty ?? 0);
            
            // Returned after job work: either direct returned or converted output
            $directReturned = (float) ($receiptRows->get($pid)?->returned_qty ?? 0);
            $convOut = (float) ($convertedOut[$pid] ?? 0);
            $convIn = (float) ($convertedIn[$pid] ?? 0);
            
            // For raw material, returned after job work is direct + converted out
            // For converted finished goods, returned/received is converted in
            $returned = max($directReturned, $convOut) + $convIn;

            $unit = $prod->unit_code ?: ($prod->unit_name ?: 'Pc');
            $owned = $inGodown + $inQuarantine + $atJobWorker;

            $prodNotes = $notes[$pid] ?? [];

            $rows[] = (object) [
                'product_id' => $pid,
                'product' => $prod->name,
                'product_code' => $prod->code,
                'lot' => $byLot ? ($mRows->first()?->lot ?: 'Unbatched') : 'Combined lots',
                'base_unit' => $unit,
                'purchased_qty' => $purchased,
                'given_to_job_work_qty' => $dispatched,
                'returned_after_job_work_qty' => $returned,
                'in_godown_qty' => $inGodown,
                'in_quarantine_qty' => $inQuarantine,
                'at_job_workers_qty' => $atJobWorker,
                'final_stock_qty' => $owned,
                'conversion_notes' => $prodNotes,
            ];
        }

        // Sort by product name
        usort($rows, fn ($a, $b) => strcmp($a->product, $b->product));

        // Summary totals across all rows
        $totals = [
            'total_purchased' => array_sum(array_column($rows, 'purchased_qty')),
            'total_given_to_job_work' => array_sum(array_column($rows, 'given_to_job_work_qty')),
            'total_returned' => array_sum(array_column($rows, 'returned_after_job_work_qty')),
            'total_in_godown' => array_sum(array_column($rows, 'in_godown_qty')),
            'total_in_quarantine' => array_sum(array_column($rows, 'in_quarantine_qty')),
            'total_at_job_workers' => array_sum(array_column($rows, 'at_job_workers_qty')),
            'total_final_stock' => array_sum(array_column($rows, 'final_stock_qty')),
        ];

        return [
            'rows' => $rows,
            'totals' => $totals,
            'filters' => $filters,
        ];
    }

    /**
     * Build chronological interactive lifecycle timeline for a product
     */
    public function inventoryTimeline(int $productId, ?string $lot, array $filters, CompanyContext $context, int $actor): array
    {
        $this->posting->authorize('core.inventory', 'products-index', $context, $actor);
        $this->guard->owned(Product::class, $productId, $context, 'product_id');

        $product = Product::with('unit')->findOrFail($productId);
        $unit = $product->unit?->unit_code ?: ($product->unit?->unit_name ?: 'Pc');
        $from = $filters['from'] ?? '2000-01-01';
        $to = $filters['to'] ?? now()->toDateString();
        $toDateEndOfDay = \Carbon\CarbonImmutable::parse($to)->addDay()->toDateString();

        // Opening balance calculation before period start
        $openingGodownStock = (float) DB::table('stock_movement_lines as l')
            ->join('stock_movements as m', 'm.id', '=', 'l.stock_movement_id')
            ->where('l.company_id', $context->companyId)
            ->where('l.product_id', $productId)
            ->where('m.projection_mode', 'applied')
            ->where('m.status', 'posted')
            ->where('m.movement_date', '<', $from)
            ->sum('l.qty_base');

        $events = [];

        // 1. Inward Purchases
        $purchases = DB::table('stock_movement_lines as l')
            ->join('stock_movements as m', 'm.id', '=', 'l.stock_movement_id')
            ->leftJoin('warehouses as w', 'w.id', '=', 'l.warehouse_id')
            ->leftJoin('purchases as p', function ($join) {
                $join->on('p.id', '=', 'm.source_id')->where('m.source_type', '=', 'purchase');
            })
            ->leftJoin('suppliers as s', 's.id', '=', 'p.supplier_id')
            ->where('l.company_id', $context->companyId)
            ->where('l.product_id', $productId)
            ->where('m.projection_mode', 'applied')
            ->where('m.status', 'posted')
            ->where('l.qty_base', '>', 0)
            ->where(function ($q) {
                $q->where('m.movement_type', 'purchase')->orWhere('m.source_type', 'purchase');
            })
            ->orderBy('m.movement_date')
            ->get(['m.movement_date', 'm.movement_no', 'm.source_no', 'l.qty_base', 'l.unit_cost', 'l.value',
                   'w.name as warehouse_name', 's.name as supplier_name', 'p.reference_no as purchase_ref']);

        foreach ($purchases as $p) {
            $events[] = [
                'date' => substr($p->movement_date, 0, 10),
                'timestamp' => $p->movement_date,
                'stage' => 'purchase',
                'badge' => 'Purchased Inward',
                'badge_class' => 'badge-purchase',
                'icon' => 'dripicons-download',
                'title' => 'Raw Material Inward',
                'ref_no' => $p->purchase_ref ?: ($p->source_no ?: $p->movement_no),
                'party' => $p->supplier_name ?: 'Direct Purchase',
                'warehouse' => $p->warehouse_name ?: 'Main Godown',
                'inward_qty' => (float) $p->qty_base,
                'outward_qty' => 0.0,
                'qty' => (float) $p->qty_base,
                'unit' => $unit,
                'cost' => (float) $p->unit_cost,
                'total_value' => (float) $p->value,
                'description' => "Purchased {$p->qty_base} {$unit} received into {$p->warehouse_name}",
            ];
        }

        // 2. Job Work Dispatches (Given to Job Work)
        $dispatches = DB::table('job_work_dispatch_lines as dl')
            ->join('job_work_dispatches as d', 'd.id', '=', 'dl.dispatch_id')
            ->join('job_work_orders as o', 'o.id', '=', 'd.job_work_order_id')
            ->leftJoin('suppliers as jw', 'jw.id', '=', 'o.job_worker_party_id')
            ->leftJoin('warehouses as w', 'w.id', '=', 'd.warehouse_id')
            ->where('d.company_id', $context->companyId)
            ->where('dl.product_id', $productId)
            ->whereNull('d.reversed_at')
            ->orderBy('d.business_date')
            ->get(['d.id as dispatch_id', 'd.business_date', 'd.reference_no as dc_no', 'dl.qty_base', 'dl.value',
                   'o.reference_no as jwo_no', 'jw.name as job_worker_name', 'w.name as warehouse_name', 'o.details_json']);

        foreach ($dispatches as $d) {
            $proc = json_decode($d->details_json ?? '{}', true)['process'] ?? [];
            $procName = $proc['name'] ?? ($proc['code'] ?? 'Job Processing');

            $events[] = [
                'date' => substr($d->business_date, 0, 10),
                'timestamp' => $d->business_date,
                'stage' => 'dispatch',
                'badge' => 'Given to Job Work',
                'badge_class' => 'badge-dispatch',
                'icon' => 'dripicons-export',
                'title' => "Dispatched for {$procName}",
                'ref_no' => $d->dc_no,
                'jwo_ref' => $d->jwo_no,
                'party' => $d->job_worker_name ?: 'Job Worker',
                'warehouse' => $d->warehouse_name ?: 'Main Godown',
                'inward_qty' => 0.0,
                'outward_qty' => (float) $d->qty_base,
                'qty' => (float) $d->qty_base,
                'unit' => $unit,
                'process' => $procName,
                'description' => "Dispatched {$d->qty_base} {$unit} to {$d->job_worker_name} for {$procName} (Order: {$d->jwo_no})",
            ];
        }

        // 3. Job Work Receipts (Returned after Job Work)
        $receipts = DB::table('job_work_receipt_lines as rl')
            ->join('job_work_receipts as r', 'r.id', '=', 'rl.receipt_id')
            ->join('job_work_dispatch_lines as dl', 'dl.id', '=', 'rl.dispatch_line_id')
            ->join('job_work_dispatches as d', 'd.id', '=', 'r.dispatch_id')
            ->join('job_work_orders as o', 'o.id', '=', 'd.job_work_order_id')
            ->leftJoin('suppliers as jw', 'jw.id', '=', 'o.job_worker_party_id')
            ->leftJoin('warehouses as w', 'w.id', '=', 'r.warehouse_id')
            ->where('r.company_id', $context->companyId)
            ->where('dl.product_id', $productId)
            ->whereNull('r.reversed_at')
            ->orderBy('r.business_date')
            ->get(['r.business_date', 'r.reference_no as grn_no', 'rl.received_qty', 'rl.accepted_qty',
                   'rl.rejected_qty', 'rl.loss_qty', 'w.name as warehouse_name', 'jw.name as job_worker_name',
                   'r.details_json', 'd.reference_no as dc_no']);

        $convertedToProducts = [];

        foreach ($receipts as $r) {
            $change = json_decode($r->details_json ?? '{}', true)['stock_name_change'] ?? null;
            $convertedTo = null;
            if ($change && isset($change['items'])) {
                foreach ($change['items'] as $item) {
                    if ((int) $item['from_product_id'] === $productId) {
                        $convertedTo = $item['to_name'];
                        $convertedToProducts[] = [
                            'id' => $item['to_product_id'],
                            'name' => $item['to_name'],
                            'qty' => (float) $item['qty'],
                            'ref' => $r->grn_no,
                            'date' => substr($r->business_date, 0, 10),
                        ];
                    }
                }
            }

            $events[] = [
                'date' => substr($r->business_date, 0, 10),
                'timestamp' => $r->business_date,
                'stage' => 'return',
                'badge' => 'Returned after Job Work',
                'badge_class' => 'badge-returned',
                'icon' => 'dripicons-checkmark',
                'title' => $convertedTo ? "Returned & Converted to {$convertedTo}" : 'Returned from Processor',
                'ref_no' => $r->grn_no,
                'dc_ref' => $r->dc_no,
                'party' => $r->job_worker_name ?: 'Job Worker',
                'warehouse' => $r->warehouse_name ?: 'Main Godown',
                'inward_qty' => $convertedTo ? 0.0 : (float) $r->accepted_qty,
                'outward_qty' => 0.0,
                'accepted_qty' => (float) $r->accepted_qty,
                'rejected_qty' => (float) $r->rejected_qty,
                'loss_qty' => (float) $r->loss_qty,
                'qty' => (float) $r->accepted_qty,
                'unit' => $unit,
                'converted_to' => $convertedTo,
                'description' => "Received back {$r->accepted_qty} {$unit} into {$r->warehouse_name}" .
                    ($convertedTo ? " (Converted to {$convertedTo})" : "") .
                    ((float) $r->loss_qty > 0 ? " · Process loss: {$r->loss_qty} {$unit}" : "") .
                    ((float) $r->rejected_qty > 0 ? " · Rejected: {$r->rejected_qty} {$unit}" : ""),
            ];
        }

        // 4. Receipts where this product is the RESULT of a conversion (Converted Inward)
        $conversionInReceipts = DB::table('job_work_receipts')
            ->where('company_id', $context->companyId)
            ->whereNull('reversed_at')
            ->where('details_json', 'like', '%"stock_name_change"%')
            ->get();

        $convertedFromProducts = [];

        foreach ($conversionInReceipts as $cr) {
            $change = json_decode($cr->details_json ?? '{}', true)['stock_name_change'] ?? null;
            if (!$change || empty($change['items'])) continue;
            foreach ($change['items'] as $item) {
                if ((int) $item['to_product_id'] === $productId) {
                    $convertedFromProducts[] = [
                        'id' => $item['from_product_id'],
                        'name' => $item['from_name'],
                        'qty' => (float) $item['qty'],
                        'ref' => $cr->reference_no,
                        'date' => substr($change['date'] ?? $cr->business_date, 0, 10),
                    ];

                    $events[] = [
                        'date' => substr($change['date'] ?? $cr->business_date, 0, 10),
                        'timestamp' => $change['date'] ?? $cr->business_date,
                        'stage' => 'conversion_in',
                        'badge' => 'Produced from Job Work',
                        'badge_class' => 'badge-produced',
                        'icon' => 'dripicons-star',
                        'title' => "Created from {$item['from_name']}",
                        'ref_no' => $cr->reference_no,
                        'party' => 'In-House Production',
                        'warehouse' => 'Main Godown',
                        'inward_qty' => (float) $item['qty'],
                        'outward_qty' => 0.0,
                        'qty' => (float) $item['qty'],
                        'unit' => $unit,
                        'converted_from' => $item['from_name'],
                        'description' => "Created {$item['qty']} {$unit} from processing of {$item['from_name']} (Bill: {$change['purchase_no']})",
                    ];
                }
            }
        }

        // 5. Sales Outward
        $sales = DB::table('stock_movement_lines as l')
            ->join('stock_movements as m', 'm.id', '=', 'l.stock_movement_id')
            ->leftJoin('warehouses as w', 'w.id', '=', 'l.warehouse_id')
            ->leftJoin('sales as s', function ($join) {
                $join->on('s.id', '=', 'm.source_id')->where('m.source_type', '=', 'sale');
            })
            ->leftJoin('customers as c', 'c.id', '=', 's.customer_id')
            ->where('l.company_id', $context->companyId)
            ->where('l.product_id', $productId)
            ->where('m.projection_mode', 'applied')
            ->where('m.status', 'posted')
            ->where(function ($q) {
                $q->where('m.movement_type', 'sale')->orWhere('m.source_type', 'sale');
            })
            ->orderBy('m.movement_date')
            ->get(['m.movement_date', 'm.movement_no', 'm.source_no', 'l.qty_base', 'l.unit_cost', 'l.value',
                   'w.name as warehouse_name', 'c.name as customer_name', 's.reference_no as sale_ref']);

        foreach ($sales as $s) {
            $saleQty = abs((float) $s->qty_base);
            $events[] = [
                'date' => substr($s->movement_date, 0, 10),
                'timestamp' => $s->movement_date,
                'stage' => 'sale',
                'badge' => 'Sales Outward',
                'badge_class' => 'badge-sale',
                'icon' => 'dripicons-cart',
                'title' => 'Commercial Sale Outward',
                'ref_no' => $s->sale_ref ?: ($s->source_no ?: $s->movement_no),
                'party' => $s->customer_name ?: 'Customer Issue',
                'warehouse' => $s->warehouse_name ?: 'Main Godown',
                'inward_qty' => 0.0,
                'outward_qty' => $saleQty,
                'qty' => $saleQty,
                'unit' => $unit,
                'cost' => (float) $s->unit_cost,
                'total_value' => (float) $s->value,
                'description' => "Dispatched {$saleQty} {$unit} for sale to {$s->customer_name}",
            ];
        }

        // 6. Stock Adjustments
        $adjustments = DB::table('stock_movement_lines as l')
            ->join('stock_movements as m', 'm.id', '=', 'l.stock_movement_id')
            ->leftJoin('warehouses as w', 'w.id', '=', 'l.warehouse_id')
            ->where('l.company_id', $context->companyId)
            ->where('l.product_id', $productId)
            ->where('m.projection_mode', 'applied')
            ->where('m.status', 'posted')
            ->where('m.movement_type', 'adjustment')
            ->orderBy('m.movement_date')
            ->get(['m.movement_date', 'm.movement_no', 'm.source_no', 'l.qty_base', 'l.unit_cost', 'l.value',
                   'w.name as warehouse_name']);

        foreach ($adjustments as $adj) {
            $adjQty = (float) $adj->qty_base;
            $isPos = $adjQty >= 0;
            $events[] = [
                'date' => substr($adj->movement_date, 0, 10),
                'timestamp' => $adj->movement_date,
                'stage' => 'adjustment',
                'badge' => $isPos ? 'Stock Adjustment (+)' : 'Stock Adjustment (-)',
                'badge_class' => $isPos ? 'badge-adjustment-pos' : 'badge-adjustment-neg',
                'icon' => 'dripicons-retweet',
                'title' => 'Inventory Count Adjustment',
                'ref_no' => $adj->source_no ?: $adj->movement_no,
                'party' => 'Audit Reconciliation',
                'warehouse' => $adj->warehouse_name ?: 'Main Godown',
                'inward_qty' => $isPos ? $adjQty : 0.0,
                'outward_qty' => $isPos ? 0.0 : abs($adjQty),
                'qty' => abs($adjQty),
                'unit' => $unit,
                'cost' => (float) $adj->unit_cost,
                'total_value' => (float) $adj->value,
                'description' => "Audited stock balance adjustment of {$adjQty} {$unit} in {$adj->warehouse_name}",
            ];
        }

        // Filter events strictly within requested date window
        $events = array_values(array_filter($events, function ($e) use ($from, $to) {
            return $e['date'] >= $from && $e['date'] <= $to;
        }));

        // Sort all events chronologically
        usort($events, fn ($a, $b) => strcmp($a['timestamp'], $b['timestamp']));

        // Compute running balance step-by-step
        $running = $openingGodownStock;
        foreach ($events as &$ev) {
            $running = $running + $ev['inward_qty'] - $ev['outward_qty'];
            $ev['running_balance'] = $running;
        }
        unset($ev);

        // Compute current live snapshot
        $hasQuarantine = Schema::hasColumn('warehouses', 'is_quarantine');
        $hasExtJwo = Schema::hasColumn('warehouses', 'external_job_order_id');

        $currentGodownQuery = DB::table('stock_movement_lines as l')
            ->join('warehouses as w', 'w.id', '=', 'l.warehouse_id')
            ->where('l.company_id', $context->companyId)
            ->where('l.product_id', $productId);
        if ($hasQuarantine) $currentGodownQuery->where('w.is_quarantine', 0);
        if ($hasExtJwo) $currentGodownQuery->whereNull('w.external_job_order_id');
        $currentGodown = (float) $currentGodownQuery->sum('l.qty_base');

        $currentQuarantine = $hasQuarantine ? (float) DB::table('stock_movement_lines as l')
            ->join('warehouses as w', 'w.id', '=', 'l.warehouse_id')
            ->where('l.company_id', $context->companyId)
            ->where('l.product_id', $productId)
            ->where('w.is_quarantine', 1)
            ->sum('l.qty_base') : 0.0;

        $currentWip = $hasExtJwo ? (float) DB::table('stock_movement_lines as l')
            ->join('warehouses as w', 'w.id', '=', 'l.warehouse_id')
            ->where('l.company_id', $context->companyId)
            ->where('l.product_id', $productId)
            ->whereNotNull('w.external_job_order_id')
            ->sum('l.qty_base') : 0.0;

        $totalInward = array_sum(array_column($events, 'inward_qty'));
        $totalOutward = array_sum(array_column($events, 'outward_qty'));
        $netMovement = $totalInward - $totalOutward;

        $summary = [
            'opening_stock' => $openingGodownStock,
            'total_purchased' => array_sum(array_map(fn ($e) => $e['stage'] === 'purchase' ? $e['qty'] : 0, $events)),
            'total_dispatched' => array_sum(array_map(fn ($e) => $e['stage'] === 'dispatch' ? $e['qty'] : 0, $events)),
            'total_returned' => array_sum(array_map(fn ($e) => in_array($e['stage'], ['return', 'conversion_in']) ? $e['qty'] : 0, $events)),
            'total_inward' => $totalInward,
            'total_outward' => $totalOutward,
            'net_movement' => $netMovement,
            'transaction_count' => count($events),
            'in_godown' => $currentGodown,
            'in_quarantine' => $currentQuarantine,
            'at_processors_wip' => $currentWip,
            'final_owned' => $currentGodown + $currentQuarantine + $currentWip,
            'unit' => $unit,
        ];

        // 7. Time-Series Chart Data Generation for Chart.js
        $chartDates = [];
        $chartBalances = [];
        $chartInwards = [];
        $chartOutwards = [];
        $chartCumulative = [];
        $chartAnnotations = [];
        $chartGodownSeries = [];
        $chartWipSeries = [];
        $chartInwardSeries = [];
        $chartReturnedSeries = [];
        $chartPointsMeta = [];

        // Baseline on from date
        $chartDates[] = $from;
        $chartBalances[] = round($openingGodownStock, 2);
        $chartInwards[] = 0;
        $chartOutwards[] = 0;
        $chartCumulative[] = round($openingGodownStock, 2);
        $chartAnnotations[] = 'Opening: ' . round($openingGodownStock, 2) . ' ' . $unit;
        $chartGodownSeries[] = round($openingGodownStock, 2);
        $chartWipSeries[] = 0.0;
        $chartInwardSeries[] = 0.0;
        $chartReturnedSeries[] = 0.0;
        $chartPointsMeta[] = [
            'date' => $from,
            'title' => 'Period Baseline',
            'stage' => 'baseline',
            'ref_no' => 'Period Start',
            'party' => 'Starting Balance',
            'movement' => round($openingGodownStock, 2),
            'qty_label' => round($openingGodownStock, 2) . ' ' . $unit,
            'qty_class' => 'text-primary',
            'godown_balance' => round($openingGodownStock, 2),
            'wip_balance' => 0.0,
            'why' => round($openingGodownStock, 2) . " {$unit} period opening balance",
        ];

        $cum = $openingGodownStock;
        $currentGodownTrack = $openingGodownStock;
        $currentWipTrack = 0.0;
        $cumPurchasedTrack = 0.0;
        $cumReturnedTrack = 0.0;

        foreach ($events as $ev) {
            $chartDates[] = $ev['date'];
            $chartBalances[] = round($ev['running_balance'], 2);
            $chartInwards[] = round($ev['inward_qty'], 2);
            $chartOutwards[] = round($ev['outward_qty'], 2);
            $cum += ($ev['inward_qty'] - $ev['outward_qty']);
            $chartCumulative[] = round($cum, 2);

            $tag = '';
            if ($ev['inward_qty'] > 0) $tag = '+' . round($ev['inward_qty'], 2);
            elseif ($ev['outward_qty'] > 0) $tag = '-' . round($ev['outward_qty'], 2);
            $chartAnnotations[] = $tag ? ($tag . ' ' . $unit) : ($ev['badge']);

            // Update process tracking
            $refStr = (!empty($ev['ref_no']) && $ev['ref_no'] !== '-') ? " ({$ev['ref_no']})" : "";
            if ($ev['stage'] === 'purchase') {
                $currentGodownTrack += $ev['inward_qty'];
                $cumPurchasedTrack += $ev['inward_qty'];
                $whyText = rtrim(rtrim(number_format($ev['inward_qty'], 4), '0'), '.') . " {$unit} purchased from {$ev['party']}{$refStr}";
            } elseif ($ev['stage'] === 'dispatch') {
                $currentGodownTrack -= $ev['outward_qty'];
                $currentWipTrack += $ev['outward_qty'];
                $whyText = rtrim(rtrim(number_format($ev['outward_qty'], 4), '0'), '.') . " {$unit} dispatched to {$ev['party']}{$refStr}";
            } elseif (in_array($ev['stage'], ['return', 'conversion_in'])) {
                $retQty = $ev['qty'];
                $currentWipTrack = max(0.0, $currentWipTrack - $retQty);
                if (empty($ev['converted_to'])) {
                    $currentGodownTrack += $retQty;
                }
                $cumReturnedTrack += $retQty;
                $convStr = !empty($ev['converted_to']) ? " (Converted to {$ev['converted_to']})" : "";
                $whyText = rtrim(rtrim(number_format($retQty, 4), '0'), '.') . " {$unit} returned from {$ev['party']}{$refStr}{$convStr}";
            } elseif ($ev['stage'] === 'sale') {
                $currentGodownTrack -= $ev['outward_qty'];
                $whyText = rtrim(rtrim(number_format($ev['outward_qty'], 4), '0'), '.') . " {$unit} sold to {$ev['party']}{$refStr}";
            } else {
                $currentGodownTrack += ($ev['inward_qty'] - $ev['outward_qty']);
                $whyText = rtrim(rtrim(number_format($ev['qty'], 4), '0'), '.') . " {$unit} adjusted in {$ev['warehouse']}{$refStr}";
            }

            $chartGodownSeries[] = round($ev['running_balance'], 2);
            $chartWipSeries[] = round($currentWipTrack, 2);
            $chartInwardSeries[] = round($cumPurchasedTrack, 2);
            $chartReturnedSeries[] = round($cumReturnedTrack, 2);

            $qtyLabel = '';
            $qtyClass = 'text-primary';
            if ($ev['stage'] === 'purchase') {
                $qtyLabel = '+' . rtrim(rtrim(number_format($ev['inward_qty'], 4), '0'), '.') . ' ' . $unit;
                $qtyClass = 'text-success';
            } elseif ($ev['stage'] === 'dispatch') {
                $qtyLabel = '-' . rtrim(rtrim(number_format($ev['outward_qty'], 4), '0'), '.') . ' ' . $unit;
                $qtyClass = 'text-danger';
            } elseif (in_array($ev['stage'], ['return', 'conversion_in'])) {
                $qtyLabel = '+' . rtrim(rtrim(number_format($ev['qty'], 4), '0'), '.') . ' ' . $unit;
                $qtyClass = 'text-success';
            } elseif ($ev['stage'] === 'sale') {
                $qtyLabel = '-' . rtrim(rtrim(number_format($ev['outward_qty'], 4), '0'), '.') . ' ' . $unit;
                $qtyClass = 'text-danger';
            } else {
                $qtyLabel = rtrim(rtrim(number_format($ev['qty'], 4), '0'), '.') . ' ' . $unit;
                $qtyClass = 'text-primary';
            }

            $chartPointsMeta[] = [
                'date' => $ev['date'],
                'title' => $ev['title'],
                'stage' => $ev['stage'],
                'ref_no' => $ev['ref_no'],
                'party' => $ev['party'],
                'movement' => $ev['inward_qty'] > 0 ? (+$ev['inward_qty']) : (-$ev['outward_qty']),
                'qty_label' => $qtyLabel,
                'qty_class' => $qtyClass,
                'godown_balance' => round($ev['running_balance'], 2),
                'wip_balance' => round($currentWipTrack, 2),
                'why' => $whyText,
            ];
        }

        // If to date is after last event date, plot closing node
        if (end($chartDates) !== $to) {
            $chartDates[] = $to;
            $chartBalances[] = round($running, 2);
            $chartInwards[] = 0;
            $chartOutwards[] = 0;
            $chartCumulative[] = round($cum, 2);
            $chartAnnotations[] = 'Closing: ' . round($running, 2) . ' ' . $unit;
            $chartGodownSeries[] = round($running, 2);
            $chartWipSeries[] = round($currentWipTrack, 2);
            $chartInwardSeries[] = round($cumPurchasedTrack, 2);
            $chartReturnedSeries[] = round($cumReturnedTrack, 2);
            $chartPointsMeta[] = [
                'date' => $to,
                'title' => 'Closing Reconciled Position',
                'stage' => 'closing',
                'ref_no' => 'Reconciled',
                'party' => 'Main Godown',
                'movement' => round($running, 2),
                'qty_label' => rtrim(rtrim(number_format($running, 4), '0'), '.') . ' ' . $unit,
                'qty_class' => 'text-primary font-weight-bold',
                'godown_balance' => round($running, 2),
                'wip_balance' => round($currentWipTrack, 2),
                'why' => rtrim(rtrim(number_format($running, 4), '0'), '.') . " {$unit} current stock in hand",
            ];
        }

        $chart = [
            'labels' => $chartDates,
            'balances' => $chartBalances,
            'inwards' => $chartInwards,
            'outwards' => $chartOutwards,
            'cumulative' => $chartCumulative,
            'annotations' => $chartAnnotations,
            'godown_series' => $chartGodownSeries,
            'wip_series' => $chartWipSeries,
            'inward_series' => $chartInwardSeries,
            'returned_series' => $chartReturnedSeries,
            'points_meta' => $chartPointsMeta,
        ];

        // 8. Daily Summary Table Aggregation
        $dailyGrouped = [];
        foreach ($events as $ev) {
            $d = $ev['date'];
            if (!isset($dailyGrouped[$d])) {
                $dailyGrouped[$d] = [
                    'date' => $d,
                    'inward' => 0.0,
                    'outward' => 0.0,
                    'tx_count' => 0,
                    'closing' => $ev['running_balance'],
                ];
            }
            $dailyGrouped[$d]['inward'] += $ev['inward_qty'];
            $dailyGrouped[$d]['outward'] += $ev['outward_qty'];
            $dailyGrouped[$d]['tx_count']++;
            $dailyGrouped[$d]['closing'] = $ev['running_balance'];
        }
        $dailySummary = array_values($dailyGrouped);

        // 9. Movement Summary Aggregation
        $moveGrouped = [];
        foreach ($events as $ev) {
            $st = $ev['stage'];
            if (!isset($moveGrouped[$st])) {
                $moveGrouped[$st] = [
                    'stage' => $st,
                    'badge' => $ev['badge'],
                    'badge_class' => $ev['badge_class'],
                    'icon' => $ev['icon'],
                    'count' => 0,
                    'inward' => 0.0,
                    'outward' => 0.0,
                ];
            }
            $moveGrouped[$st]['count']++;
            $moveGrouped[$st]['inward'] += $ev['inward_qty'];
            $moveGrouped[$st]['outward'] += $ev['outward_qty'];
        }
        $movementSummary = array_values($moveGrouped);

        // 10. Horizontal Connected Process Lifecycle Stages & Milestones (Combined Sleek Pipeline)
        $firstPurchase = collect($events)->where('stage', 'purchase')->first();
        $firstDispatch = collect($events)->where('stage', 'dispatch')->first();
        $firstReturn = collect($events)->whereIn('stage', ['return', 'conversion_in'])->first();

        $lifecycleStages = [
            [
                'step' => 1,
                'key' => 'purchased',
                'title' => '1. Raw Material Inward',
                'label' => 'Purchased Inward',
                'qty' => $summary['total_purchased'],
                'unit' => $unit,
                'status' => $summary['total_purchased'] > 0 ? 'completed' : 'pending',
                'status_label' => $summary['total_purchased'] > 0 ? 'Inward Complete' : 'No Purchase',
                'icon' => 'dripicons-download',
                'tone' => 'z-tone-success',
                'badge_class' => 'badge-success',
                'subtext' => 'Direct vendor receipts',
                'date' => $firstPurchase['date'] ?? null,
                'ref_no' => $firstPurchase['ref_no'] ?? null,
                'party' => $firstPurchase['party'] ?? null,
            ],
            [
                'step' => 2,
                'key' => 'dispatched',
                'title' => '2. Sent to Job Work',
                'label' => 'Dispatched (DC)',
                'qty' => $summary['total_dispatched'],
                'unit' => $unit,
                'status' => $summary['total_dispatched'] > 0 ? 'completed' : 'pending',
                'status_label' => $summary['total_dispatched'] > 0 ? 'Dispatched' : 'In Godown Only',
                'icon' => 'dripicons-export',
                'tone' => 'z-tone-warning',
                'badge_class' => 'badge-warning',
                'subtext' => 'Delivery challans issued',
                'date' => $firstDispatch['date'] ?? null,
                'ref_no' => $firstDispatch['ref_no'] ?? null,
                'party' => $firstDispatch['party'] ?? null,
            ],
            [
                'step' => 3,
                'key' => 'wip',
                'title' => '3. At Processors (WIP)',
                'label' => 'Processor WIP',
                'qty' => $summary['at_processors_wip'],
                'unit' => $unit,
                'status' => $summary['at_processors_wip'] > 0 ? 'active' : 'completed',
                'status_label' => $summary['at_processors_wip'] > 0 ? 'Processing Active' : 'All Returned',
                'icon' => 'dripicons-gear',
                'tone' => 'z-tone-info',
                'badge_class' => $summary['at_processors_wip'] > 0 ? 'badge-info' : 'badge-secondary',
                'subtext' => 'Pending with workers',
                'date' => $summary['at_processors_wip'] > 0 ? 'In Progress' : 'Completed',
                'ref_no' => $firstDispatch['jwo_ref'] ?? ($firstDispatch['ref_no'] ?? null),
                'party' => $firstDispatch['party'] ?? null,
            ],
            [
                'step' => 4,
                'key' => 'returned',
                'title' => '4. Returned & Converted',
                'label' => 'Returned Goods',
                'qty' => $summary['total_returned'],
                'unit' => $unit,
                'status' => $summary['total_returned'] > 0 ? 'completed' : 'pending',
                'status_label' => $summary['total_returned'] > 0 ? 'Received & Stocked' : 'Awaiting Return',
                'icon' => 'dripicons-checkmark',
                'tone' => 'z-tone-success',
                'badge_class' => 'badge-success',
                'subtext' => !empty($convertedToProducts) ? 'Converted & Named' : 'Processed Goods',
                'date' => $firstReturn['date'] ?? null,
                'ref_no' => $firstReturn['ref_no'] ?? null,
                'party' => $firstReturn['party'] ?? null,
            ],
            [
                'step' => 5,
                'key' => 'stock',
                'title' => '5. Available Ready Stock',
                'label' => 'Ready in Godown',
                'qty' => $summary['in_godown'],
                'unit' => $unit,
                'status' => $summary['in_godown'] > 0 ? 'completed' : 'low',
                'status_label' => $summary['in_godown'] > 0 ? 'Ready for Sale / Issue' : 'Nil Balance',
                'icon' => 'dripicons-box',
                'tone' => 'z-tone-primary',
                'badge_class' => 'badge-primary',
                'subtext' => 'Owned physical stock',
                'date' => $to,
                'ref_no' => 'Reconciled',
                'party' => 'Main Godown',
            ],
        ];

        return [
            'product' => $product,
            'events' => $events,
            'summary' => $summary,
            'chart' => $chart,
            'dailySummary' => $dailySummary,
            'movementSummary' => $movementSummary,
            'lifecycleStages' => $lifecycleStages,
            'convertedToProducts' => $convertedToProducts,
            'convertedFromProducts' => $convertedFromProducts,
            'filters' => $filters,
        ];
    }
}
