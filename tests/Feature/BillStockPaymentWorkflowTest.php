<?php

namespace Tests\Feature;

use App\Models\{Payment, Sale};
use App\Models\Accounting\JournalEntry;
use App\Services\Commercial\{CommercialApplicationService, CommercialReversalService};
use App\Services\Inventory\{InventoryMovementService, StockLine, StockMovementCommand, StockPolicyException};
use App\Services\Industry\IndustryStockQueries;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\Support\OperationsTestCase;

class BillStockPaymentWorkflowTest extends OperationsTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DB::table('accounts')->where('id', 1)->update(['name' => 'Cash drawer', 'chart_of_account_id' =>
            \App\Models\Accounting\ChartOfAccount::forCompany($this->context())->where('code', '1010')->value('id')]);
    }

    public function test_coa_bank_creation_links_payment_picker_and_reuses_the_same_ledger(): void
    {
        $before = \App\Models\Accounting\ChartOfAccount::forCompany($this->context())->count();
        $this->post('/accounting/chart-of-accounts', ['code' => '111', 'name' => 'IOB', 'type' => 'asset', 'sub_type' => 'Bank'])->assertRedirect();
        $ledger = \App\Models\Accounting\ChartOfAccount::forCompany($this->context())->where('code', '111')->sole();
        $this->assertSame('bank', $ledger->control_type);
        $alias = \App\Models\Account::forCompany($this->context())->where('chart_of_account_id', $ledger->id)->sole();
        $this->assertSame('IOB', $alias->name);
        $this->get('/selling/payments')->assertOk()->assertSee('IOB');
        $this->get('/buying/payments')->assertOk()->assertSee('IOB');
        $this->get('/accounting/chart-of-accounts')->assertOk()->assertSee('IOB')
            ->assertViewHas('allAccounts', fn ($accounts) => $accounts->contains('id', $ledger->id))
            ->assertViewHas('parentAccounts', fn ($accounts) => !$accounts->contains('id', $ledger->id));
        $this->postJson('/payment-accounts', ['name' => 'IOB', 'kind' => 'bank'])->assertCreated()->assertJsonPath('data.ledger_id', $ledger->id);
        $this->assertSame($before + 1, \App\Models\Accounting\ChartOfAccount::forCompany($this->context())->count());
        $this->assertEquals(0, $ledger->fresh()->current_balance);
        $this->put('/accounting/chart-of-accounts/'.$ledger->id, ['name' => 'IOB Current'])->assertRedirect();
        $this->assertSame('IOB Current', $alias->fresh()->name);
        $this->post('/accounting/chart-of-accounts', ['code' => '112', 'name' => 'Child of bank', 'type' => 'asset', 'sub_type' => 'bank', 'parent_id' => $ledger->id])->assertStatus(422);
    }

    public function test_existing_unlinked_coa_bank_is_repaired_without_money_or_default_mapping_changes(): void
    {
        $ledger = \App\Models\Accounting\ChartOfAccount::forceCreate(['company_id' => $this->company->id,
            'code' => 'IOB-OLD', 'name' => 'IOB', 'type' => 'asset', 'sub_type' => 'bank', 'control_type' => 'none',
            'opening_balance' => 25, 'current_balance' => 25, 'is_active' => true]);
        $mapping = DB::table('semantic_account_mappings')->where('company_id', $this->company->id)->where('semantic_role', 'bank')->value('account_id');
        $count = \App\Models\Accounting\ChartOfAccount::count();
        $this->postJson('/payment-accounts', ['name' => 'IOB', 'kind' => 'bank'])->assertCreated()->assertJsonPath('data.ledger_id', $ledger->id);
        $this->postJson('/payment-accounts', ['name' => 'IOB', 'kind' => 'bank'])->assertCreated();
        $this->assertSame($count, \App\Models\Accounting\ChartOfAccount::count());
        $this->assertSame(1, DB::table('accounts')->where('chart_of_account_id', $ledger->id)->count());
        $this->assertEquals(25, $ledger->fresh()->current_balance);
        $this->assertEquals(25, $ledger->fresh()->opening_balance);
        $this->assertSame($mapping, DB::table('semantic_account_mappings')->where('company_id', $this->company->id)->where('semantic_role', 'bank')->value('account_id'));
        $alias = \App\Models\Account::forCompany($this->context())->where('chart_of_account_id', $ledger->id)->sole();
        $this->get('/accounts')->assertOk()->assertViewHas('lims_account_all', fn ($accounts) => (float) $accounts->firstWhere('id', $alias->id)->balance === 25.0);
        $this->post('/account-statement', ['account_id' => $alias->id, 'start_date' => '2026-10-01', 'end_date' => '2026-10-31'])
            ->assertRedirect(route('accounting.general-ledger', ['account_id' => $ledger->id, 'start_date' => '2026-10-01', 'end_date' => '2026-10-31']));
    }

    public function test_named_bank_accounts_post_to_the_selected_bank_and_reject_method_mismatch(): void
    {
        $data = ['name' => 'HDFC Current', 'kind' => 'bank', 'account_no' => '123456789012'];
        $hdfc = $this->postJson('/payment-accounts', $data)->assertCreated()->assertJsonPath('data.last_four', '9012')->json('data');
        $sbi = $this->postJson('/payment-accounts', ['name' => 'SBI Current', 'kind' => 'bank', 'account_no' => '123456789999'])->assertCreated()->json('data');
        $this->postJson('/payment-accounts', $data)->assertCreated()->assertJsonPath('data.id', $hdfc['id']);
        $this->assertSame(3, DB::table('accounts')->count());
        $this->assertEquals(0, DB::table('chart_of_accounts')->where('id', $hdfc['ledger_id'])->value('current_balance'));
        $sale = app(CommercialApplicationService::class)->create('sale', $this->saleData($this->material()), 'named-bank-bill', 1, $this->context());
        $this->get('/selling/payments?bill='.$sale->id)->assertOk()->assertSee('HDFC Current')->assertSee('SBI Current')->assertDontSee('123456789012');
        $payment = ['bill_id' => $sale->id, 'amount' => 7, 'payment_at' => '2026-10-03', 'account_id' => $hdfc['id'],
            'paying_method' => 'Cash', 'idempotency_key' => (string) Str::uuid()];
        $this->postJson('/selling/payments', $payment)->assertUnprocessable()->assertJsonValidationErrors('account_id');
        $this->postJson('/selling/payments', array_replace($payment, ['paying_method' => 'Bank', 'account_id' => 1]))->assertUnprocessable();
        $this->postJson('/selling/payments', array_replace($payment, ['paying_method' => 'Bank']))->assertCreated();
        $entry = JournalEntry::where('reference_type', 'payment')->sole();
        $this->assertEquals(7, $entry->items()->where('chart_of_account_id', $hdfc['ledger_id'])->sum('debit'));
        $this->assertEquals(0, $entry->items()->where('chart_of_account_id', $sbi['ledger_id'])->count());
        $this->assertEquals(7, DB::table('chart_of_accounts')->where('id', $hdfc['ledger_id'])->value('current_balance'));
        $this->assertEquals(0, DB::table('chart_of_accounts')->where('id', $sbi['ledger_id'])->value('current_balance'));
        $this->assertEquals(7, $sale->fresh()->paid_amount);
        $purchase = app(CommercialApplicationService::class)->create('purchase', $this->purchaseData($this->material(0)), 'named-bank-purchase', 1, $this->context());
        $this->postJson('/buying/payments', ['bill_id' => $purchase->id, 'amount' => 4, 'payment_at' => '2026-10-03',
            'account_id' => $hdfc['id'], 'paying_method' => 'Bank', 'idempotency_key' => (string) Str::uuid()])->assertCreated();
        $outgoing = JournalEntry::where('reference_type', 'payment')->orderByDesc('id')->first();
        $this->assertEquals(4, $outgoing->items()->where('chart_of_account_id', $hdfc['ledger_id'])->sum('credit'));
        $this->assertEquals(3, DB::table('chart_of_accounts')->where('id', $hdfc['ledger_id'])->value('current_balance'));
        $this->assertEquals(4, $purchase->fresh()->paid_amount);
    }

    public function test_money_transfers_use_contra_ledgers_and_preserve_legacy_history(): void
    {
        $bank = app(\App\Services\Accounting\SettlementAccountService::class)->create(['name' => 'IOB', 'kind' => 'bank'], $this->context(), 1);
        Schema::create('money_transfers', function (Blueprint $table) {
            $table->increments('id'); $table->unsignedBigInteger('company_id'); $table->string('reference_no');
            $table->unsignedInteger('from_account_id'); $table->unsignedInteger('to_account_id');
            $table->decimal('amount', 18, 4); $table->timestamps();
        });
        DB::table('money_transfers')->insert(['id' => 1, 'company_id' => $this->company->id, 'reference_no' => 'OLD-TRANSFER',
            'from_account_id' => 1, 'to_account_id' => $bank->id, 'amount' => 5, 'created_at' => '2026-10-03', 'updated_at' => '2026-10-03']);
        $this->get('/money-transfers')->assertOk()->assertSee('New transfer · Contra voucher')->assertSee('OLD-TRANSFER');
        $this->post('/money-transfers', ['from_account_id' => 1, 'to_account_id' => $bank->id, 'amount' => 8])->assertStatus(409);
        $this->put('/money-transfers/1', ['id' => 1, 'amount' => 99])->assertStatus(409);
        $this->delete('/money-transfers/1')->assertStatus(409);
        $this->assertEquals(5, DB::table('money_transfers')->where('id', 1)->value('amount'));
        app(\App\Services\Accounting\VoucherService::class)->post(['voucher_type' => 'contra', 'entry_date' => '2026-10-03',
            'description' => 'Cash deposit into IOB', 'idempotency_key' => 'linked-contra', 'reference_mode' => 'on_account',
            'items' => [['chart_of_account_id' => $bank->chart_of_account_id, 'debit' => 10],
                ['chart_of_account_id' => \App\Models\Account::findOrFail(1)->chart_of_account_id, 'credit' => 10]]], $this->context());
        $this->get('/accounts')->assertOk()->assertViewHas('lims_account_all', fn ($accounts) => (float) $accounts->firstWhere('id', $bank->id)->balance === 10.0);
    }

    private function lot($product, string $number, float $qty, string $expiry = '2027-01-01'): int
    {
        $movement = app(InventoryMovementService::class)->receive(new StockMovementCommand(
            date: '2026-10-03', lines: [new StockLine($product->id, $qty, unitCost: 5,
                batch: ['batch_no' => $number, 'expired_date' => $expiry])], warehouseId: 1, context: $this->context(), userId: 1));
        return $movement->lines->first()->batch_id;
    }

    public function test_combined_sale_skips_empty_lot_splits_stock_and_reversal_restores_each_lot(): void
    {
        $product = $this->material(0, 5, ['is_batch' => true, 'negative_stock_policy' => 'block']);
        $empty = $this->lot($product, 'EMPTY', 1);
        app(InventoryMovementService::class)->issue(new StockMovementCommand(date: '2026-10-03',
            lines: [new StockLine($product->id, 1, batchId: $empty)], warehouseId: 1, context: $this->context(), userId: 1));
        $first = $this->lot($product, 'FIRST', 3);
        $second = $this->lot($product, 'SECOND', 4, '2027-02-01');
        $sale = app(CommercialApplicationService::class)->create('sale', $this->saleData($product, [
            'items' => [['product_id' => $product->id, 'qty' => 5, 'net_unit_price' => 10]]]), 'combined', 1, $this->context());
        $this->assertCount(1, $sale->productSales);
        $lines = DB::table('stock_movement_lines')->join('stock_movements', 'stock_movements.id', '=', 'stock_movement_lines.stock_movement_id')
            ->where('source_type', 'sale')->where('source_id', $sale->id)->pluck('qty_base', 'batch_id')->all();
        $this->assertEquals([$first => -3, $second => -2], $lines);
        $this->assertEquals(2, $product->fresh()->qty);
        $combined = app(IndustryStockQueries::class)->stockByLot(['from' => '2026-10-01', 'to' => '2026-10-31', 'group_by' => 'product'], $this->context(), 1)->get();
        $this->assertCount(1, $combined);
        $this->assertEquals(2, $combined->first()->current_qty);
        app(CommercialReversalService::class)->reverse($sale, '2026-10-03', 'Cancel test sale', $this->context());
        $this->assertEquals(7, $product->fresh()->qty);
        $this->assertEquals(3, DB::table('product_batches')->where('id', $first)->value('qty'));
        $this->assertEquals(4, DB::table('product_batches')->where('id', $second)->value('qty'));
    }

    public function test_combined_shortage_rolls_back_every_lot_and_does_not_sell_expired_stock(): void
    {
        $product = $this->material(0, 5, ['is_batch' => true, 'negative_stock_policy' => 'block']);
        $valid = $this->lot($product, 'VALID', 3);
        $this->lot($product, 'EXPIRED', 10, '2026-10-02');
        $before = DB::table('stock_movements')->count();
        try {
            app(CommercialApplicationService::class)->create('sale', $this->saleData($product, [
                'items' => [['product_id' => $product->id, 'qty' => 2, 'net_unit_price' => 10],
                    ['product_id' => $product->id, 'qty' => 2, 'net_unit_price' => 10]]]), 'shortage', 1, $this->context());
            $this->fail('Expected combined shortage.');
        } catch (StockPolicyException $error) {
            $this->assertStringContainsString('Insufficient combined lot stock', $error->getMessage());
        }
        $this->assertSame($before, DB::table('stock_movements')->count());
        $this->assertSame(0, Sale::count());
        $this->assertEquals(3, DB::table('product_batches')->where('id', $valid)->value('qty'));
    }

    public function test_changed_product_uses_new_existing_stock_and_records_both_names_with_bill(): void
    {
        $old = $this->material(10)->forceFill(['name' => 'Original fabric']); $old->save();
        $new = $this->material(8)->forceFill(['name' => 'Finished fabric']); $new->save();
        $service = app(CommercialApplicationService::class);
        $sale = $service->create('sale', $this->saleData($old), 'original', 1, $this->context());
        $replacement = app(CommercialReversalService::class)->replace($sale, $this->saleData($new), 'changed-product',
            '2026-10-03', 'Select finished fabric', $this->context());
        $this->assertEquals(10, $old->fresh()->qty);
        $this->assertEquals(6, $new->fresh()->qty);
        $this->assertEquals($sale->id, $replacement->replaces_id);
        $this->assertEquals($new->id, $replacement->productSales->first()->product_id);
        $history = $replacement->attributes_json['item_change_history'];
        $this->assertSame('Original fabric', $history['before'][0]['name']);
        $this->assertSame('Finished fabric', $history['after'][0]['name']);
        $description = JournalEntry::where('reference_type', 'sale')->where('reference_id', $replacement->id)->value('description');
        $this->assertStringContainsString('Original fabric', $description);
        $this->assertStringContainsString('Finished fabric', $description);
        $this->assertStringContainsString($sale->reference_no, $description);
    }

    public function test_payment_workspace_posts_receipt_once_updates_due_and_separates_internal_voucher(): void
    {
        $sale = app(CommercialApplicationService::class)->create('sale', $this->saleData($this->material()), 'bill', 1, $this->context());
        $this->get('/selling/payments?bill='.$sale->id)->assertOk()->assertSee('Sales Payments &amp; Receipts', false)->assertSee($sale->reference_no);
        $data = ['bill_id' => $sale->id, 'amount' => 7, 'payment_at' => '2026-10-03', 'paying_method' => 'Cash', 'account_id' => 1,
            'idempotency_key' => (string) Str::uuid()];
        $id = $this->postJson('/selling/payments', $data)->assertCreated()->json('data.id');
        $this->postJson('/selling/payments', $data)->assertCreated()->assertJsonPath('data.id', $id);
        $this->postJson('/selling/payments', $data + ['discount' => 1, 'discount_reason' => 'Changed retry'])->assertUnprocessable();
        $this->assertSame(1, Payment::count());
        $this->assertEquals(7, $sale->fresh()->paid_amount);
        if (getenv('ERP_EXPORT_BILL_PAYMENT_BROWSER') === '1') {
            foreach (['HDFC Current', 'SBI Current'] as $name) {
                app(\App\Services\Accounting\SettlementAccountService::class)->create(['name' => $name, 'kind' => 'bank'], $this->context(), 1);
            }
            $this->post('/accounting/chart-of-accounts', ['code' => 'IOB-AUTO', 'name' => 'IOB', 'type' => 'asset', 'sub_type' => 'bank'])->assertRedirect();
            foreach (['payments' => '/selling/payments?bill='.$sale->id, 'receipt' => '/bill-payments/'.$id,
                'voucher' => '/bill-payments/'.$id.'?voucher=1', 'coa' => '/accounting/chart-of-accounts', 'accounts' => '/accounts'] as $name => $url) {
                file_put_contents(base_path('scratch/bill-payment-'.$name.'.html'), $this->get($url)->assertOk()->getContent());
            }
        }
        $entry = JournalEntry::where('reference_type', 'payment')->sole();
        $this->assertSame('receipt', $entry->voucher_type);
        $this->assertEquals($entry->total_debit, $entry->total_credit);
        $this->get('/bill-payments/'.$id)->assertOk()->assertSee($sale->reference_no)->assertDontSee('Accounting voucher ·');
        $this->get('/bill-payments/'.$id.'?voucher=1')->assertOk()->assertSee('Receipt Voucher')->assertSee($entry->entry_number);
        $this->get('/accounting/vouchers/'.$entry->id)->assertOk()->assertSee($entry->entry_number);
        $this->postJson('/selling/payments', array_replace($data, ['amount' => 14, 'idempotency_key' => (string) Str::uuid()]))->assertUnprocessable();
        $this->assertEquals(7, $sale->fresh()->paid_amount);
    }

    public function test_paid_supplier_bill_creates_purchase_voucher_and_separate_payment_voucher(): void
    {
        $bill = app(CommercialApplicationService::class)->create('purchase', $this->purchaseData($this->material(0), ['paid_amount' => 4]), 'supplier-bill', 1, $this->context());
        $this->assertEquals(4, $bill->paid_amount);
        $this->assertSame('purchase', JournalEntry::where('reference_type', 'purchase')->value('voucher_type'));
        $this->assertSame('payment', JournalEntry::where('reference_type', 'payment')->value('voucher_type'));
        $this->get('/buying/payments?bill='.$bill->id)->assertOk()->assertSee('Supplier Payments &amp; Vouchers', false);
        $this->get('/bill-payments/'.Payment::sole()->id)->assertOk()->assertSee('Payment Voucher')->assertSee($bill->reference_no);
    }
}
