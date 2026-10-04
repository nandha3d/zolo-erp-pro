<?php

namespace App\Services\Industry;

use App\Services\ERP\CompanyWriteGuard;
use App\Services\Operations\OperationPosting;
use App\Services\Platform\CompanyContext;
use Illuminate\Support\Facades\DB;

/** Read-only profile views of shared inventory balances and persisted dimensional results. */
class IndustryStockQueries
{
    public function pieces(int $product, int $warehouse, string $term, CompanyContext $context, int $actor): array
    {
        app(OperationPosting::class)->authorize('inventory.dimension_tracking', 'products-index', $context, $actor);
        app(CompanyWriteGuard::class)->owned(\App\Models\Product::class, $product, $context, 'product_id');
        app(CompanyWriteGuard::class)->warehouse($warehouse, $context, $actor);
        $balances = DB::table('stock_movement_lines as l')->join('stock_movements as m', 'm.id', '=', 'l.stock_movement_id')
            ->where('l.company_id', $context->companyId)->where('m.company_id', $context->companyId)
            ->where('m.projection_mode', 'applied')->where('l.warehouse_id', $warehouse)->where('l.product_id', $product)
            ->groupBy('l.stock_identity_id')->selectRaw('l.stock_identity_id, SUM(l.qty_base) AS qty_base')->havingRaw('SUM(l.qty_base) > 0.00005');
        $species = DB::table('product_attribute_values as v')->join('product_attribute_definitions as d', 'd.id', '=', 'v.definition_id')
            ->where('v.company_id', $context->companyId)->where('d.company_id', $context->companyId)->where('d.key', 'species')
            ->where('v.product_id', $product)->value('v.value_json');
        $species = $species ? (string) json_decode($species) : '';
        $term = mb_strtolower(trim($term));
        return DB::table('stock_identities as i')->join('stock_dimensions as d', 'd.stock_identity_id', '=', 'i.id')
            ->joinSub($balances, 'b', 'b.stock_identity_id', '=', 'i.id')->where('i.company_id', $context->companyId)
            ->where('i.product_id', $product)->where('i.warehouse_id', $warehouse)->where('i.identity_type', 'piece')
            ->where('i.status', 'in_stock')->orderBy('i.id')->get(['i.id','i.identity_no','d.length','d.width','d.thickness',
                'd.dimension_uom','d.grade','d.computed_cft','d.computed_cbm','d.formula_version','b.qty_base'])
            ->map(fn ($row) => (array) $row + ['species' => $species])->filter(fn ($row) => $term === ''
                || str_contains(mb_strtolower(implode(' ', array_map(fn ($value) => (string) $value, $row))), $term))
            ->take(200)->values()->all();
    }

    public function batchBalances(CompanyContext $context, int $actor): array
    {
        app(OperationPosting::class)->authorize('inventory.batch_expiry', 'products-index', $context, $actor);
        return DB::table('product_warehouse as s')->join('warehouses as w', 'w.id', '=', 's.warehouse_id')
            ->join('product_batches as b', 'b.id', '=', 's.product_batch_id')->join('products as p', 'p.id', '=', 's.product_id')
            ->where('s.company_id', $context->companyId)->where('w.company_id', $context->companyId)->where('w.branch_id', $context->branchId)
            ->where('b.company_id', $context->companyId)->where('p.company_id', $context->companyId)->where('s.qty', '>', 0)
            ->orderBy('b.expired_date')->limit(500)->get(['p.name as product', 'w.name as warehouse', 'b.batch_no', 'b.mfg_date', 'b.expired_date', 'b.mrp', 's.qty'])->toArray();
    }
}
