<?php

namespace App\Services\Tax;

use App\Models\Purchase;
use App\Services\Platform\CompanyContext;
use Illuminate\Support\Facades\DB;

/**
 * Section 194Q: a buyer whose turnover last year exceeded ₹10 crore deducts TDS at 0.1% on the part of its purchases
 * from one seller that exceeds ₹50 lakh in the financial year (5% under 206AA when the seller has no PAN).
 * The base excludes GST shown separately on the bill (CBDT circular 23/2017). TCS under 206C(1H) ended on 1 April 2025.
 */
class Tds194qService
{
    public const THRESHOLD = 5000000;

    public function forPurchase(Purchase $purchase, CompanyContext $context): ?array
    {
        if (!GstSetupService::tradeSettings($context->companyId)['tds_194q'] || !$purchase->supplier_id) return null;
        $current = $this->taxable($purchase);
        if ($current <= 0) return null;
        $earlier = Purchase::visibleIn($context)->where('supplier_id', $purchase->supplier_id)->where('financial_year_id', $purchase->financial_year_id)
            ->whereNotNull('posted_at')->whereNull('reversed_at')->where('status', '<>', 4)->where('id', '<>', $purchase->id)->get()
            ->sum(fn (Purchase $bill) => $this->taxable($bill));
        $base = round(max(0, $earlier + $current - self::THRESHOLD) - max(0, $earlier - self::THRESHOLD), 2);
        if ($base <= 0) return null;
        $pan = $this->pan($purchase->supplier_id);
        $rate = $pan ? 0.1 : 5.0;
        // TDS is rounded to the nearest rupee (Sec 288B).
        return ['section' => '194Q', 'base' => $base, 'rate' => $rate, 'amount' => round($base * $rate / 100), 'pan' => $pan,
            'year_to_date' => round($earlier + $current, 2)];
    }

    /** Bills in the period that carry 194Q TDS, for the deposit (challan 281) and the quarterly 26Q return. */
    public function register(CompanyContext $context, string $from, string $to): array
    {
        return Purchase::visibleIn($context)->whereBetween('created_at', [$from.' 00:00:00', $to.' 23:59:59'])->whereNotNull('posted_at')
            ->whereNull('reversed_at')->orderBy('created_at')->get()->filter(fn ($bill) => !empty($bill->attributes_json['tds_194q']))
            ->map(fn ($bill) => ['bill' => $bill->reference_no, 'date' => $bill->created_at->toDateString(),
                'supplier' => DB::table('suppliers')->where('id', $bill->supplier_id)->value('name')] + $bill->attributes_json['tds_194q'])->values()->all();
    }

    private function taxable(Purchase $purchase): float
    {
        return (float) DB::table('product_purchases')->where('purchase_id', $purchase->id)->get()->sum(function ($line) {
            $snapshot = json_decode($line->tax_snapshot_json ?? 'null', true);
            return $snapshot ? (float) $snapshot['taxable_value'] : (float) $line->total - (float) $line->tax;
        });
    }

    /** PAN is characters 3–12 of the GSTIN. */
    private function pan(int $supplierId): ?string
    {
        $gstin = strtoupper((string) DB::table('suppliers')->where('id', $supplierId)->value('vat_number'));
        $profile = DB::table('party_tax_profiles')->where('party_type', 'supplier')->where('party_id', $supplierId)->value('gstin');
        foreach ([$gstin, strtoupper((string) $profile)] as $value) {
            if (preg_match('/^[0-9]{2}([A-Z]{5}[0-9]{4}[A-Z])/', $value, $match)) return $match[1];
        }
        return null;
    }
}
