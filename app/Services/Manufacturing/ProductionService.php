<?php

namespace App\Services\Manufacturing;

use App\Models\Company;
use App\Models\Operations\Bom;
use App\Models\Operations\ProductionOrder;
use App\Services\Accounting\AccountingPostingService;
use App\Services\Accounting\SemanticAccountResolver;
use App\Services\ERP\CompanyWriteGuard;
use App\Services\Inventory\InventoryMovementService;
use App\Services\Inventory\InventoryPolicy;
use App\Services\Inventory\UomConversionService;
use App\Services\Operations\OperationPosting;
use App\Services\Operations\OperationStock;
use App\Services\Platform\CompanyContext;
use App\Services\Platform\DocumentNumberService;
use App\Support\LedgerAmount;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductionService
{
    public function plan(array $data, string $key, CompanyContext $context, int $actor): ProductionOrder
    {
        return app(OperationPosting::class)->run('production_plan', ProductionOrder::class, 'manufacturing.production', 'manufacturing.production.post',
            $data, $key, $context, $actor, function ($data, $date) use ($context, $actor) {
                validator($data, ['bom_id' => 'required|integer|min:1', 'warehouse_id' => 'required|integer|min:1',
                    'planned_qty' => 'required|numeric|gt:0|max:999999999'])->validate();
                $bom = Bom::forCompany($context)->where('status', 'published')->findOrFail($data['bom_id']);
                $this->effective($bom, $date);
                app(CompanyWriteGuard::class)->warehouse($data['warehouse_id'], $context, $actor);
                $numbers = app(DocumentNumberService::class);
                $number = $numbers->reserve('production', $context, $date, $actor);
                $order = ProductionOrder::create(app(OperationPosting::class)->header($context, $actor) + [
                    'bom_id' => $bom->id, 'warehouse_id' => $data['warehouse_id'], 'reference_no' => $number->formatted_number,
                    'business_date' => $date, 'planned_qty' => $data['planned_qty'],
                    'cost_method' => app(InventoryPolicy::class)->valuation($context->companyId),
                ]);
                $numbers->assign($number, $order);
                return $order;
            });
    }

    public function complete(int $id, array $data, string $key, CompanyContext $context, int $actor): ProductionOrder
    {
        $data['production_order_id'] = $id;
        return app(OperationPosting::class)->run('production_complete', ProductionOrder::class, 'manufacturing.production', 'manufacturing.production.post',
            $data, $key, $context, $actor, function ($data, $date) use ($id, $context, $actor) {
                validator($data, ['completed_qty' => 'required|numeric|gt:0|max:999999999',
                    'direct_cost' => 'sometimes|numeric|min:0|max:999999999', 'overhead_cost' => 'sometimes|numeric|min:0|max:999999999',
                    'components' => 'sometimes|array|min:1|max:500', 'outputs' => 'sometimes|array|min:1|max:500',
                    'scrap_outputs' => 'sometimes|array|max:500', 'waste_qty' => 'sometimes|numeric|min:0',
                    'yield_basis' => 'sometimes|in:base_qty,volume_cbm'])->validate();
                $order = ProductionOrder::visibleIn($context)->lockForUpdate()->findOrFail($id);
                if ($order->status !== 'planned' || $date < $order->business_date || (float) $data['completed_qty'] > (float) $order->planned_qty) {
                    throw ValidationException::withMessages(['production_order' => 'Complete a planned order within its quantity and on or after its date.']);
                }
                $bom = Bom::forCompany($context)->findOrFail($order->bom_id);
                $this->effective($bom, $date);
                app(CompanyWriteGuard::class)->warehouse($order->warehouse_id, $context, $actor);
                $stock = app(OperationStock::class);
                $components = [];
                $bomLines = DB::table('bom_lines')->where('bom_id', $bom->id)->orderBy('line_no')->get();
                $entered = collect($data['components'] ?? [])->keyBy('bom_line_id');
                if (isset($data['components']) && ($entered->count() !== count($data['components']) || $entered->count() !== $bomLines->count()
                    || $entered->keys()->diff($bomLines->pluck('id'))->isNotEmpty())) {
                    throw ValidationException::withMessages(['components' => 'Provide each BOM component exactly once.']);
                }
                foreach ($bomLines as $line) {
                    $component = $entered->get($line->id, []);
                    // Explicit actual quantities preserve yield/wastage rather than recomputing historical production later.
                    $components[] = $stock->line([
                        'product_id' => $line->component_product_id, 'uom_id' => $line->uom_id, 'variant_id' => $line->variant_id,
                        'qty' => $component['qty'] ?? round($line->qty * $data['completed_qty'] / $bom->output_qty * (1 + $line->scrap_percent / 100), 4),
                    ] + array_diff_key($component, array_flip(['product_id', 'uom_id', 'variant_id', 'unit_cost'])), $context);
                }
                $movement = app(InventoryMovementService::class)->productionConsume($stock->command($order, $date, $components, $order->warehouse_id, $context, $actor, 'consume'));
                $consumed = -$stock->value($movement);
                $direct = LedgerAmount::units($data['direct_cost'] ?? 0);
                $overhead = LedgerAmount::units($data['overhead_cost'] ?? 0);
                $total = $consumed + $direct + $overhead;
                $outputs = $data['outputs'] ?? [['product_id' => $bom->product_id, 'uom_id' => $bom->output_uom_id, 'qty' => $data['completed_qty'], 'cost_weight' => 1]];
                $main = collect($outputs)->where('product_id', $bom->product_id);
                if (abs((float) $main->sum('qty') - (float) $data['completed_qty']) > InventoryMovementService::EPSILON
                    || $main->contains(fn ($line) => (int) ($line['uom_id'] ?? $bom->output_uom_id) !== (int) $bom->output_uom_id)) {
                    throw ValidationException::withMessages(['outputs' => 'Primary output quantity and unit must match completed quantity and BOM unit.']);
                }
                $all = [...$outputs, ...($data['scrap_outputs'] ?? [])];
                foreach ($all as $line) validator($line, ['product_id' => 'required|integer|min:1', 'qty' => 'required|numeric|gt:0|max:999999999',
                    'cost_weight' => 'required|numeric|min:0|max:999999999'])->validate();
                $weight = array_sum(array_column($all, 'cost_weight'));
                if ($weight <= 0) throw ValidationException::withMessages(['outputs' => 'Output cost weights must have a positive total.']);
                $allocated = 0; $out = []; $scrap = []; $saved = [];
                $componentIds = $bomLines->pluck('component_product_id')->all();
                foreach ($all as $i => $line) {
                    if (in_array((int) $line['product_id'], $componentIds, true)) {
                        throw ValidationException::withMessages(['outputs' => 'Output stock must use a different product from consumed components.']);
                    }
                    $value = $i === count($all) - 1 ? $total - $allocated : (int) round($total * $line['cost_weight'] / $weight);
                    $allocated += $value;
                    $line['unit_cost'] = $value / 10000 / (float) $line['qty'];
                    $output = $stock->line($line, $context);
                    $i < count($outputs) ? $out[] = $output : $scrap[] = $output;
                    $saved[] = ['production_order_id' => $order->id, 'line_no' => $i + 1, 'product_id' => $line['product_id'],
                        'qty' => $line['qty'], 'uom_id' => $output->uomId, 'value' => LedgerAmount::decimal($value),
                        'stock_details_json' => json_encode($line, JSON_THROW_ON_ERROR)];
                }
                $outputMovement = app(InventoryMovementService::class)->productionOutput($stock->command($order, $date, $out, $order->warehouse_id, $context, $actor, 'output'));
                $scrapMovement = $scrap ? app(InventoryMovementService::class)->productionScrap($stock->command($order, $date, $scrap, $order->warehouse_id, $context, $actor, 'scrap')) : null;
                if ($stock->value($outputMovement) + ($scrapMovement ? $stock->value($scrapMovement) : 0) !== $total) {
                    throw ValidationException::withMessages(['outputs' => 'Allocated costs exceed stock precision; adjust output allocation.']);
                }
                $yield = $this->yieldSnapshot($movement, $outputMovement, $scrapMovement, $data);
                DB::table('production_outputs')->insert(app(OperationPosting::class)->lines('production_outputs', $saved, $context));
                $journal = null;
                $perpetual = Company::findOrFail($context->companyId)->settings_json['inventory']['perpetual'] ?? true;
                if ($perpetual && $total) {
                    $resolver = app(SemanticAccountResolver::class);
                    $inventory = $resolver->resolve('inventory', $context)->id;
                    $items = [['chart_of_account_id' => $inventory, 'debit' => LedgerAmount::decimal($total)],
                        ['chart_of_account_id' => $inventory, 'credit' => LedgerAmount::decimal($consumed)]];
                    if ($direct + $overhead) $items[] = ['chart_of_account_id' => $resolver->resolve('production_costs', $context)->id, 'credit' => LedgerAmount::decimal($direct + $overhead)];
                    $journal = app(AccountingPostingService::class)->postJournalEntry(['entry_date' => $date, 'reference_type' => 'production',
                        'reference_id' => $order->id, 'reference_no' => $order->reference_no, 'description' => 'Production '.$order->reference_no,
                        'created_by' => $actor], $items, $context);
                }
                $order->forceFill(['status' => 'completed', 'completed_qty' => $data['completed_qty'], 'completed_date' => $date,
                    'direct_cost' => LedgerAmount::decimal($direct), 'overhead_cost' => LedgerAmount::decimal($overhead),
                    'total_cost' => LedgerAmount::decimal($total), 'consume_movement_id' => $movement->id,
                    'output_movement_id' => $outputMovement->id, 'scrap_movement_id' => $scrapMovement?->id,
                    'journal_entry_id' => $journal?->id, 'details_json' => $yield + ['bom_version' => $bom->version], 'posted_at' => now()])->save();
                return $order;
            });
    }

    public function reverse(int $id, string $date, string $reason, CompanyContext $context, int $actor): ProductionOrder
    {
        app(OperationPosting::class)->authorize('manufacturing.production', 'manufacturing.production.reverse', $context, $actor);
        validator(['reason' => $reason], ['reason' => 'required|string|max:500'])->validate();
        return DB::transaction(function () use ($id, $date, $reason, $context, $actor) {
            app(CompanyWriteGuard::class)->begin($context, $date);
            $order = ProductionOrder::visibleIn($context)->lockForUpdate()->findOrFail($id);
            if ($order->status === 'reversed') return $order;
            if ($order->status !== 'completed' || $date < $order->completed_date) {
                throw ValidationException::withMessages(['production_order' => 'Reverse completed production on or after completion date.']);
            }
            // Remove every output before restoring components; a sold output prevents the entire reversal.
            foreach ([$order->scrap_movement_id, $order->output_movement_id, $order->consume_movement_id] as $movement) {
                if ($movement) app(InventoryMovementService::class)->reverse($movement, $reason, $actor, $date);
            }
            if ($order->journal_entry_id) app(AccountingPostingService::class)->reverse($order->journal_entry_id, $date, $reason, $context);
            DB::table('production_orders')->where('id', $id)->update(['status' => 'reversed', 'reversed_at' => now(), 'reversal_reason' => $reason]);
            app(OperationPosting::class)->audit('production_reverse', $id, $context, $actor, compact('date', 'reason'));
            return $order->fresh();
        }, 3);
    }

    private function effective(Bom $bom, string $date): void
    {
        if ($date < $bom->effective_from || ($bom->effective_to && $date > $bom->effective_to)) {
            throw ValidationException::withMessages(['bom_id' => 'Select a BOM effective on the production date.']);
        }
    }

    private function yieldSnapshot($consume, $output, $scrap, array $data): array
    {
        $basis = $data['yield_basis'] ?? 'base_qty';
        $measure = function ($movement) use ($basis) {
            if (!$movement) return 0.0;
            if ($basis === 'base_qty') return abs((float) $movement->lines->sum('qty_base'));
            return app(\App\Services\Inventory\DimensionCalculationService::class)->movementVolume($movement);
        };
        $input = $measure($consume); $good = $measure($output); $recovered = $measure($scrap); $waste = (float) ($data['waste_qty'] ?? 0);
        if ($basis === 'volume_cbm' && abs($input - $good - $recovered - $waste) > max(0.000001, $input * 0.005)) {
            throw ValidationException::withMessages(['waste_qty' => 'Input volume must reconcile outputs, recovered scrap and documented waste within 0.5%.']);
        }
        return ['yield_basis' => $basis, 'input_qty' => $input, 'output_qty' => $good, 'recovered_scrap_qty' => $recovered,
            'waste_qty' => $waste, 'yield_percent' => $input ? round($good / $input * 100, 4) : 0];
    }
}
