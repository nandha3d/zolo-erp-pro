<?php

use App\Models\Accounting\ChartOfAccount;
use App\Services\Accounting\SemanticAccountResolver;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * Year close now moves profit into Retained Earnings, and party opening balances post against an Opening Balance
 * Adjustment account. Standard-chart companies get 3020 tagged as retained earnings, a new 3040, and both roles mapped.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('chart_of_accounts') || !Schema::hasTable('semantic_account_mappings')) return;
        $template = collect(ChartOfAccountsSeeder::accounts())->keyBy('code');
        foreach (DB::table('chart_of_accounts')->whereNotNull('company_id')->distinct()->pluck('company_id') as $companyId) {
            $codes = ChartOfAccount::where('company_id', $companyId)->pluck('id', 'code')->all();
            if (!isset($codes['1000'], $codes['3000'])) continue; // not built from the standard chart
            DB::transaction(function () use ($template, $companyId, $codes) {
                // Retag only the untouched template account.
                ChartOfAccount::where('company_id', $companyId)->where('code', '3020')->where('name', 'Retained Earnings')
                    ->where('sub_type', 'equity')->update(['sub_type' => 'retained_earnings']);
                if (!isset($codes['3040'])) {
                    $account = $template['3040'];
                    (new ChartOfAccount)->forceFill(['company_id' => $companyId, 'code' => '3040', 'name' => $account['name'],
                        'type' => $account['type'], 'sub_type' => $account['sub_type'], 'parent_id' => $codes['3000'],
                        'is_system' => $account['is_system'], 'description' => $account['description'], 'is_active' => true])->save();
                }
            });
            app(SemanticAccountResolver::class)->seedCompany((int) $companyId);
        }
    }

    public function down(): void
    {
        // Accounts may already carry postings; nothing to undo safely.
    }
};
