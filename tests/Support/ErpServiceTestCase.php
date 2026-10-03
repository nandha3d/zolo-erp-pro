<?php

namespace Tests\Support;

use App\Models\Product;
use App\Models\Product_Warehouse;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Isolated fixtures for the existing ERP services, not a full migration rehearsal.
 * Each test uses a dedicated in-memory connection and never touches configured data.
 */
abstract class ErpServiceTestCase extends TestCase
{
    use UsesDisposableMysql;
    use CreatesInventoryLedgerFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        if (getenv('ERP_TEST_MYSQL') === '1') {
            $this->configureDisposableMysql();
        } else {
            config([
            'database.default' => 'erp_regression',
            'database.connections.erp_regression' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
            ]);
        }

        $this->createFixtures();
    }

    protected function tearDown(): void
    {
        DB::purge('erp_regression');
        parent::tearDown();
    }

    private function createFixtures(): void
    {
        foreach (['customers', 'suppliers', 'warehouses', 'billers'] as $name) {
            Schema::create($name, function (Blueprint $table) {
                $table->increments('id');
                $table->string('name');
                $table->timestamps();
            });
            DB::table($name)->insert(['id' => 1, 'name' => 'Regression fixture']);
        }
        DB::table('warehouses')->insert(['id' => 2, 'name' => 'Destination']);

        Schema::create('products', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('code');
            $table->string('type')->default('standard');
            $table->double('qty')->default(0);
            $table->double('cost')->default(0);
            $table->double('price')->default(0);
            $table->timestamps();
        });
        Schema::create('product_warehouse', function (Blueprint $table) {
            $table->increments('id');
            $table->string('product_id');
            $table->integer('warehouse_id');
            $table->double('qty')->default(0);
            $table->double('price')->nullable();
            $table->timestamps();
        });

        // Reuse the original commercial table definitions, including required fields.
        $migrations = [
            '2018_04_10_085927_create_sales_table.php' => 'CreateSalesTable',
            '2018_04_06_133606_create_purchases_table.php' => 'CreatePurchasesTable',
            '2018_04_14_121802_create_transfers_table.php' => 'CreateTransfersTable',
            '2018_04_10_090133_create_product_sales_table.php' => 'CreateProductSalesTable',
            '2018_04_06_154600_create_product_purchases_table.php' => 'CreateProductPurchasesTable',
            '2018_04_14_121913_create_product_transfer_table.php' => 'CreateProductTransferTable',
        ];
        foreach ($migrations as $file => $class) {
            require_once database_path('migrations/'.$file);
            (new $class)->up();
        }

        foreach (['sales', 'purchases', 'transfers'] as $name) {
            Schema::table($name, function (Blueprint $table) use ($name) {
                $table->integer('user_id');
                if ($name !== 'transfers') {
                    $table->softDeletes();
                }
                if ($name === 'sales') {
                    $table->integer('cash_register_id')->nullable();
                    $table->string('order_discount_type')->nullable();
                    $table->double('order_discount_value')->nullable();
                    $table->integer('coupon_id')->nullable();
                    $table->double('coupon_discount')->nullable();
                    $table->integer('currency_id')->nullable();
                    $table->double('exchange_rate')->nullable();
                }
            });
        }
        foreach (['product_sales', 'product_purchases'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->integer('product_batch_id')->nullable();
                $table->integer('variant_id')->nullable();
                $table->text('imei_number')->nullable();
            });
        }

        Schema::create('payments', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('user_id');
            $table->integer('sale_id')->nullable();
            $table->integer('purchase_id')->nullable();
            $table->integer('account_id');
            $table->string('payment_reference');
            $table->double('amount');
            $table->double('change');
            $table->string('paying_method');
            $table->text('payment_note')->nullable();
            $table->timestamps();
        });

        (require database_path('migrations/2026_09_19_000001_create_double_entry_accounting_tables.php'))->up();
        $this->createInventoryLedgerFixtures();
    }

    protected function stock(float $qty = 20, float $cost = 5): Product
    {
        $product = Product::create([
            'name' => 'Test product', 'code' => 'TEST',
            'qty' => $qty, 'cost' => $cost, 'price' => 10,
        ]);
        Product_Warehouse::create([
            'product_id' => $product->id, 'warehouse_id' => 1, 'qty' => $qty,
        ]);

        return $product;
    }
}
