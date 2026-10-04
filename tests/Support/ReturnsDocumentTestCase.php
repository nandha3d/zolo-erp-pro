<?php

namespace Tests\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

abstract class ReturnsDocumentTestCase extends ComplianceTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('currencies', function (Blueprint $t) { $t->id(); $t->string('code'); });
        DB::table('currencies')->insert(['id' => 1, 'code' => 'INR']);
        $this->company->update(['base_currency_id' => 1]);
        require_once database_path('migrations/2021_03_07_093606_create_product_batches_table.php');
        (new \CreateProductBatchesTable)->up();
        Schema::table('product_batches', function (Blueprint $t) {
            $t->unsignedBigInteger('company_id')->nullable();
            $t->date('mfg_date')->nullable(); $t->decimal('mrp', 18, 4)->nullable();
            $t->string('status')->default('active');
        });
        foreach ([
            '2018_05_21_163419_create_returns_table.php' => 'CreateReturnsTable',
            '2018_05_21_163443_create_product_returns_table.php' => 'CreateProductReturnsTable',
            '2018_12_26_064330_create_return_purchases_table.php' => 'CreateReturnPurchasesTable',
            '2018_12_26_144708_create_purchase_product_return_table.php' => 'CreatePurchaseProductReturnTable',
        ] as $file => $class) { require_once database_path('migrations/'.$file); (new $class)->up(); }
        foreach (['returns', 'return_purchases', 'product_returns', 'purchase_product_return'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->unsignedBigInteger('company_id')->nullable());
        }
        Schema::table('returns', function (Blueprint $t) { $t->unsignedInteger('sale_id')->nullable(); $t->unsignedInteger('user_id')->nullable(); $t->unsignedInteger('account_id')->nullable(); });
        Schema::table('return_purchases', fn (Blueprint $t) => $t->unsignedInteger('purchase_id')->nullable());
        foreach (['product_returns', 'purchase_product_return'] as $table) {
            Schema::table($table, function (Blueprint $t) { $t->integer('variant_id')->nullable(); $t->integer('product_batch_id')->nullable(); $t->text('imei_number')->nullable(); });
        }
        (require database_path('migrations/2026_09_19_000003_create_damage_stock_and_exchange_tables.php'))->up();
        (require database_path('migrations/2026_10_06_000002_extend_returns_and_document_delivery.php'))->up();
        DB::table('warehouses')->where('id', 2)->update(['company_id' => $this->company->id, 'branch_id' => $this->branch->id, 'is_quarantine' => true]);
        $this->app->bind(\App\Services\Platform\CapabilityService::class, fn () => new class extends \App\Services\Platform\CapabilityService {
            public function enabled(string $key, \App\Services\Platform\CompanyContext|int|null $company = null): bool { return true; }
        });
    }

    protected function noteData(object $sale, array $extra = []): array
    {
        return $extra + ['business_date' => '2026-10-04', 'reason' => 'Customer returned item', 'note_type' => 'credit', 'adjustment_type' => 'quantity',
            'items' => [['line_id' => $sale->productSales->first()->id, 'qty' => 1]]];
    }

    protected function postedReturn(object $sale, array $extra = [], string $key = 'return'): \App\Models\Returns
    {
        $note = app(\App\Services\Commercial\ReturnService::class)->create('sale', $sale->id, $this->noteData($sale, $extra), $key, $this->context(), 1);
        return app(\App\Services\Commercial\ReturnService::class)->approve('sale', $note->id, $this->context(), 1);
    }
}
