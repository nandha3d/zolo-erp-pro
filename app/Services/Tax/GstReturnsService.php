<?php

namespace App\Services\Tax;

use App\Services\Platform\CompanyContext;
use App\Support\LedgerAmount;
use Illuminate\Support\Facades\DB;

/**
 * Return figures built from posted snapshots: GSTR-3B (with ITC set-off in the Sec 49A / Rule 88A order) and Form GST ITC-04
 * (goods sent to and received from job workers). They are the figures to file; the portal upload itself happens on the GST portal.
 */
class GstReturnsService
{
    private const HEADS = ['igst', 'cgst', 'sgst', 'cess'];

    public function gstr3b(CompanyContext $context, string $from, string $to): array
    {
        $rows = app(GstProjectionService::class)->report($context, $from, $to);
        $zero = fn () => ['taxable_value' => 0, 'igst' => 0, 'cgst' => 0, 'sgst' => 0, 'cess' => 0];
        $t = ['3.1(a)' => $zero(), '3.1(b)' => $zero(), '3.1(c)' => $zero(), '3.1(d)' => $zero(), '3.1(e)' => $zero(),
            '4(A)(3)' => $zero(), '4(A)(5)' => $zero(), '4(B)(1)' => $zero(), '5' => ['inter_state' => 0, 'intra_state' => 0]];
        $interB2c = [];
        $add = function (string $box, array $line, int $sign) use (&$t) {
            foreach (['taxable_value', ...self::HEADS] as $field) $t[$box][$field] += $sign * LedgerAmount::units($line[$field] ?? 0);
        };
        foreach ($rows as $row) {
            // Credit notes and reversals carry sign -1 on their snapshot.
            $sign = ($row['snapshot']['sign'] ?? 1) < 0 ? -1 : 1;
            foreach ($row['snapshot']['lines'] ?? [] as $line) {
                $type = $line['supply_type'] ?? 'taxable';
                if ($row['section'] !== 'inward') {
                    $box = match ($type) { 'zero_rated' => '3.1(b)', 'nil_rated', 'exempt' => '3.1(c)', 'non_gst' => '3.1(e)', default => '3.1(a)' };
                    $add($box, $line, $sign);
                    if ($row['section'] === 'b2c' && (float) ($line['igst'] ?? 0) > 0) {
                        $place = $line['place_of_supply'] ?? '';
                        $interB2c[$place]['taxable_value'] = ($interB2c[$place]['taxable_value'] ?? 0) + $sign * LedgerAmount::units($line['taxable_value']);
                        $interB2c[$place]['igst'] = ($interB2c[$place]['igst'] ?? 0) + $sign * LedgerAmount::units($line['igst']);
                    }
                    continue;
                }
                if (!empty($line['reverse_charge'])) {
                    $add('3.1(d)', $line, $sign);
                    $add($line['input_credit_allowed'] ? '4(A)(3)' : '4(B)(1)', $line, $sign);
                    continue;
                }
                $taxed = array_sum(array_map(fn ($head) => (float) ($line[$head] ?? 0), self::HEADS)) > 0;
                if (!$taxed) {
                    // Exempt, nil-rated, non-GST and composition-dealer purchases.
                    $t['5'][($line['igst'] ?? 0) || ($row['snapshot']['seller_state'] ?? '') !== ($row['snapshot']['place_of_supply'] ?? '') ? 'inter_state' : 'intra_state']
                        += $sign * LedgerAmount::units($line['taxable_value']);
                    continue;
                }
                // CBIC (Notification 14/2022): all ITC goes to 4(A); Sec 17(5) blocked credit is then reversed in 4(B)(1).
                $add('4(A)(5)', $line, $sign);
                if (!$line['input_credit_allowed']) $add('4(B)(1)', $line, $sign);
            }
        }
        $credit = []; $liability = [];
        foreach (self::HEADS as $head) {
            $credit[$head] = $t['4(A)(3)'][$head] + $t['4(A)(5)'][$head] - $t['4(B)(1)'][$head];
            // Reverse-charge tax is paid in cash only; ITC is set off against forward-charge liability.
            $liability[$head] = $t['3.1(a)'][$head] + $t['3.1(b)'][$head];
        }
        [$used, $cash, $carry] = $this->setOff($liability, $credit);
        foreach (self::HEADS as $head) $cash[$head] += $t['3.1(d)'][$head];
        $decimal = fn ($units) => (float) LedgerAmount::decimal($units);
        $map = fn (array $fields) => array_map($decimal, $fields);
        $c = ['4(C) net ITC' => $map($credit)] + array_map(fn ($box) => is_array($box) ? $map($box) : $box, $t);
        return ['period' => [$from, $to], 'boxes' => $c, '3.2_inter_state_unregistered' => array_map($map, $interB2c),
            '6.1' => ['liability' => $map(array_combine(self::HEADS, array_map(fn ($h) => $liability[$h] + $t['3.1(d)'][$h], self::HEADS))),
                'paid_through_itc' => array_map($map, $used), 'paid_in_cash' => $map($cash), 'credit_carried_forward' => $map($carry)]];
    }

    /**
     * Rule 88A: IGST credit first against IGST, then CGST and SGST; CGST credit against CGST then IGST; SGST credit against SGST
     * then IGST. CGST credit never pays SGST, nor SGST credit CGST. Cess credit pays cess only.
     */
    public function setOff(array $liability, array $credit): array
    {
        $used = []; $owed = $liability; $left = $credit;
        $use = function (string $from, string $to) use (&$owed, &$left, &$used) {
            $amount = max(0, min($left[$from], $owed[$to]));
            if (!$amount) return;
            $left[$from] -= $amount; $owed[$to] -= $amount; $used[$from][$to] = ($used[$from][$to] ?? 0) + $amount;
        };
        foreach ([['igst', 'igst'], ['igst', 'cgst'], ['igst', 'sgst'], ['cgst', 'cgst'], ['cgst', 'igst'], ['sgst', 'sgst'], ['sgst', 'igst'], ['cess', 'cess']] as [$from, $to]) {
            $use($from, $to);
        }
        return [$used, array_map(fn ($v) => max(0, $v), $owed), array_map(fn ($v) => max(0, $v), $left)];
    }

    /** Form GST ITC-04: table 4 goods sent, 5A goods received back (with loss), 5C supplied from the job worker's premises. */
    public function itc04(CompanyContext $context, string $from, string $to): array
    {
        $worker = function (int $supplierId) use ($context) {
            $party = DB::table('suppliers')->where('id', $supplierId)->first(['name', 'company_name', 'vat_number']);
            $profile = DB::table('party_tax_profiles')->where('company_id', $context->companyId)->where('party_type', 'supplier')->where('party_id', $supplierId)->first();
            $gstin = $profile->gstin ?? $party->vat_number ?? null;
            return ['job_worker' => $party->company_name ?? null ?: $party->name ?? '', 'job_worker_gstin' => $gstin ?: '',
                'job_worker_state' => $gstin ? substr($gstin, 0, 2) : ($profile->state_code ?? '')];
        };
        $uqc = fn ($productId) => strtoupper((string) (DB::table('products as p')->leftJoin('units as u', 'u.id', '=', 'p.unit_id')->where('p.id', $productId)->value('u.unit_code') ?: 'OTH'));
        $item = fn ($productId) => DB::table('products')->where('id', $productId)->first(['name', 'hsn_code', 'tax_category_id']);
        $rate = function ($product, string $date) {
            return $product && $product->tax_category_id ? (float) (app(GstSetupService::class)->rateFor($product, $date)['rate'] ?? 0) : 0.0;
        };
        $capital = \Illuminate\Support\Facades\Schema::hasColumn('products', 'is_capital_goods');
        $sent = []; $received = []; $supplied = [];
        $dispatches = DB::table('job_work_dispatches as d')->join('job_work_orders as o', 'o.id', '=', 'd.job_work_order_id')
            ->where('d.company_id', $context->companyId)->whereNull('d.reversed_at')->whereBetween('d.business_date', [$from, $to])
            ->orderBy('d.business_date')->get(['d.id', 'd.reference_no', 'd.business_date', 'o.job_worker_party_id']);
        foreach ($dispatches as $dispatch) {
            foreach (DB::table('job_work_dispatch_lines')->where('dispatch_id', $dispatch->id)->get() as $line) {
                $product = $item($line->product_id); $date = substr((string) $dispatch->business_date, 0, 10);
                $sent[] = $worker((int) $dispatch->job_worker_party_id) + ['challan_no' => $dispatch->reference_no, 'challan_date' => $date,
                    'description' => $product->name ?? '', 'hsn' => $product->hsn_code ?? '', 'uqc' => $uqc($line->product_id), 'qty' => (float) $line->qty_base,
                    'taxable_value' => round((float) $line->value, 2), 'goods_type' => $capital && DB::table('products')->where('id', $line->product_id)->value('is_capital_goods') ? 'Capital goods' : 'Inputs',
                    'gst_rate' => $rate($product, $date)];
            }
        }
        $receipts = DB::table('job_work_receipts as r')->join('job_work_dispatches as d', 'd.id', '=', 'r.dispatch_id')->join('job_work_orders as o', 'o.id', '=', 'd.job_work_order_id')
            ->where('r.company_id', $context->companyId)->whereNull('r.reversed_at')->whereBetween('r.business_date', [$from, $to])
            ->orderBy('r.business_date')->get(['r.id', 'r.reference_no', 'r.business_date', 'd.reference_no as challan_no', 'd.business_date as challan_date', 'o.job_worker_party_id']);
        foreach ($receipts as $receipt) {
            foreach (DB::table('job_work_receipt_lines as l')->join('job_work_dispatch_lines as dl', 'dl.id', '=', 'l.dispatch_line_id')
                ->where('l.receipt_id', $receipt->id)->get(['dl.product_id', 'l.received_qty', 'l.loss_qty']) as $line) {
                $product = $item($line->product_id);
                $received[] = $worker((int) $receipt->job_worker_party_id) + ['original_challan_no' => $receipt->challan_no,
                    'original_challan_date' => substr((string) $receipt->challan_date, 0, 10), 'receipt_no' => $receipt->reference_no,
                    'receipt_date' => substr((string) $receipt->business_date, 0, 10), 'description' => $product->name ?? '', 'uqc' => $uqc($line->product_id),
                    'qty' => (float) $line->received_qty, 'loss_and_waste_qty' => (float) $line->loss_qty];
            }
        }
        if (\Illuminate\Support\Facades\Schema::hasColumn('warehouses', 'external_job_order_id')) {
            $sales = DB::table('sales as s')->join('warehouses as w', 'w.id', '=', 's.warehouse_id')->join('job_work_orders as o', 'o.id', '=', 'w.external_job_order_id')
                ->where('s.company_id', $context->companyId)->whereNull('s.reversed_at')->whereNotNull('s.posted_at')
                ->whereBetween('s.created_at', [$from.' 00:00:00', $to.' 23:59:59'])->get(['s.id', 's.reference_no', 's.created_at', 'o.job_worker_party_id']);
            foreach ($sales as $sale) {
                foreach (DB::table('product_sales')->where('sale_id', $sale->id)->get(['product_id', 'qty']) as $line) {
                    $supplied[] = $worker((int) $sale->job_worker_party_id) + ['invoice_no' => $sale->reference_no, 'invoice_date' => substr((string) $sale->created_at, 0, 10),
                        'description' => $item($line->product_id)->name ?? '', 'uqc' => $uqc($line->product_id), 'qty' => (float) $line->qty];
                }
            }
        }
        return ['period' => [$from, $to], 'table_4_sent' => $sent, 'table_5a_received_back' => $received, 'table_5c_supplied_from_job_worker' => $supplied];
    }

    /** One CSV per table, as the GST offline tool's sheets expect them. */
    public function csv(array $rows): string
    {
        if (!$rows) return '';
        $out = fopen('php://temp', 'r+');
        fputcsv($out, array_keys($rows[0]));
        foreach ($rows as $row) fputcsv($out, array_values($row));
        rewind($out);
        return stream_get_contents($out);
    }
}
