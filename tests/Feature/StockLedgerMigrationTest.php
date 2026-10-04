<?php

namespace Tests\Feature;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\UsesDisposableMysql;
use Tests\TestCase;

/**
 * Migration 2026_10_04_000001 must survive MySQL committing each DDL statement on its own. Idempotence and
 * refusal run everywhere; interruption proofs need an explicitly named disposable MySQL database.
 */
class StockLedgerMigrationTest extends TestCase
{
    use UsesDisposableMysql;

    private const FOREIGN_KEYS = [
        'stock_movements' => 2, 'stock_identities' => 5, 'stock_dimensions' => 1,
        'stock_movement_lines' => 6, 'product_uom_conversions' => 2,
    ];

    private object $migration;

    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('ERP_TEST_MYSQL') === '1') {
            $this->configureDisposableMysql();
        } else {
            config(['database.default' => 'erp_regression', 'database.connections.erp_regression' => [
                'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
            ]]);
        }
        foreach (['products', 'warehouses', 'companies'] as $parent) {
            Schema::create($parent, function (Blueprint $table) use ($parent) {
                $parent === 'companies' ? $table->id() : $table->increments('id');
                $table->string('name')->default('x');
            });
        }
        Schema::create('product_batches', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('product_id');
            $table->string('batch_no');
            $table->date('expired_date');
            $table->double('qty');
            $table->timestamps();
        });
        $this->migration = require database_path('migrations/2026_10_04_000001_create_stock_ledger_tables.php');
    }

    protected function tearDown(): void
    {
        DB::purge('erp_regression');
        parent::tearDown();
    }

    private function assertComplete(): void
    {
        foreach (self::FOREIGN_KEYS as $table => $count) {
            $this->assertTrue(Schema::hasTable($table), "$table exists");
            $this->assertCount($count, Schema::getForeignKeys($table), "$table foreign keys");
        }
        foreach ([
            'stock_movements' => ['stock_movements_movement_no_unique', 'stock_movements_reversal_of_id_unique',
                'stock_movements_idempotency_key_unique', 'stock_movements_company_id_index', 'stock_movements_source_type_source_id_index'],
            'stock_identities' => ['stock_identities_product_id_identity_type_identity_no_unique', 'stock_identities_warehouse_id_status_index'],
            'stock_dimensions' => ['stock_dimensions_stock_identity_id_unique'],
            'stock_movement_lines' => ['stock_movement_lines_stock_movement_id_line_no_unique', 'stock_movement_lines_batch_id_index'],
            'product_uom_conversions' => ['product_uom_conversions_product_id_from_uom_id_to_uom_id_unique'],
            'product_batches' => ['product_batches_company_id_index'],
        ] as $table => $indexes) {
            foreach ($indexes as $index) {
                $this->assertTrue(Schema::hasIndex($table, $index), "$table.$index");
            }
        }
        foreach (['company_id', 'mfg_date', 'mrp', 'status'] as $column) {
            $this->assertTrue(Schema::hasColumn('product_batches', $column), "product_batches.$column");
        }
    }

    public function test_repeating_the_migration_changes_nothing_and_keeps_rows(): void
    {
        $this->migration->up();
        $this->assertComplete();
        DB::table('stock_movements')->insert(['movement_date' => '2026-10-04', 'movement_type' => 'opening']);
        DB::table('product_batches')->insert(['product_id' => 1, 'batch_no' => 'B', 'expired_date' => '2027-01-01', 'qty' => 3]);

        $this->migration->up();

        $this->assertComplete();
        $this->assertSame(1, DB::table('stock_movements')->count());
        $this->assertSame('active', DB::table('product_batches')->value('status'));
    }

    public function test_a_table_missing_owned_columns_is_refused_before_any_ddl(): void
    {
        Schema::create('stock_movements', fn (Blueprint $table) => $table->id());

        try {
            $this->migration->up();
            $this->fail('An incompatible table must be refused.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('Incompatible existing table stock_movements', $error->getMessage());
        }
        $this->assertFalse(Schema::hasTable('stock_identities'));
        $this->assertFalse(Schema::hasColumn('product_batches', 'mfg_date'));
    }

    public function test_an_index_with_the_wrong_shape_is_refused(): void
    {
        $this->migration->up();
        Schema::table('stock_movements', fn (Blueprint $table) => $table->dropUnique('stock_movements_movement_no_unique'));
        Schema::table('stock_movements', fn (Blueprint $table) => $table->index('movement_no', 'stock_movements_movement_no_unique'));

        $this->expectExceptionMessage('Unexpected existing index stock_movements.stock_movements_movement_no_unique');
        $this->migration->up();
    }

    public function test_down_can_be_repeated_and_up_restores_everything(): void
    {
        $this->migration->up();
        $this->migration->down();
        $this->migration->down();
        foreach (array_keys(self::FOREIGN_KEYS) as $table) {
            $this->assertFalse(Schema::hasTable($table));
        }
        $this->assertFalse(Schema::hasColumn('product_batches', 'status'));

        $this->migration->up();
        $this->assertComplete();
    }

    /** @return array<string, array{0: string, 1: string}> label => [interrupt after statement prefix, table that must then exist] */
    public static function interruptions(): array
    {
        return [
            'after CREATE stock_movements' => ['create table `stock_movements`', 'stock_movements'],
            'after CREATE stock_identities' => ['create table `stock_identities`', 'stock_identities'],
            'after CREATE stock_dimensions' => ['create table `stock_dimensions`', 'stock_dimensions'],
            'after CREATE stock_movement_lines' => ['create table `stock_movement_lines`', 'stock_movement_lines'],
            'after CREATE product_uom_conversions' => ['create table `product_uom_conversions`', 'product_uom_conversions'],
            'after first stock_movements unique index' => ['alter table `stock_movements` add unique', 'stock_movements'],
            'after first stock_movement_lines foreign key' => ['alter table `stock_movement_lines` add constraint', 'stock_movement_lines'],
            'after product_batches column' => ['alter table `product_batches` add `mfg_date`', 'product_batches'],
        ];
    }

    /**
     * Committed MySQL DDL survives a failure: rerunning must finish the schema without losing retained rows.
     *
     */
    #[DataProvider('interruptions')]
    public function test_interrupted_mysql_ddl_is_resumed_without_losing_rows(string $interruptAfter, string $table): void
    {
        if (getenv('ERP_TEST_MYSQL') !== '1') {
            $this->markTestSkipped('Committed-DDL recovery proof requires disposable MySQL.');
        }
        $armed = true;
        DB::listen(function (QueryExecuted $query) use (&$armed, $interruptAfter) {
            if ($armed && str_starts_with($query->sql, $interruptAfter)) {
                $armed = false;
                throw new RuntimeException('Injected failure after committed DDL');
            }
        });

        try {
            $this->migration->up();
            $this->fail('The injected failure must abort the migration.');
        } catch (RuntimeException $error) {
            $this->assertSame('Injected failure after committed DDL', $error->getMessage());
        }
        $this->assertTrue(Schema::hasTable($table), 'the interrupted statement was committed');
        // Retain rows in the first table and in batches, as a partially used install would.
        DB::table('product_batches')->insert(['product_id' => 1, 'batch_no' => 'RETAINED', 'expired_date' => '2027-01-01', 'qty' => 5]);
        DB::table('stock_movements')->insert(['movement_date' => '2026-10-04', 'movement_type' => 'opening', 'movement_no' => 'SM-RETAINED']);

        $this->migration->up();

        $this->assertComplete();
        $this->assertSame(1, DB::table('product_batches')->where('batch_no', 'RETAINED')->count());
        $this->assertSame(1, DB::table('stock_movements')->where('movement_no', 'SM-RETAINED')->count());
    }
}
