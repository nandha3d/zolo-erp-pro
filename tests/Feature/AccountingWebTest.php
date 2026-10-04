<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;

class AccountingWebTest extends TestCase
{
    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adminUser = User::first();
    }

    public function test_chart_of_accounts_page_loads(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('accounting.coa'));
        $response->assertStatus(200)
            ->assertSee('Chart of Accounts')
            ->assertSee('Assets');
    }

    public function test_journal_entries_page_loads(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('accounting.journal-entries'));
        $response->assertStatus(200)
            ->assertSee('General Journal Entries');
    }

    public function test_trial_balance_page_loads(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('accounting.trial-balance'));
        $response->assertStatus(200)
            ->assertSee('Trial Balance')
            ->assertSee('Balanced Trial Balance');
    }

    public function test_profit_loss_page_loads(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('accounting.profit-loss'));
        $response->assertStatus(200)
            ->assertSee('Statement of Profit');
    }

    public function test_balance_sheet_page_loads(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('accounting.balance-sheet'));
        $response->assertStatus(200)
            ->assertSee('Balance Sheet')
            ->assertSee('Accounting Equation Balanced');
    }

    public function test_general_ledger_page_loads(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('accounting.general-ledger'));
        $response->assertStatus(200)
            ->assertSee('General Ledger');
    }

    public function test_pos_page_loads_with_neo_design(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('sale.pos'));
        $response->assertStatus(200)
            ->assertSee('zolo-erp-neo.css');
    }
}
