<?php

namespace Tests\Feature;

use App\Http\Controllers\AddonInstallController;
use App\Http\Controllers\AdjustmentController;
use App\Http\Controllers\CafeOperationsController;
use App\Http\Controllers\DamageStockController;
use App\Http\Controllers\ExchangeController;
use App\Http\Controllers\PackingSlipController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\TransferController;
use App\Http\Controllers\WarehouseController;
use App\Models\Adjustment;
use App\Models\Inventory\StockIdentity;
use App\Models\Inventory\StockMovement;
use App\Models\Product;
use App\Models\ProductAdjustment;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\Transfer;
use App\Models\User;
use App\Services\Inventory\InventoryReconciliationService;
use App\Services\Inventory\LegacyInventoryPosting;
use App\Services\Inventory\LegacyStockShadow;
use App\Services\Inventory\StockLine;
use App\Services\Inventory\StockPolicyException;
use App\Services\Platform\CompanyContext;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Manufacturing\Entities\Production;
use Modules\Manufacturing\Http\Controllers\ProductionController;
use Tests\Support\InventoryLedgerTestCase;

/** Real controller/command effects on isolated schemas, including rollback and historical compatibility. */
class LegacyInventoryCutoverTest extends InventoryLedgerTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs((new User)->forceFill(['id' => 1, 'role_id' => 1, 'is_active' => 1]));
        config(['addons' => '', 'decimal' => 2, 'without_stock' => 'no', 'inventory.legacy_ledger_mode' => 'off']);
        $migrations = [
            '2018_05_20_054532_create_adjustments_table.php' => 'CreateAdjustmentsTable',
            '2018_05_20_054859_create_product_adjustments_table.php' => 'CreateProductAdjustmentsTable',
            '2021_02_10_074859_add_variant_id_to_product_adjustments_table.php' => 'AddVariantIdToProductAdjustmentsTable',
            '2024_02_04_131826_add_unit_cost_to_product_adjustments_table.php' => null,
            '2024_07_05_192531_create_packing_slips_table.php' => null,
            '2024_07_05_193002_create_packing_slip_products_table.php' => null,
            '2024_07_14_122415_add_variant_id_to_packing_slip_products_table.php' => null,
            '2024_07_14_122519_add_packing_slip_ids_to_deliveries_table.php' => null,
        ];
        // Delivery schema is deliberately small; the stock writer only owns these columns.
        Schema::create('deliveries', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('sale_id');
            $table->integer('user_id');
            $table->string('reference_no');
            $table->text('address')->nullable();
            $table->string('recieved_by')->nullable();
            $table->integer('status');
            $table->timestamps();
        });
        foreach ($migrations as $file => $class) {
            if ($class === null) {
                (require database_path('migrations/'.$file))->up();
                continue;
            }
            require_once database_path('migrations/'.$file);
            (new $class)->up();
        }
        Schema::table('packing_slips', fn (Blueprint $table) => $table->integer('delivery_id')->nullable());
        foreach ([
            '2024_09_01_120515_create_productions_table.php' => 'CreateProductionsTable',
            '2024_09_01_120536_create_product_productions_table.php' => 'CreateProductProductionsTable',
            '2025_07_16_145551_add_production_cost_to_productions_table.php' => 'AddProductionCostToProductionsTable',
            '2025_07_22_104045_add_column_to_productions_table.php' => 'AddColumnToProductionsTable',
        ] as $file => $class) {
            require_once base_path('Modules/Manufacturing/Database/Migrations/'.$file);
            (new $class)->up();
        }
        (require database_path('migrations/2026_09_19_000003_create_damage_stock_and_exchange_tables.php'))->up();
        (require database_path('migrations/2026_09_19_000006_create_cafe_bakery_and_qr_tables.php'))->up();
        Schema::table('products', function (Blueprint $table) {
            $table->text('product_list')->nullable();
            $table->text('variant_list')->nullable();
            $table->text('qty_list')->nullable();
            $table->boolean('is_active')->default(true);
            $table->double('alert_quantity')->default(0);
            $table->integer('tax_id')->nullable();
            $table->integer('tax_method')->default(1);
            foreach (['barcode_symbology', 'product_details', 'starting_date', 'last_date', 'variant_option', 'variant_value', 'image'] as $column) {
                $table->string($column)->nullable();
            }
            foreach (['category_id', 'purchase_unit_id', 'sale_unit_id'] as $column) {
                $table->integer($column)->nullable();
            }
        });
        Schema::table('product_sales', fn (Blueprint $table) => $table->boolean('is_packing')->default(false));
        Schema::table('warehouses', function (Blueprint $table) {
            $table->boolean('is_active')->default(true);
            foreach (['email', 'phone', 'address'] as $column) {
                $table->string($column)->nullable();
            }
        });
        Schema::create('mail_settings', function (Blueprint $table) {
            $table->increments('id');
            $table->timestamps();
        });
        Schema::create('custom_fields', function (Blueprint $table) {
            $table->increments('id');
            $table->string('belongs_to');
            $table->string('name');
            $table->string('type');
        });
        Schema::create('pos_setting', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('warehouse_id');
            $table->timestamps();
        });
        Schema::create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('role_id');
            $table->boolean('is_active');
        });
        DB::table('users')->insert(['id' => 1, 'role_id' => 1, 'is_active' => true]);
        DB::table('pos_setting')->insert(['warehouse_id' => 1, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('units')->insert(['id' => 1, 'unit_name' => 'Each', 'unit_code' => 'ea', 'operator' => '*', 'operation_value' => 1, 'is_active' => true]);
        $this->installCompanyFoundation();
    }

    /** Business documents take their numbers from the company series, which needs the platform tables and one company. */
    private function installCompanyFoundation(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->increments('id');
            $table->boolean('is_active')->default(true);
        });
        DB::table('roles')->insert(['id' => 1]);
        Schema::table('users', fn (Blueprint $table) => $table->boolean('is_deleted')->default(false));
        foreach (['000001_create_company_context_tables', '000002_add_nullable_company_keys_to_core_tables', '000004_create_capability_tables', '000005_create_document_numbering_tables'] as $migration) {
            (require database_path('migrations/2026_10_03_'.$migration.'.php'))->up();
        }
        $company = \App\Models\Company::create(['code' => 'A', 'legal_name' => 'Cutover Co', 'timezone' => 'UTC']);
        $company->users()->attach(1, ['is_default' => true]);
        $branch = $company->branches()->create(['code' => 'MAIN', 'name' => 'Main']);
        DB::table('company_user_branches')->insert(['company_id' => $company->id, 'user_id' => 1, 'branch_id' => $branch->id]);
        \App\Models\Accounting\FiscalYear::create(['company_id' => $company->id, 'name' => 'Cutover FY', 'start_date' => now()->startOfYear()->toDateString(),
            'end_date' => now()->endOfYear()->toDateString(), 'status' => 'open']);
        foreach (['damage_stocks', 'exchanges'] as $table) {
            if (!Schema::hasColumn($table, 'company_id')) {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->unsignedBigInteger('company_id')->nullable());
            }
        }
        DB::table('warehouses')->update(['company_id' => $company->id]);
    }

    protected function companyWithInventoryPolicy(array $policy): int
    {
        DB::table('companies')->where('id', 1)->update(['settings_json' => json_encode(['inventory' => $policy])]);
        return 1;
    }

    private function request(array $payload, array $files = []): Request
    {
        $request = Request::create('/cutover-test', 'POST', $payload, [], $files);
        $this->app->instance('request', $request);
        return $request;
    }

    private function adjustmentPayload(Product $product, float $qty, string $action = '+', int $warehouse = 1): array
    {
        return ['warehouse_id' => $warehouse, 'product_id' => [$product->id], 'product_code' => [$product->code],
            'qty' => [$qty], 'action' => [$action], 'unit_cost' => [4], 'total_qty' => $qty, 'item' => 1];
    }

    private function transferPayload(Product $product, float $qty, int $status = 1): array
    {
        return ['from_warehouse_id' => 1, 'to_warehouse_id' => 2, 'status' => $status,
            'product_id' => [$product->id], 'product_code' => [$product->code], 'qty' => [$qty],
            'product_batch_id' => [''],
            'purchase_unit' => ['Each'], 'net_unit_cost' => [4], 'tax_rate' => [0], 'tax' => [0], 'subtotal' => [$qty * 4],
            'item' => 1, 'total_qty' => $qty, 'total_tax' => 0, 'total_cost' => $qty * 4, 'shipping_cost' => 0, 'grand_total' => $qty * 4];
    }

    private function assertReconciled(): void
    {
        $this->assertSame([], app(InventoryReconciliationService::class)->differences());
        $this->assertSame(0, StockMovement::where('projection_mode', 'shadow')->count());
        $this->assertSame(0, DB::transactionLevel());
    }

    public function test_adjustment_update_reverses_old_quantity_then_posts_replacement_and_delete_restores(): void
    {
        $product = $this->product();
        $this->receive($product, 10, 4);
        $controller = app(AdjustmentController::class);
        $controller->store($this->request($this->adjustmentPayload($product, 2, '-')));
        $adjustment = Adjustment::sole();
        $controller->update($this->request($this->adjustmentPayload($product, 3, '+', 2)), $adjustment->id);
        $this->assertEquals(10, $this->warehouseQty($product, 1));
        $this->assertEquals(3, $this->warehouseQty($product, 2));
        $this->assertEquals(13, $product->fresh()->qty);
        $this->assertEquals(3, ProductAdjustment::sole()->qty);
        $controller->destroy($adjustment->id);
        $this->assertEquals(10, $product->fresh()->qty);
        $this->assertEquals(0, $this->warehouseQty($product, 2));
        $this->assertSame(2, StockMovement::where('movement_type', 'reversal')->count());
        $this->assertReconciled();
    }

    public function test_failed_adjustment_replacement_preserves_old_document_and_stock(): void
    {
        $product = $this->product();
        $this->receive($product, 10, 4);
        $controller = app(AdjustmentController::class);
        $controller->store($this->request($this->adjustmentPayload($product, 2, '-')));
        $id = Adjustment::sole()->id;
        try {
            $controller->update($this->request($this->adjustmentPayload($product, 20, '-')), $id);
            $this->fail('Insufficient stock must reject the replacement.');
        } catch (StockPolicyException) {
        }
        $this->assertEquals(8, $product->fresh()->qty);
        $this->assertEquals(2, ProductAdjustment::sole()->qty);
        $this->assertSame(0, StockMovement::where('movement_type', 'reversal')->count());
        $this->assertReconciled();
    }

    public function test_historical_adjustment_replacement_does_not_reverse_its_compensation_on_next_edit(): void
    {
        $product = $this->product();
        $this->receive($product, 12, 4); // Includes the historical +2 document in its opening balance.
        $adjustment = Adjustment::create(['reference_no' => 'old', 'warehouse_id' => 1, 'total_qty' => 2, 'item' => 1]);
        ProductAdjustment::create(['adjustment_id' => $adjustment->id, 'product_id' => $product->id, 'qty' => 2, 'action' => '+', 'unit_cost' => 4]);
        $controller = app(AdjustmentController::class);
        $controller->update($this->request($this->adjustmentPayload($product, 3)), $adjustment->id);
        $controller->update($this->request($this->adjustmentPayload($product, 4)), $adjustment->id);
        $this->assertEquals(14, $product->fresh()->qty);
        $controller->destroy($adjustment->id);
        $this->assertEquals(10, $product->fresh()->qty);
        $this->assertReconciled();
    }

    public function test_variant_adjustment_updates_all_three_projections(): void
    {
        $product = $this->product(['is_variant' => true]);
        $variant = ProductVariant::forceCreate(['product_id' => $product->id, 'variant_id' => 7, 'item_code' => 'VAR', 'position' => 1, 'qty' => 0]);
        $payload = $this->adjustmentPayload($product, 2);
        $payload['product_variant_id'] = [$variant->id];
        app(AdjustmentController::class)->store($this->request($payload));
        $this->assertEquals(2, $variant->fresh()->qty);
        app(AdjustmentController::class)->deleteBySelection($this->request(['adjustmentIdArray' => [Adjustment::sole()->id]]));
        $this->assertEquals(0, $variant->fresh()->qty);
        $this->assertEquals(0, $product->fresh()->qty);
        $this->assertReconciled();
    }

    public function test_transfer_store_update_delete_net_and_keep_original_cost_when_master_cost_changes(): void
    {
        $product = $this->product();
        $this->receive($product, 10, 4);
        $controller = app(TransferController::class);
        $controller->store($this->request($this->transferPayload($product, 2)));
        $transfer = Transfer::sole();
        $product->update(['cost' => 99]);
        $controller->update($this->request($this->transferPayload($product, 3)), $transfer->id);
        $this->assertEquals(7, $this->warehouseQty($product));
        $this->assertEquals(3, $this->warehouseQty($product, 2));
        $controller->destroy($transfer->id);
        $this->assertEquals(10, $this->warehouseQty($product));
        $this->assertEquals(0, $this->warehouseQty($product, 2));
        $this->assertEquals(10, $product->fresh()->qty);
        $this->assertEquals(0, StockMovement::where('source_type', 'legacy:transfers')->get()->sum(fn ($movement) => $movement->lines->sum('value')));
        $this->assertReconciled();
    }

    public function test_pending_transfer_completion_is_applied_once_even_when_retried(): void
    {
        $product = $this->product();
        $this->receive($product, 10, 4);
        $controller = app(TransferController::class);
        $controller->store($this->request($this->transferPayload($product, 2, 2)));
        $id = Transfer::sole()->id;
        $this->assertSame(1, StockMovement::count());
        $controller->changeStatus($this->request(['id' => $id, 'status' => 1]));
        $controller->changeStatus($this->request(['id' => $id, 'status' => 1]));
        $this->assertSame(2, StockMovement::count());
        $this->assertEquals(8, $this->warehouseQty($product));
        $this->assertEquals(2, $this->warehouseQty($product, 2));
        $this->assertReconciled();
    }

    public function test_serial_transfer_changes_location_once_and_delete_restores_serials(): void
    {
        $product = $this->product(['is_imei' => true]);
        $this->receive($product, 2, 4, 1, ['serials' => ['S1', 'S2']]);
        $payload = $this->transferPayload($product, 1);
        $payload['imei_number'] = ['S1'];
        $controller = app(TransferController::class);
        $controller->store($this->request($payload));
        $this->assertEquals(2, StockIdentity::where('identity_no', 'S1')->value('warehouse_id'));
        $controller->destroy(Transfer::sole()->id);
        $this->assertEquals(1, StockIdentity::where('identity_no', 'S1')->value('warehouse_id'));
        $serials = StockLine::parseSerials(DB::table('product_warehouse')->where('warehouse_id', 1)->sole()->imei_number);
        sort($serials);
        $this->assertSame(['S1', 'S2'], $serials);
        $this->assertReconciled();
    }

    public function test_historical_transfer_delete_converts_division_uom_and_restores_both_warehouses(): void
    {
        $product = $this->product(['unit_id' => 1]);
        $this->receive($product, 8, 4, 1);
        $this->receive($product, 2, 4, 2);
        DB::table('units')->insert(['id' => 2, 'unit_name' => 'Half', 'unit_code' => 'half', 'base_unit' => 1, 'operator' => '/', 'operation_value' => 2, 'is_active' => true]);
        $transfer = Transfer::create(['reference_no' => 'old-transfer', 'user_id' => 1, 'status' => 1,
            'from_warehouse_id' => 1, 'to_warehouse_id' => 2, 'item' => 1, 'total_qty' => 4,
            'total_tax' => 0, 'total_cost' => 8, 'grand_total' => 8]);
        DB::table('product_transfer')->insert(['transfer_id' => $transfer->id, 'product_id' => $product->id,
            'qty' => 4, 'purchase_unit_id' => 2, 'net_unit_cost' => 2, 'tax_rate' => 0, 'tax' => 0, 'total' => 8]);
        app(TransferController::class)->destroy($transfer->id);
        $this->assertEquals(10, $this->warehouseQty($product));
        $this->assertEquals(0, $this->warehouseQty($product, 2));
        $this->assertReconciled();
    }

    public function test_in_transit_transfer_updates_aggregates_and_bulk_delete_restores(): void
    {
        $product = $this->product();
        $this->receive($product, 10, 4);
        $controller = app(TransferController::class);
        $controller->store($this->request($this->transferPayload($product, 2, 3)));
        $this->assertEquals(8, $product->fresh()->qty);
        $this->assertEquals(0, $this->warehouseQty($product, 2));
        $controller->deleteBySelection($this->request(['transferIdArray' => [Transfer::sole()->id]]));
        $this->assertEquals(10, $product->fresh()->qty);
        $this->assertReconciled();
    }

    public function test_transfer_csv_import_posts_stock_with_uom_conversion(): void
    {
        $product = $this->product(['tax_method' => 1]);
        $this->receive($product, 10, 4);
        DB::table('units')->insert(['id' => 2, 'unit_name' => 'Pair', 'unit_code' => 'pair', 'base_unit' => 1, 'operator' => '*', 'operation_value' => 2, 'is_active' => true]);
        $product->update(['unit_id' => 1]);
        $file = UploadedFile::fake()->createWithContent('transfer.csv', "code,qty,unit,cost,tax\n{$product->code},2,pair,8,no tax\n");
        app(TransferController::class)->importTransfer($this->request($this->transferPayload($product, 2), ['file' => $file]));
        $this->assertEquals(6, $this->warehouseQty($product));
        $this->assertEquals(4, $this->warehouseQty($product, 2));
        $this->assertReconciled();
    }

    public function test_invalid_transfer_csv_rolls_back_all_rows_and_stock(): void
    {
        $product = $this->product(['unit_id' => 1, 'tax_method' => 1]);
        $this->receive($product, 1, 4);
        $file = UploadedFile::fake()->createWithContent('transfer.csv', "code,qty,unit,cost,tax\n{$product->code},2,ea,4,no tax\n");
        try {
            app(TransferController::class)->importTransfer($this->request($this->transferPayload($product, 2), ['file' => $file]));
            $this->fail('CSV issue beyond stock must roll back.');
        } catch (StockPolicyException) {
        }
        $this->assertSame(0, Transfer::count());
        $this->assertSame(0, DB::table('product_transfer')->count());
        $this->assertEquals(1, $this->warehouseQty($product));
        $this->assertReconciled();
    }

    public function test_damage_missing_row_blocks_atomically_and_allow_policy_creates_correct_row(): void
    {
        $product = $this->product(['cost' => 0]);
        $payload = ['warehouse_id' => 1, 'product_id' => $product->id, 'qty' => 2, 'reason' => 'Broken'];
        try {
            app(DamageStockController::class)->store($this->request($payload));
            $this->fail('Missing stock must block by default.');
        } catch (StockPolicyException) {
        }
        $this->assertSame(0, DB::table('damage_stocks')->count());
        $this->assertSame(0, StockMovement::count());
        config(['without_stock' => 'yes']);
        app(DamageStockController::class)->store($this->request($payload));
        $this->assertEquals(-2, $product->fresh()->qty);
        $this->assertEquals(-2, $this->warehouseQty($product));
        $this->assertReconciled();
    }

    public function test_cafe_consumption_has_warehouse_stock_and_rolls_back_log_on_failure(): void
    {
        $product = $this->product();
        $this->receive($product, 5, 4);
        $payload = ['warehouse_id' => 1, 'product_id' => $product->id, 'opening_stock' => 5, 'received_today' => 1, 'consumed_today' => 2];
        app(CafeOperationsController::class)->storeRawMaterial($this->request($payload));
        $this->assertEquals(3, $this->warehouseQty($product));
        $this->assertEquals(3, $product->fresh()->qty);
        $this->assertEquals(4, DB::table('cafe_raw_materials')->sole()->closing_stock);
        try {
            app(CafeOperationsController::class)->storeRawMaterial($this->request(array_merge($payload, ['consumed_today' => 20])));
            $this->fail('Invalid consumption must roll back the log.');
        } catch (StockPolicyException) {
        }
        $this->assertSame(1, DB::table('cafe_raw_materials')->count());
        $this->assertReconciled();
    }

    public function test_exchange_receipt_and_issue_roll_back_together_when_issue_fails(): void
    {
        $returned = $this->product(['code' => 'RETURNED']);
        $outgoing = $this->product(['code' => 'OUTGOING']);
        $payload = ['warehouse_id' => 1, 'customer_id' => 1,
            'returned_products' => [['product_id' => $returned->id, 'qty' => 2, 'unit_price' => 10]],
            'exchanged_products' => [['product_id' => $outgoing->id, 'qty' => 2, 'unit_price' => 10]]];
        try {
            app(ExchangeController::class)->store($this->request($payload));
            $this->fail('Outgoing stock must block the entire exchange.');
        } catch (StockPolicyException) {
        }
        $this->assertEquals(0, $returned->fresh()->qty);
        $this->assertSame(0, DB::table('exchanges')->count());
        $this->receive($outgoing, 5, 4);
        app(ExchangeController::class)->store($this->request($payload));
        $this->assertEquals(2, $returned->fresh()->qty);
        $this->assertEquals(3, $outgoing->fresh()->qty);
        $this->assertSame(2, StockMovement::where('source_type', 'legacy:exchanges')->count());
        $this->assertReconciled();
    }

    private function productionPayload(Product $output, Product $ingredient): array
    {
        return ['warehouse_id' => 1, 'product_id' => $output->id, 'total_qty' => 2,
            'product_list' => [$ingredient->id], 'product_qty' => [3], 'unit_price' => [4],
            'wastage_percent' => [0], 'production_unit_ids' => [1], 'subtotal' => [12],
            'total_cost' => 12, 'grand_total' => 12, 'status' => 1];
    }

    public function test_production_delete_uses_recorded_ingredients_even_after_recipe_changes(): void
    {
        $output = $this->product(['code' => 'OUTPUT']);
        $ingredient = $this->product(['code' => 'INPUT']);
        $this->receive($ingredient, 10, 4);
        $controller = app(ProductionController::class);
        $controller->store($this->request($this->productionPayload($output, $ingredient)));
        $this->assertEquals(2, $output->fresh()->qty);
        $this->assertEquals(7, $ingredient->fresh()->qty);
        $output->update(['product_list' => '999', 'qty_list' => '999']);
        $controller->destroy(Production::sole()->id);
        $this->assertEquals(0, $output->fresh()->qty);
        $this->assertEquals(10, $ingredient->fresh()->qty);
        $this->assertReconciled();
    }

    public function test_failed_production_consumption_does_not_leave_output_or_document(): void
    {
        $output = $this->product(['code' => 'OUTPUT']);
        $ingredient = $this->product(['code' => 'INPUT']);
        app(ProductionController::class)->store($this->request($this->productionPayload($output, $ingredient)));
        $this->assertSame(0, Production::count());
        $this->assertEquals(0, $output->fresh()->qty);
        $this->assertSame(0, StockMovement::count());
        $this->assertReconciled();
    }

    public function test_packing_combo_delete_restores_recorded_components_after_recipe_changes(): void
    {
        $ingredient = $this->product(['code' => 'COMPONENT']);
        $this->receive($ingredient, 10, 4);
        $combo = $this->product(['code' => 'KIT', 'type' => 'combo', 'product_list' => (string) $ingredient->id, 'qty_list' => '2']);
        $sale = $this->packingSale($combo);
        $controller = app(PackingSlipController::class);
        $response = $controller->store($this->request(['sale_id' => $sale->id, 'amount' => 10, 'is_packing' => [$combo->id.'|']]));
        $this->assertSame(302, $response->getStatusCode());
        $this->assertEquals(6, $this->warehouseQty($ingredient));
        $combo->update(['product_list' => '999', 'qty_list' => '999']);
        $controller->delete(DB::table('packing_slips')->sole()->id);
        $this->assertEquals(10, $this->warehouseQty($ingredient));
        $this->assertEquals(0, $combo->fresh()->qty);
        $this->assertSame(0, DB::table('packing_slip_products')->count());
        $this->assertReconciled();
    }

    public function test_packing_same_sale_line_twice_is_rejected_without_duplicate_stock_or_slip(): void
    {
        $product = $this->product(['unit_id' => 1]);
        $this->receive($product, 10, 4);
        $sale = $this->packingSale($product);
        $payload = ['sale_id' => $sale->id, 'amount' => 10, 'is_packing' => [$product->id.'|']];
        $controller = app(PackingSlipController::class);
        $this->assertSame(302, $controller->store($this->request($payload))->getStatusCode());
        $this->assertSame(422, $controller->store($this->request($payload))->getStatusCode());
        $this->assertSame(1, DB::table('packing_slips')->count());
        $this->assertSame(1, DB::table('packing_slip_products')->count());
        $this->assertEquals(8, $this->warehouseQty($product));
        $this->assertReconciled();
    }

    private function packingSale(Product $product): Sale
    {
        $sale = Sale::forceCreate(['reference_no' => 'packing-sale', 'user_id' => 1, 'customer_id' => 1,
            'warehouse_id' => 1, 'biller_id' => 1, 'item' => 1, 'total_qty' => 2, 'total_discount' => 0,
            'total_tax' => 0, 'total_price' => 20, 'grand_total' => 20, 'paid_amount' => 0, 'sale_status' => 2, 'payment_status' => 2]);
        DB::table('product_sales')->insert(['sale_id' => $sale->id, 'product_id' => $product->id,
            'qty' => 2, 'sale_unit_id' => 1, 'net_unit_price' => 10, 'discount' => 0, 'tax_rate' => 0, 'tax' => 0, 'total' => 20]);
        return $sale;
    }

    public function test_historical_production_without_ingredient_snapshot_is_rejected_without_deleting(): void
    {
        $production = Production::create(['reference_no' => 'old-incomplete', 'user_id' => 1, 'warehouse_id' => 1,
            'item' => 1, 'total_qty' => 2, 'total_tax' => 0, 'total_cost' => 10, 'grand_total' => 10, 'status' => 1]);
        try {
            app(ProductionController::class)->destroy($production->id);
            $this->fail('Missing historical snapshot must block deletion.');
        } catch (StockPolicyException) {
        }
        $this->assertSame(1, Production::count());
        $this->assertSame(0, StockMovement::count());
        $this->assertReconciled();
    }

    public function test_reversal_rejects_active_company_mismatch(): void
    {
        $companyId = $this->companyWithInventoryPolicy([]);
        $product = $this->product();
        $this->receive($product, 10, 4);
        $controller = app(AdjustmentController::class);
        $controller->store($this->request($this->adjustmentPayload($product, 2)));
        $request = $this->request([]);
        $request->attributes->set(CompanyContext::class, new CompanyContext($companyId + 1, 1, 1));
        $adjustmentId = Adjustment::withoutGlobalScopes()->sole()->id;
        try {
            $controller->destroy($adjustmentId);
            $this->fail('A foreign document must not be reversed.');
        } catch (StockPolicyException|\Illuminate\Database\Eloquent\ModelNotFoundException) {
            // The request-company scope hides the document, or the ledger refuses the foreign movement.
        }
        $this->assertEquals(12, $product->fresh()->qty);
        $this->assertSame(1, Adjustment::withoutGlobalScopes()->count());
        $this->assertReconciled();
    }

    public function test_product_opening_purchase_posts_once_and_the_global_scheduled_purchase_is_retired(): void
    {
        $product = $this->product(['unit_id' => 1, 'cost' => 4, 'alert_quantity' => 8]);
        app(ProductController::class)->autoPurchase($product, 1, 3);
        $this->assertEquals(3, $product->fresh()->qty);
        // After company foundation the global legacy job refuses to run instead of buying for every company.
        $this->artisan('purchase:auto')->assertFailed();
        $this->assertEquals(3, $product->fresh()->qty);
        $this->assertSame(1, DB::table('purchases')->count());
        $this->assertStringStartsWith('ERP-PUR-', DB::table('purchases')->value('reference_no'));
        $this->assertSame(1, StockMovement::where('source_type', 'legacy:purchases')->count());
        $this->assertReconciled();
    }

    public function test_product_creation_posts_openings_once_and_ignores_client_projection_quantity(): void
    {
        app(ProductController::class)->store($this->request([
            'name' => 'New product', 'code' => 'NEW', 'type' => 'standard', 'barcode_symbology' => 'C128',
            'category_id' => 1, 'unit_id' => 1, 'purchase_unit_id' => 1, 'sale_unit_id' => 1,
            'cost' => 4, 'price' => 10, 'product_details' => '', 'starting_date' => null, 'last_date' => null,
            'is_initial_stock' => 1, 'stock_warehouse_id' => [1, 2], 'stock' => [3, 4], 'qty' => 999,
        ]));
        $product = Product::sole();
        $this->assertEquals(7, $product->qty);
        $this->assertEquals(3, $this->warehouseQty($product));
        $this->assertEquals(4, $this->warehouseQty($product, 2));
        $this->assertSame(2, StockMovement::count());
        $this->assertReconciled();
    }

    public function test_historical_combo_packing_without_snapshot_is_preserved_for_review(): void
    {
        $combo = $this->product(['type' => 'combo', 'product_list' => '999', 'qty_list' => '2']);
        $sale = $this->packingSale($combo);
        $slipId = DB::table('packing_slips')->insertGetId(['reference_no' => 'old-slip', 'sale_id' => $sale->id, 'amount' => 10, 'status' => 'Pending']);
        DB::table('packing_slip_products')->insert(['packing_slip_id' => $slipId, 'product_id' => $combo->id]);
        try {
            app(PackingSlipController::class)->delete($slipId);
            $this->fail('Historical combo without a component snapshot must block deletion.');
        } catch (StockPolicyException) {
        }
        $this->assertSame(1, DB::table('packing_slips')->count());
        $this->assertSame(1, DB::table('packing_slip_products')->count());
        $this->assertReconciled();
    }

    public function test_warehouse_creation_initializes_empty_projections_without_a_movement(): void
    {
        $product = $this->product();
        app(WarehouseController::class)->store($this->request(['name' => 'New warehouse']));
        $this->assertSame(1, DB::table('product_warehouse')->where('product_id', $product->id)->count());
        $this->assertEquals(0, $product->fresh()->qty);
        $this->assertSame(0, StockMovement::count());
        $this->assertReconciled();
    }

    public function test_retired_addon_endpoints_return_gone_without_remote_or_file_operations(): void
    {
        foreach (['saasInstall', 'ecommerceInstall', 'woocommerceInstall', 'apiInstall'] as $method) {
            $this->assertSame(410, app(AddonInstallController::class)->{$method}($this->request(['purchase_code' => 'anything']))->getStatusCode());
        }
    }

    public function test_cutover_routes_are_removed_from_shadow_map(): void
    {
        foreach ([AdjustmentController::class, TransferController::class, PackingSlipController::class,
            DamageStockController::class, ExchangeController::class, CafeOperationsController::class,
            ProductController::class, ProductionController::class, \App\Http\Controllers\SaleController::class,
            \App\Http\Controllers\PurchaseController::class, \App\Http\Controllers\ReturnController::class,
            \App\Http\Controllers\ReturnPurchaseController::class] as $controller) {
            $this->assertArrayNotHasKey($controller, LegacyStockShadow::WRITERS);
        }
    }
}
