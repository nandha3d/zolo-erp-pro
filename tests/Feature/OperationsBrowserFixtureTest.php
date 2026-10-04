<?php

namespace Tests\Feature;

use App\Services\Industry\IndustryProfileService;
use App\Services\Manufacturing\ProductionService;
use Illuminate\Support\Facades\DB;
use Tests\Support\OperationsTestCase;

class OperationsBrowserFixtureTest extends OperationsTestCase
{
    public function test_operator_pages_and_optional_browser_fixture_export(): void
    {
        $input = $this->material(1000); $output = $this->material(0);
        $input->forceFill(['name' => 'Raw material', 'code' => 'RAW-01'])->save();
        $output->forceFill(['name' => 'Finished product', 'code' => 'FIN-01'])->save();
        $bom = $this->bom($input, $output);
        $order = app(ProductionService::class)->plan(['bom_id' => $bom->id, 'warehouse_id' => 1,
            'planned_qty' => 10, 'business_date' => '2026-10-03'], 'browser-plan', $this->context(), 1);
        [$job] = $this->job($input, 500);
        $profiles = app(IndustryProfileService::class);
        $profiles->apply('timber', 'trading', $this->context(), 1);
        $timber = $this->material(0); $timber->forceFill(['name' => 'Teak board', 'code' => 'TEAK-01', 'price' => 6])->save();
        $profiles->attributes($timber->id, $this->context(), 1, ['species' => 'Teak', 'grade' => 'A']);
        app(\App\Services\Inventory\InventoryMovementService::class)->receive(new \App\Services\Inventory\StockMovementCommand(
            date: '2026-10-03', lines: [new \App\Services\Inventory\StockLine(productId: $timber->id, qty: 20, unitCost: 3,
                dimensions: ['identity_no' => 'TEAK-20', 'length' => 10, 'width' => 1, 'thickness' => 1, 'pieces' => 2, 'dimension_uom' => 'ft', 'grade' => 'A'])],
            warehouseId: 1, context: $this->context(), userId: 1));
        $profiles->apply('solar', 'epc', $this->context(), 1);
        $panel = $this->material(0, 50, ['is_imei' => true, 'price' => 100]); $panel->forceFill(['name' => 'Solar panel', 'code' => 'PANEL-01'])->save();
        $profiles->attributes($panel->id, $this->context(), 1, ['wattage' => 500, 'warranty_months' => 120]);
        app(\App\Services\Inventory\InventoryMovementService::class)->receive(new \App\Services\Inventory\StockMovementCommand(
            date: '2026-10-03', lines: [new \App\Services\Inventory\StockLine(productId: $panel->id, qty: 1, unitCost: 50, serials: ['PANEL-DEMO'])],
            warehouseId: 1, context: $this->context(), userId: 1));
        app(\App\Services\Industry\ProjectService::class)->create(['title' => '5kW rooftop', 'client_id' => 1,
            'site_address' => 'Fixture rooftop', 'system_kw' => 5, 'business_date' => '2026-10-03'], 'browser-site', $this->context(), 1);
        $profiles->apply('textile', 'in_house_processing', $this->context(), 1);
        $this->get('/operations/manufacturing')->assertOk();
        $this->get('/operations/production/'.$order->id)->assertOk();
        $this->get('/operations/job-work/'.$job->id)->assertOk();
        $this->get('/operations/profiles')->assertOk();
        if (getenv('ERP_EXPORT_OPERATIONS_BROWSER') === '1') {
            $this->assertSame('sqlite', DB::connection()->getDriverName());
            $path = base_path('scratch/operations-browser-'.bin2hex(random_bytes(6)).'.sqlite');
            DB::statement('VACUUM INTO '.DB::connection()->getPdo()->quote($path));
            file_put_contents(base_path('scratch/operations-browser-path.txt'), $path);
        }
    }
}
