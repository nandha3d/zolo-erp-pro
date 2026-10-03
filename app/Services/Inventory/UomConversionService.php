<?php

namespace App\Services\Inventory;

use App\Models\Inventory\ProductUomConversion;
use App\Models\Product;
use App\Models\Unit;
use InvalidArgumentException;

/**
 * Converts a quantity in any unit to the product base unit (products.unit_id).
 * Order: product_uom_conversions (either direction), then legacy units.operator/operation_value.
 */
class UomConversionService
{
    public const SCALE = 4;

    public function toBase(Product $product, float $qty, ?int $uomId): float
    {
        $baseId = (int) ($product->unit_id ?? 0);
        if ($uomId === null || $baseId === 0 || $uomId === $baseId) {
            return round($qty, self::SCALE);
        }

        $direct = ProductUomConversion::where('product_id', $product->id)
            ->where('from_uom_id', $uomId)->where('to_uom_id', $baseId)->first();
        if ($direct) {
            return round($qty * (float) $direct->factor, min((int) $direct->rounding_scale, self::SCALE));
        }
        $inverse = ProductUomConversion::where('product_id', $product->id)
            ->where('from_uom_id', $baseId)->where('to_uom_id', $uomId)->first();
        if ($inverse && (float) $inverse->factor > 0) {
            return round($qty / (float) $inverse->factor, min((int) $inverse->rounding_scale, self::SCALE));
        }

        $unit = Unit::find($uomId);
        $value = (float) ($unit->operation_value ?? 0);
        if ($unit && (int) $unit->base_unit === $baseId && $value > 0) {
            return match ($unit->operator) {
                '*' => round($qty * $value, self::SCALE),
                '/' => round($qty / $value, self::SCALE),
                default => throw new InvalidArgumentException("Unit {$uomId} has an unsupported conversion operator."),
            };
        }

        throw new InvalidArgumentException("No conversion from unit {$uomId} to the base unit of product {$product->id}.");
    }
}
