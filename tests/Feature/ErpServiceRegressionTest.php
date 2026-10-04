<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\V1\PurchaseApiController;
use App\Models\Accounting\ChartOfAccount;
use App\Models\Accounting\JournalEntry;
use App\Models\Payment;
use App\Models\Product_Warehouse;
use App\Models\Purchase;
use App\Models\Sale;
use App\Services\Accounting\AccountingService;
use App\Services\ERP\InventoryService;
use App\Services\ERP\PurchaseService;
use App\Services\ERP\SaleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;
use Tests\Support\CompanyContextTestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ErpServiceRegressionTest extends CompanyContextTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(\App\Models\User::findOrFail(1));
        foreach (['customers', 'suppliers', 'billers'] as $tableName) {
            DB::table($tableName)->update(['company_id' => $this->company->id]);
        }
        DB::table('warehouses')->update(['company_id' => $this->company->id, 'branch_id' => $this->branch->id]);
        foreach (['units', 'accounts'] as $tableName) {
            Schema::create($tableName, function (Blueprint $table) {
                $table->increments('id');
                $table->string('name');
                $table->unsignedBigInteger('company_id');
            });
            DB::table($tableName)->insert(['id' => 1, 'name' => 'Owned A', 'company_id' => $this->company->id]);
        }
        foreach ([
            ['1010', 'asset', 'cash'], ['1100', 'asset', 'accounts_receivable'],
            ['1200', 'asset', 'inventory'], ['2010', 'liability', 'accounts_payable'],
            ['4010', 'revenue', 'sales_revenue'], ['5010', 'expense', 'cogs'],
            ['GIT', 'asset', 'goods_in_transit'],
        ] as [$code, $type, $subType]) {
            (new ChartOfAccount)->forceFill([
                'company_id' => $this->company->id,
                'code' => $code, 'name' => $subType, 'type' => $type, 'sub_type' => $subType,
            ])->save();
        }
    }

    protected function stock(float $qty = 20, float $cost = 5): \App\Models\Product
    {
        $product = parent::stock($qty, $cost);
        $product->forceFill(['company_id' => $this->company->id])->save();
        Product_Warehouse::where('product_id', $product->id)->update(['company_id' => $this->company->id]);
        return $product;
    }

    public function test_completed_sale_preserves_stock_payment_and_balanced_journal(): void
    {
        $product = $this->stock();
        $sale = (new SaleService(new AccountingService()))->createSale([
            'customer_id' => 1, 'warehouse_id' => 1, 'paid_amount' => 10,
            'items' => [['product_id' => $product->id, 'qty' => 2, 'net_unit_price' => 10]],
        ], 1);

        $this->assertEquals(20, $sale->grand_total);
        $this->assertSame(3, $sale->payment_status);
        $this->assertEquals(18, $product->fresh()->qty);
        $this->assertEquals(18, Product_Warehouse::first()->qty);
        $this->assertCount(1, $sale->productSales);
        $this->assertCount(1, $sale->payments);
        $journal = JournalEntry::where('reference_type', 'sale')->sole();
        $this->assertEquals($sale->id, $journal->reference_id);
        $this->assertTrue($journal->isBalanced());
        $this->assertEquals(30, $journal->total_debit);

        $this->assertSame($product->id, $sale->load('productSales.product')->toArray()['product_sales'][0]['product']['id']);
    }

    public function test_received_purchase_preserves_received_quantity_stock_payment_and_journal(): void
    {
        $product = $this->stock();
        $purchase = (new PurchaseService(new AccountingService()))->createPurchase([
            'supplier_id' => 1, 'warehouse_id' => 1, 'paid_amount' => 5,
            'items' => [['product_id' => $product->id, 'qty' => 2, 'net_unit_cost' => 6]],
        ], 1);

        $this->assertEquals(12, $purchase->grand_total);
        $this->assertSame(3, $purchase->payment_status);
        $this->assertEquals(22, $product->fresh()->qty);
        $this->assertEquals(6, $product->fresh()->cost);
        $this->assertEquals(22, Product_Warehouse::first()->qty);
        $this->assertEquals(2, $purchase->productPurchases->sole()->recieved);
        $this->assertCount(1, $purchase->payments);
        $journal = JournalEntry::where('reference_type', 'purchase')->sole();
        $this->assertTrue($journal->isBalanced());
        $this->assertEquals(12, $journal->total_debit);

        $this->assertSame($product->id, $purchase->load('productPurchases.product')->toArray()['product_purchases'][0]['product']['id']);
    }

    public function test_pending_purchase_does_not_receive_stock(): void
    {
        $product = $this->stock();
        $purchase = (new PurchaseService(new AccountingService()))->createPurchase([
            'supplier_id' => 1, 'warehouse_id' => 1, 'status' => 3,
            'items' => [['product_id' => $product->id, 'qty' => 2, 'net_unit_cost' => 6]],
        ], 1);

        $this->assertEquals(0, $purchase->productPurchases->sole()->recieved);
        $this->assertEquals(20, $product->fresh()->qty);
        $this->assertEquals(20, Product_Warehouse::first()->qty);
        $this->assertEquals(12, ChartOfAccount::where('sub_type', 'goods_in_transit')->sole()->current_balance);
        $this->assertEquals(0, ChartOfAccount::where('sub_type', 'inventory')->sole()->current_balance);
        $this->assertTrue(JournalEntry::sole()->isBalanced());
    }

    public function test_partial_purchase_receives_four_of_ten_and_recognizes_full_supplier_bill(): void
    {
        $product = $this->stock();
        $purchase = (new PurchaseService(new AccountingService()))->createPurchase([
            'supplier_id' => 1, 'warehouse_id' => 1, 'status' => 2, 'paid_amount' => 10,
            'shipping_cost' => 5,
            'items' => [['product_id' => $product->id, 'qty' => 10, 'received_qty' => 4, 'net_unit_cost' => 5]],
        ], 1);
        $this->assertEquals(4, $purchase->productPurchases->sole()->recieved);
        $this->assertEquals(24, $product->fresh()->qty);
        $this->assertEquals(24, Product_Warehouse::first()->qty);
        $this->assertEquals(22, ChartOfAccount::where('sub_type', 'inventory')->sole()->current_balance);
        $this->assertEquals(33, ChartOfAccount::where('sub_type', 'goods_in_transit')->sole()->current_balance);
        $this->assertEquals(45, ChartOfAccount::where('sub_type', 'accounts_payable')->sole()->current_balance);
        $this->assertEquals(55, JournalEntry::sole()->total_debit);
        $this->assertTrue(JournalEntry::sole()->isBalanced());
    }

    public function test_unreceived_bill_without_transit_account_rolls_back_purchase_and_payment(): void
    {
        $product = $this->stock();
        ChartOfAccount::where('sub_type', 'goods_in_transit')->delete();
        try {
            (new PurchaseService(new AccountingService()))->createPurchase([
                'supplier_id' => 1, 'warehouse_id' => 1, 'status' => 2, 'paid_amount' => 5,
                'items' => [['product_id' => $product->id, 'qty' => 10, 'received_qty' => 4, 'net_unit_cost' => 5]],
            ], 1);
            $this->fail('Missing transit account must fail.');
        } catch (InvalidArgumentException $error) {
            $this->assertStringContainsString('goods_in_transit', $error->getMessage());
        }
        $this->assertSame(0, Purchase::count());
        $this->assertSame(0, Payment::count());
        $this->assertSame(0, JournalEntry::count());
        $this->assertEquals(20, $product->fresh()->qty);
        $this->assertEquals(20, Product_Warehouse::first()->qty);
    }

    public function test_partial_purchase_rejects_missing_or_excess_received_quantity_before_writes(): void
    {
        $product = $this->stock();
        foreach ([null, -1, 11, 'invalid'] as $received) {
            try {
                (new PurchaseService(new AccountingService()))->createPurchase([
                    'supplier_id' => 1, 'warehouse_id' => 1, 'status' => 2,
                    'items' => [['product_id' => $product->id, 'qty' => 10, 'received_qty' => $received, 'net_unit_cost' => 5]],
                ], 1);
                $this->fail('Invalid receipt must fail.');
            } catch (InvalidArgumentException $error) {
                $this->assertStringContainsString('received_qty', $error->getMessage());
            }
        }
        $this->assertSame(0, Purchase::count());
        $this->assertEquals(20, $product->fresh()->qty);
    }

    public function test_ordered_purchase_has_no_stock_or_supplier_bill_journal(): void
    {
        $product = $this->stock();
        (new PurchaseService(new AccountingService()))->createPurchase([
            'supplier_id' => 1, 'warehouse_id' => 1, 'status' => 4,
            'items' => [['product_id' => $product->id, 'qty' => 10, 'net_unit_cost' => 5]],
        ], 1);
        $this->assertSame(0, JournalEntry::count());
        $this->assertEquals(20, $product->fresh()->qty);
    }

    public function test_api_partial_purchase_requires_received_quantity(): void
    {
        $product = $this->stock();
        $request = Request::create('/api/v1/purchases', 'POST', [
            'supplier_id' => 1, 'warehouse_id' => 1, 'status' => 2,
            'items' => [['product_id' => $product->id, 'qty' => 10, 'net_unit_cost' => 5]],
        ]);
        $response = (new PurchaseApiController(new PurchaseService(new AccountingService())))->store($request);
        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame(0, Purchase::count());
        $this->assertEquals(20, $product->fresh()->qty);
    }

    public function test_free_received_goods_have_stock_effect_without_zero_value_journal(): void
    {
        $product = $this->stock();
        (new PurchaseService(new AccountingService()))->createPurchase([
            'supplier_id' => 1, 'warehouse_id' => 1,
            'items' => [['product_id' => $product->id, 'qty' => 2, 'net_unit_cost' => 0]],
        ], 1);
        $this->assertEquals(22, $product->fresh()->qty);
        $this->assertSame(0, JournalEntry::count());
    }

    public function test_purchase_bank_payment_credits_bank_instead_of_cash(): void
    {
        $bank = ChartOfAccount::create(['code' => '1020', 'name' => 'Bank', 'type' => 'asset', 'sub_type' => 'bank']);
        $bank->forceFill(['company_id' => $this->company->id])->save();
        $product = $this->stock();
        (new PurchaseService(new AccountingService()))->createPurchase([
            'supplier_id' => 1, 'warehouse_id' => 1, 'paid_amount' => 10, 'paying_method' => 'Bank',
            'items' => [['product_id' => $product->id, 'qty' => 2, 'net_unit_cost' => 5]],
        ], 1);
        $this->assertEquals(-10, $bank->fresh()->current_balance);
        $this->assertEquals(0, ChartOfAccount::where('sub_type', 'cash')->sole()->current_balance);
    }

    public function test_completed_transfer_moves_warehouse_stock_without_changing_product_total(): void
    {
        $product = $this->stock();
        $transfer = (new InventoryService(new AccountingService()))->transferStock([
            'from_warehouse_id' => 1, 'to_warehouse_id' => 2,
            'items' => [['product_id' => $product->id, 'qty' => 3, 'net_unit_cost' => 5]],
        ], 1);

        $this->assertEquals(17, Product_Warehouse::where('warehouse_id', 1)->sole()->qty);
        $this->assertEquals(3, Product_Warehouse::where('warehouse_id', 2)->sole()->qty);
        $this->assertEquals(20, $product->fresh()->qty);
        $this->assertCount(1, $transfer->productTransfers);
        $this->assertEquals($product->id, $transfer->productTransfers->sole()->product->id);
        $this->assertSame(0, JournalEntry::count());
    }

    public function test_sale_accounting_failure_rolls_back_document_stock_and_payment(): void
    {
        $product = $this->stock();
        $accounting = \Mockery::mock(AccountingService::class);
        $accounting->shouldReceive('postSaleJournal')->once()->andThrow(new RuntimeException('Posting failed'));

        try {
            (new SaleService($accounting))->createSale([
                'customer_id' => 1, 'warehouse_id' => 1, 'paid_amount' => 10,
                'items' => [['product_id' => $product->id, 'qty' => 2, 'net_unit_price' => 10]],
            ], 1);
            $this->fail('Posting must fail.');
        } catch (RuntimeException $error) {
            $this->assertSame('Posting failed', $error->getMessage());
        }

        $this->assertSame(0, Sale::count());
        $this->assertSame(0, DB::table('product_sales')->count());
        $this->assertSame(0, Payment::count());
        $this->assertEquals(20, $product->fresh()->qty);
        $this->assertEquals(20, Product_Warehouse::first()->qty);
    }

    public function test_purchase_accounting_failure_rolls_back_document_stock_cost_and_payment(): void
    {
        $product = $this->stock();
        $accounting = \Mockery::mock(AccountingService::class);
        $accounting->shouldReceive('postPurchaseJournal')->once()->andThrow(new RuntimeException('Posting failed'));

        try {
            (new PurchaseService($accounting))->createPurchase([
                'supplier_id' => 1, 'warehouse_id' => 1, 'paid_amount' => 5,
                'items' => [['product_id' => $product->id, 'qty' => 2, 'net_unit_cost' => 6]],
            ], 1);
            $this->fail('Posting must fail.');
        } catch (RuntimeException $error) {
            $this->assertSame('Posting failed', $error->getMessage());
        }

        $this->assertSame(0, Purchase::count());
        $this->assertSame(0, DB::table('product_purchases')->count());
        $this->assertSame(0, Payment::count());
        $this->assertEquals(20, $product->fresh()->qty);
        $this->assertEquals(5, $product->fresh()->cost);
        $this->assertEquals(20, Product_Warehouse::first()->qty);
    }

    public function test_unbalanced_journal_is_rejected_without_writes(): void
    {
        $accounts = ChartOfAccount::orderBy('id')->take(2)->get();
        try {
            (new AccountingService())->postJournalEntry(['entry_date' => '2026-10-03'], [
                ['chart_of_account_id' => $accounts[0]->id, 'debit' => 10],
                ['chart_of_account_id' => $accounts[1]->id, 'credit' => 9],
            ]);
            $this->fail('Unbalanced posting must fail.');
        } catch (InvalidArgumentException $error) {
            $this->assertStringContainsString('Double-entry unbalanced', $error->getMessage());
        }

        $this->assertSame(0, JournalEntry::count());
        $this->assertEquals(0, ChartOfAccount::sum('current_balance'));
    }
}
