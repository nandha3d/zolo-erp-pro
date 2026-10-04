<?php

namespace Tests\Feature;

use App\Http\Middleware\RecordLegacyStockShadow;
use App\Models\Inventory\StockIdentity;
use App\Models\Inventory\StockMovement;
use App\Models\Product;
use App\Models\Product_Warehouse;
use App\Models\Transfer;
use App\Services\Inventory\InventoryReconciliationService;
use App\Services\Inventory\LegacyStockShadow;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\Support\InventoryLedgerTestCase;

class LegacyStockShadowTest extends InventoryLedgerTestCase
{
    private function shadow(): LegacyStockShadow
    {
        return app(LegacyStockShadow::class);
    }

    /** Legacy-style stock row plus its opening movement, so ledger and projections start equal. */
    private function stocked(float $qty, array $attributes = [], int $warehouseId = 1, ?string $imei = null): Product
    {
        $product = $this->product($attributes + ['qty' => $qty, 'cost' => 4]);
        DB::table('product_warehouse')->insert(['product_id' => $product->id, 'warehouse_id' => $warehouseId, 'qty' => $qty, 'imei_number' => $imei]);
        $this->artisan('erp:stock-opening', ['--date' => '2026-10-01'])->assertSuccessful();

        return $product;
    }

    /** What legacy controllers do: change the product total and the warehouse row directly. */
    private function legacyChange(Product $product, float $qty, int $warehouseId = 1): void
    {
        $row = Product_Warehouse::where('product_id', $product->id)->where('warehouse_id', $warehouseId)->first()
            ?? new Product_Warehouse(['product_id' => $product->id, 'warehouse_id' => $warehouseId, 'qty' => 0]);
        $row->qty += $qty;
        $row->save();
        Product::whereKey($product->id)->increment('qty', $qty);
    }

    private function assertReconciled(): void
    {
        $this->assertSame([], app(InventoryReconciliationService::class)->differences());
    }

    public function test_legacy_writes_become_one_shadow_movement_without_touching_projections(): void
    {
        $product = $this->stocked(10);
        $other = $this->stocked(3);

        $this->shadow()->record('sales.store', function () use ($product, $other) {
            $this->legacyChange($product, -2);
            Product_Warehouse::where('product_id', $product->id)->first()->decrement('qty', 1);
            Product::whereKey($product->id)->decrement('qty', 1);
            $this->legacyChange($other, -3);
        });

        $movement = StockMovement::where('source_type', 'legacy:sales.store')->sole();
        $this->assertSame(['issue', 'shadow'], [$movement->movement_type, $movement->projection_mode]);
        $this->assertEquals([-3, -3], $movement->lines->pluck('qty_base')->map(fn ($qty) => (float) $qty)->all());
        $this->assertEquals(-12, $movement->lines->first()->value);
        $this->assertEquals(7, $product->fresh()->qty);
        $this->assertEquals(7, $this->warehouseQty($product));
        $this->assertReconciled();
    }

    public function test_rolled_back_savepoint_is_discarded_while_the_rest_commits(): void
    {
        $product = $this->stocked(10);

        $this->shadow()->record('sales.store', function () use ($product) {
            DB::beginTransaction();
            $this->legacyChange($product, -4);
            DB::rollBack();
            DB::beginTransaction();
            $this->legacyChange($product, -1);
            DB::commit();
            DB::transaction(fn () => $this->legacyChange($product, -2));
        });

        $this->assertEquals(-3, StockMovement::where('projection_mode', 'shadow')->sole()->lines->sole()->qty_base);
        $this->assertEquals(7, $this->warehouseQty($product));
        $this->assertReconciled();
    }

    public function test_thrown_or_rendered_failures_roll_back_the_whole_writer(): void
    {
        $product = $this->stocked(10);

        try {
            $this->shadow()->record('sales.store', function () use ($product) {
                $this->legacyChange($product, -4);
                throw new RuntimeException('legacy failure');
            });
            $this->fail('The writer exception must propagate.');
        } catch (RuntimeException) {
        }
        $response = $this->shadow()->record('sales.store', function () use ($product) {
            $this->legacyChange($product, -4);

            return (new Response('error', 500))->withException(new RuntimeException('rendered'));
        });

        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame(0, StockMovement::where('projection_mode', 'shadow')->count());
        $this->assertEquals(10, $this->warehouseQty($product));
        $this->assertEquals(10, $product->fresh()->qty);
    }

    public function test_leaked_transaction_loses_only_work_after_it_like_a_request_end(): void
    {
        $product = $this->stocked(10);

        // SaleController::store commits the sale, then genInvoice begins a transaction it never commits.
        $this->shadow()->record('sales.store', function () use ($product) {
            DB::beginTransaction();
            $this->legacyChange($product, -2);
            DB::commit();
            DB::beginTransaction();
            $this->legacyChange($product, -4);
        });

        $this->assertSame(0, DB::transactionLevel());
        $this->assertEquals(8, $this->warehouseQty($product));
        $this->assertEquals(-2, StockMovement::where('projection_mode', 'shadow')->sole()->lines->sole()->qty_base);
        $this->assertReconciled();
    }

    public function test_movement_service_postings_inside_a_scope_are_not_recorded_twice(): void
    {
        $product = $this->stocked(10);

        $this->shadow()->record('purchases.store', function () use ($product) {
            $this->receive($product, 5, 6);
            $this->legacyChange($product, 2);
        });

        $this->assertSame(1, StockMovement::where('projection_mode', 'applied')->where('movement_type', 'receipt')->count());
        $shadow = StockMovement::where('projection_mode', 'shadow')->sole();
        $this->assertSame('receipt', $shadow->movement_type);
        $this->assertEquals(2, $shadow->lines->sole()->qty_base);
        // A legacy receipt is valued at the product master cost the legacy purchase writer maintains.
        $this->assertEquals(4, $shadow->lines->sole()->unit_cost);
        $this->assertEquals(17, $this->warehouseQty($product));
        $this->assertReconciled();
    }

    public function test_mixed_changes_record_a_signed_adjustment_and_new_rows_are_captured(): void
    {
        $product = $this->stocked(10);

        $this->shadow()->record('transfers.store', function () use ($product) {
            $this->legacyChange($product, -4, 1);
            $this->legacyChange($product, 4, 2);
            Product::whereKey($product->id)->decrement('qty', 0);
        });

        $movement = StockMovement::where('projection_mode', 'shadow')->sole();
        $this->assertSame('adjustment', $movement->movement_type);
        $this->assertEquals([1 => -4, 2 => 4], $movement->lines->pluck('qty_base', 'warehouse_id')->map(fn ($qty) => (float) $qty)->all());
        $this->assertEquals(0, $movement->lines->sum('value'));
        $this->assertReconciled();
    }

    public function test_deleted_rows_partial_models_and_price_only_saves(): void
    {
        $product = $this->stocked(10);
        $gone = $this->stocked(2, ['code' => 'GONE']);

        $this->shadow()->record('products.update', function () use ($product, $gone) {
            $row = Product_Warehouse::select('id', 'qty')->where('product_id', $product->id)->first();
            $row->qty += 5;
            $row->save();
            Product::whereKey($product->id)->increment('qty', 5);
            Product_Warehouse::where('product_id', $product->id)->first()->update(['price' => 99]);
            Product_Warehouse::where('product_id', $gone->id)->get()->each->delete();
            Product::whereKey($gone->id)->update(['qty' => 0]);
        });

        $movement = StockMovement::where('projection_mode', 'shadow')->sole();
        $this->assertEquals([$product->id => 5, $gone->id => -2],
            $movement->lines->pluck('qty_base', 'product_id')->map(fn ($qty) => (float) $qty)->all());
        $this->assertReconciled();
    }

    public function test_serial_list_changes_move_serial_identities(): void
    {
        DB::table('products')->insert(['id' => 50, 'name' => 'Phone', 'code' => 'PH', 'qty' => 2, 'cost' => 100, 'is_imei' => true]);
        DB::table('product_warehouse')->insert(['product_id' => 50, 'warehouse_id' => 1, 'qty' => 2, 'imei_number' => 'S1,S2']);
        $this->artisan('erp:stock-opening', ['--date' => '2026-10-01'])->assertSuccessful();

        $this->shadow()->record('sales.store', function () {
            $row = Product_Warehouse::where('product_id', 50)->first();
            $row->qty -= 1;
            $row->imei_number = 'S1';
            $row->save();
            Product::whereKey(50)->decrement('qty', 1);
        });

        $this->assertSame(['S1' => 'in_stock', 'S2' => 'issued'], StockIdentity::orderBy('identity_no')->pluck('status', 'identity_no')->all());
        $this->assertReconciled();
    }

    public function test_applied_serial_postings_keep_the_legacy_serial_list_current(): void
    {
        $product = $this->product(['is_imei' => true]);
        $this->receive($product, 2, 10, 1, ['serials' => ['A1', 'A2']]);
        $this->movements()->issue($this->command([['product_id' => $product->id, 'qty' => 1, 'serials' => ['A1']]]));

        $this->assertSame('A2', Product_Warehouse::where('product_id', $product->id)->value('imei_number'));
    }

    public function test_failed_shadow_record_is_logged_and_never_blocks_the_legacy_writer(): void
    {
        $product = $this->stocked(10);
        Log::spy();

        $this->shadow()->record('sales.store', function () use ($product) {
            // An unknown warehouse makes the shadow posting fail; the legacy write must still commit.
            $this->legacyChange($product, 3, 99);
        });

        $this->assertEquals(3, $this->warehouseQty($product, 99));
        $this->assertSame(0, StockMovement::where('projection_mode', 'shadow')->count());
        Log::shouldHaveReceived('warning')->withArgs(fn ($message) => str_contains($message, 'not recorded'))->once();
    }

    public function test_off_mode_records_nothing_and_shadow_reversal_leaves_projections_alone(): void
    {
        $product = $this->stocked(10);
        config(['inventory.legacy_ledger_mode' => 'off']);
        $this->shadow()->record('sales.store', fn () => $this->legacyChange($product, -1));
        $this->assertSame(0, StockMovement::where('projection_mode', 'shadow')->count());

        config(['inventory.legacy_ledger_mode' => 'shadow']);
        $this->shadow()->record('sales.store', fn () => $this->legacyChange($product, -1));
        $reversal = $this->movements()->reverse(StockMovement::where('projection_mode', 'shadow')->sole(), 'Wrong document');

        $this->assertSame('shadow', $reversal->projection_mode);
        $this->assertEquals(8, $this->warehouseQty($product));
    }

    public function test_middleware_wraps_unsafe_requests_and_attributes_the_created_document(): void
    {
        $product = $this->stocked(10);
        Route::middleware(RecordLegacyStockShadow::class.':Transfer')->group(function () use ($product) {
            Route::post('/legacy-transfer', function () use ($product) {
                $transfer = (new Transfer)->forceFill([
                    'reference_no' => 'tr-legacy', 'user_id' => 1, 'status' => 1, 'from_warehouse_id' => 1, 'to_warehouse_id' => 2,
                    'item' => 1, 'total_qty' => 2, 'total_tax' => 0, 'total_cost' => 0, 'grand_total' => 0,
                ]);
                $transfer->save();
                $this->legacyChange($product, -2, 1);
                $this->legacyChange($product, 2, 2);

                return response('ok');
            });
            Route::get('/legacy-read', fn () => response((string) DB::transactionLevel()));
        });

        $this->post('/legacy-transfer')->assertOk();
        $this->get('/legacy-read')->assertSee('0');

        $movement = StockMovement::where('projection_mode', 'shadow')->sole();
        $this->assertSame([Transfer::sole()->id, 'tr-legacy'], [(int) $movement->source_id, $movement->source_no]);
        $this->assertReconciled();
    }

    public function test_every_audited_legacy_writer_route_is_wrapped(): void
    {
        $wrapped = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($route) => collect($route->gatherMiddleware())->contains(fn ($m) => is_string($m) && str_starts_with($m, 'legacy.stock')))
            ->map(fn ($route) => $route->getActionName())->unique()->values();

        foreach (LegacyStockShadow::WRITERS as $controller => [, $methods]) {
            foreach ($methods as $method) {
                $this->assertContains($controller.'@'.$method, $wrapped, "{$controller}@{$method} is not wrapped");
            }
        }
        $this->assertNotContains('App\\Http\\Controllers\\SaleController@index', $wrapped);
    }
}
