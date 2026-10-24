<?php

namespace Tests\Support;

use Illuminate\Support\Facades\{DB, Schema};

/**
 * With ERP_TEST_EVIDENCE_DIR set, each test leaves a JSON record of the documents it posted (input) and the stock,
 * ledger, open items and GST they produced (result), plus the HTML of every printed document. The textile scenario
 * matrix report shows these per case. Recording never changes a test's outcome.
 */
trait RecordsTestEvidence
{
    /** header table => [label, party column, line table, line foreign key, rate column] */
    private const EVIDENCE_DOCUMENTS = [
        'purchases' => ['Purchase bill', 'supplier_id', 'product_purchases', 'purchase_id', 'net_unit_cost'],
        'sales' => ['Sales invoice', 'customer_id', 'product_sales', 'sale_id', 'net_unit_price'],
        'returns' => ['Credit note', 'customer_id', 'product_returns', 'return_id', 'net_unit_price'],
        'return_purchases' => ['Debit note', 'supplier_id', 'purchase_product_return', 'return_id', 'net_unit_cost'],
        'delivery_challans' => ['Delivery challan', 'customer_id', 'delivery_challan_items', 'delivery_challan_id', 'rate'],
        'goods_received_notes' => ['Goods received note', 'supplier_id', 'goods_received_note_items', 'goods_received_note_id', 'rate'],
        'sales_orders' => ['Sales order', 'customer_id', null, null, null],
        'quotations' => ['Quotation', 'customer_id', null, null, null],
        'job_work_orders' => ['Job work order', 'job_worker_party_id', null, null, null],
        'job_work_dispatches' => ['Material DC', null, 'job_work_dispatch_lines', 'dispatch_id', null],
        'job_work_receipts' => ['Job Work GRN', null, 'job_work_receipt_lines', 'receipt_id', null],
        'payments' => ['Payment', null, null, null, null],
    ];

    private const EVIDENCE_FIELDS = ['reference_no', 'challan_no', 'payment_reference', 'business_date', 'challan_date', 'purpose', 'receipt_kind',
        'total_qty', 'total_tax', 'shipping_cost', 'grand_total', 'paid_amount', 'amount', 'paying_method', 'expected_return_date', 'reversed_at'];

    private const EVIDENCE_LINE_FIELDS = ['qty', 'qty_base', 'accepted_qty', 'rejected_qty', 'loss_qty', 'received_qty', 'tax_rate', 'tax', 'total', 'value'];

    protected function recordEvidence(): void
    {
        $dir = getenv('ERP_TEST_EVIDENCE_DIR');
        if (!$dir) return;
        $key = substr(strrchr('\\'.static::class, '\\'), 1).'__'.$this->name();
        $evidence = ['test' => $key];
        try {
            $evidence['input'] = $this->evidenceDocuments();
            $evidence['result'] = $this->evidenceEffects();
            $evidence['prints'] = $this->evidencePrints($dir, $key);
        } catch (\Throwable $error) {
            $evidence['capture_error'] = $error->getMessage();
        }
        @mkdir($dir, 0777, true);
        file_put_contents($dir.'/'.$key.'.json', json_encode($evidence, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR));
    }

    private function evidenceName(string $table, $id): ?string
    {
        if (!$id || !Schema::hasTable($table)) return $id ? (string) $id : null;
        $row = DB::table($table)->find($id);
        $name = $row ? (string) ($row->company_name ?? null ?: $row->name ?? $id) : (string) $id;
        // Fixture items often share a name; the id keeps them apart.
        return $table === 'products' ? $name.' #'.$id : $name;
    }

    private function evidenceRow(object $row, array $fields): array
    {
        $out = [];
        foreach ($fields as $field) {
            if (!property_exists($row, $field) || $row->{$field} === null || $row->{$field} === '') continue;
            $out[$field] = is_numeric($row->{$field}) ? $row->{$field} + 0 : $row->{$field};
        }
        return $out;
    }

    private function evidenceDocuments(): array
    {
        $documents = [];
        foreach (self::EVIDENCE_DOCUMENTS as $table => [$label, $party, $lineTable, $foreignKey, $rate]) {
            if (!Schema::hasTable($table)) continue;
            foreach (DB::table($table)->orderBy('id')->limit(12)->get() as $row) {
                $document = ['document' => $label] + $this->evidenceRow($row, self::EVIDENCE_FIELDS);
                if ($party && isset($row->{$party})) {
                    $document['party'] = $this->evidenceName(str_contains($party, 'supplier') || $party === 'job_worker_party_id' ? 'suppliers' : 'customers', $row->{$party});
                }
                $transport = json_decode($row->attributes_json ?? 'null', true);
                if (is_array($transport)) {
                    $transport = array_intersect_key($transport, array_flip(['transport_name', 'lr_number', 'bale_count', 'landed_cost_method', 'supply_type', 'lut_number', 'cash_discount_percent']));
                    if ($transport) $document['details'] = $transport;
                }
                if ($lineTable && Schema::hasTable($lineTable) && Schema::hasColumn($lineTable, $foreignKey)) {
                    $document['lines'] = DB::table($lineTable)->where($foreignKey, $row->id)->limit(10)->get()->map(function ($line) use ($rate) {
                        $out = isset($line->product_id) ? ['item' => $this->evidenceName('products', $line->product_id)] : [];
                        if ($rate && isset($line->{$rate})) $out['rate'] = $line->{$rate} + 0;
                        return $out + $this->evidenceRow($line, self::EVIDENCE_LINE_FIELDS);
                    })->all();
                }
                $documents[] = $document;
            }
        }
        if (Schema::hasTable('stock_movements')) {
            // Stock entered directly (opening, adjustment, transfer) has no commercial document.
            foreach (DB::table('stock_movements')->whereNull('source_type')->orWhereIn('movement_type', ['opening', 'adjustment'])->limit(6)->get() as $movement) {
                $documents[] = ['document' => 'Stock entry ('.$movement->movement_type.')', 'business_date' => $movement->movement_date];
            }
        }
        return $documents;
    }

    private function evidenceEffects(): array
    {
        $result = [];
        if (Schema::hasTable('company_industry_settings') && $profile = DB::table('company_industry_settings')->first()) {
            $result['business_type'] = $profile->profile_key.' / '.$profile->subtype;
            if (Schema::hasTable('company_capabilities') && Schema::hasTable('capabilities')) {
                $result['features_on'] = DB::table('company_capabilities as s')->join('capabilities as c', 'c.id', '=', 's.capability_id')
                    ->where('s.company_id', $profile->company_id)->where('s.enabled', true)->orderBy('c.key')->pluck('c.key')->all();
            }
        }
        if (Schema::hasTable('product_warehouse') && Schema::hasTable('products')) {
            $stock = [];
            foreach (DB::table('product_warehouse')->where('qty', '!=', 0)->limit(40)->get() as $row) {
                $stock[$this->evidenceName('products', $row->product_id)][$this->evidenceName('warehouses', $row->warehouse_id)] = $row->qty + 0;
            }
            if ($stock) $result['stock_on_hand'] = $stock;
        }
        if (Schema::hasTable('stock_movement_lines')) {
            $values = DB::table('stock_movement_lines')->selectRaw('product_id, SUM(qty_base) AS qty, SUM(value) AS value')->groupBy('product_id')->limit(20)->get();
            foreach ($values as $row) if ((float) $row->qty || (float) $row->value) {
                $result['stock_value'][$this->evidenceName('products', $row->product_id)] = ['qty' => round((float) $row->qty, 4), 'value' => round((float) $row->value, 2)];
            }
        }
        if (Schema::hasTable('journal_items') && Schema::hasTable('chart_of_accounts')) {
            $ledger = DB::table('journal_items as i')->join('chart_of_accounts as a', 'a.id', '=', 'i.chart_of_account_id')
                ->selectRaw('COALESCE(a.sub_type, a.name) AS account, SUM(i.debit) AS debit, SUM(i.credit) AS credit')->groupBy('a.sub_type', 'a.name')->get();
            foreach ($ledger as $row) {
                $net = round((float) $row->debit - (float) $row->credit, 2);
                if ($net) $result['ledger_net_debit'][$row->account] = $net;
            }
            if ($ledger->isNotEmpty()) $result['journals_balance'] = round($ledger->sum('debit'), 2) === round($ledger->sum('credit'), 2);
        }
        if (Schema::hasTable('account_open_items')) {
            foreach (DB::table('account_open_items')->selectRaw('source_type, SUM(open_amount) AS open')->groupBy('source_type')->get() as $row) {
                $result['open_items'][$row->source_type] = round((float) $row->open, 2);
            }
        }
        if (Schema::hasTable('gst_transaction_projections') && Schema::hasColumn('gst_transaction_projections', 'section')) {
            foreach (DB::table('gst_transaction_projections')->get() as $row) {
                $section = $row->section ?? 'other';
                foreach (['taxable_value', 'cgst', 'sgst', 'igst'] as $field) {
                    if (isset($row->{$field})) $result['gst'][$section][$field] = round(($result['gst'][$section][$field] ?? 0) + (float) $row->{$field}, 2);
                }
            }
        }
        return $result;
    }

    private function evidencePrints(string $dir, string $key): array
    {
        if (!method_exists($this, 'context') || !class_exists(\App\Services\Documents\DocumentRenderingService::class)) return [];
        $prints = []; $renderer = app(\App\Services\Documents\DocumentRenderingService::class);
        @mkdir($dir.'/prints', 0777, true);
        foreach (['sale' => 'sales', 'purchase' => 'purchases'] as $kind => $table) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'document_snapshot_json')) continue;
            foreach (DB::table($table)->whereNotNull('posted_at')->whereNotNull('document_snapshot_json')->orderBy('id')->limit(3)->pluck('id') as $id) {
                try {
                    $html = $renderer->html($renderer->dto($kind, $id, $this->context()), $renderer->profile($kind, $this->context(), null, 'a4'));
                } catch (\Throwable) { continue; }
                file_put_contents($file = $dir.'/prints/'.$key.'__'.$kind.'-'.$id.'.html', $html);
                $prints[] = basename($file);
            }
        }
        if (Schema::hasTable('job_work_dispatches')) {
            foreach (DB::table('job_work_dispatches')->orderBy('id')->limit(2)->pluck('id') as $id) {
                try { $response = $this->get('/operations/dispatch/'.$id.'/print'); } catch (\Throwable) { continue; }
                if ($response->getStatusCode() !== 200) continue;
                file_put_contents($file = $dir.'/prints/'.$key.'__material-dc-'.$id.'.html', $response->getContent());
                $prints[] = basename($file);
            }
        }
        return $prints;
    }
}
