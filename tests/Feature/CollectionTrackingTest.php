<?php

namespace Tests\Feature;

use App\Models\Accounting\AccountOpenItem;
use App\Models\Accounting\CollectionRecord;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\ChartOfAccount;
use App\Services\Accounting\CollectionService;
use App\Services\Accounting\FinancialReportService;
use App\Services\Accounting\OpenItemService;
use App\Services\Accounting\VoucherService;
use App\Services\ERP\SaleService;
use App\Services\Platform\CompanyContext;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\Support\CompanyErpServiceTestCase;

class CollectionTrackingTest extends CompanyErpServiceTestCase
{
    private CompanyContext $context;

    protected function setUp(): void
    {
        parent::setUp();
        $this->context = $this->resolver->resolve(1);
        Schema::create('permissions', function (Blueprint $table) { $table->increments('id'); $table->string('name'); });
        Schema::create('role_has_permissions', function (Blueprint $table) { $table->unsignedInteger('role_id'); $table->unsignedInteger('permission_id'); });
        (require database_path('migrations/2026_10_19_000012_create_collection_tracking.php'))->up();
    }

    private function invoice(int $customerId = 1): AccountOpenItem
    {
        $product = $this->stock();
        $sale = app(SaleService::class)->createSale(['customer_id' => $customerId, 'warehouse_id' => 1, 'business_date' => '2026-10-03',
            'items' => [['product_id' => $product->id, 'qty' => 1, 'net_unit_price' => 100]]], 1, $this->context);
        return AccountOpenItem::where('source_type', 'sale')->where('source_id', $sale->id)->sole();
    }

    private function receipt(string $amount = '40', string $key = 'receipt'): JournalEntry
    {
        $resolver = app(\App\Services\Accounting\SemanticAccountResolver::class);
        return app(VoucherService::class)->post(['voucher_type' => 'receipt', 'entry_date' => '2026-10-04', 'description' => 'Receipt',
            'idempotency_key' => $key, 'reference_mode' => 'on_account', 'items' => [
                ['chart_of_account_id' => $resolver->resolve('cash', $this->context)->id, 'debit' => $amount],
                ['chart_of_account_id' => $resolver->resolve('ar', $this->context)->id, 'credit' => $amount, 'partner_type' => 'customer', 'partner_id' => 1],
            ]], $this->context);
    }

    private function payload(AccountOpenItem $item, array $extra = []): array
    {
        return array_replace(['open_item_id' => $item->id, 'kind' => 'follow_up', 'status' => 'planned',
            'action_date' => '2026-10-10', 'notes' => 'Call customer after due date', 'idempotency_key' => 'follow-1'], $extra);
    }

    private function snapshot(): array
    {
        return collect(['journal_entries', 'journal_items', 'account_open_items', 'account_allocations', 'payments', 'stock_movements'])
            ->mapWithKeys(fn ($table) => [$table => DB::table($table)->orderBy('id')->get()->toJson()])->all();
    }

    public function test_follow_up_retry_versions_audit_and_reminder_have_no_financial_effects(): void
    {
        $item = $this->invoice(); $before = $this->snapshot(); $service = app(CollectionService::class);
        $record = $service->save($this->payload($item), $this->context);
        $this->assertSame($record->id, $service->save($this->payload($item), $this->context)->id);
        $this->assertSame(1, DB::table('collection_events')->count());
        $updated = $service->save($this->payload($item, ['version' => 1, 'status' => 'completed', 'notes' => 'Customer confirmed date', 'idempotency_key' => 'update-1']), $this->context, $record->id);
        $this->assertSame(2, $updated->version);
        $this->assertSame(2, DB::table('collection_events')->count());
        $service->save($this->payload($item, ['kind' => 'reminder', 'idempotency_key' => 'reminder']), $this->context);
        $this->assertSame($before, $this->snapshot());
        try { $service->save($this->payload($item, ['version' => 1, 'idempotency_key' => 'stale']), $this->context, $record->id); $this->fail('Stale version accepted.'); }
        catch (ValidationException $e) { $this->assertArrayHasKey('version', $e->errors()); }
        try { $service->save($this->payload($item, ['notes' => 'Changed retry']), $this->context); $this->fail('Changed retry accepted.'); }
        catch (ValidationException $e) { $this->assertArrayHasKey('idempotency_key', $e->errors()); }
        try { $service->save($this->payload($item, ['kind' => 'reminder', 'status' => 'completed', 'idempotency_key' => 'sent']), $this->context); $this->fail('Reminder notification status accepted.'); }
        catch (ValidationException $e) { $this->assertArrayHasKey('status', $e->errors()); }
    }

    public function test_pdc_clearing_requires_same_party_posted_receipt_and_never_posts(): void
    {
        $item = $this->invoice(); $receipt = $this->receipt(); $before = $this->snapshot();
        $service = app(CollectionService::class);
        $data = $this->payload($item, ['kind' => 'pdc', 'status' => 'presented', 'action_date' => '2026-10-03', 'amount' => '40', 'cheque_no' => 'PDC1', 'bank_name' => 'Customer bank']);
        $record = $service->save($data, $this->context);
        $cleared = $service->save(array_replace($data, ['version' => 1, 'status' => 'cleared', 'receipt_entry_id' => $receipt->id, 'idempotency_key' => 'clear']), $this->context, $record->id);
        $this->assertSame('cleared', $cleared->status);
        $this->assertSame($before, $this->snapshot());
        try { $service->save(array_replace($data, ['status' => 'cleared', 'amount' => '1', 'receipt_entry_id' => $receipt->id, 'idempotency_key' => 'duplicate-receipt']), $this->context); $this->fail('Receipt overlinked.'); }
        catch (ValidationException $e) { $this->assertArrayHasKey('receipt_entry_id', $e->errors()); }
        $this->expectException(ValidationException::class);
        $service->save(array_replace($data, ['status' => 'cleared', 'receipt_entry_id' => 999, 'idempotency_key' => 'invalid']), $this->context);
    }

    public function test_preview_reconstructs_as_of_principal_and_requires_explicit_basis_and_eligibility(): void
    {
        $item = $this->invoice(); $receipt = $this->receipt();
        $credit = AccountOpenItem::where('journal_entry_id', $receipt->id)->sole();
        app(OpenItemService::class)->allocate($item->id, $credit->id, '40', '2026-10-04', 'partial', $this->context);
        $before = $this->snapshot();
        $data = ['open_item_id' => $item->id, 'as_of_date' => '2026-11-02', 'interest_rate' => '12', 'day_basis' => '360', 'discount_rate' => '2', 'discount_eligible_until' => '2026-11-02'];
        $preview = app(CollectionService::class)->preview($data, $this->context);
        $this->assertSame('60.0000', $preview['principal']);
        $this->assertSame('0.6000', $preview['interest_preview']);
        $this->assertSame('1.2000', $preview['discount_preview']);
        $this->assertSame(30, $preview['days']);
        $expired = app(CollectionService::class)->preview(array_replace($data, ['discount_eligible_until' => '2026-11-01']), $this->context);
        $this->assertSame('0.0000', $expired['discount_preview']);
        $this->assertSame($before, $this->snapshot());
    }

    public function test_ageing_custom_buckets_names_prior_year_and_reconciliation(): void
    {
        $item = $this->invoice();
        DB::table('account_open_items')->where('id', $item->id)->update(['document_date' => '2025-12-20', 'due_date' => '2026-10-01']);
        $receipt = $this->receipt();
        $report = app(FinancialReportService::class)->ageing($this->context, 'customer', '2026-10-20', ['bucket_cutoffs' => [7, 14, 30], 'party_id' => 1]);
        $this->assertSame('100.0000', $report['totals']['15-30']);
        $this->assertSame('-40.0000', $report['totals']['credits']);
        $this->assertSame('Regression fixture', $report['rows'][0]['party_name']);
        $this->assertSame('60.0000', $report['reconciliation']['control_balance']);
        $this->assertSame('0.0000', $report['reconciliation']['difference']);
        $this->assertTrue($report['reconciliation']['is_reconciled']);
        DB::table('chart_of_accounts')->where('control_type', 'ar')->update(['opening_balance' => '2']);
        $this->assertSame('2.0000', app(FinancialReportService::class)->ageing($this->context, 'customer', '2026-10-20')['reconciliation']['difference']);
    }

    public function test_company_filters_and_ownership_are_enforced_at_api_boundaries(): void
    {
        $item = $this->invoice();
        $this->getJson('/api/v1/accounting/ageing?agent_id=999')->assertUnprocessable();
        $this->postJson('/api/v1/accounting/collections', $this->payload($item))->assertOk();
        $this->getJson('/api/v1/accounting/collections')->assertOk()->assertJsonCount(1, 'records');
        $this->actingAs(\App\Models\User::findOrFail(2));
        $this->getJson('/api/v1/accounting/collections')->assertOk()->assertJsonCount(0, 'records');
        $this->postJson('/api/v1/accounting/collections', $this->payload($item))->assertUnprocessable();
        $this->actingAs(\App\Models\User::findOrFail(1));
        DB::table('users')->where('id', 1)->update(['role_id' => 4]);
        $this->getJson('/api/v1/accounting/collections')->assertForbidden();
        $this->postJson('/api/v1/accounting/collection-preview', [])->assertForbidden();
    }

    public function test_metadata_filters_use_invoice_agent_and_chart_parent_and_export_matches(): void
    {
        foreach (['agents', 'areas', 'employees'] as $tableName) {
            Schema::create($tableName, function (Blueprint $table) { $table->increments('id'); $table->unsignedBigInteger('company_id'); $table->string('name'); });
            DB::table($tableName)->insert([['id' => 1, 'company_id' => $this->company->id, 'name' => 'Owned '.$tableName], ['id' => 2, 'company_id' => $this->other->id, 'name' => 'Secret '.$tableName]]);
        }
        DB::table('agents')->insert(['id' => 3, 'company_id' => $this->company->id, 'name' => 'Current party agent']);
        Schema::table('customers', function (Blueprint $table) { $table->unsignedInteger('agent_id')->nullable(); $table->unsignedInteger('area_id')->nullable(); });
        Schema::table('sales', function (Blueprint $table) { $table->unsignedInteger('agent_id')->nullable(); $table->unsignedInteger('salesman_id')->nullable(); });
        $item = $this->invoice();
        DB::table('customers')->where('id', 1)->update(['agent_id' => 3, 'area_id' => 1]);
        DB::table('sales')->where('id', $item->source_id)->update(['agent_id' => 1, 'salesman_id' => 1]);
        $group = (new ChartOfAccount)->forceFill(['company_id' => $this->company->id, 'code' => 'AGROUP', 'name' => 'Receivable group', 'type' => 'asset', 'sub_type' => 'accounts_receivable']);
        $group->save();
        DB::table('chart_of_accounts')->where('id', $item->account_id)->update(['parent_id' => $group->id]);
        $filters = ['agent_id' => 1, 'area_id' => 1, 'salesman_id' => 1, 'ledger_group_id' => $group->id];
        $report = app(FinancialReportService::class)->ageing($this->context, 'customer', '2026-10-20', $filters);
        $this->assertCount(1, $report['rows']);
        $this->assertSame('Owned agents', $report['rows'][0]['agent_name']);
        $this->assertSame('invoice', $report['rows'][0]['agent_basis']);
        $this->assertSame('Receivable group', $report['rows'][0]['ledger_group_name']);
        $none = app(FinancialReportService::class)->ageing($this->context, 'customer', '2026-10-20', ['agent_id' => 3]);
        $this->assertCount(0, $none['rows']);
        $this->assertSame('100.0000', $none['reconciliation']['open_item_balance']);
        $csv = $this->get('/api/v1/accounting/ageing?'.http_build_query($filters + ['as_of_date' => '2026-10-20', 'export' => 'csv']))->assertOk()->streamedContent();
        $this->assertStringContainsString('Owned agents', $csv);
        $this->assertStringNotContainsString('Secret', $csv);
        $this->getJson('/api/v1/accounting/ageing?agent_id=2')->assertUnprocessable();
        $this->getJson('/api/v1/accounting/ageing?bucket_cutoffs[]=30&bucket_cutoffs[]=7')->assertUnprocessable();
    }

    public function test_migration_replay_preserves_collection_history(): void
    {
        $item = $this->invoice();
        $record = app(CollectionService::class)->save($this->payload($item), $this->context);
        $migration = require database_path('migrations/2026_10_19_000012_create_collection_tracking.php');
        $migration->up();
        $this->assertSame(1, CollectionRecord::count());
        $this->expectException(\RuntimeException::class);
        $migration->down();
    }

    public function test_split_party_receipt_has_separate_pdc_capacity_and_credits_export_as_numbers(): void
    {
        $attributes = (array) DB::table('customers')->where('id', 1)->first(); unset($attributes['id']);
        $attributes['name'] = 'Second payer'; $customerId = DB::table('customers')->insertGetId($attributes);
        $first = $this->invoice(); $second = $this->invoice($customerId);
        $resolver = app(\App\Services\Accounting\SemanticAccountResolver::class);
        $receipt = app(VoucherService::class)->post(['voucher_type' => 'receipt', 'entry_date' => '2026-10-04',
            'description' => 'Two payers', 'idempotency_key' => 'split-receipt', 'reference_mode' => 'on_account', 'items' => [
                ['chart_of_account_id' => $resolver->resolve('cash', $this->context)->id, 'debit' => 80],
                ['chart_of_account_id' => $first->account_id, 'credit' => 40, 'partner_type' => 'customer', 'partner_id' => 1],
                ['chart_of_account_id' => $second->account_id, 'credit' => 40, 'partner_type' => 'customer', 'partner_id' => $customerId],
            ]], $this->context);
        $before = $this->snapshot();
        foreach ([$first, $second] as $item) {
            app(CollectionService::class)->save($this->payload($item, ['kind' => 'pdc', 'status' => 'cleared',
                'action_date' => '2026-10-04', 'amount' => 40, 'cheque_no' => 'PDC-'.$item->id, 'bank_name' => 'Fixture bank',
                'receipt_entry_id' => $receipt->id, 'idempotency_key' => 'split-pdc-'.$item->id]), $this->context);
        }
        $this->assertSame(2, CollectionRecord::where('status', 'cleared')->count());
        $this->assertSame($before, $this->snapshot());
        $csv = $this->get('/api/v1/accounting/ageing?as_of_date=2026-10-05&export=csv')->assertOk()->streamedContent();
        $this->assertStringContainsString(',-40.0000,', $csv);
        $this->assertStringNotContainsString(",-40.0000'", $csv);
        $this->assertStringNotContainsString(",'".'-40.0000', $csv);
    }

    public function test_unsupported_pdc_precision_returns_field_validation_error(): void
    {
        $item = $this->invoice();
        $this->postJson('/api/v1/accounting/collections', $this->payload($item, ['kind' => 'pdc', 'amount' => '0.00001',
            'cheque_no' => 'PRECISION', 'bank_name' => 'Fixture bank']))->assertUnprocessable()->assertJsonValidationErrors('amount');
        $this->postJson('/api/v1/accounting/collections', $this->payload($item, ['kind' => 'pdc', 'amount' => 0.00001,
            'cheque_no' => 'PRECISION-NUMBER', 'bank_name' => 'Fixture bank']))->assertUnprocessable()->assertJsonValidationErrors('amount');
        $this->assertSame(0, CollectionRecord::count());
    }

    public function test_reversed_receipt_flags_current_pdc_state_without_rewriting_audit(): void
    {
        $item = $this->invoice(); $receipt = $this->receipt();
        $record = app(CollectionService::class)->save($this->payload($item, ['kind' => 'pdc', 'status' => 'cleared',
            'action_date' => '2026-10-04', 'amount' => 40, 'cheque_no' => 'REVERSE', 'bank_name' => 'Fixture bank',
            'receipt_entry_id' => $receipt->id]), $this->context);
        app(\App\Services\Accounting\AccountingPostingService::class)->reverse($receipt->id, '2026-10-05', 'Receipt correction', $this->context);
        $before = $this->snapshot();
        $data = $this->getJson('/api/v1/accounting/collections')->assertOk()->json('records.0');
        $this->assertSame('cleared', $data['status']);
        $this->assertSame('receipt_reversed', $data['effective_status']);
        $this->assertFalse($data['receipt_valid']);
        $this->assertNotEmpty($data['receipt_warning']);
        $this->assertSame(1, DB::table('collection_events')->where('collection_record_id', $record->id)->count());
        $this->assertSame($before, $this->snapshot());
    }

    public function test_preview_preserves_exact_ledger_units_and_refuses_overflow(): void
    {
        $item = $this->invoice();
        $balance = '10000000000000.0001';
        $report = $this->createMock(FinancialReportService::class);
        $report->method('ageing')->willReturnCallback(fn () => ['rows' => [['item' => $item, 'open_amount' => $balance]]]);
        $this->app->instance(FinancialReportService::class, $report);
        $input = ['open_item_id' => $item->id, 'as_of_date' => '2026-10-03', 'interest_rate' => '0', 'day_basis' => 365,
            'discount_rate' => '100', 'discount_eligible_until' => '2026-10-03'];
        $this->assertSame($balance, app(CollectionService::class)->preview($input, $this->context)['discount_preview']);
        $maximum = '99999999999999.9999';
        $report = $this->createMock(FinancialReportService::class);
        $report->method('ageing')->willReturn(['rows' => [['item' => $item, 'open_amount' => $maximum]]]);
        $this->app->instance(FinancialReportService::class, $report);
        $this->assertSame($maximum, app(CollectionService::class)->preview($input, $this->context)['discount_preview']);
        DB::table('account_open_items')->where('id', $item->id)->update(['due_date' => '2025-01-01']);
        try { app(CollectionService::class)->preview(array_replace($input, ['as_of_date' => '2026-12-31', 'interest_rate' => '100']), $this->context); $this->fail('Overflow accepted.'); }
        catch (ValidationException $error) { $this->assertArrayHasKey('interest_rate', $error->errors()); }
    }
}
