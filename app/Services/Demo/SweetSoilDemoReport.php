<?php

namespace App\Services\Demo;

use App\Services\Platform\CompanyContext;
use Illuminate\Support\Facades\DB;

/** Reads the demo company's posted documents, stock ledger and journals for the end-to-end walkthrough. Read only. */
class SweetSoilDemoReport
{
    private int $company;

    public function build(CompanyContext $context): array
    {
        $this->company = $context->companyId;
        $company = DB::table('companies')->find($this->company);
        $purchases = $this->purchases();
        $production = $this->production();
        $sales = $this->sales();
        $ledger = $this->ledger();
        $tax = fn (string $sub) => $ledger->firstWhere('sub_type', $sub)['net'] ?? 0;
        $output = -($tax('output_tax_cgst') + $tax('output_tax_sgst') + $tax('output_tax_igst'));
        $input = $tax('input_tax_cgst') + $tax('input_tax_sgst') + $tax('input_tax_igst');

        return [
            'generated_at' => now('Asia/Kolkata')->format('d M Y, h:i A'),
            'app_url' => rtrim(config('app.url'), '/') === 'http://localhost' ? 'http://localhost:8080' : rtrim(config('app.url'), '/'),
            'company' => $company,
            'gstin' => DB::table('tax_registrations')->where('company_id', $this->company)->whereNull('effective_to')->value('gstin'),
            'masters' => $this->masters(),
            'boms' => $this->boms(),
            'purchases' => $purchases,
            'production' => $production,
            'sales' => $sales,
            'channels' => $this->channels($sales),
            'payments' => $this->payments(),
            'batches' => $this->batches(),
            'stock' => $this->stock(),
            'open_items' => $this->openItems(),
            'ledger' => $ledger,
            'gst' => ['output' => $output, 'input' => $input, 'payable' => $output - $input,
                'output_split' => ['CGST' => -$tax('output_tax_cgst'), 'SGST' => -$tax('output_tax_sgst'), 'IGST' => -$tax('output_tax_igst')],
                'input_split' => ['CGST' => $tax('input_tax_cgst'), 'SGST' => $tax('input_tax_sgst'), 'IGST' => $tax('input_tax_igst')]],
            'kpi' => [
                'bought' => $purchases->sum('total'), 'packed_value' => $production->sum('total_cost'), 'packs' => $production->sum('qty'),
                'revenue' => $sales->sum('taxable'), 'cogs' => $sales->sum('cogs'), 'margin' => $sales->sum('taxable') - $sales->sum('cogs'),
                'receivable' => $sales->sum('balance'), 'payable' => $purchases->sum('balance'),
                'stock_value' => DB::table('stock_movement_lines')->where('company_id', $this->company)->sum('value'),
            ],
        ];
    }

    private function masters(): array
    {
        $count = fn (string $table) => DB::table($table)->where('company_id', $this->company)->count();
        $groups = DB::table('customers as c')->join('customer_groups as g', 'g.id', '=', 'c.customer_group_id')->where('c.company_id', $this->company)
            ->orderBy('c.id')->get(['c.name', 'c.city', 'c.state', 'c.tax_no', 'c.credit_days', 'g.name as channel']);

        return ['customers' => $groups, 'suppliers' => DB::table('suppliers')->where('company_id', $this->company)->orderBy('id')
                ->get(['name', 'city', 'vat_number', 'credit_days']),
            'products' => DB::table('products as p')->join('categories as c', 'c.id', '=', 'p.category_id')->join('units as u', 'u.id', '=', 'p.unit_id')
                ->leftJoin('tax_categories as t', 't.id', '=', 'p.tax_category_id')->where('p.company_id', $this->company)->orderBy('p.id')
                ->get(['p.code', 'p.name', 'c.name as category', 'u.unit_code as unit', 'p.hsn_code', 't.name as gst', 'p.price', 'p.is_batch']),
            'counts' => ['Items' => $count('products'), 'Customers' => $count('customers'), 'Suppliers' => $count('suppliers'),
                'Channels' => DB::table('customer_groups')->where('company_id', $this->company)->where('name', '<>', 'General')->count(), 'Packing BOMs' => $count('boms')]];
    }

    private function boms()
    {
        return DB::table('boms as b')->join('products as p', 'p.id', '=', 'b.product_id')->where('b.company_id', $this->company)->orderBy('b.id')
            ->get(['b.id', 'b.code', 'b.output_qty', 'p.name'])->map(fn ($bom) => (array) $bom + ['lines' => DB::table('bom_lines as l')
                ->join('products as p', 'p.id', '=', 'l.component_product_id')->join('units as u', 'u.id', '=', 'l.uom_id')
                ->where('l.bom_id', $bom->id)->orderBy('l.line_no')->get(['p.name', 'l.qty', 'u.unit_code as unit', 'l.scrap_percent'])]);
    }

    private function purchases()
    {
        return DB::table('purchases as d')->join('suppliers as s', 's.id', '=', 'd.supplier_id')->where('d.company_id', $this->company)
            ->whereNull('d.reversed_at')->orderBy('d.created_at')->orderBy('d.id')
            ->get(['d.id', 'd.reference_no', 'd.supplier_invoice_no', 'd.created_at', 'd.grand_total', 'd.total_tax', 'd.paid_amount', 'd.document_snapshot_json', 's.name as party'])
            ->map(fn ($d) => $this->document($d));
    }

    private function sales()
    {
        return DB::table('sales as d')->join('customers as c', 'c.id', '=', 'd.customer_id')
            ->leftJoin('customer_groups as g', 'g.id', '=', 'c.customer_group_id')->where('d.company_id', $this->company)
            ->whereNull('d.reversed_at')->orderBy('d.created_at')->orderBy('d.id')
            ->get(['d.id', 'd.reference_no', 'd.created_at', 'd.grand_total', 'd.total_tax', 'd.paid_amount', 'd.document_snapshot_json', 'c.name as party', 'g.name as channel'])
            ->map(function ($d) {
                $row = $this->document($d);
                $row['channel'] = $d->channel ?? 'General';
                $row['batches'] = DB::table('product_sales as l')->join('product_batches as b', 'b.id', '=', 'l.product_batch_id')
                    ->where('l.sale_id', $d->id)->pluck('b.batch_no', 'l.product_id')->all();
                // Cost of goods sold is the value the sale's stock issue actually took out of inventory.
                $row['cogs'] = -(float) DB::table('stock_movement_lines as l')->join('stock_movements as m', 'm.id', '=', 'l.stock_movement_id')
                    ->where('m.company_id', $this->company)->where('m.source_type', 'sale')->where('m.source_id', $d->id)
                    ->whereNull('m.reversal_of_id')->sum('l.value');
                $row['margin'] = $row['taxable'] - $row['cogs'];
                return $row;
            });
    }

    private function document(object $d): array
    {
        $snapshot = json_decode($d->document_snapshot_json ?? '{}', true) ?: [];
        $lines = collect($snapshot['lines'] ?? []);

        return ['id' => $d->id, 'number' => $d->reference_no, 'supplier_invoice' => $d->supplier_invoice_no ?? null,
            'date' => substr($d->created_at, 0, 10), 'due' => $snapshot['due_date'] ?? null, 'party' => $d->party,
            'place' => $snapshot['place_of_supply'] ?? null, 'lines' => $lines->all(),
            'taxable' => (float) $lines->sum('taxable_value'), 'cgst' => (float) $lines->sum('cgst'), 'sgst' => (float) $lines->sum('sgst'),
            'igst' => (float) $lines->sum('igst'), 'total' => (float) $d->grand_total, 'paid' => (float) $d->paid_amount,
            'balance' => round((float) $d->grand_total - (float) $d->paid_amount, 2)];
    }

    private function production()
    {
        return DB::table('production_orders as o')->join('production_outputs as x', 'x.production_order_id', '=', 'o.id')
            ->join('products as p', 'p.id', '=', 'x.product_id')->where('o.company_id', $this->company)->where('o.status', 'completed')
            ->orderBy('o.business_date')->orderBy('o.id')
            ->get(['o.id', 'o.reference_no', 'o.business_date', 'o.direct_cost', 'o.total_cost', 'x.qty', 'x.stock_details_json', 'p.name', 'p.code'])
            ->map(function ($o) {
                $batch = json_decode($o->stock_details_json, true)['batch'] ?? [];
                $materials = DB::table('stock_movement_lines as l')->join('stock_movements as m', 'm.id', '=', 'l.stock_movement_id')
                    ->join('products as p', 'p.id', '=', 'l.product_id')->join('units as u', 'u.id', '=', 'p.unit_id')
                    ->where('m.company_id', $this->company)->where('m.source_type', 'production_orders')->where('m.source_id', $o->id)
                    ->where('m.movement_type', 'production_consume')->get(['p.name', 'u.unit_code as unit', 'l.qty_base', 'l.value'])
                    ->map(fn ($l) => ['name' => $l->name, 'unit' => $l->unit, 'qty' => -(float) $l->qty_base, 'value' => -(float) $l->value]);
                return ['id' => $o->id, 'number' => $o->reference_no, 'date' => $o->business_date, 'name' => $o->name, 'code' => $o->code,
                    'qty' => (float) $o->qty, 'labour' => (float) $o->direct_cost, 'material' => (float) $o->total_cost - (float) $o->direct_cost,
                    'total_cost' => (float) $o->total_cost, 'unit_cost' => round((float) $o->total_cost / (float) $o->qty, 2),
                    'batch' => $batch['batch_no'] ?? null, 'expiry' => $batch['expired_date'] ?? null, 'mrp' => $batch['mrp'] ?? null,
                    'materials' => $materials->all()];
            });
    }

    private function channels($sales)
    {
        return $sales->groupBy('channel')->map(fn ($rows, $channel) => ['channel' => $channel, 'invoices' => $rows->count(),
            'revenue' => $rows->sum('taxable'), 'cogs' => $rows->sum('cogs'), 'margin' => $rows->sum('margin'),
            'margin_pct' => $rows->sum('taxable') ? round($rows->sum('margin') / $rows->sum('taxable') * 100, 1) : 0,
            'gst' => $rows->sum('igst') > 0 ? 'IGST (inter-state)' : 'CGST + SGST', 'outstanding' => $rows->sum('balance')])->values();
    }

    private function payments()
    {
        return DB::table('payments as y')->leftJoin('sales as s', 's.id', '=', 'y.sale_id')->leftJoin('purchases as p', 'p.id', '=', 'y.purchase_id')
            ->leftJoin('customers as c', 'c.id', '=', 's.customer_id')->leftJoin('suppliers as v', 'v.id', '=', 'p.supplier_id')
            ->where('y.company_id', $this->company)->orderBy('y.payment_at')->orderBy('y.id')
            ->get(['y.payment_reference', 'y.payment_at', 'y.amount', 'y.paying_method', 'y.payment_note', 'y.sale_id',
                's.reference_no as sale_no', 'p.reference_no as purchase_no', 'c.name as customer', 'v.name as supplier']);
    }

    private function batches()
    {
        return DB::table('product_batches as b')->join('products as p', 'p.id', '=', 'b.product_id')->where('b.company_id', $this->company)
            ->orderBy('b.expired_date')->get(['b.id', 'b.batch_no', 'b.mfg_date', 'b.expired_date', 'b.mrp', 'p.name'])
            ->map(fn ($b) => (array) $b + ['qty' => (float) DB::table('product_warehouse')->where('company_id', $this->company)
                ->where('product_batch_id', $b->id)->sum('qty'), 'days_left' => (int) now()->startOfDay()->diffInDays($b->expired_date, false)]);
    }

    private function stock()
    {
        return DB::table('stock_movement_lines as l')->join('products as p', 'p.id', '=', 'l.product_id')
            ->join('categories as c', 'c.id', '=', 'p.category_id')->join('units as u', 'u.id', '=', 'p.unit_id')
            ->where('l.company_id', $this->company)->groupBy('p.id', 'p.code', 'p.name', 'c.name', 'u.unit_code')->orderBy('p.id')
            ->selectRaw('p.code, p.name, c.name AS category, u.unit_code AS unit, SUM(CASE WHEN l.qty_base > 0 THEN l.qty_base ELSE 0 END) AS qty_in,
                -SUM(CASE WHEN l.qty_base < 0 THEN l.qty_base ELSE 0 END) AS qty_out, SUM(l.qty_base) AS qty, SUM(l.value) AS value')->get();
    }

    private function openItems()
    {
        return DB::table('account_open_items as o')->leftJoin('customers as c', fn ($j) => $j->on('c.id', '=', 'o.party_id')->where('o.party_type', 'customer'))
            ->leftJoin('suppliers as s', fn ($j) => $j->on('s.id', '=', 'o.party_id')->where('o.party_type', 'supplier'))
            ->where('o.company_id', $this->company)->where('o.open_amount', '>', 0.004)->orderBy('o.due_date')
            ->get(['o.party_type', 'o.document_no', 'o.document_date', 'o.due_date', 'o.original_amount', 'o.open_amount', DB::raw('COALESCE(c.name, s.name) AS party')])
            ->map(fn ($o) => (array) $o + ['overdue' => $o->due_date && $o->due_date < now()->toDateString()]);
    }

    private function ledger()
    {
        return DB::table('journal_items as i')->join('journal_entries as j', 'j.id', '=', 'i.journal_entry_id')
            ->join('chart_of_accounts as a', 'a.id', '=', 'i.chart_of_account_id')->where('i.company_id', $this->company)
            ->groupBy('a.code', 'a.name', 'a.type', 'a.sub_type')->orderBy('a.code')
            ->selectRaw('a.code, a.name, a.type, a.sub_type, SUM(i.debit) AS debit, SUM(i.credit) AS credit')->get()
            ->map(fn ($a) => (array) $a + ['net' => round((float) $a->debit - (float) $a->credit, 2)])->filter(fn ($a) => abs($a['net']) >= 0.005)->values();
    }
}
