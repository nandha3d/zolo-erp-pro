<?php

namespace App\Services\Commercial;

use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use App\Services\ERP\CompanyWriteGuard;
use App\Services\Platform\CompanyContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Services\Inventory\UomConversionService;

/** Posted, visible commercial history. Customer and supplier identities never cross. */
class RateHistory
{
    public function read(string $kind, int $party, int $product, int $page, CompanyContext $context, int $actor): array
    {
        $purchase = $kind === 'purchase';
        $permission = app(CommercialPermission::class);
        $permission->assert($purchase ? 'purchases-index' : 'sales-index', $context, $actor);
        app(CompanyWriteGuard::class)->owned($purchase ? \App\Models\Supplier::class : \App\Models\Customer::class, $party, $context, 'party_id');
        $item = app(CompanyWriteGuard::class)->owned(Product::class, $product, $context, 'product_id');
        $factors = [];
        foreach (\App\Models\Unit::where('company_id', $context->companyId)->where('is_active', true)->get() as $unit) {
            try { $factor = app(UomConversionService::class)->toBase($item, 1, $unit->id); }
            catch (\InvalidArgumentException) { continue; }
            if ($factor > 0) $factors[$unit->id] = $factor;
        }
        $currency = Schema::hasTable('currencies') && Schema::hasColumn('companies', 'base_currency_id')
            ? DB::table('currencies')->where('id', DB::table('companies')->where('id', $context->companyId)->value('base_currency_id'))->value('code') : null;
        $describe = function ($line) use ($context, $currency) {
            if (!$line) return null;
            $snapshot = json_decode($line->document_snapshot_json ?? 'null', true) ?: [];
            $line->base_factor = $this->historicalFactor($line, $context);
            $line->currency = $snapshot['currency'] ?? $currency;
            $line->rate_basis = 'net_excluding_tax';
            unset($line->document_snapshot_json);
            return $line;
        };
        $lines = $this->query($kind, $party, $product, $context)->offset(($page - 1) * 10)->limit(11)->get();
        $costAllowed = $purchase || $permission->allows('purchases-index', $context, $actor);
        return ['data' => $lines->take(10)->map($describe)->values(), 'next_page' => $lines->count() > 10 ? $page + 1 : null,
            'unit_factors' => $factors, 'currency' => $currency, 'rate_basis' => 'net_excluding_tax',
            'last_sale' => $purchase ? null : $describe($this->query('sale', $party, $product, $context)->first()),
            'last_purchase' => $costAllowed ? $describe($this->query('purchase', $purchase ? $party : null, $product, $context)->first()) : null,
            'cost_visible' => $costAllowed];
    }

    /** Historical quantities are immutable even when an operator later changes a unit conversion. */
    private function historicalFactor(object $line, CompanyContext $context): ?float
    {
        $details = json_decode($line->stock_details_json ?? 'null', true) ?: [];
        if (isset($details['base_factor']) && is_numeric($details['base_factor']) && $details['base_factor'] > 0) {
            return (float) $details['base_factor'];
        }
        $kind = isset($line->purchase_id) ? 'purchase' : 'sale';
        $document = $line->{$kind.'_id'};
        $quantities = DB::table('stock_movement_lines as l')->join('stock_movements as m', 'm.id', '=', 'l.stock_movement_id')
            ->where('m.company_id', $context->companyId)->where('m.source_type', $kind)->where('m.source_id', $document)
            ->whereNull('m.reversal_of_id')->where('m.status', 'posted')->where('m.projection_mode', 'applied')
            ->where('l.attributes_json->commercial_line_id', $line->id)->where('l.uom_qty', '<>', 0)
            ->selectRaw('SUM(ABS(l.qty_base)) as base, SUM(ABS(l.uom_qty)) as entered')->first();
        if ($quantities && $quantities->entered > 0) return round($quantities->base / $quantities->entered, 8);
        // Fully material-backed bills have no second physical posting of their own.
        if (Schema::hasTable('material_settlements') && $line->qty > 0) {
            $sources = DB::table('material_settlements')->where('company_id', $context->companyId)
                ->where('settled_by_type', $kind)->where('settled_by_id', $document)->where('settled_by_line_id', $line->id)
                ->whereNull('released_at')->get();
            $factor = null;
            foreach ($sources as $source) {
                $table = $source->document_type === 'dc' ? 'delivery_challan_items' : 'goods_received_note_items';
                $item = DB::table($table)->where('company_id', $context->companyId)->find($source->document_item_id);
                if (!$item || (int) $item->unit_id !== (int) $line->unit_id || $item->qty <= 0 || $item->qty_base <= 0) return null;
                $candidate = round($item->qty_base / $item->qty, 8);
                if ($factor !== null && abs($candidate - $factor) > 0.00000001) return null;
                $factor = $candidate;
            }
            return $factor;
        }
        return null;
    }

    private function query(string $kind, ?int $party, int $product, CompanyContext $context)
    {
        $purchase = $kind === 'purchase';
        $documents = $purchase ? 'purchases' : 'sales';
        $lines = $purchase ? 'product_purchases' : 'product_sales';
        $partyTable = $purchase ? 'suppliers' : 'customers';
        $partyKey = $purchase ? 'supplier_id' : 'customer_id';
        $unitKey = $purchase ? 'purchase_unit_id' : 'sale_unit_id';
        $rate = $purchase ? 'net_unit_cost' : 'net_unit_price';
        $visible = ($purchase ? Purchase::class : Sale::class)::visibleIn($context)
            ->whereNotNull('posted_at')->whereNull('reversed_at')
            ->when($party !== null, fn ($q) => $q->where($partyKey, $party))->select('id');
        return DB::table($lines.' as l')->join($documents.' as d', 'd.id', '=', 'l.'.$kind.'_id')
            ->join($partyTable.' as p', 'p.id', '=', 'd.'.$partyKey)->join('products as item', 'item.id', '=', 'l.product_id')
            ->leftJoin('units as u', 'u.id', '=', 'l.'.$unitKey)
            ->where('l.company_id', $context->companyId)->where('d.company_id', $context->companyId)
            ->whereIn('d.id', $visible)->where('l.product_id', $product)
            ->orderByDesc('d.created_at')->orderByDesc('d.id')->orderByDesc('l.id')
            ->select(['l.*', 'd.reference_no', 'd.created_at as document_date', 'p.name as party_name', 'item.name as product_name',
                'u.unit_code', 'l.'.$unitKey.' as unit_id', 'l.'.$rate.' as rate', 'l.'.$rate.' as cost', 'l.total as amount'])
            ->selectRaw(Schema::hasColumn($documents, 'document_snapshot_json') ? 'd.document_snapshot_json' : 'NULL as document_snapshot_json');
    }
}
