<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Accounting\ChartOfAccount;
use App\Models\Accounting\JournalEntry;
use App\Models\DocumentSeries;
use App\Services\Accounting\SettlementAccountService;
use App\Services\Accounting\VoucherService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\OperationsTestCase;

class AccountingWorkspaceTest extends OperationsTestCase
{
    private Account $bank;
    private ChartOfAccount $cash;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cash = ChartOfAccount::forCompany($this->context())->where('code', '1010')->firstOrFail();
        DB::table('accounts')->where('id', 1)->update(['name' => 'Cash drawer', 'chart_of_account_id' => $this->cash->id]);
        $this->bank = app(SettlementAccountService::class)->create(['name' => 'IOB', 'kind' => 'bank'], $this->context(), 1);
        app(SettlementAccountService::class)->create(['name' => 'SBI', 'kind' => 'bank'], $this->context(), 1);
        if (!Schema::hasTable('money_transfers')) {
            Schema::create('money_transfers', function (Blueprint $table) {
                $table->increments('id'); $table->unsignedBigInteger('company_id')->nullable();
                $table->string('reference_no'); $table->unsignedInteger('from_account_id');
                $table->unsignedInteger('to_account_id'); $table->double('amount'); $table->timestamps();
            });
        }
    }

    public function test_transfer_entry_and_register_use_settlement_accounts_and_keep_advanced_options(): void
    {
        foreach (['transfer-entry' => '/accounting/vouchers?voucher_type=contra', 'vouchers' => '/accounting/vouchers', 'money-transfers' => '/money-transfers', 'voucher-entry' => '/accounting/voucher/entry'] as $key => $url) {
            $response = $this->get($url)->assertOk()->assertSee('Accounting screens')->assertSee('accounting-workspace.css');
            if (getenv('ERP_EXPORT_ACCOUNTING_BROWSER')) file_put_contents(base_path('scratch/accounting-'.$key.'.html'), $response->getContent());
        }
        $html = $this->get('/accounting/vouchers?voucher_type=contra')->assertSee('From account')->assertSee('To account')->assertSee('Use advanced debit / credit lines')->assertSee('Post Transfer')->getContent();
        $document = new \DOMDocument(); @$document->loadHTML($html);
        $xpath = new \DOMXPath($document);
        $this->assertSame(1, $xpath->query('//select[@id="transfer-from"]/option[@value="'.$this->bank->chart_of_account_id.'"]')->length);
        $this->assertSame(1, $xpath->query('//select[@id="transfer-to"]/option[@value="'.$this->cash->id.'"]')->length);
        $salesId = ChartOfAccount::forCompany($this->context())->where('type', 'revenue')->value('id');
        $this->assertSame(0, $xpath->query('//select[@id="transfer-from"]/option[@value="'.$salesId.'"]')->length);
        $this->assertSame(0, JournalEntry::count());
    }

    public function test_express_voucher_only_offers_and_previews_journal_series(): void
    {
        $context = $this->context();
        $scope = [
            'company_id' => $context->companyId,
            'branch_id' => $context->branchId,
            'financial_year_id' => $context->financialYearId,
            'next_number' => 4,
            'padding' => 5,
            'reset_policy' => 'financial_year',
            'is_default' => true,
        ];
        DocumentSeries::create($scope + ['document_type' => 'sale_payment', 'code' => 'RECEIPT', 'prefix' => 'REC-']);
        DocumentSeries::create($scope + ['document_type' => 'journal', 'code' => 'VOUCHER', 'prefix' => 'VCH-']);

        $this->get('/accounting/voucher/entry')->assertOk()
            ->assertSee('VOUCHER (VCH-...)')->assertDontSee('RECEIPT (REC-...)');
        $this->getJson('/accounting/voucher/next-number')->assertOk()->assertJsonPath('next_number', 'VCH-00004');
        $this->getJson('/accounting/voucher/next-number?series_code=VOUCHER')->assertOk()->assertJsonPath('next_number', 'VCH-00004');
        $this->getJson('/accounting/voucher/next-number?series_code=RECEIPT')->assertOk()->assertJsonPath('next_number', 'AUTO');
    }

    public function test_transfer_posts_source_and_destination_and_register_excludes_other_vouchers(): void
    {
        $bankBefore = (float) $this->bank->ledger->current_balance;
        $cashBefore = (float) $this->cash->current_balance;
        $this->post('/accounting/vouchers', ['voucher_type' => 'contra', 'entry_date' => '2026-10-03',
            'description' => 'IOB cash withdrawal', 'reference_mode' => 'on_account', 'idempotency_key' => 'ui-transfer',
            'items' => [['chart_of_account_id' => $this->bank->chart_of_account_id, 'credit' => '5000.0000'],
                ['chart_of_account_id' => $this->cash->id, 'debit' => '5000.0000']]])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertEquals($bankBefore - 5000, (float) $this->bank->ledger->fresh()->current_balance);
        $this->assertEquals($cashBefore + 5000, (float) $this->cash->fresh()->current_balance);
        $entry = JournalEntry::where('voucher_type', 'contra')->firstOrFail();
        $this->assertSame(['asset'], $entry->items->map(fn ($line) => $line->account->type)->unique()->values()->all());
        app(VoucherService::class)->post(['voucher_type' => 'journal', 'entry_date' => '2026-10-03',
            'description' => 'Unrelated adjustment fixture', 'reference_mode' => 'on_account', 'idempotency_key' => 'ui-adjustment',
            'items' => [['chart_of_account_id' => $this->cash->id, 'debit' => 1],
                ['chart_of_account_id' => $this->bank->chart_of_account_id, 'credit' => 1]]], $this->context());
        foreach (['/money-transfers', '/accounting/vouchers?voucher_type=contra'] as $url) {
            $this->get($url)->assertOk()->assertSee('IOB cash withdrawal')->assertDontSee('Unrelated adjustment fixture');
        }
        $this->get('/money-transfers?start_date=2026-10-04&end_date=2026-10-05')->assertOk()->assertDontSee('IOB cash withdrawal');
        if (getenv('ERP_EXPORT_ACCOUNTING_BROWSER')) file_put_contents(base_path('scratch/accounting-money-transfers-posted.html'), $this->get('/money-transfers')->getContent());
    }

    public function test_default_account_can_be_set_when_none_exists_and_is_company_owned(): void
    {
        DB::table('accounts')->where('company_id', $this->company->id)->update(['is_default' => false]);
        $foreign = Account::forceCreate(['company_id' => $this->other->id, 'name' => 'Foreign default', 'is_active' => true, 'is_default' => true]);
        $this->post('/make-default/'.$this->bank->id)->assertOk();
        $this->assertTrue((bool) $this->bank->fresh()->is_default);
        $this->assertTrue((bool) $foreign->fresh()->is_default);
        $this->post('/make-default/'.$foreign->id)->assertNotFound();
        $this->assertTrue((bool) $this->bank->fresh()->is_default);
    }

    public function test_navigation_respects_company_role_permissions(): void
    {
        DB::table('company_user')->where('company_id', $this->company->id)->where('user_id', 1)->update(['role_id_override' => 4]);
        $permission = DB::table('permissions')->insertGetId(['name' => 'accounting.reports.view', 'guard_name' => 'web']);
        DB::table('role_has_permissions')->insert(['role_id' => 4, 'permission_id' => $permission]);
        $html = $this->get('/accounting/vouchers')->assertOk()->assertDontSee('id="accounting-voucher-form"', false)->getContent();
        $document = new \DOMDocument(); @$document->loadHTML($html); $xpath = new \DOMXPath($document);
        $this->assertSame(0, $xpath->query('//nav[@aria-label="Accounting screens"]//a[@href="'.route('accounting.voucher.entry').'"]')->length);
        $this->assertSame(0, $xpath->query('//nav[@aria-label="Accounting screens"]//a[@href="'.route('money-transfers.index').'"]')->length);
    }

    public function test_accounting_links_and_form_routes_resolve_from_rendered_screens(): void
    {
        (require database_path('migrations/2026_10_19_000012_create_collection_tracking.php'))->up();
        $entry = app(VoucherService::class)->post(['voucher_type' => 'contra', 'entry_date' => '2026-10-03',
            'description' => 'Transfer link audit', 'reference_mode' => 'on_account', 'idempotency_key' => 'link-audit-transfer',
            'items' => [['chart_of_account_id' => $this->bank->chart_of_account_id, 'credit' => 10],
                ['chart_of_account_id' => $this->cash->id, 'debit' => 10]]], $this->context());
        DB::table('journal_entries')->where('id', $entry->id)->update(['financial_year_id' => null]);
        $pages = ['/accounts', '/money-transfers', '/accounting/vouchers?voucher_type=contra', '/accounting/monthly-ledger/'.$this->cash->id];
        foreach (['vouchers', 'voucher/entry', 'journal-entries', 'general-ledger', 'trial-balance', 'profit-loss', 'balance-sheet',
            'cash-flow-statement', 'inventory-close', 'day-book', 'cash-book', 'ageing', 'collections', 'chart-of-accounts', 'semantic-account-mappings'] as $path) {
            $pages[] = '/accounting/'.$path;
        }
        $links = [];
        $forms = [];
        foreach ($pages as $page) {
            $response = $this->get($page);
            $this->assertSame(200, $response->getStatusCode(), $page);
            $document = new \DOMDocument(); @$document->loadHTML($response->getContent());
            $xpath = new \DOMXPath($document);
            foreach ($xpath->query('//*[@id="content"]//a[@href] | //*[@id="account"]//a[@href]') as $link) {
                $url = $link->getAttribute('href');
                $path = parse_url($url, PHP_URL_PATH) ?: '';
                if (preg_match('~^/(accounting/|money-transfers(?:/|$)|accounts(?:/|$))~', $path)) {
                    $query = parse_url($url, PHP_URL_QUERY);
                    $links[$path.($query ? '?'.$query : '')] = true;
                }
            }
            foreach ($xpath->query('//*[@id="content"]//form[@action]') as $form) {
                $action = parse_url($form->getAttribute('action'), PHP_URL_PATH);
                if (!$action) continue; // Modal actions populated from their row at runtime.
                $override = $xpath->query('.//input[@name="_method"]', $form)->item(0);
                $method = strtoupper($override?->getAttribute('value') ?: $form->getAttribute('method') ?: 'GET');
                $forms[$method.' '.$action] = [$method, $action];
            }
        }
        foreach (array_diff(array_keys($links), $pages) as $link) {
            $this->assertSame(200, $this->get($link)->getStatusCode(), $link);
        }
        foreach ($forms as $key => [$method, $action]) {
            $route = app('router')->getRoutes()->match(\Illuminate\Http\Request::create($action, $method));
            $this->assertContains($method, $route->methods(), $key);
        }
        $this->assertArrayHasKey('/accounting/vouchers/'.$entry->id, $links);
        $this->assertArrayHasKey('/accounting/vouchers?voucher_type=receipt', $links);
        $detail = $this->getJson('/accounting/journal-entries/'.$entry->id)->assertOk()->assertJsonCount(2, 'items');
        if (getenv('ERP_EXPORT_ACCOUNTING_BROWSER')) {
            $receivable = ChartOfAccount::forCompany($this->context())->where('control_type', 'ar')->firstOrFail();
            $income = ChartOfAccount::forCompany($this->context())->where('code', '4010')->firstOrFail();
            $customer = \App\Models\Customer::forCompany($this->context())->firstOrFail();
            app(VoucherService::class)->post(['voucher_type' => 'journal', 'entry_date' => '2026-10-03',
                'description' => 'Open customer balance for allocation', 'reference_mode' => 'new_reference', 'idempotency_key' => 'party-audit-balance',
                'items' => [['chart_of_account_id' => $receivable->id, 'debit' => 100, 'partner_type' => 'customer', 'partner_id' => $customer->id],
                    ['chart_of_account_id' => $income->id, 'credit' => 100]]], $this->context());
            $this->post('/accounting/journal-entries', ['entry_date' => '2026-10-03', 'description' => 'Manual journal for reversal dialog',
                'items' => [['chart_of_account_id' => $this->cash->id, 'debit' => 1],
                    ['chart_of_account_id' => $income->id, 'credit' => 1]]])->assertRedirect()->assertSessionHasNoErrors();
            file_put_contents(base_path('scratch/accounting-journal-detail.json'), $detail->getContent());
            file_put_contents(base_path('scratch/accounting-journal-populated.html'), $this->get('/accounting/journal-entries?start_date=2026-10-03&end_date=2026-10-03')->getContent());
            file_put_contents(base_path('scratch/accounting-voucher-detail.html'), $this->get('/accounting/vouchers/'.$entry->id)->getContent());
            file_put_contents(base_path('scratch/accounting-receipt-entry.html'), $this->get('/accounting/vouchers?voucher_type=receipt')->getContent());
        }
    }
}
