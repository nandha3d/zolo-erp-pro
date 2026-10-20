<?php

namespace Tests\Feature;

use App\Models\Accounting\AccountOpenItem;
use App\Models\Accounting\ChartOfAccount;
use App\Models\Accounting\JournalEntry;
use App\Models\Customer;
use App\Models\Supplier;
use App\Services\Accounting\FinancialReportService;
use App\Services\Accounting\PartyOpeningBalanceService;
use App\Services\Accounting\PeriodCloseService;
use App\Services\Accounting\SemanticAccountResolver;
use App\Services\Accounting\VoucherService;
use App\Services\Platform\CompanyContext;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\Support\CompanyErpServiceTestCase;

class YearCloseAndOpeningBalanceTest extends CompanyErpServiceTestCase
{
    private CompanyContext $context;

    protected function setUp(): void
    {
        parent::setUp();
        $this->context = $this->resolver->resolve(1);
        Schema::create('permissions', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
        });
        Schema::create('role_has_permissions', function (Blueprint $table) {
            $table->unsignedInteger('role_id');
            $table->unsignedInteger('permission_id');
        });
        foreach ([['3020', 'retained_earnings'], ['3040', 'opening_balance']] as [$code, $subType]) {
            (new ChartOfAccount)->forceFill(['company_id' => $this->company->id, 'code' => $code, 'name' => $subType,
                'type' => 'equity', 'sub_type' => $subType])->save();
        }
        (new ChartOfAccount)->forceFill(['company_id' => $this->company->id, 'code' => '6090', 'name' => 'General expense',
            'type' => 'expense', 'sub_type' => 'operating_expense'])->save();
        app(SemanticAccountResolver::class)->seedCompany($this->company->id);
    }

    private function account(string $role): ChartOfAccount
    {
        return app(SemanticAccountResolver::class)->resolve($role, $this->context);
    }

    private function journal(string $key, string $date, string $debitRole, string $creditRole, float $amount): void
    {
        app(VoucherService::class)->post(['voucher_type' => 'journal', 'entry_date' => $date, 'description' => $key,
            'idempotency_key' => $key, 'reference_mode' => 'new_reference', 'items' => [
                ['chart_of_account_id' => $this->account($debitRole)->id, 'debit' => $amount],
                ['chart_of_account_id' => $this->account($creditRole)->id, 'credit' => $amount],
            ]], $this->context);
    }

    private function lines(JournalEntry $entry): array
    {
        return $entry->items()->with('account')->get()
            ->mapWithKeys(fn ($line) => [$line->account->code => (float) $line->debit - (float) $line->credit])->all();
    }

    public function test_party_opening_balance_posts_an_open_item_on_the_year_start_instead_of_a_fake_bill(): void
    {
        $openings = app(PartyOpeningBalanceService::class);
        $customerEntry = $openings->post(Customer::findOrFail(1), 1500, $this->context);
        $supplierEntry = $openings->post(Supplier::findOrFail(1), 400, $this->context);

        $this->assertSame('2026-01-01', $customerEntry->entry_date->toDateString());
        $this->assertSame('customer_opening', $customerEntry->reference_type);
        $this->assertSame(['1100' => 1500.0, '3040' => -1500.0], $this->lines($customerEntry));
        $this->assertSame(['3040' => 400.0, '2010' => -400.0], $this->lines($supplierEntry));
        $this->assertEquals(1500, AccountOpenItem::where('party_type', 'customer')->where('party_id', 1)->value('open_amount'));
        $this->assertEquals(400, AccountOpenItem::where('party_type', 'supplier')->where('party_id', 1)->value('open_amount'));
        $this->assertSame(0, DB::table('sales')->count());
        $this->assertSame(0, DB::table('purchases')->count());

        // Replay posts nothing new; a zero balance posts nothing at all.
        $openings->post(Customer::findOrFail(1), 1500, $this->context);
        $this->assertNull($openings->post(Customer::findOrFail(1), 0, $this->context));
        $this->assertSame(2, JournalEntry::count());
    }

    public function test_opening_balance_is_refused_before_saving_when_the_year_start_is_locked(): void
    {
        app(PeriodCloseService::class)->change($this->context, 'lock', '2026-03-31', 'Quarter reviewed');
        try {
            app(PartyOpeningBalanceService::class)->assertPostable('customer', 100, $this->context);
            $this->fail('A locked year start must refuse the opening balance.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('opening_balance', $error->errors());
        }
        app(PartyOpeningBalanceService::class)->assertPostable('customer', 0, $this->context);
        $this->assertSame(0, JournalEntry::count());
    }

    public function test_year_close_moves_profit_to_retained_earnings_and_reopening_reverses_it(): void
    {
        $this->journal('cash-sale', '2026-06-01', 'cash', 'sales', 1000);
        $this->journal('rent', '2026-07-01', 'expense', 'cash', 300);
        app(PeriodCloseService::class)->change($this->context, 'lock', '2026-12-31', 'Locked before close');

        app(PeriodCloseService::class)->change($this->context, 'close', null, 'Audited');

        $closing = JournalEntry::where('reference_type', 'year_close')->sole();
        $this->assertSame('2026-12-31', $closing->entry_date->toDateString());
        $this->assertSame(['4010' => 1000.0, '6090' => -300.0, '3020' => -700.0], $this->lines($closing));
        $this->assertTrue((bool) $this->year->fresh()->is_closed);
        $this->assertSame('2026-12-31', $this->year->fresh()->lock_date->toDateString());

        $reports = app(FinancialReportService::class);
        $this->assertEquals(700, $reports->getProfitAndLoss(null, null, $this->context)['net_income']);
        $trial = $reports->getTrialBalance(null, null, $this->context);
        $this->assertTrue($trial['is_balanced']);
        $this->assertEquals(1000, collect($trial['accounts'])->firstWhere('code', '4010')['credit']);
        $sheet = $reports->getBalanceSheet(null, $this->context);
        $this->assertTrue($sheet['is_balanced']);
        $equity = collect($sheet['equity'])->keyBy('code');
        $this->assertEquals(700, $equity['3020']['balance']);
        $this->assertEquals(0, $equity['CURRENT-EARNINGS']['balance']);

        app(PeriodCloseService::class)->change($this->context, 'unlock', null, 'Late bill found');
        $this->assertSame(1, JournalEntry::where('reversal_of_id', $closing->id)->count());
        $this->assertEquals(700, $reports->getProfitAndLoss(null, null, $this->context)['net_income']);
        $this->assertEquals(700, collect($reports->getBalanceSheet(null, $this->context)['equity'])->firstWhere('code', 'CURRENT-EARNINGS')['balance']);

        $this->journal('late-rent', '2026-12-20', 'expense', 'cash', 50);
        app(PeriodCloseService::class)->change($this->context, 'close', null, 'Audited again');
        $second = JournalEntry::where('reference_type', 'year_close')->latest('id')->first();
        $this->assertNotSame($closing->id, $second->id);
        $this->assertSame(-650.0, $this->lines($second)['3020']);
        $this->assertEquals(650, collect($reports->getBalanceSheet(null, $this->context)['equity'])->firstWhere('code', '3020')['balance']);
    }

    public function test_closing_a_year_with_no_income_or_expense_needs_no_retained_earnings_mapping(): void
    {
        DB::table('semantic_account_mappings')->where('semantic_role', 'retained_earnings')->update(['account_id' => null]);
        app(PeriodCloseService::class)->change($this->context, 'close', null, 'Nothing traded');
        $this->assertSame(0, JournalEntry::where('reference_type', 'year_close')->count());
        $this->assertTrue((bool) $this->year->fresh()->is_closed);
    }
}
