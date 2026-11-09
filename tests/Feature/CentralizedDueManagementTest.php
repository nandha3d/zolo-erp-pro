<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Accounting\SettlementAccountService;
use App\Services\Platform\CompanyContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CentralizedDueManagementTest extends TestCase
{
    use DatabaseTransactions;

    protected User $user;
    protected Company $company;
    protected CompanyContext $context;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::where('role_id', 1)->first() ?? User::create([
            'name' => 'Admin User',
            'email' => 'admin_centralized@test.com',
            'phone' => '9876543210',
            'password' => bcrypt('password'),
            'role_id' => 1,
            'is_active' => true,
            'is_deleted' => false,
        ]);
        $this->actingAs($this->user);
        $this->company = Company::first() ?? Company::create(['name' => 'Test Company', 'code' => 'TC', 'is_active' => true]);

        if (!$this->company->users()->where('users.id', $this->user->id)->exists()) {
            $this->company->users()->attach($this->user->id, ['is_default' => true]);
        }
        $branch = $this->company->branches()->first() ?? $this->company->branches()->create(['code' => 'MAIN', 'name' => 'Main Branch']);
        DB::table('company_user_branches')->updateOrInsert(
            ['company_id' => $this->company->id, 'user_id' => $this->user->id, 'branch_id' => $branch->id],
            ['company_id' => $this->company->id, 'user_id' => $this->user->id, 'branch_id' => $branch->id]
        );
        $year = \App\Models\Accounting\FiscalYear::where('company_id', $this->company->id)->first() ?? \App\Models\Accounting\FiscalYear::create([
            'company_id' => $this->company->id, 'name' => 'FY 2026', 'start_date' => '2026-04-01', 'end_date' => '2027-03-31', 'status' => 'open'
        ]);

        $this->withSession([
            'company_id' => $this->company->id,
            'current_company_id' => $this->company->id,
            'branch_id' => $branch->id,
            'financial_year_id' => $year->id,
            'warehouse_id' => 1,
        ]);
        $this->context = new CompanyContext($this->company->id, $branch->id, $year->id);
    }

    public function test_dues_hub_loads_sales_tab(): void
    {
        $response = $this->get(route('dues.index', ['tab' => 'sales']));
        $response->assertOk();
        $response->assertViewIs('backend.dues.index');
        $response->assertSee('Due Management Command Center');
        $response->assertSee('Customer Dues (Receivables)');
        $response->assertSee('tab-sales active', false);
        $response->assertSee('Fast Due Settlement');
    }

    public function test_dues_hub_loads_purchases_tab(): void
    {
        $response = $this->get(route('dues.index', ['tab' => 'purchases']));
        $response->assertOk();
        $response->assertViewIs('backend.dues.index');
        $response->assertSee('Supplier Dues (Payables)');
        $response->assertSee('tab-purchases active', false);
    }

    public function test_dues_hub_loads_expenses_tab(): void
    {
        $response = $this->get(route('dues.index', ['tab' => 'expenses']));
        $response->assertOk();
        $response->assertViewIs('backend.dues.index');
        $response->assertSee('Other Dues');
        $response->assertSee('tab-expenses active', false);
    }

    public function test_dues_hub_has_inline_toolbar(): void
    {
        $response = $this->get(route('dues.index', ['tab' => 'sales']));
        $response->assertOk();
        $response->assertSee('due-toolbar-form', false);
        $response->assertSee('due-search-input', false);
        $response->assertSee('due-filter-select', false);
        $response->assertSee('due-view-btn', false);
    }

    public function test_dues_hub_settles_single_sale_bill(): void
    {
        $sale = Sale::visibleIn($this->context)
            ->whereNotNull('posted_at')
            ->whereNull('reversed_at')
            ->whereRaw('grand_total > paid_amount + 0.009')
            ->first();

        if (!$sale) {
            $this->markTestSkipped('No due sales found for Company.');
        }

        $accounts = app(SettlementAccountService::class)->options($this->context);
        $account = $accounts[0] ?? null;

        if (!$account) {
            $this->markTestSkipped('No active settlement account.');
        }

        $due = max(0.0, (float)$sale->grand_total - (float)$sale->paid_amount);
        $payAmount = min(500.0, $due);

        $response = $this->postJson(route('dues.settle'), [
            'kind' => 'sale',
            'bill_id' => $sale->id,
            'amount' => $payAmount,
            'payment_at' => date('Y-m-d'),
            'paying_method' => ($account['kind'] ?? 'cash') === 'bank' ? 'Bank' : 'Cash',
            'account_id' => $account['id'],
            'payment_note' => 'Automated test settlement',
            'idempotency_key' => (string) \Illuminate\Support\Str::uuid(),
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'bill_id' => $sale->id,
        ]);

        $sale->refresh();
        $this->assertGreaterThan(0, $sale->paid_amount);
    }

    public function test_dues_hub_batch_settles_multiple_bills_fifo(): void
    {
        $sales = Sale::visibleIn($this->context)
            ->whereNotNull('posted_at')
            ->whereNull('reversed_at')
            ->whereRaw('grand_total > paid_amount + 0.009')
            ->orderBy('id', 'asc')
            ->get();

        if ($sales->isEmpty()) {
            $this->markTestSkipped('No due sales found for Company.');
        }

        $partySales = $sales->groupBy('customer_id')->first(fn($group) => $group->count() >= 1);
        if (!$partySales) {
            $this->markTestSkipped('No customer with due bills.');
        }

        $customerId = $partySales->first()->customer_id;
        $billIds = $partySales->pluck('id')->all();

        $accounts = app(SettlementAccountService::class)->options($this->context);
        $account = $accounts[0] ?? null;
        if (!$account) {
            $this->markTestSkipped('No active settlement account.');
        }

        $batchAmount = 1500.0;

        $response = $this->postJson(route('dues.batchSettle'), [
            'kind' => 'sale',
            'party_id' => $customerId,
            'bill_ids' => $billIds,
            'total_amount' => $batchAmount,
            'payment_at' => date('Y-m-d'),
            'paying_method' => ($account['kind'] ?? 'cash') === 'bank' ? 'Bank' : 'Cash',
            'account_id' => $account['id'],
            'payment_note' => 'Automated batch test',
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
        ]);
        $json = $response->json();
        $this->assertNotEmpty($json['settled']);
    }

    public function test_outstanding_dues_report_has_no_action_buttons(): void
    {
        $response = $this->get(route('report.outstandingDues', ['type' => 'receivables']));
        $response->assertOk();
        $response->assertDontSee('btn-quick-action');
        $response->assertDontSee('⚡ Settle');
        $response->assertDontSee('<th>Action</th>');
        $response->assertSee('audit and reconciliation report');
        $response->assertSee('Due Management Command Center');
    }

    public function test_dues_hub_loads_party_wise_view_and_persists_in_session(): void
    {
        $response = $this->get(route('dues.index', ['tab' => 'sales', 'view' => 'party_wise']));
        $response->assertOk();
        $response->assertSessionHas('dues_view_mode', 'party_wise');
        $response->assertSee('party-groups-table');
        $response->assertSee('fifo-breakdown-panel');
        $response->assertSee('due-btn-partywise active', false);
        $response->assertSee('due-btn-billwise', false);

        // Subsequent visit without ?view= should recall party_wise from session
        $subsequentResponse = $this->get(route('dues.index', ['tab' => 'sales']));
        $subsequentResponse->assertOk();
        $subsequentResponse->assertSee('party-groups-table');
    }

    public function test_batch_settle_partial_payment_clears_oldest_first(): void
    {
        $sales = Sale::visibleIn($this->context)
            ->whereNotNull('posted_at')
            ->whereNull('reversed_at')
            ->whereRaw('grand_total > paid_amount + 0.009')
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $partyGroup = $sales->groupBy('customer_id')->first(fn($g) => $g->count() >= 2);
        if (!$partyGroup) {
            $this->markTestSkipped('Need at least 2 due sales for a customer to verify FIFO partial settlement.');
        }

        $customerId = $partyGroup->first()->customer_id;
        $sortedBills = $partyGroup->sortBy('created_at')->values();
        $oldest = $sortedBills[0];
        $second = $sortedBills[1];

        $oldestDue = (float)$oldest->grand_total - (float)$oldest->paid_amount;
        $secondDue = (float)$second->grand_total - (float)$second->paid_amount;

        // Partial amount: clears oldest completely + pays 100 towards second bill
        $partialPay = $oldestDue + min(100.0, $secondDue / 2);

        $accounts = app(SettlementAccountService::class)->options($this->context);
        $account = $accounts[0];

        $response = $this->postJson(route('dues.batchSettle'), [
            'kind' => 'sale',
            'party_id' => $customerId,
            'bill_ids' => [$oldest->id, $second->id],
            'total_amount' => $partialPay,
            'payment_at' => date('Y-m-d'),
            'paying_method' => ($account['kind'] ?? 'cash') === 'bank' ? 'Bank' : 'Cash',
            'account_id' => $account['id'],
            'payment_note' => 'FIFO Partial test',
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
        ]);

        $settled = $response->json('settled');
        $this->assertCount(2, $settled);

        // Oldest bill must be fully paid (remaining_due == 0)
        $this->assertEquals($oldest->id, $settled[0]['bill_id']);
        $this->assertEqualsWithDelta($oldestDue, $settled[0]['allocated'], 0.01);
        $this->assertEqualsWithDelta(0.0, $settled[0]['remaining_due'], 0.01);

        // Second bill must be partially paid
        $this->assertEquals($second->id, $settled[1]['bill_id']);
        $this->assertGreaterThan(0, $settled[1]['allocated']);
        $this->assertGreaterThan(0, $settled[1]['remaining_due']);
    }

    public function test_dues_hub_displays_historical_payments_register(): void
    {
        $response = $this->get(route('dues.index', ['tab' => 'sales']));
        $response->assertOk();
        $response->assertSee('due-history-card');
        $response->assertSee('due-historical-payments-table');
        $response->assertSee('Recent Customer Receipts &amp; Settlement History', false);
    }
}

