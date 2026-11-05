<?php

namespace Tests\Feature;

use App\Models\Accounting\ChartOfAccount;
use App\Services\Accounting\SettlementAccountService;
use Illuminate\Support\Facades\DB;
use Tests\Support\OperationsTestCase;

class AccountingSetupUiTest extends OperationsTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DB::table('accounts')->where('id', 1)->update(['chart_of_account_id' => ChartOfAccount::where('company_id', $this->company->id)->where('code', '1010')->value('id')]);
    }

    public function test_setup_pages_explain_bank_selection_and_offer_working_statement_and_edit_links(): void
    {
        $bank = app(SettlementAccountService::class)->create(['name' => 'IOB', 'kind' => 'bank'], $this->context(), 1);
        $paths = ['chart-of-accounts' => '/accounting/chart-of-accounts', 'semantic-account-mappings' => '/accounting/semantic-account-mappings', 'accounts' => '/accounts'];
        foreach ($paths as $key => $path) {
            $response = $this->get($path)->assertOk()->assertSee('accounting-workspace.css');
            if (getenv('ERP_EXPORT_ACCOUNTING_BROWSER')) file_put_contents(base_path('scratch/accounting-'.$key.'.html'), $response->getContent());
        }
        $this->get('/accounting/chart-of-accounts')->assertSee('coa-category')->assertSee('coa-clear-filters')->assertSee('new-account-sub_type')->assertSee('data-id="'.$bank->chart_of_account_id.'"', false);
        $this->get('/accounting/semantic-account-mappings')->assertSee('Two banks?')->assertSee('mapping-search')->assertSee('mapping-status')->assertSee('do not map every account');
        $this->get('/accounts')->assertSee(route('accounting.general-ledger', ['account_id' => $bank->chart_of_account_id]), false)
            ->assertSee(route('accounting.coa', ['edit' => $bank->chart_of_account_id]), false)
            ->assertDontSee('href="'.route('accounts.statement', $bank->id).'"', false);
        $this->assertSame(0, DB::table('journal_entries')->count());
    }

    public function test_bank_mapping_offers_owned_active_compatible_leaf_accounts_and_keeps_selected_bank(): void
    {
        $bank = app(SettlementAccountService::class)->create(['name' => 'IOB', 'kind' => 'bank'], $this->context(), 1);
        $group = ChartOfAccount::forceCreate(['company_id' => $this->company->id, 'code' => 'BANK-GROUP', 'name' => 'Bank group', 'type' => 'asset', 'sub_type' => 'bank', 'control_type' => 'none', 'is_active' => true]);
        ChartOfAccount::forceCreate(['company_id' => $this->company->id, 'parent_id' => $group->id, 'code' => 'INACTIVE-CHILD', 'name' => 'Inactive child', 'type' => 'asset', 'sub_type' => 'bank', 'control_type' => 'none', 'is_active' => false]);
        DB::table('semantic_account_mappings')->where('company_id', $this->company->id)->where('semantic_role', 'bank')->update(['account_id' => $bank->chart_of_account_id]);
        $html = $this->get('/accounting/semantic-account-mappings')->assertOk()->getContent();
        $document = new \DOMDocument(); @$document->loadHTML($html);
        $xpath = new \DOMXPath($document);
        $select = '//select[@name="mappings[bank]"]';
        $this->assertSame(1, $xpath->query($select.'/option[@value="'.$bank->chart_of_account_id.'"][@selected]')->length);
        $this->assertSame(0, $xpath->query($select.'/option[@value="'.$group->id.'"]')->length);
        $receivable = ChartOfAccount::where('company_id', $this->company->id)->where('control_type', 'ar')->value('id');
        $this->assertSame(0, $xpath->query($select.'/option[@value="'.$receivable.'"]')->length);
    }

    public function test_account_setup_hides_management_actions_for_a_company_reader(): void
    {
        DB::table('company_user')->where('company_id', $this->company->id)->where('user_id', 1)->update(['role_id_override' => 4]);
        $this->get('/accounting/chart-of-accounts')->assertOk()->assertDontSee('data-target="#addAccountModal"', false)->assertDontSee('data-url=', false);
        $response = $this->get('/accounting/semantic-account-mappings')->assertOk()->assertSee('Ask a company administrator')->assertDontSee('form="mapping-form"', false);
        $document = new \DOMDocument(); @$document->loadHTML($response->getContent());
        $xpath = new \DOMXPath($document);
        $this->assertGreaterThan(0, $xpath->query('//form[@id="mapping-form"]//select[@disabled]')->length);
        $this->assertSame(0, $xpath->query('//form[@id="mapping-form"]//button[@type="submit"]')->length);
    }
}
