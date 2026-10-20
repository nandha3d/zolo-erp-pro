<?php

namespace Tests\Feature;

use App\Models\Accounting\ChartOfAccount;
use App\Models\CommercialOrderItem;
use App\Models\DeliveryChallan;
use App\Models\GoodsReceivedNote;
use App\Models\Product;
use App\Services\Commercial\CommercialApplicationService;
use App\Services\Commercial\OrderLifecycleService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Process\Process;
use Tests\Support\CommercialTestCase;

/** Real MySQL processes exercise the same posting, allocation and retry boundaries as document entry. */
class CommercialLifecycleConcurrencyTest extends CommercialTestCase
{
    protected function setUp(): void
    {
        // Check before the inherited fixture can clear any tables.
        if (getenv('ERP_TEST_MYSQL') === '1' && getenv('ERP_TEST_MYSQL_DATABASE') !== 'zolo_test_textile_concurrency') {
            throw new \RuntimeException('Commercial concurrency fixtures require zolo_test_textile_concurrency exclusively.');
        }
        parent::setUp();
        if (getenv('ERP_TEST_MYSQL') !== '1') {
            $this->markTestSkipped('Row-lock proof requires ERP_TEST_MYSQL=1 and zolo_test_textile_concurrency.');
        }
        Schema::table('warehouses', fn (Blueprint $t) => $t->boolean('is_active')->default(true));
        (require database_path('migrations/2026_10_13_000004_create_optech_dc_and_grn_tables.php'))->up();
        (require database_path('migrations/2026_10_17_000007_post_material_documents_physically.php'))->up();
        foreach ([['GRNI', 'liability', 'goods_received_not_invoiced'], ['GDNI', 'asset', 'goods_delivered_not_invoiced'],
            ['VAR', 'expense', 'inventory_variance']] as [$code, $type, $subType]) {
            ChartOfAccount::forceCreate(['company_id' => $this->company->id, 'code' => $code,
                'name' => $subType, 'type' => $type, 'sub_type' => $subType]);
        }
        app(\App\Services\Accounting\SemanticAccountResolver::class)->seedCompany($this->company->id);
    }

    public function test_two_invoices_competing_for_last_sales_order_unit_post_exactly_once(): void
    {
        [$order, $product, $item] = $this->order(false);
        $data = $this->consumerData($product, false) + ['sales_order_id' => $order->id];
        $data['items'][0]['order_item_id'] = $item->id;

        $this->assertOneSuccess($this->race('sale', $data));

        $this->assertSame(1, DB::table('sales')->count());
        $this->assertEquals(99, $product->fresh()->qty);
        $this->assertSame(1, DB::table('stock_movements')->where('source_type', 'sale')->count());
        $this->assertSinglePosting('sale', 10);
        $this->assertOrderConsumed('sale', $order->id, $item->id);
    }

    public function test_two_bills_competing_for_last_purchase_order_unit_receive_and_bill_exactly_once(): void
    {
        [$order, $product, $item] = $this->order(true);
        $data = $this->consumerData($product, true) + ['purchase_order_id' => $order->id];
        $data['items'][0]['order_item_id'] = $item->id;

        $this->assertOneSuccess($this->race('purchase', $data));

        $this->assertSame(1, DB::table('purchases')->where('status', '<>', 4)->count());
        $this->assertEquals(101, $product->fresh()->qty);
        $this->assertSame(1, DB::table('stock_movements')->where('source_type', 'purchase')->count());
        $this->assertSinglePosting('purchase', 5);
        $this->assertOrderConsumed('purchase', $order->id, $item->id);
    }

    public function test_two_later_receipts_competing_for_last_purchase_order_unit_receive_exactly_once(): void
    {
        [$order, $product, $item] = $this->order(true);
        $data = $this->consumerData($product, true) + ['purchase_order_id' => $order->id, 'status' => 2];
        $data['items'][0] += ['order_item_id' => $item->id, 'received_qty' => 0];
        $bill = app(CommercialApplicationService::class)->create('purchase', $data, 'unreceived-bill', 1, $this->context());
        $line = $bill->productPurchases()->firstOrFail();
        $journals = DB::table('journal_entries')->count();
        $openItems = DB::table('account_open_items')->count();

        $this->assertOneSuccess($this->race('receipt', ['purchase_id' => $bill->id, 'business_date' => '2026-10-03',
            'items' => [['line_id' => $line->id, 'qty' => 1]]]));

        $this->assertEquals(1, $line->fresh()->recieved);
        $this->assertEquals(101, $product->fresh()->qty);
        $this->assertSame(1, DB::table('stock_movements')->where('source_type', 'purchase')->count());
        $this->assertSame($journals + 1, DB::table('journal_entries')->count());
        $this->assertSame($openItems, DB::table('account_open_items')->count(), 'Receipt cannot recognize AP a second time.');
        $this->assertEquals(-5, $this->balance('accounts_payable'));
        $this->assertEquals(0, $this->balance('goods_in_transit'));
        $this->assertOrderConsumed('purchase', $order->id, $item->id);
    }

    public function test_same_payload_and_key_raced_twice_return_same_invoice_and_one_set_of_effects(): void
    {
        [$order, $product, $item] = $this->order(false);
        $data = $this->consumerData($product, false) + ['sales_order_id' => $order->id];
        $data['items'][0]['order_item_id'] = $item->id;
        $results = $this->race('sale', $data, true);

        $this->assertSame([true, true], array_column($results, 'posted'));
        $this->assertSame($results[0]['id'], $results[1]['id']);
        $this->assertSame(1, DB::table('sales')->count());
        $this->assertEquals(99, $product->fresh()->qty);
        $this->assertSame(1, DB::table('stock_movements')->where('source_type', 'sale')->count());
        $this->assertSame(1, DB::table('idempotency_keys')->where('key', 'commercial-race-shared')->count());
        $this->assertSinglePosting('sale', 10);
        $this->assertOrderConsumed('sale', $order->id, $item->id);
    }

    public function test_two_bills_cannot_settle_same_last_grn_source_quantity(): void
    {
        $this->assertMaterialRace(true);
    }

    public function test_two_invoices_cannot_settle_same_last_dc_source_quantity(): void
    {
        $this->assertMaterialRace(false);
    }

    private function assertMaterialRace(bool $purchase): void
    {
        $product = $this->stock(100); $product->forceFill(['unit_id' => 1])->save();
        $route = $purchase ? '/goods-received-notes' : '/delivery-challans';
        $data = [$purchase ? 'grn_date' : 'challan_date' => '2026-10-03', $purchase ? 'supplier_id' : 'customer_id' => 1,
            'warehouse_id' => 1, 'product_id' => [$product->id], 'unit_id' => [1], 'qty' => [1],
            $purchase ? 'cost' : 'rate' => [$purchase ? 5 : 10]];
        if (!$purchase) $data['purpose'] = 'approval';
        $id = $this->postJson($route, $data)->assertCreated()->json($purchase ? 'grn.id' : 'challan.id');
        $source = ($purchase ? GoodsReceivedNote::class : DeliveryChallan::class)::findOrFail($id);
        $sourceItem = $source->items()->firstOrFail();
        $movements = DB::table('stock_movements')->count();
        $journals = DB::table('journal_entries')->count();
        $billData = $this->consumerData($product, $purchase) + [$purchase ? 'goods_received_note_ids' : 'delivery_challan_ids' => [$id]];
        $billData['items'][0]['material_sources'] = [['document_id' => $id, 'item_id' => $sourceItem->id, 'qty_base' => 1]];

        $this->assertOneSuccess($this->race($purchase ? 'purchase' : 'sale', $billData));

        $this->assertSame(1, DB::table($purchase ? 'purchases' : 'sales')->count());
        $this->assertEquals($purchase ? 101 : 99, $product->fresh()->qty);
        $this->assertSame($movements, DB::table('stock_movements')->count(), 'Settlement must not move warehouse stock again.');
        $this->assertSame($journals + 1, DB::table('journal_entries')->count());
        $this->assertEquals(1, $sourceItem->fresh()->billed_qty_base);
        $this->assertSame(1, DB::table('material_settlements')->whereNull('released_at')->count());
        $this->assertEquals(1, DB::table('material_settlements')->whereNull('released_at')->sum('qty_base'));
        $this->assertSinglePosting($purchase ? 'purchase' : 'sale', $purchase ? 5 : 10);
        $this->assertEquals(0, $this->balance($purchase ? 'goods_received_not_invoiced' : 'goods_delivered_not_invoiced'));
    }

    private function order(bool $purchase): array
    {
        $product = $this->stock(100); $product->forceFill(['unit_id' => 1])->save();
        $data = $this->consumerData($product, $purchase);
        $order = $purchase
            ? app(CommercialApplicationService::class)->create('purchase', ['status' => 4] + $data, 'race-po', 1, $this->context())
            : app(OrderLifecycleService::class)->createSale($data, 'race-so', $this->context(), 1);
        $item = CommercialOrderItem::where('order_kind', $purchase ? 'purchase' : 'sale')->where('order_id', $order->id)->firstOrFail();
        return [$order, $product, $item];
    }

    private function consumerData(Product $product, bool $purchase): array
    {
        return [$purchase ? 'supplier_id' : 'customer_id' => 1, 'warehouse_id' => 1, 'business_date' => '2026-10-03',
            'items' => [['product_id' => $product->id, 'qty' => 1, $purchase ? 'purchase_unit_id' : 'sale_unit_id' => 1,
                $purchase ? 'net_unit_cost' : 'net_unit_price' => $purchase ? 5 : 10]]];
    }

    private function assertOrderConsumed(string $kind, int $orderId, int $itemId): void
    {
        $remaining = app(OrderLifecycleService::class)->remaining($kind, $orderId, $this->context())['items'][0];
        $this->assertEquals(0, $remaining['remaining_billing_base']);
        $this->assertEquals(0, $remaining['remaining_delivery_base']);
        foreach (['billing', 'delivery'] as $stage) {
            $allocations = DB::table('commercial_order_allocations')->where('order_item_id', $itemId)->where('stage', $stage)->whereNull('released_at');
            $this->assertSame(1, $allocations->count());
            $this->assertEquals(1, $allocations->sum('qty_base'));
        }
    }

    private function assertSinglePosting(string $kind, float $amount): void
    {
        $this->assertSame(1, DB::table('journal_entries')->where('reference_type', $kind)->where('status', 'posted')->count());
        $this->assertSame(1, DB::table('account_open_items')->count());
        $this->assertEquals($kind === 'purchase' ? -$amount : $amount,
            $this->balance($kind === 'purchase' ? 'accounts_payable' : 'accounts_receivable'));
        $this->assertSame(0, DB::table('payments')->count());
    }

    private function balance(string $subType): float
    {
        return round((float) DB::table('journal_items as i')->join('journal_entries as e', 'e.id', '=', 'i.journal_entry_id')
            ->join('chart_of_accounts as a', 'a.id', '=', 'i.chart_of_account_id')->where('e.status', 'posted')
            ->where('e.company_id', $this->company->id)->where('a.sub_type', $subType)->sum(DB::raw('i.debit - i.credit')), 4);
    }

    private function assertOneSuccess(array $results): void
    {
        $this->assertCount(1, array_filter($results, fn ($result) => $result['posted']), json_encode($results));
        $rejected = array_values(array_filter($results, fn ($result) => !$result['posted']));
        $this->assertSame('validation', $rejected[0]['error_type']);
        $this->assertNotEmpty($rejected[0]['errors']);
    }

    /** Both workers finish booting before the start barrier opens; no database fixture transaction hides rows from them. */
    private function race(string $action, array $data, bool $sameKey = false): array
    {
        $files = []; $workers = [];
        $barrier = tempnam(sys_get_temp_dir(), 'commercial-start-');
        $files[] = $barrier;
        try {
            foreach ([1, 2] as $index) {
                $path = tempnam(sys_get_temp_dir(), 'commercial-race-');
                $files[] = $path; $files[] = $path.'.ready';
                file_put_contents($path, json_encode(['action' => $action, 'data' => $data,
                    'key' => $sameKey ? 'commercial-race-shared' : 'commercial-race-'.$index,
                    'context' => [$this->company->id, $this->branch->id, $this->year->id]], JSON_THROW_ON_ERROR));
                $process = new Process([PHP_BINARY, base_path('tests/Support/commercial_lifecycle_worker.php'), $path, $barrier], base_path());
                $process->setTimeout(45); $process->start();
                $workers[] = $process;
            }
            $deadline = microtime(true) + 20;
            do {
                $ready = count(array_filter($files, fn ($path) => str_ends_with($path, '.ready') && is_file($path)));
                if ($ready === 2) break;
                foreach ($workers as $worker) {
                    if (!$worker->isRunning()) $this->fail('Concurrency worker failed before barrier: '.$worker->getErrorOutput());
                }
                usleep(10000);
            } while (microtime(true) < $deadline);
            $this->assertSame(2, $ready, 'Workers must both be ready before the race starts.');
            file_put_contents($barrier, (string) (microtime(true) + 0.2));
            $results = [];
            foreach ($workers as $worker) {
                $worker->wait();
                $this->assertTrue($worker->isSuccessful(), $worker->getErrorOutput());
                $result = json_decode($worker->getOutput(), true, 512, JSON_THROW_ON_ERROR);
                $this->assertTrue($result['lock_held'], 'Worker must reach the real company posting lock.');
                $results[] = $result;
            }
            $this->assertGreaterThanOrEqual(200, max(array_column($results, 'lock_wait_ms')),
                'At least one process must wait on the other process company lock; sequential execution is not concurrency proof.');
            return $results;
        } finally {
            foreach ($workers as $worker) if ($worker->isRunning()) $worker->stop(1);
            foreach ($files as $path) if (is_file($path)) unlink($path);
        }
    }
}
