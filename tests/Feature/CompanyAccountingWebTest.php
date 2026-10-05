<?php

namespace Tests\Feature;

use App\Models\Accounting\ChartOfAccount;
use App\Models\Accounting\JournalEntry;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\CompanyContextTestCase;

class CompanyAccountingWebTest extends CompanyContextTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->seedCompanyReaderFixtures();
        $this->installAccountingFoundation();
        $this->actingAs(User::findOrFail(1));
        Schema::table('warehouses', fn (Blueprint $table) => $table->boolean('is_active')->default(true));
        Schema::table('products', fn (Blueprint $table) => $table->double('alert_quantity')->default(0));
        Schema::table('categories', fn (Blueprint $table) => $table->boolean('is_active')->default(true));
        Schema::table('roles', fn (Blueprint $table) => $table->string('name')->default('Admin'));
        Schema::create('permissions', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
        });
        Schema::create('role_has_permissions', function (Blueprint $table) {
            $table->unsignedInteger('role_id');
            $table->unsignedInteger('permission_id');
        });
        Schema::create('product_batches', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('product_id');
            $table->double('qty');
            $table->date('expired_date');
        });
        Schema::create('currencies', function (Blueprint $table) {
            $table->increments('id');
            $table->string('code');
            $table->string('symbol');
        });
        DB::table('currencies')->insert(['id' => 1, 'code' => 'INR', 'symbol' => 'Rs']);
        $settings = [
            'company_name' => 'Shared installation', 'currency' => '1', 'currency_position' => 'prefix',
            'date_format' => 'd/m/Y', 'decimal' => '2', 'expiry_alert_days' => '0', 'expiry_date' => null,
            'is_packing_slip' => '0', 'is_zatca' => '0', 'modules' => '', 'staff_access' => 'all',
            'timezone' => 'UTC', 'vat_registration_number' => '', 'without_stock' => '0', 'is_rtl' => '0',
            'site_title' => 'Company Accounting', 'site_logo' => '', 'favicon' => '', 'theme' => 'default.css',
            'custom_css' => '', 'font_css' => '',
        ];
        Schema::create('general_settings', function (Blueprint $table) use ($settings) {
            $table->increments('id');
            foreach (array_keys($settings) as $name) {
                $table->string($name)->nullable();
            }
            $table->timestamps();
        });
        DB::table('general_settings')->insert($settings + ['created_at' => now()]);
        foreach ([$this->company->id, $this->other->id] as $owner) {
            foreach ([['1010', 'asset', 'cash'], ['4010', 'revenue', 'sales_revenue']] as [$code, $type, $subtype]) {
                (new ChartOfAccount)->forceFill([
                    'company_id' => $owner, 'code' => $owner === $this->company->id ? $code : 'B-'.$code,
                    'name' => $owner === $this->company->id ? 'Visible '.$subtype : 'Secret B ledger',
                    'type' => $type, 'sub_type' => $subtype,
                ])->save();
            }
        }
        DB::table('semantic_account_mappings')->insert([
            ['company_id' => $this->company->id, 'semantic_role' => 'sales_revenue', 'account_id' => 2],
            ['company_id' => $this->other->id, 'semantic_role' => 'secret_b_role', 'account_id' => 4],
        ]);
    }

    public function test_accounting_pages_render_owned_data_without_global_hidden_selectors(): void
    {
        $this->withoutExceptionHandling();
        foreach (['chart-of-accounts', 'journal-entries', 'trial-balance', 'profit-loss', 'balance-sheet',
            'general-ledger', 'cash-flow-statement', 'semantic-account-mappings', 'inventory-close'] as $path) {
            $response = $this->get('/accounting/'.$path)->assertOk();
            $response->assertDontSee('Secret B')->assertDontSee('secret_b_role')
                ->assertDontSee('id="notification-modal"', false)->assertDontSee('id="expense-modal"', false)
                ->assertDontSee('id="supplier-modal"', false);
        }
        $this->get('/accounting/inventory-close')->assertSee('Inventory Close Unavailable')->assertDontSee('postCloseModal');
        $this->assertSame('Company A', config('company_name'));
    }

    public function test_web_manual_journals_validate_ownership_dates_and_nested_reads(): void
    {
        $payload = ['entry_date' => '2026-10-03', 'description' => 'Owned journal', 'created_by' => 2,
            'items' => [['chart_of_account_id' => 1, 'debit' => 8], ['chart_of_account_id' => 2, 'credit' => 8]]];
        $this->postJson('/accounting/journal-entries', $payload)->assertRedirect();
        $entry = JournalEntry::firstOrFail();
        $this->assertSame($this->company->id, (int) $entry->company_id);
        $this->assertSame(1, (int) $entry->created_by);
        DB::table('journal_items')->insert(['company_id' => $this->other->id, 'journal_entry_id' => $entry->id,
            'chart_of_account_id' => 3, 'debit' => 100, 'credit' => 0]);
        $this->getJson('/accounting/journal-entries/'.$entry->id)->assertOk()->assertJsonCount(2, 'items');
        DB::table('journal_entries')->where('id', $entry->id)->update(['company_id' => $this->other->id]);
        $this->getJson('/accounting/journal-entries/'.$entry->id)->assertNotFound();
        $payload['items'][1]['chart_of_account_id'] = 4;
        $this->postJson('/accounting/journal-entries', $payload)->assertUnprocessable();
        $payload['items'][1]['chart_of_account_id'] = 2;
        $this->year->update(['lock_date' => '2026-10-03']);
        $this->postJson('/accounting/journal-entries', $payload)->assertUnprocessable();
        $this->assertSame(1, JournalEntry::count());
        $this->getJson('/accounting/general-ledger?account_id=3')->assertNotFound();
    }

    public function test_account_and_mapping_writes_are_owned_atomic_and_admin_only(): void
    {
        $payload = ['code' => 'NEW', 'name' => 'Owned account', 'type' => 'asset', 'sub_type' => 'cash',
            'parent_id' => 1, 'company_id' => $this->other->id];
        $this->postJson('/accounting/chart-of-accounts', $payload)->assertRedirect();
        $this->assertSame($this->company->id, (int) ChartOfAccount::where('code', 'NEW')->value('company_id'));
        $payload['code'] = 'REJECTED';
        $payload['parent_id'] = 3;
        $this->postJson('/accounting/chart-of-accounts', $payload)->assertUnprocessable();
        $payload['parent_id'] = 1;
        $payload['opening_balance'] = 5;
        $this->postJson('/accounting/chart-of-accounts', $payload)->assertUnprocessable();
        $this->putJson('/accounting/chart-of-accounts/3', ['name' => 'Spoof'])->assertNotFound();
        $before = DB::table('semantic_account_mappings')->orderBy('id')->get()->toJson();
        $this->postJson('/accounting/semantic-account-mappings/update', ['mappings' => ['sales_revenue' => 4]])->assertUnprocessable();
        $this->postJson('/accounting/semantic-account-mappings', ['mappings' => ['sales_revenue' => 2, 'secret_b_role' => 1]])->assertUnprocessable();
        $this->assertSame($before, DB::table('semantic_account_mappings')->orderBy('id')->get()->toJson());
        $this->postJson('/accounting/semantic-account-mappings/update', ['mappings' => ['sales_revenue' => 2]])->assertRedirect();
        DB::table('company_user')->where('company_id', $this->company->id)->where('user_id', 1)->update(['role_id_override' => 4]);
        $this->postJson('/accounting/chart-of-accounts', $payload)->assertForbidden();
        $this->putJson('/accounting/chart-of-accounts/1', ['name' => 'Spoof'])->assertForbidden();
        $this->postJson('/accounting/semantic-account-mappings', ['mappings' => ['sales_revenue' => 2]])->assertForbidden();
    }

    public function test_close_posting_is_blocked_before_any_effect_even_with_spoofed_totals(): void
    {
        foreach (['/accounting/inventory-close', '/accounting/inventory-close/post'] as $uri) {
            $this->postJson($uri, ['total_valuation' => 99999999, 'valuation_date' => '2026-10-03'])->assertStatus(409);
        }
        $this->assertSame(0, DB::table('inventory_closes')->count());
        $this->assertSame(0, JournalEntry::count());
        $this->getJson('/accounting/inventory-close?warehouse_id=2')->assertUnprocessable();
        $this->getJson('/accounting/chart-of-accounts', ['X-Company-ID' => $this->other->id])->assertForbidden();
    }

    public function test_common_cache_and_navigation_use_company_and_membership_role(): void
    {
        DB::table('permissions')->insert(['id' => 1, 'name' => 'sidebar_product']);
        DB::table('role_has_permissions')->insert(['role_id' => 1, 'permission_id' => 1]);
        $this->get('/accounting/chart-of-accounts')->assertOk()->assertSee('id="product"', false);
        $this->other->users()->attach(1, ['role_id_override' => 4]);
        DB::table('company_user_branches')->insert(['company_id' => $this->other->id, 'user_id' => 1, 'branch_id' => $this->otherBranch->id]);
        $this->get('/accounting/chart-of-accounts', ['X-Company-ID' => $this->other->id])->assertOk()
            ->assertSee('Secret B ledger')->assertDontSee('Visible cash')->assertDontSee('id="product"', false);
        $this->assertSame('Company B', config('company_name'));
        $categories = view()->shared('categories_list');
        $this->assertSame(['Secret B'], $categories->pluck('name')->all());
        DB::table('categories')->where('id', 2)->update(['name' => 'Updated B']);
        $this->get('/accounting/chart-of-accounts', ['X-Company-ID' => $this->other->id])->assertOk();
        $this->assertSame(['Updated B'], view()->shared('categories_list')->pluck('name')->all());
        $this->get('/accounting/chart-of-accounts')->assertOk()->assertSee('Visible cash')->assertDontSee('Secret B ledger');
        $this->assertSame(['Visible A'], view()->shared('categories_list')->pluck('name')->all());
    }

    public function test_voucher_hub_and_reports_render_owned_controls_and_named_permission_limits(): void
    {
        $this->get('/accounting/vouchers')->assertOk()->assertSee('Voucher Hub')->assertSee('Visible cash')
            ->assertDontSee('Secret B ledger')->assertSee('accounting-voucher-form')->assertSee('Period controls');
        foreach (['/accounting/day-book', '/accounting/cash-book', '/accounting/ageing', '/accounting/monthly-ledger/1'] as $uri) {
            $this->get($uri)->assertOk()->assertDontSee('Secret B ledger');
        }
        DB::table('company_user')->where('company_id', $this->company->id)->where('user_id', 1)->update(['role_id_override' => 4]);
        $this->get('/accounting/vouchers')->assertForbidden();
        $this->getJson('/api/v1/accounting/day-book')->assertForbidden();
        $reportPermissionId = DB::table('permissions')->insertGetId(['name' => 'accounting.reports.view']);
        DB::table('role_has_permissions')->insert(['role_id' => 4, 'permission_id' => $reportPermissionId]);
        $this->get('/accounting/vouchers')->assertOk()->assertDontSee('accounting-voucher-form')
            ->assertSee('Ask a company administrator')->assertDontSee('Period controls');
        $this->getJson('/api/v1/accounting/day-book')->assertOk();
        DB::table('permissions')->insert(['id' => 5, 'name' => 'accounting.voucher.post']);
        DB::table('role_has_permissions')->insert(['role_id' => 4, 'permission_id' => 5]);
        $this->get('/accounting/vouchers')->assertOk()->assertSee('accounting-voucher-form')->assertDontSee('Period controls');
    }
}
