<?php

namespace Tests\Feature;

use App\Models\Inventory\StockMovement;
use App\Models\User;
use App\Services\Inventory\InventoryReconciliationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Phase 4b proof on the original seeded MySQL schema: real legacy web routes run through the full
 * middleware stack while the stock shadow records them. Each request is first replayed with the shadow
 * off and rolled back, so the shadow run's projections can be compared with legacy-only behaviour.
 */
class LegacyStockShadowWebTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;

    private int $main;

    private int $branchStore;

    /** @var array<string, array{id: int, code: string}> */
    private array $items = [];

    private array $baseline;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::findOrFail(1);
        $this->main = 1;
        $this->branchStore = DB::table('warehouses')->insertGetId(
            ['name' => 'Shadow second store', 'is_active' => 1, 'company_id' => 1, 'branch_id' => 1, 'created_at' => now(), 'updated_at' => now()]
            + array_intersect_key((array) DB::table('warehouses')->find(1), array_flip(['phone', 'email', 'address'])),
        );
        $this->items['a'] = $this->item('SHADOW-A', [$this->main => 20]);
        $this->items['b'] = $this->item('SHADOW-B', [$this->main => 5, $this->branchStore => 3]);
        $this->artisan('erp:stock-opening', ['--date' => now()->toDateString()])->assertSuccessful();
        // The original seed export already disagrees with itself (e.g. product 1 total vs warehouse rows).
        $this->baseline = $this->differences();
    }

    /**
     * Non-AJAX completed sales end in SaleController::genInvoice, which opens a transaction and returns a view
     * before its commit. Legacy keeps the sale (its per-line work is already committed); the shadow must too.
     */
    public function test_web_sale_store_keeps_the_sale_and_records_one_shadow_issue(): void
    {
        $a = $this->items['a'];
        [$response, $movement] = $this->legacyRequest('sales.store', fn () => $this->post(route('sales.store'), $this->salePayload($a, 2)));

        $response->assertOk();
        $sale = DB::table('sales')->orderByDesc('id')->first();
        $this->assertShadow($movement, 'issue', 'legacy:sales.store', $sale->id, [[$a['id'], $this->main, -2]]);
        $this->assertSame(18.0, $this->warehouseQty($a['id'], $this->main));
    }

    public function test_pos_ajax_sale_store_and_destroy_record_one_shadow_movement_each(): void
    {
        $a = $this->items['a'];
        [$response, $issue] = $this->legacyRequest('sales.store (ajax)', fn () => $this->ajaxSale($a, 3));
        $saleId = (int) $response->json();
        $this->assertShadow($issue, 'issue', 'legacy:sales.store', $saleId, [[$a['id'], $this->main, -3]]);

        [$deleted, $receipt] = $this->legacyRequest('sales.destroy', fn () => $this->delete(route('sales.destroy', $saleId)));
        $deleted->assertRedirect();
        $this->assertShadow($receipt, 'receipt', 'legacy:sales.destroy', $saleId, [[$a['id'], $this->main, 3]]);
        $this->assertSame(20.0, $this->warehouseQty($a['id'], $this->main));
    }

    public function test_purchase_store_records_one_shadow_receipt(): void
    {
        $b = $this->items['b'];
        [$response, $movement] = $this->legacyRequest('purchases.store', fn () => $this->post(route('purchases.store'), $this->purchasePayload([[$b, 4, 'piece']])));

        $response->assertRedirect(url('purchases'));
        $purchase = DB::table('purchases')->orderByDesc('id')->first();
        $this->assertShadow($movement, 'receipt', 'legacy:purchases.store', $purchase->id, [[$b['id'], $this->main, 4]]);
        $this->assertSame(9.0, $this->warehouseQty($b['id'], $this->main));
    }

    /** PurchaseController::store rolls back its own transaction on a bad line and redirects: nothing is recorded. */
    public function test_purchase_store_that_rolls_back_and_redirects_records_nothing(): void
    {
        $a = $this->items['a'];
        $b = $this->items['b'];
        $before = $this->snapshot();
        $last = StockMovement::max('id');
        $this->actingAs($this->admin)
            ->post(route('purchases.store'), $this->purchasePayload([[$a, 2, 'piece'], [$b, 1, 'no such unit']]))
            ->assertRedirect(url('purchases/create'));

        $this->assertSame($before, $this->snapshot());
        $this->assertSame($last, StockMovement::max('id'));
        $this->assertSame($this->baseline, $this->differences());
    }

    public function test_sale_return_store_records_one_shadow_receipt(): void
    {
        $a = $this->items['a'];
        $saleId = (int) $this->ajaxSale($a, 3)->json();
        $line = DB::table('product_sales')->where('sale_id', $saleId)->first();
        [$response, $movement] = $this->legacyRequest('return-sale.store', fn () => $this->post(route('return-sale.store'), [
            'sale_id' => $saleId, 'customer_id' => 1, 'warehouse_id' => $this->main, 'biller_id' => 1, 'account_id' => 1,
            'currency_id' => 1, 'exchange_rate' => 1, 'item' => 1, 'total_qty' => 1, 'total_sale_discount' => 0, 'change_sale_status' => 0, 'total_tax' => 0,
            'total_price' => 10, 'order_tax_rate' => 0, 'order_tax' => 0, 'grand_total' => 10, 'return_note' => 'shadow proof',
            'is_return' => [$line->id], 'product_sale_id' => [$line->id], 'product_id' => [$a['id']], 'product_code' => [$a['code']],
            'qty' => [1], 'sale_unit' => ['piece'], 'net_unit_price' => [10], 'discount' => [0], 'tax_rate' => [0], 'tax' => [0],
            'subtotal' => [10], 'imei_number' => [''], 'product_batch_id' => [''], 'product_price' => [10],
        ]));

        $response->assertRedirect();
        $return = DB::table('returns')->orderByDesc('id')->first();
        $this->assertShadow($movement, 'receipt', 'legacy:return-sale.store', $return->id, [[$a['id'], $this->main, 1]]);
        $this->assertSame(18.0, $this->warehouseQty($a['id'], $this->main));
    }

    public function test_purchase_return_store_records_one_shadow_issue(): void
    {
        $b = $this->items['b'];
        $this->actingAs($this->admin)->post(route('purchases.store'), $this->purchasePayload([[$b, 4, 'piece']]))->assertRedirect(url('purchases'));
        $purchase = DB::table('purchases')->orderByDesc('id')->first();
        $line = DB::table('product_purchases')->where('purchase_id', $purchase->id)->first();
        [$response, $movement] = $this->legacyRequest('return-purchase.store', fn () => $this->post(route('return-purchase.store'), [
            'purchase_id' => $purchase->id, 'supplier_id' => 1, 'warehouse_id' => $this->main, 'account_id' => 1,
            'currency_id' => 1, 'exchange_rate' => 1, 'item' => 1, 'total_qty' => 2, 'total_discount' => 0, 'total_tax' => 0,
            'total_cost' => 8, 'order_tax_rate' => 0, 'order_tax' => 0, 'grand_total' => 8, 'return_note' => 'shadow proof',
            'is_return' => [$line->id], 'product_purchase_id' => [$line->id], 'product_id' => [$b['id']], 'product_code' => [$b['code']],
            'qty' => [2], 'purchase_unit' => ['piece'], 'net_unit_cost' => [4], 'discount' => [0], 'tax_rate' => [0], 'tax' => [0],
            'subtotal' => [8], 'imei_number' => [''], 'product_batch_id' => [''],
        ]));

        $response->assertRedirect();
        $return = DB::table('return_purchases')->orderByDesc('id')->first();
        $this->assertShadow($movement, 'issue', 'legacy:return-purchase.store', $return->id, [[$b['id'], $this->main, -2]]);
        $this->assertSame(7.0, $this->warehouseQty($b['id'], $this->main));
    }

    public function test_completed_transfer_store_records_one_shadow_transfer(): void
    {
        $b = $this->items['b'];
        [$response, $movement] = $this->legacyRequest('transfers.store', fn () => $this->post(route('transfers.store'), $this->transferPayload($b, 2, 1)));

        $response->assertRedirect();
        $transfer = DB::table('transfers')->orderByDesc('id')->first();
        $this->assertShadow($movement, 'transfer', 'legacy:transfers.store', $transfer->id,
            [[$b['id'], $this->main, -2], [$b['id'], $this->branchStore, 2]]);
        $this->assertSame(3.0, $this->warehouseQty($b['id'], $this->main));
        $this->assertSame(5.0, $this->warehouseQty($b['id'], $this->branchStore));
    }

    public function test_pending_transfer_completed_by_change_status_records_its_stock_once(): void
    {
        $b = $this->items['b'];
        $pending = $this->actingAs($this->admin)->post(route('transfers.store'), $this->transferPayload($b, 2, 2));
        $pending->assertRedirect();
        $transfer = DB::table('transfers')->orderByDesc('id')->first();
        $afterStore = $this->snapshot();
        $last = StockMovement::max('id');

        [$response, $movement] = $this->legacyRequest('transfers.changeStatus',
            fn () => $this->put(route('transfers.changeStatus', $transfer->id), ['status' => 1]));

        $response->assertRedirect();
        $this->assertGreaterThan($last, $movement->id);
        $this->assertSame('legacy:transfers.changeStatus', $movement->source_type);
        $this->assertSame($transfer->id, (int) $movement->source_id);
        $this->assertNotEquals($afterStore, $this->snapshot(), 'changeStatus must move stock');
        $this->assertSame(5.0, $this->warehouseQty($b['id'], $this->main) + $this->warehouseQty($b['id'], $this->branchStore) - 3.0);
    }

    public function test_adjustment_store_records_one_signed_shadow_adjustment(): void
    {
        $a = $this->items['a'];
        $b = $this->items['b'];
        [$response, $movement] = $this->legacyRequest('qty_adjustment.store', fn () => $this->post(route('qty_adjustment.store'), [
            'warehouse_id' => $this->main, 'item' => 2, 'total_qty' => 3, 'note' => 'shadow proof',
            'product_id' => [$a['id'], $b['id']], 'product_code' => [$a['code'], $b['code']], 'qty' => [2, 1],
            'action' => ['-', '+'], 'unit_cost' => [4, 4],
        ]));

        $response->assertRedirect();
        $adjustment = DB::table('adjustments')->orderByDesc('id')->first();
        $this->assertShadow($movement, 'adjustment', 'legacy:qty_adjustment.store', $adjustment->id,
            [[$a['id'], $this->main, -2], [$b['id'], $this->main, 1]]);
    }

    private function ajaxSale(array $item, float $qty): TestResponse
    {
        return $this->actingAs($this->admin)->post(route('sales.store'), $this->salePayload($item, $qty) + ['pos' => 1],
            ['X-Requested-With' => 'XMLHttpRequest'])->assertOk();
    }

    /** @param list<array{0: array, 1: float, 2: string}> $lines [item, received qty, purchase unit name] */
    private function purchasePayload(array $lines): array
    {
        $column = fn (callable $value) => array_map($value, $lines);
        $total = array_sum($column(fn ($l) => $l[1] * 4));

        return [
            'warehouse_id' => $this->main, 'supplier_id' => 1, 'status' => 1, 'currency_id' => 1, 'exchange_rate' => 1,
            'item' => count($lines), 'total_qty' => array_sum($column(fn ($l) => $l[1])), 'total_discount' => 0, 'total_tax' => 0,
            'total_cost' => $total, 'order_tax_rate' => 0, 'order_tax' => 0, 'order_discount' => 0, 'shipping_cost' => 0,
            'grand_total' => $total, 'paid_amount' => 0, 'payment_status' => 1, 'note' => 'shadow proof',
            'product_id' => $column(fn ($l) => $l[0]['id']), 'product_code' => $column(fn ($l) => $l[0]['code']),
            'qty' => $column(fn ($l) => $l[1]), 'recieved' => $column(fn ($l) => $l[1]), 'purchase_unit' => $column(fn ($l) => $l[2]),
            'unit_cost' => $column(fn () => 4), 'net_unit_cost' => $column(fn () => 4), 'net_unit_margin' => $column(fn () => 0),
            'net_unit_margin_type' => $column(fn () => 'percentage'), 'net_unit_price' => $column(fn () => 10),
            'discount' => $column(fn () => 0), 'tax_rate' => $column(fn () => 0), 'tax' => $column(fn () => 0),
            'subtotal' => $column(fn ($l) => $l[1] * 4), 'imei_number' => $column(fn () => ''),
            'batch_no' => $column(fn () => null), 'expired_date' => $column(fn () => null),
        ];
    }

    private function transferPayload(array $item, float $qty, int $status): array
    {
        return [
            'from_warehouse_id' => $this->main, 'to_warehouse_id' => $this->branchStore, 'status' => $status,
            'item' => 1, 'total_qty' => $qty, 'total_tax' => 0, 'total_cost' => $qty * 4, 'shipping_cost' => 0,
            'grand_total' => $qty * 4, 'note' => 'shadow proof',
            'product_id' => [$item['id']], 'product_code' => [$item['code']], 'qty' => [$qty], 'purchase_unit' => ['piece'],
            'net_unit_cost' => [4], 'tax_rate' => [0], 'tax' => [0], 'subtotal' => [$qty * 4], 'imei_number' => [''],
            'product_batch_id' => [''],
        ];
    }

    private function salePayload(array $item, float $qty, float $price = 10): array
    {
        $total = $qty * $price;

        return [
            'customer_id' => 1, 'warehouse_id' => $this->main, 'biller_id' => 1, 'currency_id' => 1, 'exchange_rate' => 1,
            'item' => 1, 'total_qty' => $qty, 'total_discount' => 0, 'total_tax' => 0, 'total_price' => $total,
            'order_tax_rate' => 0, 'order_tax' => 0, 'order_discount' => 0, 'order_discount_type' => 'Flat',
            'order_discount_value' => 0, 'shipping_cost' => 0, 'grand_total' => $total,
            'sale_status' => 1, 'payment_status' => 1, 'paid_amount' => 0, 'paying_amount' => 0, 'paid_by_id' => ['1'],
            'coupon_active' => 0, 'draft' => 0, 'sale_note' => 'shadow proof', 'staff_note' => null,
            'product_id' => [$item['id']], 'product_code' => [$item['code']], 'qty' => [$qty],
            'sale_unit' => ['piece'], 'net_unit_price' => [$price], 'discount' => [0], 'tax_rate' => [0],
            'tax' => [0], 'subtotal' => [$total], 'imei_number' => [''], 'product_batch_id' => [''],
        ];
    }

    /**
     * Runs the request with the shadow off (rolled back afterwards), then for real with the shadow on.
     * Asserts identical projections and exactly one new stock movement, which is returned.
     *
     * @return array{0: TestResponse, 1: StockMovement}
     */
    private function legacyRequest(string $label, callable $request): array
    {
        $before = StockMovement::max('id') ?? 0;
        $level = DB::transactionLevel();
        DB::beginTransaction();
        config(['inventory.legacy_ledger_mode' => 'off']);
        $this->actingAs($this->admin);
        $legacy = $request();
        $legacyOnly = $this->snapshot();
        $this->assertSame($before, StockMovement::max('id') ?? 0, "$label shadow-off run recorded a movement");
        // Also discards any transaction the legacy writer leaked (e.g. SaleController::genInvoice).
        DB::rollBack($level);
        config(['inventory.legacy_ledger_mode' => 'shadow']);

        $this->actingAs($this->admin);
        $response = $request();
        $context = $label.' '.$this->describe($response);
        $this->assertSame($legacy->getStatusCode(), $response->getStatusCode(), "$context: status differs from the legacy-only run");
        $this->assertEquals($legacyOnly, $this->snapshot(), "$context: projections differ from the legacy-only run");
        $movements = StockMovement::where('id', '>', $before)->get();
        $this->assertCount(1, $movements, "$context: must record exactly one movement");
        $this->assertSame($this->baseline, $this->differences(), "$label introduced reconciliation differences");

        return [$response, $movements->first()->load('lines')];
    }

    private function assertShadow(StockMovement $movement, string $type, string $source, int $sourceId, array $lines): void
    {
        $this->assertSame('shadow', $movement->projection_mode);
        $this->assertSame($type, $movement->movement_type);
        $this->assertSame($source, $movement->source_type);
        $this->assertSame($sourceId, (int) $movement->source_id);
        $actual = $movement->lines->map(fn ($l) => [(int) $l->product_id, (int) $l->warehouse_id, (float) $l->qty_base])
            ->sortBy(fn ($l) => $l[0] * 1000 + $l[1])->values()->all();
        $expected = collect($lines)->map(fn ($l) => [$l[0], $l[1], (float) $l[2]])->sortBy(fn ($l) => $l[0] * 1000 + $l[1])->values()->all();
        $this->assertSame($expected, $actual);
    }

    /** Response status plus flashed errors and shadow warnings, for failure messages. */
    private function describe(TestResponse $response): string
    {
        $flash = array_filter([
            'not_permitted' => session('not_permitted'), 'error' => session('error'),
            'errors' => session('errors')?->getBag('default')->all(),
        ]);
        $log = storage_path('logs/laravel.log');
        $warnings = is_file($log) ? array_slice(preg_grep('/Legacy stock|testing\.ERROR/', file($log)), -2) : [];

        return "[HTTP {$response->getStatusCode()} ".json_encode($flash).' '.trim(implode(' ', array_map(fn ($l) => substr($l, 0, 240), $warnings))).']';
    }

    private function item(string $code, array $stock): array
    {
        $row = (array) DB::table('products')->find(5);
        unset($row['id']);
        $id = DB::table('products')->insertGetId(array_merge($row, [
            'name' => 'Shadow proof '.$code, 'code' => $code, 'qty' => array_sum($stock), 'cost' => 4,
            'type' => 'standard', 'is_variant' => null, 'is_batch' => null, 'is_imei' => null, 'is_active' => 1,
        ]));
        foreach ($stock as $warehouse => $qty) {
            DB::table('product_warehouse')->insert([
                'product_id' => $id, 'warehouse_id' => $warehouse, 'qty' => $qty, 'company_id' => 1,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return ['id' => $id, 'code' => $code];
    }

    private function warehouseQty(int $productId, int $warehouseId): float
    {
        return (float) DB::table('product_warehouse')->where('product_id', $productId)->where('warehouse_id', $warehouseId)->sum('qty');
    }

    private function snapshot(): array
    {
        $ids = array_column($this->items, 'id');

        return [
            'products' => DB::table('products')->whereIn('id', $ids)->orderBy('id')->pluck('qty', 'id')->map(fn ($q) => round((float) $q, 4))->all(),
            'warehouses' => DB::table('product_warehouse')->whereIn('product_id', $ids)->orderBy('product_id')->orderBy('warehouse_id')
                ->get(['product_id', 'warehouse_id', 'variant_id', 'product_batch_id', 'qty', 'imei_number'])
                ->map(fn ($r) => [(int) $r->product_id, (int) $r->warehouse_id, $r->variant_id, $r->product_batch_id, round((float) $r->qty, 4), $r->imei_number])->all(),
        ];
    }

    private function differences(): array
    {
        return app(InventoryReconciliationService::class)->differences();
    }
}
