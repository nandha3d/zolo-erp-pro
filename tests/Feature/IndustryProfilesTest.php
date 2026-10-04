<?php

namespace Tests\Feature;

use App\Models\Inventory\StockDimension;
use App\Models\Inventory\StockIdentity;
use App\Models\Inventory\StockMovement;
use App\Services\Commercial\SaleApplicationService;
use App\Services\Commercial\SaleCommand;
use App\Services\Industry\FmcgInventoryService;
use App\Services\Industry\IndustryProfileService;
use App\Services\Industry\ProjectService;
use App\Services\Inventory\InventoryMovementService;
use App\Services\Inventory\InventoryReconciliationService;
use App\Services\Inventory\StockLine;
use App\Services\Inventory\StockMovementCommand;
use App\Services\Platform\CapabilityService;
use Illuminate\Support\Facades\DB;
use Tests\Support\OperationsTestCase;

class IndustryProfilesTest extends OperationsTestCase
{
    public function test_profiles_subtypes_attributes_and_general_trading_do_not_leak_or_erase_history(): void
    {
        $profiles = app(IndustryProfileService::class); $product = $this->material();
        $profiles->apply('textile', 'wholesale', $this->context(), 1);
        $this->assertSame(3, $profiles->settings($this->context())['settings']['quantity_scale']);
        $this->assertSame(68, $profiles->settings($this->context())['settings']['print']['lines']);
        $profiles->attributes($product->id, $this->context(), 1, ['fabric_type' => 'Cotton', 'gsm' => 180]);
        $this->assertSame('Cotton', collect($profiles->attributes($product->id, $this->context(), 1))->firstWhere('key', 'fabric_type')['value']);
        $profiles->apply('general_trading', 'trading', $this->context(), 1);
        $this->assertSame([], $profiles->attributes($product->id, $this->context(), 1));
        $this->assertSame(2, DB::table('product_attribute_values')->count());
        $this->assertSame(0, DB::table('company_industry_settings')->where('company_id', $this->other->id)->count());
        $this->rejected(fn () => $profiles->attributes($product->id, $this->context(), 1, ['fabric_type' => 'Silk']), 'enabled profile');
        $profiles->apply('fmcg', 'manufacturer', $this->context(), 1);
        $this->assertTrue(app(CapabilityService::class)->enabled('manufacturing.production', $this->context()));
        $profiles->apply('timber', 'processor', $this->context(), 1);
        $this->assertTrue(app(CapabilityService::class)->enabled('inventory.dimension_tracking', $this->context()));
        $this->assertTrue(app(CapabilityService::class)->enabled('operations.job_work', $this->context()));
    }

    public function test_fmcg_fefo_and_ten_plus_one_scheme_issue_all_free_stock(): void
    {
        app(IndustryProfileService::class)->apply('fmcg', 'distribution', $this->context(), 1);
        $product = $this->material(0, 2, ['is_batch' => true]);
        foreach ([['LATE', '2027-01-01', 20], ['EARLY', '2026-11-01', 5]] as [$no, $expiry, $qty]) {
            app(InventoryMovementService::class)->receive(new StockMovementCommand(date: '2026-10-03',
                lines: [new StockLine(productId: $product->id, qty: $qty, unitCost: 2, batch: ['batch_no' => $no, 'expired_date' => $expiry])],
                warehouseId: 1, context: $this->context(), userId: 1));
        }
        $service = app(FmcgInventoryService::class);
        $scheme = $service->configureScheme(['product_id' => $product->id, 'name' => '10+1', 'buy_qty' => 10, 'free_qty' => 1,
            'valid_from' => '2026-10-01', 'valid_to' => '2026-12-31'], $this->context(), 1);
        $picks = $service->suggest($product->id, 1, 11, '2026-10-03', $this->context(), 1);
        $this->assertSame('EARLY', $picks[0]['batch_no']); $this->assertEquals(5, $picks[0]['qty_base']);
        $sale = app(SaleApplicationService::class)->create(new SaleCommand($this->saleData($product, [
            'items' => [['product_id' => $product->id, 'qty' => 10, 'net_unit_price' => 10, 'quantity_scheme_id' => $scheme]],
        ]), 'sale-scheme', 1, $this->context()));
        $this->assertEquals(100, $sale->grand_total); $this->assertEquals(11, $sale->productSales->sum('qty'));
        $this->assertEquals(14, $product->fresh()->qty); $this->assertEquals(1, $sale->productSales->where('net_unit_price', 0)->sum('qty'));
        $this->assertSame([], app(InventoryReconciliationService::class)->differences());
    }

    public function test_expiry_sale_is_blocked_but_audited_writeoff_posts_stock_and_loss(): void
    {
        app(IndustryProfileService::class)->apply('fmcg', null, $this->context(), 1);
        $product = $this->material(0, 2, ['is_batch' => true]);
        $movement = app(InventoryMovementService::class)->receive(new StockMovementCommand(date: '2026-10-03',
            lines: [new StockLine(productId: $product->id, qty: 3, unitCost: 2, batch: ['batch_no' => 'EXPIRED', 'expired_date' => '2026-10-01'])],
            warehouseId: 1, context: $this->context(), userId: 1));
        $batch = $movement->lines->sole()->batch_id;
        $this->rejected(fn () => app(FmcgInventoryService::class)->suggest($product->id, 1, 1, '2026-10-03', $this->context(), 1), 'Insufficient');
        $writeoff = app(FmcgInventoryService::class)->writeOff(['warehouse_id' => 1, 'business_date' => '2026-10-03', 'reason' => 'Expired stock',
            'lines' => [['product_id' => $product->id, 'product_batch_id' => $batch, 'qty' => 3]]], 'dispose', $this->context(), 1);
        $this->assertSame('expiry_writeoff', $writeoff->movement_type); $this->assertEquals(0, $product->fresh()->qty);
        $this->assertEquals(6, DB::table('journal_entries')->where('reference_type', 'stock_loss')->sum('total_debit'));
        $this->assertSame([], app(InventoryReconciliationService::class)->differences());
    }

    public function test_dimensions_persist_normalized_volume_and_selected_piece_balance(): void
    {
        app(IndustryProfileService::class)->apply('timber', 'trading', $this->context(), 1);
        $product = $this->material(0);
        $movement = app(InventoryMovementService::class)->receive(new StockMovementCommand(date: '2026-10-03',
            lines: [new StockLine(productId: $product->id, qty: 20, unitCost: 3, dimensions: ['identity_no' => 'TIMBER-1',
                'length' => 10, 'width' => 1, 'thickness' => 1, 'pieces' => 2, 'dimension_uom' => 'ft', 'volume_uom' => 'CFT'])],
            warehouseId: 1, context: $this->context(), userId: 1));
        $dimension = StockDimension::sole(); $identity = StockIdentity::sole();
        $this->assertEquals(20, $dimension->computed_cft); $this->assertEqualsWithDelta(0.566337, $dimension->computed_cbm, 0.000001);
        $this->assertSame('rectangular-v1', $dimension->formula_version);
        app(InventoryMovementService::class)->issue(new StockMovementCommand(date: '2026-10-03',
            lines: [new StockLine(productId: $product->id, qty: 5, identityId: $identity->id)], warehouseId: 1, context: $this->context(), userId: 1));
        $this->assertEquals(15, $product->fresh()->qty); $this->assertEquals(20, $dimension->fresh()->computed_cft);
        $this->rejected(fn () => $dimension->update(['formula_version' => 'changed']), 'immutable');
    }

    public function test_solar_quote_serial_lifecycle_invoice_margin_and_replacement_history(): void
    {
        $profiles = app(IndustryProfileService::class); $profiles->apply('solar', 'epc', $this->context(), 1);
        $panel = $this->material(0, 50, ['is_imei' => true, 'price' => 100]);
        $system = $this->material(0); $bom = $this->bom($panel, $system, ['output_qty' => 5,
            'lines' => [['component_product_id' => $panel->id, 'qty' => 1, 'uom_id' => 1]]]);
        $profiles->attributes($panel->id, $this->context(), 1, ['warranty_months' => 120, 'wattage' => 500]);
        app(InventoryMovementService::class)->receive(new StockMovementCommand(date: '2026-10-03',
            lines: [new StockLine(productId: $panel->id, qty: 2, unitCost: 50, serials: ['SOLAR-1', 'SOLAR-2'])], warehouseId: 1, context: $this->context(), userId: 1));
        $service = app(ProjectService::class);
        $project = $service->create(['title' => '5kW Site', 'client_id' => 1, 'site_address' => 'Site A', 'system_kw' => 5, 'business_date' => '2026-10-03'], 'project', $this->context(), 1);
        $service->quotation($project->id, ['bom_id' => $bom->id, 'qty' => 5, 'warehouse_id' => 1, 'business_date' => '2026-10-03'], 'quote', $this->context(), 1);
        $this->assertSame(2, (int) DB::table('sales')->value('sale_status'));
        $this->assertEquals(2, $panel->fresh()->qty);
        $installed = [];
        foreach (['SOLAR-1', 'SOLAR-2'] as $i => $serial) {
            $identity = StockIdentity::where('identity_no', $serial)->first();
            $reservation = $service->allocate($project->id, ['stock_identity_id' => $identity->id, 'business_date' => '2026-10-03'], 'allocate-'.$i, $this->context(), 1);
            $this->rejected(fn () => $service->allocate($project->id, ['stock_identity_id' => $identity->id], 'duplicate-'.$i, $this->context(), 1), 'already allocated');
            $service->dispatch($reservation->id, ['business_date' => '2026-10-03'], 'dispatch-'.$i, $this->context(), 1);
            $data = ['business_date' => '2026-10-03'];
            if ($i) $data['replaces_installation_id'] = $installed[0]->id;
            $installed[] = $service->install($reservation->id, $data, 'install-'.$i, $this->context(), 1);
            $service->commission($installed[$i]->id, ['business_date' => '2026-10-03', 'amc_months' => 12], 'commission-'.$i, $this->context(), 1);
            if (!$i) {
                $sale = app(SaleApplicationService::class)->create(new SaleCommand($this->saleData($panel, ['warehouse_id' => $project->site_warehouse_id,
                    'project_id' => $project->id, 'items' => [['product_id' => $panel->id, 'qty' => 1, 'net_unit_price' => 100, 'serials' => [$serial]]]]), 'invoice', 1, $this->context()));
                $this->assertEquals(100, $sale->grand_total);
            }
        }
        $serviceData = ['event' => 'inspection', 'notes' => 'Warranty inspection passed', 'business_date' => '2026-10-03', 'idempotency_key' => 'inspection'];
        $this->postJson('/operations/project-service/'.$installed[0]->id, $serviceData)->assertCreated();
        $events = DB::table('serial_service_events')->count();
        $this->postJson('/operations/project-service/'.$installed[0]->id, $serviceData)->assertCreated();
        $this->assertSame($events, DB::table('serial_service_events')->count());
        $this->get('/operations/project/'.$project->id)->assertOk()->assertSee('Warranty inspection passed')->assertSee('continues contract');
        $margin = $service->margin($project->id, $this->context(), 1);
        $this->assertSame('100.0000', $margin['revenue']); $this->assertSame('50.0000', $margin['material_cost']); $this->assertSame('50.0000', $margin['margin']);
        $this->assertSame('replaced', $installed[0]->fresh()->status);
        $this->assertSame(4, DB::table('warranty_contracts')->count());
        $first = DB::table('warranty_contracts')->where('installation_id', $installed[0]->id)->where('kind', 'warranty')->first();
        $second = DB::table('warranty_contracts')->where('installation_id', $installed[1]->id)->where('kind', 'warranty')->first();
        $this->assertSame($first->ends_on, $second->ends_on); $this->assertEquals($first->id, $second->replaces_contract_id);
        $this->assertSame([], app(InventoryReconciliationService::class)->differences());
    }
}
