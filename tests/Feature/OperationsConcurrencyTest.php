<?php

namespace Tests\Feature;

use App\Models\Inventory\StockIdentity;
use App\Services\Industry\{IndustryProfileService, ProjectService};
use App\Services\Manufacturing\ProductionService;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\Support\OperationsTestCase;

class OperationsConcurrencyTest extends OperationsTestCase
{
    public function test_two_production_replays_post_the_same_movements_and_journal_once(): void
    {
        $this->requireMysql();
        $input = $this->material(100); $output = $this->material(0); $bom = $this->bom($input, $output);
        $order = app(ProductionService::class)->plan(['bom_id' => $bom->id, 'warehouse_id' => 1, 'planned_qty' => 10,
            'business_date' => '2026-10-03'], 'race-plan', $this->context(), 1);
        $results = $this->race('production', $order->id);
        $this->assertTrue($results[0]['posted']); $this->assertSame($results[0]['id'], $results[1]['id']);
        $this->assertSame(2, DB::table('stock_movements')->where('source_type', 'production_orders')->count());
        $this->assertSame(1, DB::table('journal_entries')->where('reference_type', 'production')->count());
        $this->assertEquals(10, $output->fresh()->qty);
    }

    public function test_two_partial_receipts_cannot_receive_the_same_remaining_material(): void
    {
        $this->requireMysql();
        $material = $this->material(500); [, $dispatch, $line] = $this->job($material);
        $results = $this->race('receipt', $dispatch->id, $line->id);
        $this->assertCount(1, array_filter($results, fn ($row) => $row['posted']));
        $this->assertSame(1, DB::table('job_work_receipts')->count());
        $this->assertEquals(200, DB::table('product_warehouse')->where('product_id', $material->id)->where('warehouse_id', '!=', 1)->sum('qty'));
    }

    public function test_two_project_allocations_cannot_reserve_one_serial_twice(): void
    {
        $this->requireMysql();
        app(IndustryProfileService::class)->apply('solar', 'epc', $this->context(), 1);
        $product = $this->material(0, 5, ['is_imei' => true]);
        app(\App\Services\Inventory\InventoryMovementService::class)->receive(new \App\Services\Inventory\StockMovementCommand(
            date: '2026-10-03', lines: [new \App\Services\Inventory\StockLine(productId: $product->id, qty: 1, unitCost: 5, serials: ['RACE-SERIAL'])],
            warehouseId: 1, context: $this->context(), userId: 1));
        $project = app(ProjectService::class)->create(['title' => 'Race site', 'client_id' => 1, 'site_address' => 'Test site',
            'system_kw' => 5, 'business_date' => '2026-10-03'], 'race-site', $this->context(), 1);
        $results = $this->race('serial', $project->id, StockIdentity::sole()->id);
        $this->assertCount(1, array_filter($results, fn ($row) => $row['posted']));
        $this->assertSame(1, DB::table('project_serial_reservations')->count());
        $this->assertEquals(1, $product->fresh()->qty);
    }

    private function requireMysql(): void
    {
        if (getenv('ERP_TEST_MYSQL') !== '1') $this->markTestSkipped('Requires disposable MySQL row-lock proof.');
    }

    private function race(string $mode, int $id, int $line = 0): array
    {
        $workers = [];
        DB::beginTransaction(); DB::table('companies')->where('id', $this->company->id)->lockForUpdate()->first();
        try {
            foreach ([0, 1] as $i) {
                $worker = new Process([PHP_BINARY, base_path('tests/Support/operations_worker.php'), (string) $this->company->id,
                    (string) $this->branch->id, (string) $this->year->id, $mode, (string) $id, (string) $line, (string) $i]);
                $worker->setTimeout(60); $worker->start(); $workers[] = $worker;
            }
        } finally { DB::commit(); }
        return array_map(function ($worker) {
            $worker->wait(); $this->assertTrue($worker->isSuccessful(), $worker->getErrorOutput().$worker->getOutput());
            return json_decode($worker->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        }, $workers);
    }
}
