<?php

namespace Tests\Feature;

use App\Models\Inventory\StockIdentity;
use App\Services\Industry\IndustryProfileService;
use App\Services\Inventory\{InventoryMovementService, InventoryReconciliationService, StockLine, StockMovementCommand};
use App\Services\JobWork\JobWorkService;
use App\Services\Manufacturing\{BomService, ProductionService};
use Illuminate\Support\Facades\DB;
use Tests\Support\OperationsTestCase;

class OperationalConversionTest extends OperationsTestCase
{
    private function dimensions(float $volume, string $number): array
    {
        return ['identity_no' => $number, 'length' => $volume, 'width' => 1, 'thickness' => 1, 'dimension_uom' => 'm'];
    }

    private function timberInput()
    {
        app(IndustryProfileService::class)->apply('timber', 'processor', $this->context(), 1);
        $input = $this->material(0);
        app(InventoryMovementService::class)->receive(new StockMovementCommand(date: '2026-10-03', lines: [
            new StockLine(productId: $input->id, qty: 10, unitCost: 5, dimensions: $this->dimensions(10, 'LOG-10'))],
            warehouseId: 1, context: $this->context(), userId: 1));
        return [$input, StockIdentity::sole()];
    }

    public function test_sawing_volume_reconciles_good_output_offcut_waste_and_reverse(): void
    {
        [$input, $identity] = $this->timberInput(); $output = $this->material(0); $offcut = $this->material(0);
        $bom = $this->bom($input, $output, ['output_qty' => 8, 'lines' => [['component_product_id' => $input->id, 'qty' => 10, 'uom_id' => 1]]]);
        $service = app(ProductionService::class);
        $order = $service->plan(['bom_id' => $bom->id, 'warehouse_id' => 1, 'planned_qty' => 8, 'business_date' => '2026-10-03'], 'saw-plan', $this->context(), 1);
        $data = ['completed_qty' => 8, 'business_date' => '2026-10-03', 'yield_basis' => 'volume_cbm', 'waste_qty' => 0,
            'components' => [['bom_line_id' => DB::table('bom_lines')->value('id'), 'qty' => 10, 'stock_identity_id' => $identity->id]],
            'outputs' => [['product_id' => $output->id, 'qty' => 8, 'cost_weight' => 4, 'dimensions' => $this->dimensions(8, 'BOARD-8')]],
            'scrap_outputs' => [['product_id' => $offcut->id, 'qty' => 1, 'cost_weight' => 1, 'dimensions' => $this->dimensions(1, 'OFFCUT-1')]]];
        $this->rejected(fn () => $service->complete($order->id, $data, 'saw-bad', $this->context(), 1), 'reconcile');
        $this->assertEquals(10, $input->fresh()->qty);
        $data['waste_qty'] = 1;
        $posted = $service->complete($order->id, $data, 'saw-good', $this->context(), 1);
        $this->assertEquals(50, $posted->total_cost); $this->assertEquals(80, $posted->details_json['yield_percent']);
        $this->assertEquals(40, DB::table('production_outputs')->where('product_id', $output->id)->value('value'));
        $this->assertEquals(10, DB::table('production_outputs')->where('product_id', $offcut->id)->value('value'));
        $this->assertSame([], app(InventoryReconciliationService::class)->differences());
        $service->reverse($order->id, '2026-10-03', 'Rebuild', $this->context(), 1);
        $this->assertEquals(10, $input->fresh()->qty); $this->assertEquals(0, $output->fresh()->qty);
        $this->assertSame([], app(InventoryReconciliationService::class)->differences());
    }

    public function test_external_timber_conversion_validates_volume_and_preserves_cost(): void
    {
        [$input, $identity] = $this->timberInput(); $output = $this->material(0); $service = app(JobWorkService::class);
        $process = DB::table('process_types')->where('code', 'SAWING')->value('id');
        $order = $service->order(['job_worker_party_id' => 1, 'process_type_id' => $process, 'business_date' => '2026-10-03'], 'saw-order', $this->context(), 1);
        $dispatch = $service->dispatch($order->id, ['warehouse_id' => 1, 'business_date' => '2026-10-03',
            'lines' => [['product_id' => $input->id, 'qty' => 10, 'stock_identity_id' => $identity->id]]], 'saw-dispatch', $this->context(), 1);
        $data = ['warehouse_id' => 1, 'business_date' => '2026-10-03', 'lines' => [[
            'dispatch_line_id' => DB::table('job_work_dispatch_lines')->value('id'), 'accepted_qty' => 9, 'rejected_qty' => 0, 'loss_qty' => 1]],
            'outputs' => [['product_id' => $output->id, 'qty' => 9, 'cost_weight' => 1, 'dimensions' => $this->dimensions(8, 'SAWN-9')]]];
        $this->rejected(fn () => $service->receive($dispatch->id, $data, 'wrong-volume', $this->context(), 1), 'reconcile');
        $this->assertEquals(10, $service->pending($this->context(), 1)[0]['pending_qty']);
        $data['outputs'][0]['dimensions'] = $this->dimensions(9, 'SAWN-9');
        $receipt = $service->receive($dispatch->id, $data, 'right-volume', $this->context(), 1);
        $this->assertEquals(45, DB::table('stock_movement_lines')->where('stock_movement_id', $receipt->output_movement_id)->sum('value'));
        $this->assertEquals(5, DB::table('journal_entries')->where('id', $receipt->journal_entry_id)->value('total_debit'));
        $this->assertSame([], $service->pending($this->context(), 1));
        $this->assertSame([], app(InventoryReconciliationService::class)->differences());
        $service->reverse('receipt', $receipt->id, '2026-10-03', 'Recount', $this->context(), 1);
        $this->assertEquals(10, $service->pending($this->context(), 1)[0]['pending_qty']);
        $this->assertSame([], app(InventoryReconciliationService::class)->differences());
    }

    public function test_legacy_import_dry_run_rejects_cross_recipe_cycles_then_preserves_source_prices(): void
    {
        $a = $this->material(0); $b = $this->material(0); $raw = $this->material(0);
        foreach ([[$a, $b], [$b, $a]] as [$product, $component]) $product->forceFill(['is_recipe' => true,
            'product_list' => (string) $component->id, 'qty_list' => '2', 'combo_unit_id' => '1', 'wastage_percent' => '0', 'price' => 123])->save();
        $service = app(BomService::class);
        $this->rejected(fn () => $service->importLegacy($this->context(), 1, true), 'cycles');
        $this->assertSame(0, DB::table('boms')->count());
        $b->forceFill(['product_list' => (string) $raw->id])->save();
        $this->assertSame(['recipes' => 2, 'dry_run' => true], $service->importLegacy($this->context(), 1, true));
        $this->assertSame(0, DB::table('boms')->count());
        $service->importLegacy($this->context(), 1, false); $service->importLegacy($this->context(), 1, false);
        $this->assertSame(2, DB::table('boms')->count()); $this->assertEquals(123, $a->fresh()->price);
        $this->assertSame((string) $b->id, $a->fresh()->product_list);
    }
}
