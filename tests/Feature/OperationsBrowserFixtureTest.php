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
        app(IndustryProfileService::class)->apply('textile', 'in_house_processing', $this->context(), 1);
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
