<?php

namespace Tests\Feature;

use App\Models\Accounting\ChartOfAccount;
use App\Models\Accounting\FiscalYear;
use App\Models\Accounting\JournalEntry;
use App\Models\DocumentNumberReservation;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\User;
use App\Services\Accounting\AccountingService;
use App\Services\ERP\InventoryService;
use App\Services\ERP\PurchaseService;
use App\Services\ERP\SaleService;
use App\Services\Platform\CompanyContext;
use App\Services\Platform\DocumentNumberService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use LogicException;
use RuntimeException;
use Symfony\Component\Process\Process;
use Tests\Support\CompanyContextTestCase;

class DocumentNumberServiceTest extends CompanyContextTestCase
{
    private DocumentNumberService $numbers;
    private CompanyContext $context;

    protected function setUp(): void
    {
        parent::setUp();
        $this->numbers = app(DocumentNumberService::class);
        $this->context = $this->resolver->resolve(1);
        $this->actingAs(User::findOrFail(1));
        foreach (['customers', 'suppliers', 'billers'] as $table) {
            DB::table($table)->update(['company_id' => $this->company->id]);
        }
        DB::table('warehouses')->update(['company_id' => $this->company->id, 'branch_id' => $this->branch->id]);
        foreach (['units', 'accounts'] as $table) {
            Schema::create($table, function (Blueprint $table) {
                $table->increments('id');
                $table->string('name');
                $table->unsignedBigInteger('company_id');
            });
            DB::table($table)->insert(['id' => 1, 'name' => 'Owned account/unit', 'company_id' => $this->company->id]);
        }
        foreach ([
            ['1010', 'asset', 'cash'], ['1100', 'asset', 'accounts_receivable'],
            ['1200', 'asset', 'inventory'], ['2010', 'liability', 'accounts_payable'],
            ['4010', 'revenue', 'sales_revenue'], ['5010', 'expense', 'cogs'],
        ] as [$code, $type, $subType]) {
            (new ChartOfAccount)->forceFill(['company_id' => $this->company->id,
                'code' => $code, 'name' => $subType, 'type' => $type, 'sub_type' => $subType])->save();
        }
        $product = $this->stock(50);
        $product->forceFill(['company_id' => $this->company->id])->save();
        DB::table('product_warehouse')->update(['company_id' => $this->company->id]);
        $this->installAccountingFoundation();
        app(\App\Services\Accounting\SemanticAccountResolver::class)->seedCompany($this->company->id);
    }

    private function reserve(string $type = 'sale', ?CompanyContext $context = null, string $date = '2026-10-03', int $actor = 1): DocumentNumberReservation
    {
        return DB::transaction(fn () => $this->numbers->reserve($type, $context ?? $this->context, $date, $actor));
    }

    private function salePayload(): array
    {
        return ['customer_id' => 1, 'warehouse_id' => 1, 'business_date' => '2026-10-03',
            'items' => [['product_id' => 1, 'qty' => 1, 'net_unit_price' => 10]]];
    }

    public function test_repeated_posts_in_same_second_have_distinct_numbers_and_assigned_sources(): void
    {
        $service = app(SaleService::class);
        $first = $service->createSale($this->salePayload(), 1, $this->context);
        $second = $service->createSale($this->salePayload(), 1, $this->context);
        $this->assertNotSame($first->reference_no, $second->reference_no);
        $this->assertStringEndsWith('000001', $first->reference_no);
        $this->assertStringEndsWith('000002', $second->reference_no);
        $this->assertSame(4, DocumentNumberReservation::where('status', 'assigned')->count());
        $this->assertSame($first->id, (int) DocumentNumberReservation::where('formatted_number', $first->reference_no)->value('source_id'));
        $this->assertNotSame(JournalEntry::first()->entry_number, JournalEntry::orderByDesc('id')->first()->entry_number);
    }

    public function test_sale_payment_purchase_payment_and_transfer_use_atomic_series(): void
    {
        $sale = app(SaleService::class)->createSale($this->salePayload() + ['paid_amount' => 2], 1, $this->context);
        $payment = app(SaleService::class)->addPayment($sale, ['amount' => 3, 'business_date' => '2026-10-03'], 1, $this->context);
        $purchase = app(PurchaseService::class)->createPurchase([
            'warehouse_id' => 1, 'supplier_id' => 1, 'business_date' => '2026-10-03', 'paid_amount' => 2,
            'items' => [['product_id' => 1, 'qty' => 1, 'net_unit_cost' => 5]],
        ], 1, $this->context);
        $transfer = app(InventoryService::class)->transferStock([
            'from_warehouse_id' => 1, 'to_warehouse_id' => 2, 'business_date' => '2026-10-03',
            'items' => [['product_id' => 1, 'qty' => 1, 'net_unit_cost' => 5]],
        ], 1, $this->context);
        $this->assertStringEndsWith('000002', $payment->payment_reference);
        $this->assertStringContainsString('ERP-PUR-', $purchase->reference_no);
        $this->assertStringContainsString('ERP-TRF-', $transfer->reference_no);
        $this->assertSame(3, Payment::count());
        $this->assertSame(0, DocumentNumberReservation::where('status', 'reserved')->count());
        $this->assertSame(6, DB::table('document_series')->count());
    }

    public function test_failed_invoice_post_rolls_back_number_counter_stock_and_source(): void
    {
        $accounting = \Mockery::mock(AccountingService::class);
        $accounting->shouldReceive('postSaleJournal')->once()->andThrow(new RuntimeException('Posting failed'));
        try {
            (new SaleService($accounting))->createSale($this->salePayload(), 1, $this->context);
            $this->fail('Posting failure must abort invoice.');
        } catch (RuntimeException $error) {
            $this->assertSame('Posting failed', $error->getMessage());
        }
        $this->assertSame(0, Sale::count());
        $this->assertSame(0, DocumentNumberReservation::count());
        $this->assertSame(0, DB::table('document_series')->count());
        $this->assertEquals(50, DB::table('product_warehouse')->value('qty'));
        $sale = app(SaleService::class)->createSale($this->salePayload(), 1, $this->context);
        $this->assertStringEndsWith('000001', $sale->reference_no);
    }

    public function test_outer_rollback_preserves_existing_counter(): void
    {
        $this->reserve();
        DB::beginTransaction();
        $this->numbers->reserve('sale', $this->context, '2026-10-03', 1);
        DB::rollBack();
        $next = $this->reserve();
        $this->assertSame(2, $next->reserved_number);
        $this->assertSame(2, DocumentNumberReservation::count());
    }

    public function test_fy_reset_is_once_per_company_and_prior_year_continues(): void
    {
        $first = $this->reserve();
        $future = FiscalYear::create(['company_id' => $this->company->id, 'name' => '2027',
            'start_date' => '2027-01-01', 'end_date' => '2027-12-31', 'status' => 'open']);
        $futureContext = new CompanyContext($this->company->id, $this->branch->id, $future->id);
        $second = $this->reserve(context: $futureContext, date: '2027-01-01');
        $third = $this->reserve(context: $futureContext, date: '2027-01-02');
        $prior = $this->reserve();
        $this->assertSame([1, 1, 2, 2], [$first->reserved_number, $second->reserved_number, $third->reserved_number, $prior->reserved_number]);
        $this->assertNotSame($first->formatted_number, $second->formatted_number);
        $this->assertSame(2, DB::table('document_series')->count());
        auth()->logout();
        $other = $this->reserve(context: $this->resolver->resolve(2), actor: 2);
        $this->assertSame(1, $other->reserved_number);
        $this->assertNotSame($first->formatted_number, $other->formatted_number);
    }

    public function test_branch_and_document_type_series_are_independent(): void
    {
        $branch = $this->company->branches()->create(['code' => 'NORTH', 'name' => 'North']);
        DB::table('company_user_branches')->insert(['company_id' => $this->company->id, 'user_id' => 1, 'branch_id' => $branch->id]);
        $other = new CompanyContext($this->company->id, $branch->id, $this->year->id);
        $a = $this->reserve();
        $b = $this->reserve(context: $other);
        $c = $this->reserve('purchase');
        $this->assertSame([1, 1, 1], [$a->reserved_number, $b->reserved_number, $c->reserved_number]);
        $this->assertCount(3, array_unique([$a->formatted_number, $b->formatted_number, $c->formatted_number]));
    }

    public function test_numbering_requires_the_document_transaction(): void
    {
        $this->expectException(LogicException::class);
        $this->numbers->reserve('sale', $this->context, '2026-10-03', 1);
    }

    public function test_foreign_context_is_rejected(): void
    {
        $this->expectException(AuthorizationException::class);
        $this->reserve(context: new CompanyContext($this->other->id, $this->otherBranch->id, $this->otherYear->id));
    }

    public function test_spoofed_branch_or_fy_is_rejected(): void
    {
        $this->expectException(AuthorizationException::class);
        $this->reserve(context: new CompanyContext($this->company->id, $this->otherBranch->id, $this->year->id));
    }

    public function test_closed_and_outside_fy_dates_never_consume_number(): void
    {
        foreach (['2025-12-31', '2027-01-01', '2026-02-30'] as $date) {
            try {
                $this->reserve(date: $date);
                $this->fail('Invalid date must fail.');
            } catch (ValidationException) {
                $this->assertSame(0, DocumentNumberReservation::count());
            }
        }
        $this->year->update(['status' => 'locked']);
        $this->expectException(ValidationException::class);
        $this->reserve();
    }

    public function test_custom_series_padding_and_suffix_are_used(): void
    {
        $this->numbers->configure($this->context, ['document_type' => 'sale', 'code' => 'COUNTER',
            'prefix' => 'INV/', 'suffix' => '/26', 'padding' => 4, 'next_number' => 91], 1);
        $number = $this->reserve();
        $this->assertSame('INV/0091/26', $number->formatted_number);
        $this->assertSame(92, (int) DB::table('document_series')->value('next_number'));
    }

    public function test_used_series_cannot_be_reset_or_reformatted(): void
    {
        $this->reserve();
        $this->expectException(ValidationException::class);
        $this->numbers->configure($this->context, ['document_type' => 'sale', 'code' => 'MAIN', 'next_number' => 1], 1);
    }

    public function test_database_rejects_two_defaults_in_same_scope(): void
    {
        $this->reserve();
        $row = (array) DB::table('document_series')->first();
        unset($row['id'], $row['default_slot']);
        $row['code'] = 'DUPLICATE';
        $this->expectException(QueryException::class);
        DB::table('document_series')->insert($row);
    }

    public function test_new_default_does_not_reuse_prior_numbers_and_can_select_nondefault(): void
    {
        $original = $this->reserve();
        $this->numbers->configure($this->context, ['document_type' => 'sale', 'code' => 'NEW', 'prefix' => 'NEW/'], 1);
        $next = $this->reserve();
        $prior = DB::transaction(fn () => $this->numbers->reserve('sale', $this->context, '2026-10-03', 1, 'MAIN'));
        $this->assertSame('NEW/000001', $next->formatted_number);
        $this->assertSame($original->series_id, $prior->series_id);
        $this->assertSame(2, $prior->reserved_number);
        $this->assertSame(1, DB::table('document_series')->where('is_default', true)->count());
    }

    public function test_duplicate_format_across_series_rolls_back_allocation(): void
    {
        $original = $this->reserve();
        $prefix = DB::table('document_series')->value('prefix');
        $id = $this->numbers->configure($this->context, ['document_type' => 'sale', 'code' => 'NEW', 'prefix' => $prefix], 1);
        try {
            $this->reserve();
            $this->fail('Duplicate format must reject.');
        } catch (QueryException) {
            $this->assertSame(1, (int) DB::table('document_series')->where('id', $id)->value('next_number'));
            $this->assertSame(1, DocumentNumberReservation::count());
        }
    }

    public function test_retained_legacy_number_including_deleted_invoice_cannot_be_reused(): void
    {
        $sale = app(SaleService::class)->createSale($this->salePayload(), 1, $this->context);
        $sale->reference_no = 'LEGACY/000001';
        $sale->save();
        $sale->delete();
        $this->numbers->configure($this->context, ['document_type' => 'sale', 'code' => 'LEGACY', 'prefix' => 'LEGACY/'], 1);
        $this->expectException(ValidationException::class);
        $this->reserve();
    }

    public function test_assignment_is_idempotent_and_reprint_never_allocates(): void
    {
        $sale = app(SaleService::class)->createSale($this->salePayload(), 1, $this->context);
        $reservation = DocumentNumberReservation::where('formatted_number', $sale->reference_no)->firstOrFail();
        DB::transaction(fn () => $this->numbers->assign($reservation, $sale));
        $this->assertSame($sale->reference_no, $sale->fresh()->reference_no);
        $this->assertSame(2, (int) DB::table('document_series')->where('document_type', 'sale')->value('next_number'));
        $this->assertSame(2, DocumentNumberReservation::count());
    }

    public function test_reservation_cannot_be_bound_to_another_document(): void
    {
        $service = app(SaleService::class);
        $one = $service->createSale($this->salePayload(), 1, $this->context);
        $two = $service->createSale($this->salePayload(), 1, $this->context);
        $reservation = DocumentNumberReservation::where('formatted_number', $one->reference_no)->firstOrFail();
        $this->expectException(AuthorizationException::class);
        DB::transaction(fn () => $this->numbers->assign($reservation, $two));
    }

    public function test_series_api_cannot_accept_spoofed_ownership_or_staff_configuration(): void
    {
        $this->postJson('/api/v1/company-context/document-series', ['document_type' => 'sale', 'code' => 'API',
            'company_id' => $this->other->id, 'branch_id' => $this->otherBranch->id, 'prefix' => 'API/'])->assertCreated();
        $this->assertSame($this->company->id, (int) DB::table('document_series')->value('company_id'));
        $this->getJson('/api/v1/company-context/document-series')->assertOk()->assertJsonCount(1, 'data');
        DB::table('users')->where('id', 1)->update(['role_id' => 4]);
        $this->postJson('/api/v1/company-context/document-series', ['document_type' => 'sale', 'code' => 'STAFF'])->assertForbidden();
    }

    public function test_concurrent_mysql_invoice_posts_allocate_unique_assigned_numbers_from_first_series(): void
    {
        if (getenv('ERP_TEST_MYSQL') !== '1') {
            $this->markTestSkipped('Concurrent row-lock proof requires disposable MySQL.');
        }
        $processes = [];
        for ($i = 0; $i < 6; $i++) {
            $process = new Process([PHP_BINARY, base_path('tests/Support/document_number_worker.php'),
                (string) $this->company->id, (string) $this->branch->id, (string) $this->year->id]);
            $process->setTimeout(45);
            $process->start();
            $processes[] = $process;
        }
        $numbers = [];
        foreach ($processes as $process) {
            $process->wait();
            $this->assertTrue($process->isSuccessful(), $process->getErrorOutput().$process->getOutput());
            $this->assertJson($process->getOutput(), $process->getErrorOutput().$process->getOutput());
            $numbers[] = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR)['number'];
        }
        $this->assertCount(6, array_unique($numbers));
        $this->assertSame(6, Sale::count());
        $this->assertSame(6, DocumentNumberReservation::where('document_type', 'sale')->where('status', 'assigned')->count());
        $this->assertSame(7, (int) DB::table('document_series')->where('document_type', 'sale')->value('next_number'));
        $this->assertSame(1, DB::table('document_series')->where('document_type', 'sale')->count());
    }
}
