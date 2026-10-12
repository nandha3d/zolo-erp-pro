<?php

namespace Tests\Unit;

use App\Services\Commercial\CommercialPricing;
use App\Services\Commercial\LandedCostService;
use App\Models\Product;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CommercialPricingTest extends TestCase
{
    public function test_line_taxes_discount_and_freight_are_calculated_at_four_decimals(): void
    {
        $result = (new CommercialPricing)->calculate(['items' => [['qty' => 1.125, 'net_unit_price' => 10, 'tax_rate' => 10]],
            'order_discount' => 1, 'shipping_cost' => 2], false);
        $this->assertSame(1.125, $result['items'][0]['tax']);
        $this->assertSame(13.375, $result['grand_total']);
    }

    public function test_gst_sale_rounds_to_whole_rupees_and_purchase_keeps_paise(): void
    {
        $gst = fn (float $cgst) => ['cgst' => $cgst, 'sgst' => $cgst, 'igst' => 0, 'cess' => 0, 'reverse_charge' => false, 'input_credit_allowed' => true];
        $data = ['_tax_snapshot' => ['place_of_supply' => '33'], 'items' => [
            ['qty' => 1000, 'net_unit_price' => 6.2, 'net_unit_cost' => 6.2, 'tax_rate' => 5, '_tax_snapshot' => $gst(155)],
            ['qty' => 26, 'net_unit_price' => 129.55, 'net_unit_cost' => 129.55, 'tax_rate' => 0, '_tax_snapshot' => $gst(0)]]];
        $sale = (new CommercialPricing)->calculate($data, false);
        $this->assertSame(-0.3, $sale['round_off']);
        $this->assertSame(9878.0, (float) $sale['grand_total']);
        $purchase = (new CommercialPricing)->calculate($data, true);
        $this->assertSame(0.0, (float) $purchase['round_off']);
        $this->assertSame(9878.3, $purchase['grand_total']);
        $this->expectException(ValidationException::class);
        (new CommercialPricing)->calculate($data + ['round_off' => 0], false);
    }

    public function test_non_gst_sale_is_not_rounded(): void
    {
        $result = (new CommercialPricing)->calculate(['items' => [['qty' => 1, 'net_unit_price' => 10.4]]], false);
        $this->assertSame(10.4, $result['grand_total']);
    }

    public function test_forged_total_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        (new CommercialPricing)->calculate(['items' => [['qty' => 2, 'net_unit_price' => 10]], 'grand_total' => 1], false);
    }

    public function test_nan_negative_or_excess_precision_amount_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        (new CommercialPricing)->calculate(['items' => [['qty' => 2, 'net_unit_price' => '0.00001']]], false);
    }
}
