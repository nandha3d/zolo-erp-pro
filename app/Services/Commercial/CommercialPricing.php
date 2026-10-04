<?php

namespace App\Services\Commercial;

use Illuminate\Validation\ValidationException;

/** Shared arithmetic only. GST determination belongs to Phase 7, not the browser. */
class CommercialPricing
{
    public function preview(array $data, bool $purchase, \App\Services\Platform\CompanyContext $context): array
    {
        if (empty($data['items'])) {
            return ['grand_total' => 0, 'total_tax' => 0, 'total_price' => 0];
        }
        if (!is_array($data['items']) || !array_is_list($data['items'])) {
            $this->invalid('items', 'Lines must be a list.');
        }
        foreach ($data['items'] as &$line) {
            if (!is_array($line) || count($data['items']) > 500) {
                $this->invalid('items', 'Use at most 500 object lines.');
            }
            $product = app(\App\Services\ERP\CompanyWriteGuard::class)->product($line, $context, $purchase ? 'purchase_unit_id' : 'sale_unit_id');
            $line['tax_rate'] = $product->tax_id ? (float) \App\Models\Tax::where('company_id', $context->companyId)
                ->where('is_active', true)->findOrFail($product->tax_id)->rate : 0;
            unset($line['tax'], $line['total']);
        }
        unset($line, $data['grand_total'], $data['order_tax']);
        $data['order_tax_rate'] = 0;
        $data = $this->calculate($data, $purchase);
        return ['grand_total' => $data['grand_total'], 'total_tax' => array_sum(array_column($data['items'], 'tax')),
            'total_price' => array_sum(array_column($data['items'], 'total')), 'items' => $data['items']];
    }

    public function calculate(array $data, bool $purchase): array
    {
        if (empty($data['items']) || !is_array($data['items']) || !array_is_list($data['items']) || count($data['items']) > 500) {
            $this->invalid('items', 'Use between one and 500 lines.');
        }
        $total = $tax = $discount = 0;
        $priceField = $purchase ? 'net_unit_cost' : 'net_unit_price';
        foreach ($data['items'] as $i => &$line) {
            if (!is_array($line)) {
                $this->invalid('items', 'Each line must be an object.');
            }
            $qty = $this->number($line['qty'] ?? null, 'items.'.$i.'.qty', 6);
            $price = $this->number($line[$priceField] ?? null, 'items.'.$i.'.'.$priceField);
            if ($qty <= 0) {
                $this->invalid('items.'.$i.'.qty', 'Quantity must be positive.');
            }
            $lineDiscount = $this->units($this->number($line['discount'] ?? 0, 'items.'.$i.'.discount'));
            // net_unit_* is the already-discounted price used by existing forms.
            $net = $this->units($qty * $price);
            $rate = $this->number($line['tax_rate'] ?? 0, 'items.'.$i.'.tax_rate');
            if ($rate > 100) {
                $this->invalid('items.'.$i.'.tax_rate', 'Tax rate cannot exceed 100.');
            }
            $lineTax = $this->units($net / 10000 * $rate / 100);
            $lineTotal = $net + $lineTax;
            $this->check($line, 'tax', $lineTax);
            $this->check($line, 'total', $lineTotal);
            $line['tax'] = $lineTax / 10000;
            $line['total'] = $lineTotal / 10000;
            $line['discount'] = $lineDiscount / 10000;
            $line['tax_rate'] = $rate;
            $total += $lineTotal;
            $tax += $lineTax;
            $discount += $lineDiscount;
        }
        unset($line);
        $rate = $this->number($data['order_tax_rate'] ?? 0, 'order_tax_rate');
        if ($rate > 100) {
            $this->invalid('order_tax_rate', 'Tax rate cannot exceed 100.');
        }
        $orderDiscount = $this->units($this->number($data['order_discount'] ?? 0, 'order_discount'));
        if ($orderDiscount > $total) {
            $this->invalid('order_discount', 'Discount exceeds line total.');
        }
        $orderTax = $this->units(($total - $orderDiscount) / 10000 * $rate / 100);
        $shipping = $this->units($this->number($data['shipping_cost'] ?? 0, 'shipping_cost'));
        $grand = $total - $orderDiscount + $orderTax + $shipping;
        $this->check($data, 'order_tax', $orderTax);
        $this->check($data, 'grand_total', $grand);
        $data['order_tax'] = $orderTax / 10000;
        $data['grand_total'] = $grand / 10000;
        $data['paid_amount'] = $this->number($data['paid_amount'] ?? 0, 'paid_amount');
        if ($this->units($data['paid_amount']) > $grand) {
            $this->invalid('paid_amount', 'Payment exceeds invoice total.');
        }
        return $data;
    }

    public function number(mixed $value, string $field, int $precision = 4): float
    {
        if (!is_numeric($value) || !is_finite((float) $value) || (float) $value < 0 || (float) $value > 1000000000
            || abs((float) $value - round((float) $value, $precision)) > 0.00000001) {
            $this->invalid($field, 'Use a nonnegative amount with at most '.$precision.' decimals.');
        }
        return round((float) $value, $precision);
    }

    public function units(float $amount): int
    {
        if (!is_finite($amount) || abs($amount) > 1000000000) {
            $this->invalid('amount', 'Amount exceeds supported precision.');
        }
        return (int) round($amount * 10000);
    }

    private function check(array $values, string $field, int $expected): void
    {
        if (isset($values[$field]) && $this->units($this->number($values[$field], $field)) !== $expected) {
            $this->invalid($field, 'Submitted total does not match server calculation.');
        }
    }

    private function invalid(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
