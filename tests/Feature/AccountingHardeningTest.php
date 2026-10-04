<?php

namespace Tests\Feature;

use App\Models\Accounting\AccountAllocation;
use App\Models\Accounting\AccountOpenItem;
use App\Models\Accounting\ChartOfAccount;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\JournalItem;
use App\Models\Accounting\SemanticAccountMapping;
use App\Services\Accounting\AccountingPostingService;
use App\Services\Accounting\AccountingService;
use App\Services\Accounting\FinancialReportService;
use App\Services\Accounting\LedgerReconciliationService;
use App\Services\Accounting\OpenItemService;
use App\Services\Accounting\PeriodCloseService;
use App\Services\Accounting\VoucherService;
use App\Services\ERP\SaleService;
use App\Services\ERP\PurchaseService;
use App\Services\Platform\CompanyContext;
use App\Support\LedgerAmount;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;
use Symfony\Component\Process\Process;
use Tests\Support\CompanyErpServiceTestCase;

class AccountingHardeningTest extends CompanyErpServiceTestCase
{
    private CompanyContext $context;

    protected function setUp(): void
    {
        parent::setUp();
        $this->context = $this->resolver->resolve(1);
        \Illuminate\Support\Facades\Schema::create('permissions', function (\Illuminate\Database\Schema\Blueprint $table) {
            $table->increments('id');
            $table->string('name');
        });
        \Illuminate\Support\Facades\Schema::create('role_has_permissions', function (\Illuminate\Database\Schema\Blueprint $table) {
            $table->unsignedInteger('role_id');
            $table->unsignedInteger('permission_id');
        });
    }

    private function account(string $role): ChartOfAccount
    {
        return app(\App\Services\Accounting\SemanticAccountResolver::class)->resolve($role, $this->context);
    }

    private function sale(float $amount = 10, float $paid = 0): \App\Models\Sale
    {
        $product = $this->stock();
        return app(SaleService::class)->createSale([
            'customer_id' => 1, 'warehouse_id' => 1, 'business_date' => '2026-10-03', 'paid_amount' => $paid,
            'items' => [['product_id' => $product->id, 'qty' => 1, 'net_unit_price' => $amount]],
        ], 1, $this->context);
    }

    private function receipt(float|string $amount = 3, string $key = 'receipt-1', array $extra = []): JournalEntry
    {
        return app(VoucherService::class)->post(array_replace([
            'voucher_type' => 'receipt', 'entry_date' => '2026-10-04', 'description' => 'Customer receipt',
            'idempotency_key' => $key, 'reference_mode' => 'on_account',
            'items' => [
                ['chart_of_account_id' => $this->account('cash')->id, 'debit' => $amount],
                ['chart_of_account_id' => $this->account('ar')->id, 'credit' => $amount, 'partner_type' => 'customer', 'partner_id' => 1],
            ],
        ], $extra), $this->context);
    }

    private function snapshot(): array
    {
        return collect(['sales', 'purchases', 'payments', 'journal_entries', 'journal_items', 'account_open_items',
            'account_allocations', 'chart_of_accounts', 'stock_movements', 'product_warehouse', 'document_number_reservations', 'document_series'])
            ->mapWithKeys(fn ($table) => [$table => DB::table($table)->orderBy('id')->get()->toJson()])->all();
    }

    public function test_manual_api_key_replay_normalizes_account_ids_and_posts_only_once(): void
    {
        $data = ['entry_date' => '2026-10-04', 'description' => 'Manual key replay', 'items' => [
            ['chart_of_account_id' => $this->account('cash')->id, 'debit' => '0.0001'],
            ['chart_of_account_id' => $this->account('sales')->id, 'credit' => '0.0001']]];
        $this->postJson('/api/v1/accounting/journal-entries', $data, ['Idempotency-Key' => 'manual-replay'])->assertCreated();
        $before = $this->snapshot();
        foreach ($data['items'] as &$line) {
            $line['chart_of_account_id'] = (string) $line['chart_of_account_id'];
        }
        unset($line);
        $this->postJson('/api/v1/accounting/journal-entries', $data, ['Idempotency-Key' => 'manual-replay'])->assertCreated();
        $this->assertSame($before, $this->snapshot());
    }

    public function test_source_replay_is_idempotent_and_does_not_reserve_another_number_or_open_item(): void
    {
        $sale = $this->sale();
        $entry = JournalEntry::where('reference_type', 'sale')->sole();
        $before = $this->snapshot();
        $replay = app(AccountingService::class)->postSaleJournal($sale, 5, $this->context, 1);
        $this->assertSame($entry->id, $replay->id);
        $this->assertSame($before, $this->snapshot());
        $this->assertSame('sale:'.$sale->id.':v1', $entry->posting_key);
        $this->assertSame($this->branch->id, (int) $entry->branch_id);
        $this->assertSame($this->year->id, (int) $entry->financial_year_id);
        $this->assertNotNull($entry->document_series_id);
        $this->assertNotNull($entry->posted_at);
    }

    public function test_conflicting_posting_key_rolls_back_all_effects(): void
    {
        $entry = $this->receipt();
        $before = $this->snapshot();
        try {
            $this->receipt(4);
            $this->fail('Conflicting key must fail.');
        } catch (InvalidArgumentException $error) {
            $this->assertStringContainsString('different journal effects', $error->getMessage());
        }
        $this->assertSame($before, $this->snapshot());
        $this->assertSame($entry->id, $this->receipt()->id);
    }

    public function test_posted_header_lines_and_append_are_immutable(): void
    {
        $entry = $this->receipt();
        foreach ([fn () => $entry->update(['description' => 'changed']), fn () => $entry->delete(),
            fn () => $entry->items->first()->update(['debit' => 2]), fn () => $entry->items->first()->delete(),
            fn () => $entry->items()->create(['chart_of_account_id' => $this->account('cash')->id, 'debit' => 1])] as $change) {
            try {
                $change();
                $this->fail('Posted journal mutation must fail.');
            } catch (LogicException $error) {
                $this->assertStringContainsString('Posted journal', $error->getMessage());
            }
        }
        $this->assertSame('Customer receipt', $entry->fresh()->description);
        $this->assertSame(2, JournalItem::count());
    }

    public function test_mapping_owns_account_choice_even_when_original_code_still_exists(): void
    {
        $original = $this->account('sales');
        $custom = (new ChartOfAccount)->forceFill(['company_id' => $this->company->id, 'code' => 'CUSTOM',
            'name' => 'Mapped sales', 'type' => 'revenue', 'sub_type' => 'sales_revenue'])->save();
        $custom = ChartOfAccount::where('code', 'CUSTOM')->sole();
        SemanticAccountMapping::where('company_id', $this->company->id)->where('semantic_role', 'sales')->update(['account_id' => $custom->id]);
        $this->sale();
        $this->assertSame('10.0000', $custom->fresh()->current_balance);
        $this->assertSame('0.0000', $original->fresh()->current_balance);
    }

    public function test_missing_or_foreign_mapping_rolls_back_document_stock_and_accounting(): void
    {
        SemanticAccountMapping::where('company_id', $this->company->id)->where('semantic_role', 'sales')->update(['account_id' => null]);
        $product = $this->stock();
        $before = $this->snapshot();
        try {
            app(SaleService::class)->createSale(['customer_id' => 1, 'warehouse_id' => 1,
                'items' => [['product_id' => $product->id, 'qty' => 1, 'net_unit_price' => 10]]], 1, $this->context);
            $this->fail('Missing mapping must fail.');
        } catch (InvalidArgumentException) {
            $this->assertSame($before, $this->snapshot());
        }
    }

    public function test_partial_payment_allocates_exact_remaining_amount_and_replay_has_no_effect(): void
    {
        $sale = $this->sale(0.3);
        $payment = app(SaleService::class)->addPayment($sale, ['amount' => '0.1000', 'business_date' => '2026-10-04',
            'idempotency_key' => 'fractional-payment'], 1, $this->context);
        $invoice = AccountOpenItem::where('source_type', 'sale')->sole();
        $this->assertSame('0.2000', $invoice->open_amount);
        $this->assertSame('0.0000', AccountOpenItem::where('source_type', 'payment')->sole()->open_amount);
        $before = $this->snapshot();
        $again = app(SaleService::class)->addPayment($sale, ['amount' => '0.1000', 'business_date' => '2026-10-04',
            'idempotency_key' => 'fractional-payment'], 1, $this->context);
        $this->assertSame($payment->id, $again->id);
        $this->assertSame($before, $this->snapshot());
        app(SaleService::class)->addPayment($sale, ['amount' => '0.2000', 'business_date' => '2026-10-04'], 1, $this->context);
        $this->assertSame('0.0000', $invoice->fresh()->open_amount);
        $this->assertTrue(app(LedgerReconciliationService::class)->reconcile($this->context)['is_reconciled']);
    }

    public function test_supplier_payment_allocates_the_payable_without_stock_changes(): void
    {
        $product = $this->stock();
        $purchase = app(PurchaseService::class)->createPurchase(['supplier_id' => 1, 'warehouse_id' => 1,
            'business_date' => '2026-10-03', 'items' => [['product_id' => $product->id, 'qty' => 1, 'net_unit_cost' => 10]]], 1, $this->context);
        $stock = DB::table('product_warehouse')->get()->toJson();
        app(PurchaseService::class)->addPayment($purchase, ['amount' => 4, 'business_date' => '2026-10-04'], 1, $this->context);
        $this->assertSame('6.0000', AccountOpenItem::where('source_type', 'purchase')->sole()->open_amount);
        $this->assertSame($stock, DB::table('product_warehouse')->get()->toJson());
        $this->assertTrue(app(LedgerReconciliationService::class)->reconcile($this->context)['is_reconciled']);
    }

    public function test_against_reference_voucher_uses_same_open_items_and_updates_invoice_paid_total(): void
    {
        $sale = $this->sale();
        $invoice = AccountOpenItem::where('source_type', 'sale')->sole();
        $entry = $this->receipt(3, 'allocated-receipt', ['reference_mode' => 'against_reference',
            'cheque_no' => 'CH-17', 'cheque_date' => '2026-10-04',
            'allocations' => [['open_item_id' => $invoice->id, 'line_index' => 1, 'amount' => 3]]]);
        $this->assertSame('7.0000', $invoice->fresh()->open_amount);
        $this->assertEquals(3, $sale->fresh()->paid_amount);
        $this->assertSame('CH-17', $entry->cheque_no);
        $this->assertSame(1, AccountAllocation::count());
        $this->assertTrue(app(LedgerReconciliationService::class)->reconcile($this->context)['is_reconciled']);
        $before = $this->snapshot();
        try {
            $this->receipt(8, 'excess-voucher', ['reference_mode' => 'against_reference',
                'allocations' => [['open_item_id' => $invoice->id, 'line_index' => 1, 'amount' => 8]]]);
            $this->fail('Over-allocation must roll back voucher.');
        } catch (InvalidArgumentException) {
            $this->assertSame($before, $this->snapshot());
        }
    }

    public function test_advance_and_on_account_can_be_allocated_later_without_new_journals(): void
    {
        $advance = $this->receipt(4, 'advance', ['reference_mode' => 'advance']);
        $sale = $this->sale();
        $invoice = AccountOpenItem::where('source_type', 'sale')->sole();
        $credit = AccountOpenItem::where('journal_entry_id', $advance->id)->sole();
        $allocation = app(OpenItemService::class)->allocate($invoice->id, $credit->id, '4.0000', '2026-10-04', 'against-advance', $this->context);
        $this->assertSame('6.0000', $invoice->fresh()->open_amount);
        $this->assertSame('0.0000', $credit->fresh()->open_amount);
        $this->assertSame(2, JournalEntry::count());
        $this->assertSame($allocation->id, app(OpenItemService::class)->allocate($invoice->id, $credit->id, '4.0000', '2026-10-04', 'against-advance', $this->context)->id);
    }

    public function test_payment_reversal_reopens_invoice_and_preserves_original_journal_and_allocation(): void
    {
        $sale = $this->sale();
        $payment = app(SaleService::class)->addPayment($sale, ['amount' => 4, 'business_date' => '2026-10-04'], 1, $this->context);
        $entry = JournalEntry::where('reference_type', 'payment')->where('reference_id', $payment->id)->sole();
        $original = $entry->toArray();
        $allocation = AccountAllocation::sole()->toArray();
        $reversal = app(AccountingPostingService::class)->reverse($entry->id, '2026-10-05', 'Payment entered twice', $this->context);
        $this->assertSame($original, $entry->fresh()->toArray());
        $this->assertSame($allocation, AccountAllocation::findOrFail($allocation['id'])->toArray());
        $this->assertSame('10.0000', AccountOpenItem::where('source_type', 'sale')->sole()->open_amount);
        $this->assertEquals(0, $sale->fresh()->paid_amount);
        $this->assertSame($entry->id, (int) $reversal->reversal_of_id);
        $this->assertTrue($reversal->isBalanced());
        $this->assertSame(3, AccountAllocation::count());
        $this->assertTrue(app(LedgerReconciliationService::class)->reconcile($this->context)['is_reconciled']);
        $this->assertSame($reversal->id, app(AccountingPostingService::class)->reverse($entry->id, '2026-10-05', 'Payment entered twice', $this->context)->id);
    }

    public function test_allocation_reversal_reopens_both_sides_without_moving_cash(): void
    {
        $this->sale();
        $entry = $this->receipt(3);
        $invoice = AccountOpenItem::where('source_type', 'sale')->sole();
        $credit = AccountOpenItem::where('journal_entry_id', $entry->id)->sole();
        $allocation = app(OpenItemService::class)->allocate($invoice->id, $credit->id, 3, '2026-10-04', 'allocate', $this->context);
        $cash = $this->account('cash')->current_balance;
        app(OpenItemService::class)->reverseAllocation($allocation->id, '2026-10-05', 'Wrong bill', $this->context);
        $this->assertSame('10.0000', $invoice->fresh()->open_amount);
        $this->assertSame('-3.0000', $credit->fresh()->open_amount);
        $this->assertSame($cash, $this->account('cash')->current_balance);
        $this->assertTrue(app(LedgerReconciliationService::class)->reconcile($this->context)['is_reconciled']);
    }

    public function test_ageing_reconstructs_partial_allocations_and_reversal_at_historical_dates(): void
    {
        $this->sale();
        $entry = $this->receipt();
        $invoice = AccountOpenItem::where('source_type', 'sale')->sole();
        $credit = AccountOpenItem::where('journal_entry_id', $entry->id)->sole();
        $allocation = app(OpenItemService::class)->allocate($invoice->id, $credit->id, 3, '2026-10-04', 'ageing', $this->context);
        app(OpenItemService::class)->reverseAllocation($allocation->id, '2026-10-05', 'Wrong bill', $this->context);
        $reports = app(FinancialReportService::class);
        $this->assertSame('10.0000', $reports->ageing($this->context, 'customer', '2026-10-03')['totals']['0-30']);
        $this->assertSame('7.0000', $reports->ageing($this->context, 'customer', '2026-10-04')['totals']['0-30']);
        $this->assertSame('10.0000', $reports->ageing($this->context, 'customer', '2026-10-05')['totals']['0-30']);
        $this->assertSame('-3.0000', $reports->ageing($this->context, 'customer', '2026-10-05')['totals']['credits']);
        $this->assertSame('10.0000', $reports->ageing($this->context, 'customer', '2026-11-04')['totals']['31-60']);
        $this->assertSame('10.0000', $reports->ageing($this->context, 'customer', '2026-12-04')['totals']['60+']);
    }

    public function test_period_lock_unlock_and_close_are_audited_and_enforced_by_every_posting(): void
    {
        app(PeriodCloseService::class)->change($this->context, 'lock', '2026-10-04', 'Reviewed October opening');
        $this->postJson('/api/v1/accounting/vouchers', ['voucher_type' => 'journal', 'entry_date' => '2026-10-04',
            'description' => 'Locked', 'reference_mode' => 'new_reference', 'idempotency_key' => 'locked',
            'items' => [['chart_of_account_id' => $this->account('cash')->id, 'debit' => 1],
                ['chart_of_account_id' => $this->account('sales')->id, 'credit' => 1]]])->assertUnprocessable();
        $this->assertSame(0, JournalEntry::count());
        app(PeriodCloseService::class)->change($this->context, 'unlock', null, 'Administrator approved reopening');
        $this->receipt();
        app(PeriodCloseService::class)->change($this->context, 'close', null, 'Reconciled year');
        $this->assertSame('closed', $this->year->fresh()->status);
        $this->assertSame(3, DB::table('accounting_period_events')->count());
        $this->assertSame('2026-10-04', DB::table('accounting_period_events')->where('action', 'unlock')->value('previous_lock_date'));
        $this->getJson('/api/v1/accounting/day-book')->assertOk();
    }

    public function test_reconciliation_detects_cache_drift_rebuilds_only_projection_and_reports_legacy_control_gaps(): void
    {
        $this->sale();
        $cash = $this->account('cash');
        $cash->update(['current_balance' => 999]);
        $service = app(LedgerReconciliationService::class);
        $this->assertFalse($service->reconcile($this->context)['is_reconciled']);
        $this->assertTrue($service->reconcile($this->context, true)['is_reconciled']);
        $this->assertSame('0.0000', $cash->fresh()->current_balance);
        $ar = $this->account('ar');
        $ar->update(['opening_balance' => 2]);
        $result = $service->reconcile($this->context, true);
        $row = collect($result['accounts'])->firstWhere('account_id', $ar->id);
        $this->assertSame('2.0000', $row['control_difference']);
        $this->assertFalse($result['is_reconciled']);
    }

    public function test_api_ownership_branch_period_and_permission_spoofing_leave_no_effect(): void
    {
        $this->sale();
        $invoice = AccountOpenItem::sole();
        DB::table('account_open_items')->where('id', $invoice->id)->update(['branch_id' => $this->otherBranch->id]);
        $this->getJson('/api/v1/accounting/open-items')->assertOk()->assertJsonCount(0, 'data');
        $before = $this->snapshot();
        $this->postJson('/api/v1/accounting/allocations', ['first_item_id' => $invoice->id, 'second_item_id' => 999,
            'amount' => 1, 'allocation_date' => '2026-10-04', 'idempotency_key' => 'foreign'])->assertUnprocessable();
        $this->postJson('/api/v1/accounting/period', ['action' => 'unlock', 'reason' => 'Spoof'], ['X-Company-ID' => $this->other->id])->assertForbidden();
        DB::table('company_user')->where('company_id', $this->company->id)->where('user_id', 1)->update(['role_id_override' => 4]);
        $this->postJson('/api/v1/accounting/period', ['action' => 'unlock', 'reason' => 'Staff'])->assertForbidden();
        $this->postJson('/api/v1/accounting/journal-entries/1/reverse', ['entry_date' => '2026-10-04', 'reason' => 'Staff'])->assertForbidden();
        $this->postJson('/api/v1/accounting/vouchers', [])->assertForbidden();
        $this->assertSame($before, $this->snapshot());
    }

    public function test_close_refuses_corrupt_journal_and_rebuild_does_not_rewrite_history(): void
    {
        $entry = $this->receipt();
        DB::table('journal_entries')->where('id', $entry->id)->update(['total_debit' => 4]);
        $before = $this->snapshot();
        $result = app(LedgerReconciliationService::class)->reconcile($this->context, true);
        $this->assertFalse($result['is_reconciled']);
        $this->assertFalse($result['rebuilt']);
        $this->assertNotEmpty($result['integrity_issues']);
        $this->postJson('/api/v1/accounting/period', ['action' => 'close', 'reason' => 'Corrupt history'])->assertUnprocessable();
        $this->assertSame($before, $this->snapshot());
        $this->assertSame(0, DB::table('accounting_period_events')->count());
    }

    public function test_reconciliation_detects_compensating_open_item_corruption(): void
    {
        $this->sale();
        $this->receipt();
        DB::table('account_open_items')->where('source_type', 'sale')->update(['open_amount' => 11]);
        DB::table('account_open_items')->where('source_type', 'voucher')->update(['open_amount' => -4]);
        $result = app(LedgerReconciliationService::class)->reconcile($this->context);
        $this->assertSame('0.0000', collect($result['accounts'])->firstWhere('control_type', 'ar')['control_difference']);
        $this->assertFalse($result['is_reconciled']);
        $this->assertCount(2, $result['integrity_issues']);
    }

    public function test_close_refuses_unbalanced_opening_balances_even_when_cached_accounts_match(): void
    {
        $this->account('cash')->update(['opening_balance' => 1, 'current_balance' => 1]);
        $result = app(LedgerReconciliationService::class)->reconcile($this->context);
        $this->assertSame('1.0000', $result['trial_balance_difference']);
        $this->assertFalse($result['is_reconciled']);
        $this->postJson('/api/v1/accounting/period', ['action' => 'close', 'reason' => 'Unbalanced opening'])->assertUnprocessable();
        $this->assertSame('open', $this->year->fresh()->status);
    }

    public function test_contra_transfers_between_distinct_settlement_accounts_and_payment_has_correct_cash_direction(): void
    {
        $bank = (new ChartOfAccount)->forceFill(['company_id' => $this->company->id, 'code' => 'BANK-TEST',
            'name' => 'Bank', 'type' => 'asset', 'sub_type' => 'bank', 'control_type' => 'bank']);
        $bank->save();
        DB::table('semantic_account_mappings')->where('company_id', $this->company->id)->where('semantic_role', 'bank')->update(['account_id' => $bank->id]);
        $data = ['voucher_type' => 'contra', 'entry_date' => '2026-10-04', 'description' => 'Bank deposit',
            'reference_mode' => 'new_reference', 'idempotency_key' => 'contra', 'items' => [
                ['chart_of_account_id' => $this->account('bank')->id, 'debit' => 3],
                ['chart_of_account_id' => $this->account('cash')->id, 'credit' => 3]]];
        $this->postJson('/api/v1/accounting/vouchers', $data)->assertOk();
        $this->assertSame('3.0000', $this->account('bank')->current_balance);
        $this->assertSame('-3.0000', $this->account('cash')->current_balance);
        $before = $this->snapshot();
        $data['idempotency_key'] = 'bad-contra';
        $data['items'][0]['chart_of_account_id'] = $this->account('cash')->id;
        $this->postJson('/api/v1/accounting/vouchers', $data)->assertUnprocessable();
        $data['voucher_type'] = 'payment';
        $data['items'][1]['chart_of_account_id'] = $this->account('sales')->id;
        $this->postJson('/api/v1/accounting/vouchers', $data)->assertUnprocessable();
        $this->assertSame($before, $this->snapshot());
    }

    public function test_manual_control_posting_is_blocked_but_voucher_requires_correct_owned_party(): void
    {
        $this->postJson('/api/v1/accounting/journal-entries', ['entry_date' => '2026-10-04', 'description' => 'Control bypass',
            'items' => [['chart_of_account_id' => $this->account('ar')->id, 'debit' => 1],
                ['chart_of_account_id' => $this->account('sales')->id, 'credit' => 1]]])->assertStatus(400);
        $before = $this->snapshot();
        try {
            $this->receipt(3, 'bad-party', ['items' => [['chart_of_account_id' => $this->account('cash')->id, 'debit' => 3],
                ['chart_of_account_id' => $this->account('ar')->id, 'credit' => 3, 'partner_type' => 'supplier', 'partner_id' => 1]]]);
            $this->fail('Wrong control party must fail.');
        } catch (InvalidArgumentException) {
            $this->assertSame($before, $this->snapshot());
        }
    }

    public function test_reports_respect_financial_year_and_ledger_filter_opening_includes_earlier_activity(): void
    {
        $this->receipt(3, 'earlier');
        $this->receipt(2, 'later', ['entry_date' => '2026-10-05']);
        $ledger = app(AccountingService::class)->getGeneralLedger($this->account('cash')->id, '2026-10-05', '2026-10-05', $this->context);
        $this->assertEquals(3, $ledger['opening_balance']);
        $this->assertEquals(5, $ledger['closing_balance']);
        $monthly = app(FinancialReportService::class)->monthlyLedger($this->account('cash')->id, $this->context);
        $this->assertCount(12, $monthly['months']);
        $this->assertSame('Jan 2026', $monthly['months'][0]['label']);
        $this->assertSame('5.0000', $monthly['months'][9]['debit']);
        $this->getJson('/api/v1/accounting/trial-balance?start_date=2025-01-01')->assertUnprocessable();
        DB::table('journal_entries')->update(['financial_year_id' => $this->otherYear->id]);
        $this->getJson('/api/v1/accounting/day-book')->assertOk()->assertJsonCount(0, 'entries.data');
        $this->assertEquals(0, app(AccountingService::class)->getTrialBalance(null, null, $this->context)['total_debit']);
    }

    public function test_exact_ledger_arithmetic_rejects_extra_precision_and_balances_small_amounts(): void
    {
        $this->assertSame(1001, LedgerAmount::units('0.1001'));
        $entry = $this->receipt('0.0001');
        $this->assertSame('0.0001', $entry->total_credit);
        $this->assertTrue($entry->isBalanced());
        $this->expectException(InvalidArgumentException::class);
        $this->receipt('0.00001', 'invalid');
    }

    public function test_cash_book_reports_cash_lines_instead_of_whole_journal_totals(): void
    {
        app(VoucherService::class)->post(['voucher_type' => 'journal', 'entry_date' => '2026-10-04',
            'description' => 'Mixed sale', 'reference_mode' => 'new_reference', 'idempotency_key' => 'mixed', 'items' => [
                ['chart_of_account_id' => $this->account('cash')->id, 'debit' => 3],
                ['chart_of_account_id' => $this->account('ar')->id, 'debit' => 2, 'partner_type' => 'customer', 'partner_id' => 1],
                ['chart_of_account_id' => $this->account('sales')->id, 'credit' => 5]]], $this->context);
        $report = app(FinancialReportService::class)->dayBook($this->context, null, null, true);
        $this->assertSame('3.0000', $report['entries'][0]->cash_debit);
        $this->assertSame('0.0000', $report['entries'][0]->cash_credit);
        $this->assertSame('5.0000', $report['entries'][0]->total_debit);
    }

    public function test_concurrent_journal_replays_post_once_on_mysql(): void
    {
        if (getenv('ERP_TEST_MYSQL') !== '1') {
            $this->markTestSkipped('Requires disposable MySQL row-lock proof.');
        }
        $results = $this->race('journal', (string) $this->account('cash')->id, (string) $this->account('sales')->id);
        $this->assertSame($results[0]['id'], $results[1]['id']);
        $this->assertSame(1, JournalEntry::count());
        $this->assertSame(2, JournalItem::count());
        $this->assertSame(1, DB::table('document_number_reservations')->count());
    }

    public function test_concurrent_allocations_cannot_exhaust_the_same_bill_twice_on_mysql(): void
    {
        if (getenv('ERP_TEST_MYSQL') !== '1') {
            $this->markTestSkipped('Requires disposable MySQL row-lock proof.');
        }
        $this->sale(10);
        $invoice = AccountOpenItem::where('source_type', 'sale')->sole();
        $one = $this->receipt(8, 'one');
        $two = $this->receipt(8, 'two');
        $oneId = AccountOpenItem::where('journal_entry_id', $one->id)->sole()->id;
        $twoId = AccountOpenItem::where('journal_entry_id', $two->id)->sole()->id;
        $results = $this->race('allocation', (string) $invoice->id, (string) $oneId, (string) $twoId);
        $this->assertSame(1, collect($results)->where('posted', true)->count());
        $this->assertSame('2.0000', $invoice->fresh()->open_amount);
        $this->assertSame(1, AccountAllocation::count());
        $this->assertTrue(app(LedgerReconciliationService::class)->reconcile($this->context)['is_reconciled']);
    }

    private function race(string $mode, string ...$arguments): array
    {
        $processes = [];
        DB::beginTransaction();
        DB::table('companies')->where('id', $this->company->id)->lockForUpdate()->first();
        try {
            for ($i = 0; $i < 2; $i++) {
                $process = new Process([PHP_BINARY, base_path('tests/Support/accounting_worker.php'),
                    (string) $this->company->id, (string) $this->branch->id, (string) $this->year->id, $mode, (string) $i, ...$arguments]);
                $process->setTimeout(45);
                $process->start();
                $processes[] = $process;
            }
        } finally {
            DB::commit();
        }
        $results = [];
        foreach ($processes as $process) {
            $process->wait();
            $this->assertTrue($process->isSuccessful(), $process->getErrorOutput().$process->getOutput());
            $results[] = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        }
        return $results;
    }
}
