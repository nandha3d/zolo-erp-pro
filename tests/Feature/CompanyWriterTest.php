<?php

namespace Tests\Feature;

use App\Models\Accounting\ChartOfAccount;
use App\Models\User;
use App\Services\Accounting\AccountingService;
use App\Services\ERP\SaleService;
use App\Services\Platform\CompanyContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\CompanyContextTestCase;

class CompanyWriterTest extends CompanyContextTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCompanyReaderFixtures();
        DB::table('billers')->where('id', 1)->update(['company_id' => $this->company->id]);
        DB::table('billers')->insert(['id' => 2, 'name' => 'Secret B', 'company_id' => $this->other->id]);
        Schema::create('accounts', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->unsignedBigInteger('company_id')->nullable();
        });
        DB::table('accounts')->insert([
            ['id' => 1, 'name' => 'Cash A', 'company_id' => $this->company->id],
            ['id' => 2, 'name' => 'Cash B', 'company_id' => $this->other->id],
        ]);
        DB::table('warehouses')->insert(['id' => 4, 'name' => 'Destination A',
            'company_id' => $this->company->id, 'branch_id' => $this->branch->id]);
        foreach ([$this->company->id, $this->other->id] as $owner) {
            foreach ([
                ['1010', 'asset', 'cash'], ['1020', 'asset', 'bank'],
                ['1100', 'asset', 'accounts_receivable'], ['1200', 'asset', 'inventory'],
                ['2010', 'liability', 'accounts_payable'], ['4010', 'revenue', 'sales_revenue'],
                ['5010', 'expense', 'cogs'], ['GIT', 'asset', 'goods_in_transit'],
            ] as [$code, $type, $role]) {
                (new ChartOfAccount)->forceFill(['company_id' => $owner,
                    'code' => $owner === $this->company->id ? $code : 'B-'.$code,
                    'name' => $role, 'type' => $type, 'sub_type' => $role])->save();
            }
        }
    }

    public function test_explicit_account_lookup_cannot_use_a_forged_company_context(): void
    {
        $this->actingAs(User::findOrFail(1));
        $this->expectException(AuthorizationException::class);
        app(AccountingService::class)->getAccount('1010',
            new CompanyContext($this->other->id, $this->otherBranch->id, $this->otherYear->id));
    }

    private function payload(string $type): array
    {
        $line = ['product_id' => 1, 'qty' => 2];
        return match ($type) {
            'sales' => ['customer_id' => 1, 'warehouse_id' => 1, 'paid_amount' => 5,
                'items' => [$line + ['net_unit_price' => 10]]],
            'purchases' => ['supplier_id' => 1, 'warehouse_id' => 1, 'paid_amount' => 5,
                'items' => [$line + ['net_unit_cost' => 5]]],
            'inventory/transfer' => ['from_warehouse_id' => 1, 'to_warehouse_id' => 4,
                'items' => [$line + ['net_unit_cost' => 5]]],
        };
    }

    private function snapshot(): array
    {
        $result = [];
        foreach (['sales', 'product_sales', 'purchases', 'product_purchases', 'payments',
            'transfers', 'product_transfer', 'products', 'product_warehouse', 'chart_of_accounts',
            'journal_entries', 'journal_items', 'document_series', 'document_number_reservations'] as $tableName) {
            $result[$tableName] = DB::table($tableName)->orderBy('id')->get()->toJson();
        }
        return $result;
    }

    public function test_sale_and_partial_purchase_stamp_owned_effects_and_preserve_foreign_company(): void
    {
        $foreignProduct = (array) DB::table('products')->where('id', 2)->first();
        $foreignAccounts = DB::table('chart_of_accounts')->where('company_id', $this->other->id)->get()->toJson();
        $sale = $this->postJson('/api/v1/sales', $this->payload('sales') + [
            'company_id' => $this->other->id, 'branch_id' => $this->otherBranch->id,
            'financial_year_id' => $this->otherYear->id, 'user_id' => 2,
        ])->assertCreated()->assertJsonPath('data.company_id', $this->company->id)->assertJsonPath('data.user_id', 1);
        $this->getJson('/api/v1/sales/'.$sale->json('data.id'))->assertOk()->assertJsonCount(1, 'data.payments');
        $purchase = $this->payload('purchases');
        $purchase['status'] = 2;
        $purchase['items'][0]['qty'] = 10;
        $purchase['items'][0]['received_qty'] = 4;
        $this->postJson('/api/v1/purchases', $purchase)->assertCreated()->assertJsonPath('data.company_id', $this->company->id)
            ->assertJsonPath('data.product_purchases.0.recieved', 4);
        foreach (['sales', 'product_sales', 'purchases', 'product_purchases', 'payments', 'journal_entries', 'journal_items'] as $tableName) {
            $this->assertSame(0, DB::table($tableName)->whereNull('company_id')->count(), $tableName);
            $this->assertSame(0, DB::table($tableName)->where('company_id', '<>', $this->company->id)->count(), $tableName);
        }
        $this->assertSame($foreignProduct, (array) DB::table('products')->where('id', 2)->first());
        $this->assertSame($foreignAccounts, DB::table('chart_of_accounts')->where('company_id', $this->other->id)->get()->toJson());
        $this->assertEquals(6, DB::table('product_warehouse')->where('warehouse_id', 1)->value('qty'));
        $this->assertEquals(30, ChartOfAccount::where('company_id', $this->company->id)->where('sub_type', 'goods_in_transit')->value('current_balance'));
        $this->assertSame(4, DB::table('document_number_reservations')->where('status', 'assigned')->where('document_type', '<>', 'journal')->count());
    }

    public function test_foreign_writer_parents_and_units_are_rejected_before_any_effect(): void
    {
        foreach ([
            ['sales', 'customer_id'], ['sales', 'warehouse_id'], ['sales', 'biller_id'], ['sales', 'account_id'],
            ['purchases', 'supplier_id'], ['purchases', 'warehouse_id'], ['purchases', 'account_id'],
            ['inventory/transfer', 'from_warehouse_id'], ['inventory/transfer', 'to_warehouse_id'],
        ] as [$path, $field]) {
            $before = $this->snapshot();
            $this->postJson('/api/v1/'.$path, array_replace($this->payload($path), [$field => 2]))->assertUnprocessable();
            $this->assertSame($before, $this->snapshot());
        }
        foreach (['sales', 'purchases', 'inventory/transfer'] as $path) {
            foreach (['product_id', $path === 'sales' ? 'sale_unit_id' : 'purchase_unit_id'] as $field) {
                $data = $this->payload($path);
                $data['items'][0][$field] = 2;
                $before = $this->snapshot();
                $this->postJson('/api/v1/'.$path, $data)->assertUnprocessable();
                $this->assertSame($before, $this->snapshot());
            }
            $this->postJson('/api/v1/'.$path, $this->payload($path), ['X-Company-ID' => $this->other->id])->assertForbidden();
        }
    }

    public function test_posting_dates_block_closed_locked_out_of_year_and_invalid_documents_atomically(): void
    {
        foreach (['sales', 'purchases', 'inventory/transfer'] as $path) {
            foreach (['2025-12-31', '2026-02-30', '0000-01-01', '2026-10-03 10:00:00'] as $date) {
                $before = $this->snapshot();
                $this->postJson('/api/v1/'.$path, $this->payload($path) + ['business_date' => $date])->assertUnprocessable();
                $this->assertSame($before, $this->snapshot());
            }
        }
        $this->year->update(['lock_date' => '2026-10-03']);
        foreach (['sales', 'purchases', 'inventory/transfer'] as $path) {
            $before = $this->snapshot();
            $this->postJson('/api/v1/'.$path, $this->payload($path) + ['business_date' => '2026-10-03'])->assertUnprocessable();
            $this->assertSame($before, $this->snapshot());
        }
        $this->year->update(['status' => 'closed', 'is_closed' => true, 'lock_date' => null]);
        foreach (['sales', 'purchases', 'inventory/transfer'] as $path) {
            $before = $this->snapshot();
            $this->postJson('/api/v1/'.$path, $this->payload($path))->assertUnprocessable();
            $this->assertSame($before, $this->snapshot());
        }
    }

    public function test_sale_payment_uses_its_own_date_locks_header_and_rejects_foreign_or_excess_settlement(): void
    {
        $saleId = $this->postJson('/api/v1/sales', $this->payload('sales'))->assertCreated()->json('data.id');
        $before = $this->snapshot();
        $this->postJson('/api/v1/sales/'.$saleId.'/payments', ['amount' => 5, 'paying_method' => 'Cash', 'account_id' => 2])
            ->assertUnprocessable();
        $this->assertSame($before, $this->snapshot());
        $this->postJson('/api/v1/sales/'.$saleId.'/payments', ['amount' => 20, 'paying_method' => 'Cash'])->assertStatus(400);
        $this->assertSame($before, $this->snapshot());
        $this->postJson('/api/v1/sales/'.$saleId.'/payments', [
            'amount' => 5, 'paying_method' => 'Bank', 'business_date' => '2026-10-04', 'company_id' => $this->other->id,
        ])->assertCreated()->assertJsonPath('data.company_id', $this->company->id);
        $this->assertSame('2026-10-04', \App\Models\Accounting\JournalEntry::where('reference_type', 'payment')->sole()->entry_date->toDateString());
        $this->assertEquals(10, DB::table('sales')->where('id', $saleId)->value('paid_amount'));
        $this->year->update(['lock_date' => '2026-10-04']);
        $before = $this->snapshot();
        $this->postJson('/api/v1/sales/'.$saleId.'/payments', ['amount' => 5, 'paying_method' => 'Cash', 'business_date' => '2026-10-04'])
            ->assertUnprocessable();
        $this->assertSame($before, $this->snapshot());
    }

    public function test_transfer_requires_both_branch_grants_and_preserves_company_stock_total(): void
    {
        $data = $this->payload('inventory/transfer');
        $data['to_warehouse_id'] = 3;
        $before = $this->snapshot();
        $this->postJson('/api/v1/inventory/transfer', $data)->assertForbidden();
        $this->assertSame($before, $this->snapshot());
        $northId = DB::table('warehouses')->where('id', 3)->value('branch_id');
        DB::table('company_user_branches')->insert(['company_id' => $this->company->id, 'user_id' => 1, 'branch_id' => $northId]);
        $this->postJson('/api/v1/inventory/transfer', $data, ['X-Branch-ID' => $this->branch->id])->assertCreated()
            ->assertJsonPath('data.company_id', $this->company->id)->assertJsonPath('data.product_transfers.0.company_id', $this->company->id);
        $this->assertEquals(2, DB::table('product_warehouse')->where('warehouse_id', 1)->value('qty'));
        $this->assertEquals(97, DB::table('product_warehouse')->where('warehouse_id', 3)->value('qty'));
        $this->assertEquals(99, DB::table('products')->where('id', 1)->value('qty'));
        $this->assertEquals(8, DB::table('products')->where('id', 2)->value('qty'));
        $this->assertSame(0, DB::table('journal_entries')->count());
    }

    public function test_stock_corruption_shortage_and_missing_accounts_roll_back_numbers_and_document(): void
    {
        DB::table('product_warehouse')->where('warehouse_id', 1)->update(['company_id' => $this->other->id]);
        $before = $this->snapshot();
        // The inventory ledger rejects foreign projection rows as a stock policy failure.
        $this->postJson('/api/v1/sales', $this->payload('sales'))->assertStatus(400)
            ->assertJsonFragment(['message' => 'Stock ownership or identity of product 1 in warehouse 1 requires reconciliation.']);
        $this->assertSame($before, $this->snapshot());
        DB::table('product_warehouse')->where('warehouse_id', 1)->update(['company_id' => $this->company->id]);
        $data = $this->payload('sales');
        $data['items'][0]['qty'] = 5;
        $before = $this->snapshot();
        $this->postJson('/api/v1/sales', $data)->assertStatus(400);
        $this->assertSame($before, $this->snapshot());
        ChartOfAccount::where('company_id', $this->company->id)->where('sub_type', 'sales_revenue')->delete();
        $before = $this->snapshot();
        $this->postJson('/api/v1/sales', $this->payload('sales'))->assertStatus(400);
        $this->assertSame($before, $this->snapshot());
    }

    public function test_authorized_company_switch_writes_only_its_owned_roles_and_ignores_another_company_close(): void
    {
        $this->other->users()->attach(1);
        DB::table('company_user_branches')->insert(['company_id' => $this->other->id, 'user_id' => 1, 'branch_id' => $this->otherBranch->id]);
        $this->year->update(['status' => 'closed', 'is_closed' => true]);
        $this->postJson('/api/v1/sales', $this->payload('sales'))->assertUnprocessable();
        $before = $this->snapshot();
        $this->postJson('/api/v1/sales', $this->payload('sales'), ['X-Company-ID' => $this->other->id])->assertUnprocessable();
        $this->assertSame($before, $this->snapshot());
        $data = $this->payload('sales');
        $data['customer_id'] = 2;
        $data['warehouse_id'] = 2;
        $data['items'][0]['product_id'] = 2;
        $response = $this->postJson('/api/v1/sales', $data, ['X-Company-ID' => $this->other->id])
            ->assertCreated()->assertJsonPath('data.company_id', $this->other->id);
        $this->assertSame(0, DB::table('journal_entries')->where('company_id', $this->company->id)->count());
        $this->assertSame(0, DB::table('journal_items')->where('company_id', $this->company->id)->count());
        $this->assertEquals(0, ChartOfAccount::where('company_id', $this->company->id)->sum('current_balance'));
        $this->assertEquals(6, DB::table('products')->where('id', 2)->value('qty'));
        $this->getJson('/api/v1/sales/'.$response->json('data.id'), ['X-Company-ID' => $this->other->id])->assertOk();
    }

    public function test_default_posting_date_uses_company_timezone_and_conflicting_date_fields_are_rejected(): void
    {
        $before = $this->snapshot();
        $this->postJson('/api/v1/sales', $this->payload('sales') + [
            'business_date' => '2026-10-03', 'created_at' => '2026-10-04',
        ])->assertUnprocessable();
        $this->assertSame($before, $this->snapshot());
        \Carbon\CarbonImmutable::setTestNow('2026-12-31 20:00:00 UTC');
        $this->postJson('/api/v1/sales', $this->payload('sales'), ['X-Financial-Year-ID' => $this->year->id])->assertUnprocessable();
        $this->assertSame($before, $this->snapshot());
        $this->postJson('/api/v1/sales', $this->payload('sales') + ['business_date' => '2026-12-31'],
            ['X-Financial-Year-ID' => $this->year->id])->assertCreated();
    }

    public function test_accounting_reports_scope_root_accounts_items_headers_and_hierarchy(): void
    {
        $cash = ChartOfAccount::where('company_id', $this->company->id)->where('sub_type', 'cash')->sole();
        $revenue = ChartOfAccount::where('company_id', $this->company->id)->where('sub_type', 'sales_revenue')->sole();
        $entryId = $this->postJson('/api/v1/accounting/journal-entries', [
            'entry_date' => '2026-10-03', 'description' => 'Owned capital', 'company_id' => $this->other->id,
            'created_by' => 2, 'items' => [['chart_of_account_id' => $cash->id, 'debit' => 7],
                ['chart_of_account_id' => $revenue->id, 'credit' => 7]],
        ])->assertCreated()->assertJsonPath('data.company_id', $this->company->id)
            ->assertJsonPath('data.created_by', 1)->json('data.id');
        $foreignEntry = DB::table('journal_entries')->insertGetId([
            'company_id' => $this->other->id, 'entry_number' => 'FOREIGN', 'entry_date' => '2026-10-03',
            'description' => 'Secret B',
        ]);
        DB::table('journal_items')->insert([
            ['company_id' => $this->company->id, 'journal_entry_id' => $foreignEntry, 'chart_of_account_id' => $cash->id, 'debit' => 1000, 'memo' => 'Secret B'],
            ['company_id' => $this->other->id, 'journal_entry_id' => $entryId, 'chart_of_account_id' => $cash->id, 'debit' => 2000, 'memo' => 'Secret B'],
        ]);
        $foreignCash = ChartOfAccount::where('company_id', $this->other->id)->where('sub_type', 'cash')->sole();
        $foreignCash->forceFill(['parent_id' => $cash->id, 'name' => 'Secret B'])->save();
        $this->getJson('/api/v1/accounting/chart-of-accounts')->assertOk()->assertDontSee('Secret B');
        $this->getJson('/api/v1/accounting/trial-balance')->assertOk()->assertJsonPath('data.total_debit', 7)->assertJsonPath('data.total_credit', 7);
        $this->getJson('/api/v1/accounting/profit-and-loss')->assertOk()->assertJsonPath('data.net_income', 7);
        $this->getJson('/api/v1/accounting/balance-sheet')->assertOk()->assertJsonPath('data.total_assets', 7)->assertJsonPath('data.total_equity', 7);
        $this->getJson('/api/v1/accounting/general-ledger/'.$cash->id)->assertOk()->assertJsonCount(1, 'data.transactions')
            ->assertJsonPath('data.closing_balance', 7)->assertDontSee('Secret B');
        $this->getJson('/api/v1/accounting/general-ledger/'.$foreignCash->id)->assertNotFound();
        $this->assertEquals(7, $cash->calculateBalance());
        $this->getJson('/api/v1/accounting/trial-balance?start_date=invalid')->assertUnprocessable();
        $this->getJson('/api/v1/accounting/profit-and-loss?start_date=2026-10-03&end_date=2026-01-01')->assertUnprocessable();
    }

    public function test_manual_journal_ownership_partner_and_period_errors_have_no_effect(): void
    {
        $cash = ChartOfAccount::where('company_id', $this->company->id)->where('sub_type', 'cash')->sole();
        $revenue = ChartOfAccount::where('company_id', $this->company->id)->where('sub_type', 'sales_revenue')->sole();
        $foreign = ChartOfAccount::where('company_id', $this->other->id)->where('sub_type', 'cash')->sole();
        $data = ['entry_date' => '2026-10-03', 'description' => 'Owned entry',
            'items' => [['chart_of_account_id' => $cash->id, 'debit' => 10],
                ['chart_of_account_id' => $revenue->id, 'credit' => 10]]];
        $corrupt = $data;
        $corrupt['items'][0]['chart_of_account_id'] = $foreign->id;
        $before = $this->snapshot();
        $this->postJson('/api/v1/accounting/journal-entries', $corrupt)->assertUnprocessable();
        $this->assertSame($before, $this->snapshot());
        $corrupt = $data;
        $corrupt['items'][0] += ['partner_type' => 'customer', 'partner_id' => 2];
        $this->postJson('/api/v1/accounting/journal-entries', $corrupt)->assertUnprocessable();
        $this->assertSame($before, $this->snapshot());
        $this->year->update(['status' => 'closed', 'is_closed' => true]);
        $this->postJson('/api/v1/accounting/journal-entries', $data)->assertUnprocessable();
        $this->assertSame($before, $this->snapshot());
        $this->getJson('/api/v1/accounting/trial-balance', ['X-Financial-Year-ID' => $this->year->id])->assertOk();
        $this->postJson('/api/v1/accounting/journal-entries', $data, ['X-Company-ID' => $this->other->id])->assertForbidden();
    }

    public function test_direct_shared_service_revalidates_explicit_context_instead_of_trusting_it(): void
    {
        $before = $this->snapshot();
        try {
            (new SaleService(new AccountingService))->createSale($this->payload('sales'), 1,
                new CompanyContext($this->other->id, $this->otherBranch->id, $this->otherYear->id));
            $this->fail('A forged context must not grant company access.');
        } catch (AuthorizationException $error) {
            $this->assertSame('Company access denied.', $error->getMessage());
        }
        $this->assertSame($before, $this->snapshot());
        auth()->forgetGuards();
        $this->expectException(AuthorizationException::class);
        (new SaleService(new AccountingService))->createSale($this->payload('sales'));
    }
}
