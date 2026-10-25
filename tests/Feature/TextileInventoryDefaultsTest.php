<?php

namespace Tests\Feature;

use App\Models\{Category, Product, Unit};
use App\Services\Industry\{IndustryProfileService, IndustryStockQueries};
use App\Services\Inventory\{InventoryMovementService, InventoryPolicy, StockLine, StockMovementCommand, StockPolicyException, UomConversionService};
use App\Services\JobWork\JobWorkService;
use App\Services\Tax\GstSetupService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};
use Tests\Support\OperationsTestCase;

class TextileInventoryDefaultsTest extends OperationsTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        foreach (['base_unit', 'operator', 'operation_value'] as $column) {
            if (!Schema::hasColumn('units', $column)) Schema::table('units', fn (Blueprint $t) => $t->string($column)->nullable());
        }
        if (!Schema::hasColumn('products', 'hsn_code')) Schema::table('products', fn (Blueprint $t) => $t->string('hsn_code')->nullable());
        if (!Schema::hasColumn('categories', 'updated_at')) Schema::table('categories', fn (Blueprint $t) => $t->timestamps());
        (require database_path('migrations/2026_10_16_000005_job_work_textile_process_route.php'))->up();
        (require database_path('migrations/2026_10_17_000010_textile_inventory_defaults.php'))->up();
    }

    private function command(Product $product, float $qty, string $date = '2026-10-03', array $line = []): StockMovementCommand
    {
        return new StockMovementCommand(date: $date, lines: [StockLine::fromArray(['product_id' => $product->id, 'qty' => $qty] + $line)],
            warehouseId: 1, context: $this->context(), userId: 1);
    }

    public function test_explicit_item_policy_precedes_company_and_company_edits_are_not_cached(): void
    {
        $product = $this->material(0); $policy = app(InventoryPolicy::class);
        DB::table('companies')->where('id', $this->company->id)->update(['settings_json' => json_encode(['inventory' => ['negative_stock' => 'block']])]);
        $this->assertSame('block', $policy->negativeStock($this->company->id, $product));
        DB::table('companies')->where('id', $this->company->id)->update(['settings_json' => json_encode(['inventory' => ['negative_stock' => 'warn']])]);
        $this->assertSame('warn', $policy->negativeStock($this->company->id, $product));
        $product->forceFill(['negative_stock_policy' => 'allow'])->save();
        $this->assertSame('allow', $policy->negativeStock($this->company->id, $product));
        app(InventoryMovementService::class)->issue($this->command($product, 1));
        $this->assertEquals(-1, $product->fresh()->qty);
        $product->forceFill(['negative_stock_policy' => 'warn'])->save();
        $warning = app(InventoryMovementService::class)->issue($this->command($product, 1));
        $this->assertCount(1, $warning->warnings_json);
        $product->forceFill(['negative_stock_policy' => 'block'])->save();
        $before = DB::table('stock_movements')->count();
        try { app(InventoryMovementService::class)->issue($this->command($product, 1)); $this->fail('Expected stock rejection.'); }
        catch (StockPolicyException $e) { $this->assertStringContainsString('Insufficient', $e->getMessage()); }
        $this->assertSame($before, DB::table('stock_movements')->count());
        $this->assertEquals(-2, $product->fresh()->qty);
        $this->assertSame('block', $policy->negativeStock($this->other->id, $product));
    }

    public function test_mixed_document_rolls_back_allow_line_when_another_product_blocks_and_serial_rules_remain(): void
    {
        $allow = $this->material(0, 5, ['negative_stock_policy' => 'allow']);
        $block = $this->material(0, 5, ['negative_stock_policy' => 'block']);
        $before = DB::table('stock_movements')->count();
        try {
            app(InventoryMovementService::class)->issue(new StockMovementCommand(date: '2026-10-03', warehouseId: 1, context: $this->context(), userId: 1,
                lines: [new StockLine(productId: $allow->id, qty: 1), new StockLine(productId: $block->id, qty: 1)]));
            $this->fail('Expected stock rejection.');
        } catch (StockPolicyException) {}
        $this->assertEquals(0, $allow->fresh()->qty); $this->assertSame($before, DB::table('stock_movements')->count());
        $serial = $this->material(0, 5, ['is_imei' => true, 'negative_stock_policy' => 'allow']);
        $this->expectException(StockPolicyException::class);
        app(InventoryMovementService::class)->issue($this->command($serial, 1));
    }

    public function test_upgrade_leaves_ignored_legacy_flag_inherited_and_replays_without_duplicate_units(): void
    {
        if (!Schema::hasColumn('products', 'allow_negative_stock')) Schema::table('products', fn (Blueprint $t) => $t->boolean('allow_negative_stock')->default(true));
        $product = $this->material(0);
        DB::table('units')->where('id', 1)->update(['unit_code' => 'NOS', 'unit_name' => 'Nos']);
        app(IndustryProfileService::class)->apply('textile', 'wholesale', $this->context(), 1);
        $count = DB::table('units')->where('company_id', $this->company->id)->count();
        (require database_path('migrations/2026_10_17_000010_textile_inventory_defaults.php'))->up();
        (require database_path('migrations/2026_10_17_000010_textile_inventory_defaults.php'))->up();
        $this->assertNull($product->fresh()->negative_stock_policy);
        $this->assertSame($count, DB::table('units')->where('company_id', $this->company->id)->count());
        $this->assertSame(0, DB::table('units')->where('company_id', $this->company->id)->where('unit_code', 'PCS')->count());
        foreach (['MTR', 'KGS', 'DOZ'] as $code) $this->assertSame(1, DB::table('units')->where('company_id', $this->company->id)->where('unit_code', $code)->count());
        $this->assertSame(0, DB::table('units')->where('company_id', $this->other->id)->count());
    }

    public function test_dozen_converts_only_compatible_count_products_and_keeps_explicit_factor(): void
    {
        app(IndustryProfileService::class)->apply('textile', 'wholesale', $this->context(), 1);
        $units = DB::table('units')->where('company_id', $this->company->id)->pluck('id', 'unit_code');
        $product = $this->material(0); $uom = app(UomConversionService::class);
        $this->assertEquals(24, $uom->toBase($product, 2, $units['DOZ']));
        DB::table('units')->where('id', $units['DOZ'])->update(['base_unit' => 1, 'operator' => '*', 'operation_value' => 10]);
        $this->assertEquals(20, $uom->toBase($product, 2, $units['DOZ']));
        $product->forceFill(['unit_id' => $units['MTR']])->save();
        $this->expectException(\InvalidArgumentException::class);
        $uom->toBase($product, 2, $units['DOZ']);
    }

    public function test_bleaching_requires_configuration_then_preserves_customized_policy_on_replay(): void
    {
        $profiles = app(IndustryProfileService::class); $profiles->apply('textile', 'manufacturing', $this->context(), 1);
        $process = DB::table('process_types')->where('company_id', $this->company->id)->where('code', 'BLEACHING')->first();
        $this->assertNotNull($process); $this->assertFalse((bool) $process->is_active); $this->assertEquals(0, $process->max_loss_percent);
        app(JobWorkService::class)->configureProcess(['code' => 'BLEACHING', 'name' => 'Our Bleaching', 'max_loss_percent' => 1.25, 'conversion' => true], $this->context(), 1);
        $profiles->apply('textile', 'manufacturing', $this->context(), 1);
        $process = DB::table('process_types')->find($process->id);
        $this->assertSame('Our Bleaching', $process->name); $this->assertEquals(1.25, $process->max_loss_percent);
        $this->assertTrue((bool) $process->conversion); $this->assertTrue((bool) $process->is_active);
    }

    public function test_hsn_uses_explicit_then_direct_group_and_preserves_existing_product(): void
    {
        $gst = app(GstSetupService::class); $group = Category::findOrFail(1); $group->update(['default_hsn_code' => '5208']);
        $this->assertSame('5208', $gst->productCode(null, 1, 'standard', $this->context()));
        $this->assertSame('5407', $gst->productCode('54 07', 1, 'standard', $this->context()));
        $this->assertNull($gst->productCode(null, null, 'standard', $this->context()));
        $product = $this->material(0, 5, ['hsn_code' => '5208']);
        $group->update(['default_hsn_code' => '5407']); $this->assertSame('5208', $product->fresh()->hsn_code);
        $this->rejected(fn () => $gst->productCode(null, 1, 'service', $this->context()), 'SAC');
        $foreign = Category::forceCreate(['company_id' => $this->other->id, 'name' => 'Foreign', 'default_hsn_code' => '5208']);
        $this->rejected(fn () => $gst->productCode('5208', $foreign->id, 'standard', $this->context()), 'item group');
    }

    public function test_quick_product_inherits_group_code_and_policy_and_rejects_foreign_or_wrong_kind_before_creation(): void
    {
        foreach (['brand_id','profit_margin_type','profit_margin','wholesale_price','daily_sale_objective','warranty','warranty_type',
            'guarantee','guarantee_type','featured','is_embeded','product_details'] as $column) {
            if (!Schema::hasColumn('products', $column)) Schema::table('products', fn (Blueprint $t) => $t->string($column)->nullable());
        }
        DB::table('categories')->where('id', 1)->update(['default_hsn_code' => '5208']);
        $id = $this->postJson('/products/quick-store', ['name' => 'New cotton', 'code' => 'COTTON-QUICK', 'category_id' => 1,
            'unit_id' => 1, 'negative_stock_policy' => 'warn'])->assertOk()->json('product.id');
        $this->assertSame('5208', Product::findOrFail($id)->hsn_code);
        $this->assertSame('warn', Product::findOrFail($id)->negative_stock_policy);
        $before = Product::count();
        $this->postJson('/products/quick-store', ['name' => 'Invalid service', 'type' => 'service', 'category_id' => 1])->assertUnprocessable();
        $foreign = Category::forceCreate(['company_id' => $this->other->id, 'name' => 'Foreign group', 'default_hsn_code' => '5208']);
        $this->postJson('/products/quick-store', ['name' => 'Foreign product', 'category_id' => $foreign->id])->assertUnprocessable();
        $this->postJson('/products/quick-store', ['name' => 'Bad policy', 'category_id' => 1, 'negative_stock_policy' => 'yes'])->assertUnprocessable();
        $this->postJson('/products', ['name' => 'Invalid normal service', 'type' => 'service', 'category_id' => 1])->assertUnprocessable();
        $this->assertSame($before, Product::count());
    }

    public function test_lot_report_has_opening_inward_outward_closing_reversal_and_branch_scope_without_expiry(): void
    {
        app(IndustryProfileService::class)->apply('textile', 'wholesale', $this->context(), 1);
        $product = $this->material(0, 5, ['is_batch' => true]); $inventory = app(InventoryMovementService::class);
        $receipt = $inventory->receive($this->command($product, 10, '2026-10-02', ['unit_cost' => 5, 'batch' => ['batch_no' => 'LOT-A']]));
        $batch = $receipt->lines->first()->batch_id;
        $issue = $inventory->issue($this->command($product, 3, '2026-10-03', ['product_batch_id' => $batch]));
        $inventory->reverse($issue, 'Wrong issue', 1, '2026-10-04');
        $rows = app(IndustryStockQueries::class)->stockByLot(['from' => '2026-10-03', 'to' => '2026-10-03'], $this->context(), 1)->get();
        $this->assertCount(1, $rows); $row = $rows->first();
        $this->assertSame('LOT-A', $row->lot); $this->assertEquals(10, $row->opening_qty); $this->assertEquals(0, $row->inward_qty);
        $this->assertEquals(3, $row->outward_qty); $this->assertEquals(7, $row->closing_qty); $this->assertEquals(10, $row->current_qty);
        $this->assertEquals(10, $row->owned_current_qty); $this->assertFalse(app(\App\Services\Platform\CapabilityService::class)->enabled('inventory.batch_expiry', $this->context()));
        DB::table('warehouses')->where('id', 1)->update(['branch_id' => $this->otherBranch->id]);
        $this->assertCount(0, app(IndustryStockQueries::class)->stockByLot(['from' => '2026-10-03', 'to' => '2026-10-04'], $this->context(), 1)->get());
    }

    public function test_current_custody_and_gdni_reconcile_after_partial_billing_and_reversal(): void
    {
        (require database_path('migrations/2026_10_13_000004_create_optech_dc_and_grn_tables.php'))->up();
        (require database_path('migrations/2026_10_17_000007_post_material_documents_physically.php'))->up();
        \App\Models\Accounting\ChartOfAccount::forceCreate(['company_id' => $this->company->id, 'code' => 'GDNI-TEST',
            'name' => 'goods_delivered_not_invoiced', 'type' => 'asset', 'sub_type' => 'goods_delivered_not_invoiced']);
        app(\App\Services\Accounting\SemanticAccountResolver::class)->seedCompany($this->company->id);
        $product = $this->material(10);
        $id = $this->postJson('/delivery-challans', ['challan_date' => '2026-10-03', 'customer_id' => 1, 'warehouse_id' => 1,
            'purpose' => 'approval', 'unit_id' => [1], 'product_id' => [$product->id], 'qty' => [4], 'rate' => [10]])->assertCreated()->json('challan.id');
        $query = fn () => app(IndustryStockQueries::class)->stockByLot(['from' => '2026-10-03', 'to' => '2026-10-03'], $this->context(), 1)->first();
        $row = $query(); $this->assertEquals(6, $row->current_qty); $this->assertEquals(4, $row->custody_qty);
        $this->assertEquals(20, $row->custody_value); $this->assertEquals(10, $row->owned_current_qty);
        $sale = app(\App\Services\Commercial\SaleApplicationService::class)->create(new \App\Services\Commercial\SaleCommand(
            $this->saleData($product, ['delivery_challan_id' => $id, 'items' => [['product_id' => $product->id, 'qty' => 2, 'net_unit_price' => 10]]]),
            'lot-custody-sale', 1, $this->context()));
        $row = $query(); $this->assertEquals(6, $row->current_qty); $this->assertEquals(2, $row->custody_qty);
        $this->assertEquals(10, $row->custody_value); $this->assertEquals(8, $row->owned_current_qty);
        app(\App\Services\Commercial\CommercialReversalService::class)->reverse($sale, '2026-10-03', 'Wrong invoice', $this->context());
        $row = $query(); $this->assertEquals(6, $row->current_qty); $this->assertEquals(4, $row->custody_qty);
        $this->assertEquals(10, $row->owned_current_qty);
        $dc = \App\Models\DeliveryChallan::findOrFail($id);
        DB::transaction(fn () => app(\App\Services\Commercial\MaterialDocumentService::class)->returnOpen($dc, [$dc->items->first()->id => 1], '2026-10-03', 'Approval returned', $this->context(), 1, 'lot-return'));
        $row = $query(); $this->assertEquals(7, $row->current_qty); $this->assertEquals(3, $row->custody_qty);
        $this->assertEquals(15, $row->custody_value); $this->assertEquals(10, $row->owned_current_qty);
    }

    public function test_lot_http_view_export_and_filters_keep_current_balances_explicit(): void
    {
        // The route is intentionally independent of optional batch-expiry capability.
        if (!\Illuminate\Support\Facades\Route::has('inventory-reports.stock-by-lot')) require base_path('routes/inventory-reports.php');
        $product = $this->material(5);
        $url = '/inventory-reports/stock-by-lot?from=2026-10-01&to=2026-10-04&product_id='.$product->id;
        $this->get($url)->assertOk()->assertSee('Stock by lot')->assertSee('current balances only')->assertSee('Unbatched')->assertSee('Historical owned totals are unavailable');
        $csv = $this->get($url.'&export=csv')->assertOk()->streamedContent();
        $this->assertStringContainsString('"Customer custody current"', $csv); $this->assertStringContainsString('Unbatched', $csv);
        $this->getJson('/inventory-reports/stock-by-lot?from=2026-10-04&to=2026-10-01')->assertUnprocessable();
        $this->getJson('/inventory-reports/stock-by-lot?warehouse_id=999999')->assertUnprocessable();
    }

    public function test_lot_query_paginates_full_result_and_exact_unbatched_filter(): void
    {
        $product = $this->material(5);
        $base = DB::table('stock_movements')->first();
        $line = (array) DB::table('stock_movement_lines')->first(); unset($line['id']);
        $products = []; $lines = [];
        for ($i = 0; $i < 505; $i++) {
            $p = $this->material(0); $p->update(['name' => sprintf('Report %04d', $i)]);
            $lines[] = array_merge($line, ['product_id' => $p->id, 'stock_movement_id' => $base->id, 'line_no' => $i + 2, 'qty_base' => 1]);
        }
        DB::table('stock_movement_lines')->insert($lines);
        $query = app(IndustryStockQueries::class)->stockByLot(['from' => '2026-10-01', 'to' => '2026-10-31', 'lot' => '__unbatched__'], $this->context(), 1);
        $this->assertSame(506, (clone $query)->paginate(50)->total()); $this->assertSame(506, $query->get()->count());
        if (!\Illuminate\Support\Facades\Route::has('inventory-reports.stock-by-lot')) require base_path('routes/inventory-reports.php');
        $csv = $this->get('/inventory-reports/stock-by-lot?from=2026-10-01&to=2026-10-31&lot=__unbatched__&export=csv')->assertOk()->streamedContent();
        $this->assertCount(507, array_filter(explode("\n", trim($csv))));
        $this->assertCount(1, app(IndustryStockQueries::class)->stockByLot(['from' => '2026-10-01', 'to' => '2026-10-31', 'product_id' => $product->id], $this->context(), 1)->get());
    }
}
