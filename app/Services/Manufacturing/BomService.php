<?php

namespace App\Services\Manufacturing;

use App\Models\Operations\Bom;
use App\Models\Product;
use App\Models\Unit;
use App\Services\ERP\CompanyWriteGuard;
use App\Services\Inventory\UomConversionService;
use App\Services\Operations\OperationPosting;
use App\Services\Platform\CompanyContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Published versions are immutable; a change creates another effective-dated version. */
class BomService
{
    public function create(array $data, string $key, CompanyContext $context, int $actor): Bom
    {
        return app(OperationPosting::class)->run('bom', Bom::class, 'manufacturing.bom', 'manufacturing.bom.manage',
            $data, $key, $context, $actor, function ($data, $date) use ($context, $actor) {
                $data = $this->validate($data, $context);
                $version = (int) Bom::forCompany($context)->where('code', $data['code'])->max('version') + 1;
                $existing = Bom::forCompany($context)->where('code', $data['code'])->first();
                if ($existing && (int) $existing->product_id !== (int) $data['product_id']) {
                    throw ValidationException::withMessages(['code' => 'A BOM code belongs to one output product.']);
                }
                if ($data['is_default'] && Bom::forCompany($context)->where('product_id', $data['product_id'])->where('is_default', true)
                    ->where('effective_from', '<=', $data['effective_to'] ?? '9999-12-31')
                    ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $data['effective_from']))->exists()) {
                    throw ValidationException::withMessages(['effective_from' => 'Default BOM effective ranges must not overlap.']);
                }
                $bom = Bom::create(app(OperationPosting::class)->header($context, $actor) + [
                    'product_id' => $data['product_id'], 'code' => $data['code'], 'version' => $version,
                    'effective_from' => $data['effective_from'], 'effective_to' => $data['effective_to'] ?? null,
                    'output_qty' => $data['output_qty'], 'output_uom_id' => $data['output_uom_id'],
                    'is_default' => $data['is_default'], 'legacy_import' => $data['legacy_import'] ?? false,
                ]);
                foreach ($data['lines'] as $i => $line) DB::table('bom_lines')->insert([
                    'bom_id' => $bom->id, 'line_no' => $i + 1, 'component_product_id' => $line['component_product_id'],
                    'variant_id' => $line['variant_id'] ?? null, 'qty' => $line['qty'], 'uom_id' => $line['uom_id'],
                    'scrap_percent' => $line['scrap_percent'] ?? 0,
                ]);
                $bom->forceFill(['status' => 'published', 'posted_at' => now()])->save();
                return $bom;
            });
    }

    public function validate(array $data, CompanyContext $context): array
    {
        $data = validator($data, [
            'product_id' => 'required|integer|min:1', 'code' => 'required|string|max:60|regex:/^[A-Z0-9_-]+$/D',
            'effective_from' => 'required|date_format:Y-m-d', 'effective_to' => 'nullable|date_format:Y-m-d|after_or_equal:effective_from',
            'output_qty' => 'required|numeric|gt:0|max:999999999', 'output_uom_id' => 'required|integer|min:1',
            'is_default' => 'sometimes|boolean', 'legacy_import' => 'sometimes|boolean', 'lines' => 'required|array|min:1|max:500',
            'lines.*.component_product_id' => 'required|integer|min:1', 'lines.*.qty' => 'required|numeric|gt:0|max:999999999',
            'lines.*.uom_id' => 'required|integer|min:1', 'lines.*.variant_id' => 'nullable|integer|min:1',
            'lines.*.scrap_percent' => 'sometimes|numeric|min:0|max:100',
        ])->validate();
        $guard = app(CompanyWriteGuard::class);
        $output = $guard->owned(Product::class, $data['product_id'], $context, 'product_id');
        if ($output->type !== 'standard' || !$output->is_active) {
            throw ValidationException::withMessages(['product_id' => 'BOM output must be an active stock product.']);
        }
        $guard->owned(Unit::class, $data['output_uom_id'], $context, 'output_uom_id');
        app(UomConversionService::class)->toBase($output, (float) $data['output_qty'], (int) $data['output_uom_id']);
        foreach ($data['lines'] as $line) {
            $component = ['product_id' => $line['component_product_id'], 'variant_id' => $line['variant_id'] ?? null, 'uom_id' => $line['uom_id']];
            $product = $guard->product($component, $context, 'uom_id');
            if ((int) $product->id === (int) $output->id || $product->type !== 'standard' || !$product->is_active) {
                throw ValidationException::withMessages(['lines' => 'Use active stock components other than the output product.']);
            }
            app(UomConversionService::class)->toBase($product, (float) $line['qty'], (int) $line['uom_id']);
            // Reject cycles through already published BOMs, including indirect kit expansion.
            $this->assertAcyclic($product->id, $output->id, $context, []);
        }
        $data['is_default'] ??= false;
        return $data;
    }

    private function assertAcyclic(int $product, int $output, CompanyContext $context, array $visited): void
    {
        if ($product === $output) throw ValidationException::withMessages(['lines' => 'BOM component graph must not contain cycles.']);
        if (isset($visited[$product])) return;
        $visited[$product] = true;
        $children = DB::table('bom_lines')->join('boms', 'boms.id', '=', 'bom_lines.bom_id')
            ->where('boms.company_id', $context->companyId)->where('boms.product_id', $product)->where('boms.status', 'published')
            ->pluck('component_product_id');
        foreach ($children as $child) $this->assertAcyclic((int) $child, $output, $context, $visited);
    }

    /** Validate every legacy row before importing any; preserve the source arrays and prices. */
    public function importLegacy(CompanyContext $context, int $actor, bool $dryRun = true): array
    {
        app(OperationPosting::class)->authorize('manufacturing.bom', 'manufacturing.bom.manage', $context, $actor);
        return DB::transaction(function () use ($context, $actor, $dryRun) {
            app(CompanyWriteGuard::class)->begin($context, null);
            $rows = [];
            foreach (Product::forCompany($context)->where('is_recipe', true)->orderBy('id')->lockForUpdate()->get() as $product) {
                $ids = explode(',', (string) $product->product_list);
                $qty = explode(',', (string) $product->qty_list);
                $units = explode(',', (string) $product->combo_unit_id);
                $scrap = explode(',', (string) $product->wastage_percent);
                $variants = $product->variant_list ? explode(',', $product->variant_list) : array_fill(0, count($ids), null);
                if (count(array_unique(array_map('count', [$ids, $qty, $units, $scrap, $variants]))) !== 1) {
                    throw ValidationException::withMessages(['legacy' => 'Recipe '.$product->id.' has mismatched component arrays.']);
                }
                $lines = [];
                foreach ($ids as $i => $id) $lines[] = ['component_product_id' => $id, 'qty' => $qty[$i], 'uom_id' => $units[$i],
                    'scrap_percent' => $scrap[$i], 'variant_id' => $variants[$i] ?: null];
                $rows[$product->id] = $this->validate(['product_id' => $product->id, 'code' => 'LEGACY-'.$product->id,
                    'effective_from' => $product->updated_at->toDateString(), 'output_qty' => 1, 'output_uom_id' => $product->unit_id,
                    'is_default' => false, 'legacy_import' => true, 'lines' => $lines], $context);
            }
            if (!$dryRun) foreach ($rows as $id => $row) $this->create($row, 'legacy-bom:'.$id, $context, $actor);
            return ['recipes' => count($rows), 'dry_run' => $dryRun];
        });
    }
}
