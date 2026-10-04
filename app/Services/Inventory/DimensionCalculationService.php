<?php

namespace App\Services\Inventory;

/** Persist results on receipt. Later formulas never reinterpret historical dimensions. */
class DimensionCalculationService
{
    public const VERSION = 'rectangular-v1';
    private const METRES = ['mm' => 0.001, 'cm' => 0.01, 'm' => 1, 'in' => 0.0254, 'ft' => 0.3048];

    public function movementVolume(\App\Models\Inventory\StockMovement $movement): float
    {
        // A transfer has two sides. Measure one side, preserving the original dimensional snapshot.
        $lines = $movement->lines;
        if ($movement->movement_type === 'transfer') $lines = $lines->where('qty_base', '>', 0);
        return (float) $lines->sum(function ($line) use ($movement) {
            $dimension = \Illuminate\Support\Facades\DB::table('stock_dimensions')
                ->where('stock_identity_id', $line->stock_identity_id)->first();
            $initial = (float) \Illuminate\Support\Facades\DB::table('stock_movement_lines')
                ->where('company_id', $movement->company_id)->where('stock_identity_id', $line->stock_identity_id)
                ->where('qty_base', '>', 0)->orderBy('id')->value('qty_base');
            if (!$dimension || !$dimension->computed_cbm || $initial <= 0) {
                throw \Illuminate\Validation\ValidationException::withMessages(['yield_basis' => 'Volume reconciliation needs dimensioned identities.']);
            }
            return (float) $dimension->computed_cbm * abs((float) $line->qty_base) / $initial;
        });
    }

    public function calculate(array $data): array
    {
        $data = validator($data, ['length' => 'required|numeric|gt:0|max:999999', 'width' => 'required|numeric|gt:0|max:999999',
            'thickness' => 'required|numeric|gt:0|max:999999', 'pieces' => 'sometimes|integer|min:1|max:1000000',
            'dimension_uom' => 'required|in:mm,cm,m,in,ft', 'volume_uom' => 'nullable|string',
            'grade' => 'nullable|string|max:50', 'identity_no' => 'nullable|string|max:191'])->validate();
        $pieces = $data['pieces'] ?? 1;
        $cbm = $data['length'] * $data['width'] * $data['thickness'] * $pieces * self::METRES[$data['dimension_uom']] ** 3;
        $cft = $cbm / 0.028316846592;
        $volumeUnit = strtolower($data['volume_uom'] ?? (in_array($data['dimension_uom'], ['in', 'ft'], true) ? 'cft' : 'cbm'));
        $volumeUnit = match ($volumeUnit) { 'm3' => 'cbm', 'ft3' => 'cft', default => $volumeUnit };
        if (!in_array($volumeUnit, ['cbm', 'cft'], true) || !is_finite($cbm) || round($cbm, 6) <= 0 || $cft > 999999999999) {
            throw \Illuminate\Validation\ValidationException::withMessages(['dimensions' => 'Use CFT/CBM within supported dimension range.']);
        }
        return $data + ['pieces' => $pieces, 'computed_cbm' => round($cbm, 6), 'computed_cft' => round($cft, 6),
            'computed_volume' => round($volumeUnit === 'cft' ? $cft : $cbm, 6), 'formula_version' => self::VERSION,
            'normalized_volume_uom' => $volumeUnit];
    }
}
