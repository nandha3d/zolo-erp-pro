<?php

namespace App\Services\Commercial;

use App\Models\Product;
use Illuminate\Validation\ValidationException;

/** Allocate at ledger precision; residual goes to the last eligible line, preserving the full bill. */
class LandedCostService
{
    public function allocate(array $data, array $products): array
    {
        $pricing = app(CommercialPricing::class);
        $lines = $data['items'];
        $values = array_map(fn ($line) => $pricing->units((float) $line['total']), $lines);
        $basis = array_sum($values) > 0 ? $values : array_column($lines, 'qty');
        $adjustments = $this->spread($pricing->units((float) $data['order_tax'] - (float) ($data['order_discount'] ?? 0)), $basis);
        $method = $data['landed_cost_method'] ?? 'value';
        if (!in_array($method, ['value', 'quantity', 'weight', 'manual'], true)) {
            throw ValidationException::withMessages(['landed_cost_method' => 'Choose value, quantity, weight or manual.']);
        }
        $weights = [];
        foreach ($lines as $i => $line) {
            $service = in_array($products[$line['product_id']]->type, ['service', 'digital'], true);
            $weights[$i] = $service ? 0 : match ($method) {
                'quantity' => (float) $line['qty'],
                'weight' => $pricing->number($line['weight'] ?? null, 'items.'.$i.'.weight', 6),
                'manual' => $pricing->number($line['landed_cost'] ?? null, 'items.'.$i.'.landed_cost'),
                default => (float) $line['total'],
            };
        }
        $freight = $pricing->units((float) ($data['shipping_cost'] ?? 0));
        if ($method === 'manual') {
            $allocations = array_map(fn ($amount) => $pricing->units((float) $amount), $weights);
            if (array_sum($allocations) !== $freight) {
                throw ValidationException::withMessages(['landed_cost' => 'Manual allocations must equal freight.']);
            }
        } else {
            if (array_sum($weights) == 0) {
                // All-service bills expense freight. Free physical goods allocate by quantity.
                $hasStock = collect($products)->contains(fn ($product) => !in_array($product->type, ['service', 'digital'], true));
                $weights = array_map(fn ($line) => $hasStock && in_array($products[$line['product_id']]->type, ['service', 'digital'], true)
                    ? 0 : (float) $line['qty'], $lines);
            }
            $allocations = $this->spread($freight, $weights);
        }
        foreach ($lines as $i => &$line) {
            $amount = $values[$i] + $adjustments[$i] + $allocations[$i];
            if ($amount < 0) {
                throw ValidationException::withMessages(['landed_cost' => 'Landed cost cannot be negative.']);
            }
            $line['valuation_amount'] = $amount / 10000;
            $line['attributes'] = array_merge($line['attributes'] ?? [], ['landed_cost_method' => $method, 'freight_amount' => $allocations[$i] / 10000]);
        }
        return $lines;
    }

    private function spread(int $amount, array $weights): array
    {
        $result = array_fill(0, count($weights), 0);
        $sum = array_sum($weights);
        if (!$sum || !$amount) {
            return $result;
        }
        $eligible = array_keys(array_filter($weights, fn ($weight) => $weight > 0));
        $last = array_pop($eligible);
        $remaining = $amount;
        foreach ($eligible as $i) {
            $result[$i] = (int) round($amount * $weights[$i] / $sum);
            $remaining -= $result[$i];
        }
        $result[$last] = $remaining;
        return $result;
    }
}
