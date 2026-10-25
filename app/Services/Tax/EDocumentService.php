<?php

namespace App\Services\Tax;

use App\Models\Company;
use App\Models\Sale;
use App\Services\Platform\CompanyContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Upload files for the government portals, built from posted documents: the e-invoice JSON (IRP schema version 1.1)
 * for a B2B, SEZ or export invoice, and the e-way bill bulk-generation JSON for an invoice or a job work Material DC.
 * Posting them through an API needs a GSP or direct-API account; these files are uploaded on the portal instead.
 */
class EDocumentService
{
    public const EWAY_THRESHOLD = 50000;

    public function einvoice(int $saleId, CompanyContext $context): array
    {
        $sale = Sale::visibleIn($context)->whereNotNull('posted_at')->findOrFail($saleId);
        $tax = $sale->tax_snapshot_json ?? throw ValidationException::withMessages(['document' => 'Only a GST invoice has an e-invoice.']);
        $supply = $tax['supply_type'] ?? 'domestic';
        if ($supply === 'domestic' && empty($tax['buyer_gstin'])) {
            throw ValidationException::withMessages(['document' => 'E-invoicing applies to invoices to registered buyers, SEZ and exports, not B2C.']);
        }
        $seller = $this->seller($context); $buyer = $this->party('customer', $sale->customer_id, $context);
        $export = str_starts_with($supply, 'export');
        $items = []; $totals = ['AssVal' => 0, 'CgstVal' => 0, 'SgstVal' => 0, 'IgstVal' => 0, 'CesVal' => 0];
        foreach ($sale->productSales as $i => $line) {
            $s = $line->tax_snapshot_json; $product = DB::table('products')->find($line->product_id);
            $gross = round((float) $s['taxable_value'] + (float) $s['discount'], 2);
            $item = ['SlNo' => (string) ($i + 1), 'PrdDesc' => $s['product_name'], 'IsServc' => in_array($product->type, ['service', 'digital'], true) ? 'Y' : 'N',
                'HsnCd' => $s['hsn_sac'], 'Qty' => round((float) $s['qty'], 3), 'Unit' => $this->uqc($line->sale_unit_id ?: $product->unit_id),
                'UnitPrice' => round((float) $line->net_unit_price, 3), 'TotAmt' => $gross, 'Discount' => round((float) $s['discount'], 2),
                'AssAmt' => round((float) $s['taxable_value'], 2), 'GstRt' => (float) $s['rate'], 'IgstAmt' => round((float) $s['igst'], 2),
                'CgstAmt' => round((float) $s['cgst'], 2), 'SgstAmt' => round((float) $s['sgst'], 2), 'CesRt' => (float) ($s['cess_rate'] ?? 0),
                'CesAmt' => round((float) ($s['cess'] ?? 0), 2)];
            $item['TotItemVal'] = round($item['AssAmt'] + $item['IgstAmt'] + $item['CgstAmt'] + $item['SgstAmt'] + $item['CesAmt'], 2);
            foreach (['AssVal' => 'AssAmt', 'CgstVal' => 'CgstAmt', 'SgstVal' => 'SgstAmt', 'IgstVal' => 'IgstAmt', 'CesVal' => 'CesAmt'] as $total => $field) $totals[$total] += $item[$field];
            $items[] = $item;
        }
        $invoice = ['Version' => '1.1',
            'TranDtls' => ['TaxSch' => 'GST', 'SupTyp' => ['domestic' => 'B2B', 'sez_lut' => 'SEZWOP', 'sez_igst' => 'SEZWP', 'export_lut' => 'EXPWOP', 'export_igst' => 'EXPWP'][$supply],
                'RegRev' => !empty($tax['reverse_charge']) ? 'Y' : 'N', 'IgstOnIntra' => 'N'],
            'DocDtls' => ['Typ' => 'INV', 'No' => substr((string) $sale->reference_no, 0, 16), 'Dt' => $sale->created_at->format('d/m/Y')],
            'SellerDtls' => ['Gstin' => $tax['registration_gstin'], 'LglNm' => $tax['seller_name'], 'Addr1' => $seller['address'], 'Loc' => $seller['city'],
                'Pin' => $seller['pin'], 'Stcd' => $seller['state']],
            'BuyerDtls' => ['Gstin' => $export ? 'URP' : $tax['buyer_gstin'], 'LglNm' => $buyer['name'], 'Pos' => $export ? '96' : $tax['place_of_supply'],
                'Addr1' => $buyer['address'], 'Loc' => $buyer['city'], 'Pin' => $export ? 999999 : $buyer['pin'], 'Stcd' => $export ? '96' : substr((string) $tax['buyer_gstin'], 0, 2)],
            'ItemList' => $items,
            'ValDtls' => array_map(fn ($v) => round($v, 2), $totals) + ['Discount' => 0, 'OthChrg' => 0,
                'RndOffAmt' => round((float) ($sale->attributes_json['round_off'] ?? 0), 2), 'TotInvVal' => round((float) $sale->grand_total, 2)]];
        if (!empty($sale->attributes_json['dispatched_from'])) $invoice['DispDtls'] = ['Nm' => $sale->attributes_json['dispatched_from'], 'Addr1' => $sale->attributes_json['dispatched_from'],
            'Loc' => $seller['city'], 'Pin' => $seller['pin'], 'Stcd' => $seller['state']];
        $this->requireFields(['seller PIN code (Company profile)' => $seller['pin'], 'buyer PIN code (customer master)' => $export ? 1 : $buyer['pin']]);
        return $invoice;
    }

    /** E-way bill bulk JSON for an invoice ('sale') or a job work Material DC ('dispatch'). */
    public function ewayBill(string $kind, int $id, CompanyContext $context, array $transport = []): array
    {
        $seller = $this->seller($context); $gstin = DB::table('tax_registrations')->where('company_id', $context->companyId)->value('gstin');
        if ($kind === 'sale') {
            $sale = Sale::visibleIn($context)->whereNotNull('posted_at')->findOrFail($id); $tax = $sale->tax_snapshot_json ?? [];
            $buyer = $this->party('customer', $sale->customer_id, $context); $export = str_starts_with($tax['supply_type'] ?? '', 'export');
            $lines = $sale->productSales->map(fn ($line) => ['name' => $line->tax_snapshot_json['product_name'], 'hsn' => $line->tax_snapshot_json['hsn_sac'],
                'qty' => (float) $line->qty, 'unit' => $this->uqc($line->sale_unit_id ?: DB::table('products')->where('id', $line->product_id)->value('unit_id')),
                'taxable' => (float) $line->tax_snapshot_json['taxable_value'], 'rate' => (float) $line->tax_snapshot_json['rate'],
                'cgst' => (float) $line->tax_snapshot_json['cgst'], 'sgst' => (float) $line->tax_snapshot_json['sgst'], 'igst' => (float) $line->tax_snapshot_json['igst']])->all();
            $doc = ['docType' => 'INV', 'subSupplyType' => $export ? 3 : 1, 'docNo' => $sale->reference_no, 'docDate' => $sale->created_at->format('d/m/Y'),
                'to' => array_merge($buyer, ['gstin' => $export ? 'URP' : ($tax['buyer_gstin'] ?: 'URP'), 'state' => $export ? '96' : ($tax['place_of_supply'] ?? $buyer['state'])]),
                'total' => (float) $sale->grand_total, 'attributes' => $sale->attributes_json ?? []];
        } elseif ($kind === 'dispatch') {
            $dispatch = DB::table('job_work_dispatches')->where('company_id', $context->companyId)->whereNull('reversed_at')->find($id)
                ?? throw ValidationException::withMessages(['document' => 'Select an active Material DC.']);
            $order = DB::table('job_work_orders')->find($dispatch->job_work_order_id);
            $worker = $this->party('supplier', (int) $order->job_worker_party_id, $context);
            $lines = DB::table('job_work_dispatch_lines')->where('dispatch_id', $id)->get()->map(function ($line) {
                $product = DB::table('products')->find($line->product_id);
                $rate = $product->tax_category_id ? (float) (app(GstSetupService::class)->rateFor($product)['rate'] ?? 0) : 0.0;
                // Goods sent for job work carry no tax; the rate is shown, the tax is not charged.
                return ['name' => $product->name, 'hsn' => $product->hsn_code, 'qty' => (float) $line->qty_base, 'unit' => $this->uqc($product->unit_id),
                    'taxable' => round((float) $line->value, 2), 'rate' => $rate, 'cgst' => 0, 'sgst' => 0, 'igst' => 0];
            })->all();
            $details = json_decode($dispatch->details_json ?? '{}', true) ?: [];
            $doc = ['docType' => 'CHL', 'subSupplyType' => 4, 'docNo' => $dispatch->reference_no, 'docDate' => date('d/m/Y', strtotime($dispatch->business_date)),
                'to' => $worker, 'total' => array_sum(array_column($lines, 'taxable')), 'attributes' => ['vehicle_number' => $details['transport']['vehicle_number'] ?? null,
                    'transport_name' => $details['transport']['transporter_name'] ?? null]];
        } else {
            throw ValidationException::withMessages(['document' => 'E-way bills are for invoices and Material DCs.']);
        }
        $transport = $transport + $doc['attributes'];
        $interState = $seller['state'] !== $doc['to']['state'];
        $bill = ['userGstin' => $gstin, 'supplyType' => 'O', 'subSupplyType' => $doc['subSupplyType'], 'subSupplyDesc' => '', 'docType' => $doc['docType'],
            'docNo' => $doc['docNo'], 'docDate' => $doc['docDate'],
            'fromGstin' => $gstin, 'fromTrdName' => $seller['name'], 'fromAddr1' => $seller['address'], 'fromAddr2' => '', 'fromPlace' => $seller['city'],
            'fromPincode' => $seller['pin'], 'actFromStateCode' => (int) $seller['state'], 'fromStateCode' => (int) $seller['state'],
            'toGstin' => $doc['to']['gstin'] ?: 'URP', 'toTrdName' => $doc['to']['name'], 'toAddr1' => $doc['to']['address'], 'toAddr2' => '', 'toPlace' => $doc['to']['city'],
            'toPincode' => $doc['to']['pin'] ?: 999999, 'actToStateCode' => (int) $doc['to']['state'], 'toStateCode' => (int) $doc['to']['state'],
            // 3 = Bill From - Dispatch From: goods leave the job worker's premises, not ours.
            'transactionType' => !empty($doc['attributes']['dispatched_from']) ? 3 : 1, 'otherValue' => 0,
            'totalValue' => round(array_sum(array_column($lines, 'taxable')), 2), 'cgstValue' => round(array_sum(array_column($lines, 'cgst')), 2),
            'sgstValue' => round(array_sum(array_column($lines, 'sgst')), 2), 'igstValue' => round(array_sum(array_column($lines, 'igst')), 2),
            'cessValue' => 0, 'cessNonAdvolValue' => 0, 'totInvValue' => round($doc['total'], 2),
            'transporterId' => (string) ($transport['transporter_id'] ?? ''), 'transporterName' => (string) ($transport['transport_name'] ?? ''),
            'transDocNo' => (string) ($transport['lr_number'] ?? $transport['lr_no'] ?? ''), 'transMode' => '1',
            'transDistance' => (string) ($transport['distance_km'] ?? '0'), 'transDocDate' => !empty($transport['lr_date']) ? date('d/m/Y', strtotime($transport['lr_date'])) : '',
            'vehicleNo' => str_replace(' ', '', (string) ($transport['vehicle_number'] ?? '')), 'vehicleType' => 'R',
            'itemList' => array_map(fn ($line, $i) => ['itemNo' => $i + 1, 'productName' => $line['name'], 'productDesc' => $line['name'], 'hsnCode' => (int) $line['hsn'],
                'quantity' => $line['qty'], 'qtyUnit' => $line['unit'], 'taxableAmount' => round($line['taxable'], 2),
                'cgstRate' => $interState ? 0 : $line['rate'] / 2, 'sgstRate' => $interState ? 0 : $line['rate'] / 2, 'igstRate' => $interState ? $line['rate'] : 0,
                'cessRate' => 0, 'cessNonadvol' => 0], $lines, array_keys($lines))];
        $this->requireFields(['seller PIN code (Company profile)' => $seller['pin'], 'party PIN code' => $doc['to']['gstin'] === 'URP' && $doc['to']['state'] === '96' ? 1 : $doc['to']['pin']]);
        // Rule 138: needed above ₹50,000, and for inter-state job work whatever the value.
        return ['version' => '1.0.0621', 'required' => $doc['total'] > self::EWAY_THRESHOLD || ($kind === 'dispatch' && $interState), 'billLists' => [$bill]];
    }

    private function seller(CompanyContext $context): array
    {
        $company = Company::findOrFail($context->companyId);
        $registration = DB::table('tax_registrations')->where('company_id', $context->companyId)->first();
        $address = (string) ($registration->address ?? null ?: $company->address);
        return ['name' => $registration->legal_name ?? $company->name, 'address' => mb_substr($address, 0, 100), 'city' => (string) ($company->city ?? ''),
            'pin' => $this->pin($company->postal_code ?? null, $address), 'state' => (string) ($registration->state_code ?? $company->state_code ?? '')];
    }

    private function party(string $type, int $id, CompanyContext $context): array
    {
        $party = DB::table($type === 'customer' ? 'customers' : 'suppliers')->find($id);
        $profile = DB::table('party_tax_profiles')->where('company_id', $context->companyId)->where('party_type', $type)->where('party_id', $id)->first();
        return ['name' => $party->company_name ?? null ?: $party->name, 'address' => mb_substr((string) ($party->address ?? ''), 0, 100),
            'city' => (string) ($party->city ?? ''), 'pin' => $this->pin($party->postal_code ?? null, (string) ($party->address ?? '')),
            'gstin' => $profile->gstin ?? '', 'state' => (string) ($profile->state_code ?? '')];
    }

    private function pin(?string $postal, string $address): ?int
    {
        foreach ([(string) $postal, $address] as $value) if (preg_match('/\b([1-9][0-9]{5})\b/', $value, $match)) return (int) $match[1];
        return null;
    }

    /** GST unit quantity codes; the textile units map one to one. */
    private function uqc(?int $unitId): string
    {
        $code = strtoupper((string) DB::table('units')->where('id', $unitId)->value('unit_code'));
        return in_array($code, ['MTR', 'KGS', 'PCS', 'NOS', 'DOZ', 'BOX', 'ROL', 'BDL', 'BAL', 'SQM', 'TON', 'LTR', 'SET', 'PRS'], true) ? $code
            : (['ROLL' => 'ROL', 'BALE' => 'BAL', 'PAIR' => 'PRS', 'KG' => 'KGS', 'MTRS' => 'MTR'][$code] ?? 'OTH');
    }

    private function requireFields(array $fields): void
    {
        $missing = array_keys(array_filter($fields, fn ($value) => !$value));
        if ($missing) throw ValidationException::withMessages(['document' => 'Fill in '.implode(' and ', $missing).' first.']);
    }
}
