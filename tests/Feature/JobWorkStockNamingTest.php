<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Purchase;
use App\Services\Commercial\CommercialReversalService;
use App\Services\Industry\IndustryProfileService;
use App\Services\Industry\IndustryStockQueries;
use App\Services\Inventory\InventoryMovementService;
use App\Services\Inventory\InventoryReconciliationService;
use App\Services\Inventory\StockLine;
use App\Services\Inventory\StockMovementCommand;
use App\Services\JobWork\JobWorkService;
use App\Services\JobWork\JobWorkStockNaming;
use Illuminate\Support\Facades\DB;
use Tests\Support\OperationsTestCase;

class JobWorkStockNamingTest extends OperationsTestCase
{
    private function received(float $opening = 200): array
    {
        app(IndustryProfileService::class)->apply('textile', 'manufacturing', $this->context(), 1);
        if (!\Illuminate\Support\Facades\Schema::hasColumn('products', 'short_description')) {
            \Illuminate\Support\Facades\Schema::table('products', fn ($t) => $t->text('short_description')->nullable());
        }
        $material = $this->material($opening, 5, ['short_description' => 'Original fabric']);
        [$order, $dispatch, $line] = $this->job($material, 100, false);
        $receipt = app(JobWorkService::class)->receive($dispatch->id, ['warehouse_id' => 1, 'business_date' => '2026-10-03',
            'lines' => [['dispatch_line_id' => $line->id, 'accepted_qty' => 100, 'rejected_qty' => 0, 'loss_qty' => 0]]], 'received', $this->context(), 1);
        $charge = $this->material(0); $charge->forceFill(['type' => 'service'])->save();
        $data = ['business_date' => '2026-10-03', 'items' => [['product_id' => $charge->id, 'qty' => 100, 'net_unit_cost' => 2]]];
        return [$material, $receipt, $data, $order];
    }

    public function test_service_bill_names_only_its_returned_stock_and_preserves_cost_retry_and_reversal(): void
    {
        [$material, $receipt, $data] = $this->received();
        $data['stock_names'] = [$material->id => 'RBM'];
        $service = app(JobWorkService::class);
        $billed = $service->serviceBill($receipt->id, $data, 'named-bill', $this->context(), 1);
        $rbm = Product::forCompany($this->context())->where('name', 'RBM')->sole();
        $this->assertEquals(100, $material->fresh()->qty);
        $this->assertEquals(100, $rbm->qty);
        $this->assertStringContainsString('Original fabric', $material->fresh()->short_description);
        $this->assertStringContainsString('Returned as RBM', $material->fresh()->short_description);
        $value = fn ($id) => (float) DB::table('stock_movement_lines')->where('product_id', $id)->sum('value');
        $this->assertEquals(500, $value($material->id), 'unrelated 100 units retain original value');
        $this->assertEquals(700, $value($rbm->id), '500 material and 200 processing move together');
        $bill = Purchase::findOrFail($billed->service_purchase_id);
        $this->assertEquals(200, $bill->grand_total);
        $this->assertStringContainsString($material->name.' to RBM', $bill->note);
        $this->assertSame($receipt->material_movement_id, $billed->material_movement_id, 'original GRN remains intact');
        $this->assertSame($rbm->id, $billed->details_json['stock_name_change']['items'][0]['to_product_id']);
        $count = DB::table('stock_movements')->count();
        $service->serviceBill($receipt->id, $data, 'named-bill', $this->context(), 1);
        $this->assertSame($count, DB::table('stock_movements')->count());
        $rows = app(IndustryStockQueries::class)->stockByLot(['from' => '2026-10-01', 'to' => '2026-10-03', 'group_by' => 'lot'], $this->context(), 1)->get();
        $this->assertEquals(100, $rows->firstWhere('product_id', $rbm->id)->current_qty);
        $this->assertSame([], app(InventoryReconciliationService::class)->differences());
        app(CommercialReversalService::class)->reverse($bill, '2026-10-03', 'Wrong returned name', $this->context());
        $this->assertEquals(200, $material->fresh()->qty);
        $this->assertEquals(0, $rbm->fresh()->qty);
        $this->assertEquals(1000, $value($material->id));
        $this->assertEquals(0, $value($rbm->id));
        $this->assertArrayNotHasKey('stock_name_change', $receipt->fresh()->details_json);
        $service->serviceBill($receipt->id, $data, 'named-again', $this->context(), 1);
        $this->assertSame(1, Product::where('name', 'RBM')->count());
        $this->assertEquals(100, $rbm->fresh()->qty);
        $this->assertSame([], app(InventoryReconciliationService::class)->differences());
    }

    public function test_existing_billed_receipt_can_move_to_existing_stock_without_rebilling(): void
    {
        [$material, $receipt, $data, $order] = $this->received();
        $target = $this->material(25, 8, ['name' => 'RBM']);
        $billed = app(JobWorkService::class)->serviceBill($receipt->id, $data, 'old-bill', $this->context(), 1);
        $before = DB::table('journal_items')->orderBy('id')->get()->toJson();
        $count = Product::count();
        $form = ['business_date' => '2026-10-03', 'stock_names' => [$material->id => 'RBM'], 'idempotency_key' => 'fix-name'];
        $workspace = $this->get('/operations/job-work/'.$order->id)->assertOk()->assertSee('Returned stock name')->getContent();
        $this->postJson('/operations/job-stock-name/'.$receipt->id, $form)->assertCreated();
        $this->assertSame($billed->service_purchase_id, $receipt->fresh()->service_purchase_id);
        $this->assertSame($before, DB::table('journal_items')->orderBy('id')->get()->toJson());
        $this->assertSame($count, Product::count());
        $this->assertEquals(125, $target->fresh()->qty);
        $this->assertEquals(900, DB::table('stock_movement_lines')->where('product_id', $target->id)->sum('value'));
        $this->postJson('/operations/job-stock-name/'.$receipt->id, $form)->assertCreated();
        $this->assertEquals(125, $target->fresh()->qty);
        $this->postJson('/operations/job-stock-name/'.$receipt->id, array_replace($form, ['stock_names' => [$material->id => 'Different']]))->assertUnprocessable();
        $after = $this->get('/operations/job-work/'.$order->id)->assertOk()->assertSee('Stock added under returned name:')->assertSee('RBM')->getContent();
        $report = $this->get('/inventory-reports/stock-by-lot?from=2026-10-01&to=2026-10-03&group_by=lot')->assertOk()->assertSee('100 returned as RBM')->assertSee('100 received from')->getContent();
        if (getenv('ERP_EXPORT_JOB_STOCK_BROWSER')) {
            foreach (['before' => $workspace, 'after' => $after, 'report' => $report] as $name => $html) file_put_contents(base_path('scratch/job-stock-'.$name.'.html'), $html);
        }
    }

    public function test_unavailable_stock_foreign_names_and_incompatible_products_roll_back(): void
    {
        [$material, $receipt, $data] = $this->received(100);
        $billed = app(JobWorkService::class)->serviceBill($receipt->id, $data, 'old-bill', $this->context(), 1);
        $service = app(JobWorkStockNaming::class);
        $this->rejected(fn () => $service->apply($receipt->id, ['business_date' => '2026-10-03', 'stock_names' => [999 => 'RBM']], 'foreign', $this->context(), 1), 'only accepted stock');
        $wrong = $this->material(0, 5, ['type' => 'service', 'name' => 'Service name']);
        $this->rejected(fn () => $service->apply($receipt->id, ['business_date' => '2026-10-03', 'stock_names' => [$material->id => $wrong->name]], 'wrong', $this->context(), 1), 'active stock');
        app(InventoryMovementService::class)->issue(new StockMovementCommand(date: '2026-10-03', lines: [new StockLine($material->id, 1)], warehouseId: 1,
            sourceType: 'test', sourceId: 1, idempotencyKey: 'sold-one', userId: 1, context: $this->context()));
        $count = DB::table('stock_movements')->count();
        $this->rejected(fn () => $service->apply($receipt->id, ['business_date' => '2026-10-03', 'stock_names' => [$material->id => 'RBM']], 'missing', $this->context(), 1), 'Not enough returned stock');
        $this->assertSame($count, DB::table('stock_movements')->count());
        $this->assertFalse(Product::where('name', 'RBM')->exists());
        $this->assertSame($billed->service_purchase_id, $receipt->fresh()->service_purchase_id);
    }

    public function test_returned_lot_moves_to_a_product_owned_lot_and_used_stock_blocks_reversal(): void
    {
        app(IndustryProfileService::class)->apply('textile', 'manufacturing', $this->context(), 1);
        (require database_path('migrations/2026_10_16_000005_job_work_textile_process_route.php'))->up();
        $material = $this->material(200, 5);
        $inventory = app(InventoryMovementService::class);
        $lot = $inventory->receive(new StockMovementCommand(date: '2026-10-03', warehouseId: 1, userId: 1, context: $this->context(),
            lines: [new StockLine($material->id, 100, unitCost: 5, batch: ['batch_no' => 'RB-LOT'])]))->lines->first()->batch_id;
        $service = app(JobWorkService::class);
        $process = $service->configureProcess(['code' => 'LOT', 'name' => 'Finishing', 'max_loss_percent' => 4, 'conversion' => false], $this->context(), 1);
        $order = $service->order(['job_worker_party_id' => 1, 'process_type_id' => $process, 'business_date' => '2026-10-03'], 'order', $this->context(), 1);
        $dispatch = $service->dispatch($order->id, ['warehouse_id' => 1, 'business_date' => '2026-10-03',
            'lines' => [['product_id' => $material->id, 'product_batch_id' => $lot, 'qty' => 100]]], 'dispatch', $this->context(), 1);
        $line = DB::table('job_work_dispatch_lines')->where('dispatch_id', $dispatch->id)->first();
        $receipt = $service->receive($dispatch->id, ['warehouse_id' => 1, 'business_date' => '2026-10-03',
            'lines' => [['dispatch_line_id' => $line->id, 'accepted_qty' => 100, 'rejected_qty' => 0, 'loss_qty' => 0]]], 'receive', $this->context(), 1);
        $charge = $this->material(0, 0, ['type' => 'service']);
        $billed = $service->serviceBill($receipt->id, ['business_date' => '2026-10-03', 'stock_names' => [$material->id => 'RBM'],
            'items' => [['product_id' => $charge->id, 'qty' => 100, 'net_unit_cost' => 2]]], 'bill', $this->context(), 1);
        $target = Product::where('name', 'RBM')->sole();
        $newLot = DB::table('product_batches')->where('product_id', $target->id)->where('batch_no', 'RB-LOT')->sole();
        $this->assertNotEquals($lot, $newLot->id);
        $this->assertEquals(100, $newLot->qty);
        $this->assertNull($newLot->expired_date);
        $this->assertEquals(200, $material->fresh()->qty);
        $this->assertEquals(0, DB::table('product_batches')->where('id', $lot)->value('qty'));
        $this->get('/inventory-reports/stock-by-lot?from=2026-10-01&to=2026-10-03&group_by=lot&lot=RB-LOT')->assertOk()->assertSee('100 returned as RBM');
        $inventory->issue(new StockMovementCommand(date: '2026-10-03', warehouseId: 1, userId: 1, context: $this->context(),
            lines: [new StockLine($target->id, 1, batchId: $newLot->id)]));
        $count = DB::table('journal_entries')->count();
        $this->rejected(fn () => app(CommercialReversalService::class)->reverse(Purchase::findOrFail($billed->service_purchase_id),
            '2026-10-03', 'Wrong stock name', $this->context()), 'Returned stock was used');
        $this->assertSame($count, DB::table('journal_entries')->count());
        $this->assertSame([], app(InventoryReconciliationService::class)->differences());
    }
}
