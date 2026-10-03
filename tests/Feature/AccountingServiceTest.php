<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Services\Accounting\AccountingService;
use App\Models\Accounting\ChartOfAccount;
use App\Models\Accounting\JournalEntry;
use Database\Seeders\ChartOfAccountsSeeder;
use InvalidArgumentException;

class AccountingServiceTest extends TestCase
{
    protected AccountingService $accountingService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->accountingService = new AccountingService();
    }

    public function test_trial_balance_is_balanced(): void
    {
        $tb = $this->accountingService->getTrialBalance();
        $this->assertTrue($tb['is_balanced']);
        $this->assertEquals(0, $tb['difference']);
    }

    public function test_post_balanced_journal_entry(): void
    {
        $cashAcc = ChartOfAccount::where('code', '1010')->first();
        $equityAcc = ChartOfAccount::where('code', '3010')->first();

        $this->assertNotNull($cashAcc);
        $this->assertNotNull($equityAcc);

        $initialCash = (float) $cashAcc->current_balance;

        $entry = $this->accountingService->postJournalEntry(
            [
                'entry_date' => now()->toDateString(),
                'reference_type' => 'test',
                'description' => 'Test capital injection',
            ],
            [
                [
                    'chart_of_account_id' => $cashAcc->id,
                    'debit' => 5000,
                    'credit' => 0,
                    'memo' => 'Debit Cash',
                ],
                [
                    'chart_of_account_id' => $equityAcc->id,
                    'debit' => 0,
                    'credit' => 5000,
                    'memo' => 'Credit Capital',
                ],
            ]
        );

        $this->assertInstanceOf(JournalEntry::class, $entry);
        $this->assertTrue($entry->isBalanced());
        $this->assertEquals(5000, $entry->total_debit);
        $this->assertEquals(5000, $entry->total_credit);

        // Verify account balance updated
        $cashAcc->refresh();
        $this->assertEquals($initialCash + 5000, (float) $cashAcc->current_balance);
    }

    public function test_unbalanced_journal_entry_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $cashAcc = ChartOfAccount::where('code', '1010')->first();
        $equityAcc = ChartOfAccount::where('code', '3010')->first();

        $this->accountingService->postJournalEntry(
            [
                'entry_date' => now()->toDateString(),
                'reference_type' => 'test',
                'description' => 'Unbalanced entry',
            ],
            [
                [
                    'chart_of_account_id' => $cashAcc->id,
                    'debit' => 5000,
                    'credit' => 0,
                ],
                [
                    'chart_of_account_id' => $equityAcc->id,
                    'debit' => 0,
                    'credit' => 4000, // Mis-match!
                ],
            ]
        );
    }

    public function test_balance_sheet_assets_equal_liabilities_and_equity(): void
    {
        $bs = $this->accountingService->getBalanceSheet();
        $this->assertTrue($bs['is_balanced']);
        $this->assertEquals($bs['total_assets'], $bs['total_liabilities_and_equity']);
    }
}
