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
