<?php

use App\Models\Accounting\ChartOfAccount;
use App\Services\Accounting\SemanticAccountResolver;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * The standard chart lacked accounts for shrinkage/loss, variance, returns, freight, goods in transit, input tax and
 * production costs, so those posting roles stayed unmapped and job work loss could not post. Companies that use the
 * standard chart get the missing accounts, untouched template accounts get their role tag, and unmapped roles are mapped.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('chart_of_accounts') || !Schema::hasTable('semantic_account_mappings')) return;
        $template = collect(ChartOfAccountsSeeder::accounts())->keyBy('code');
        foreach (DB::table('chart_of_accounts')->whereNotNull('company_id')->distinct()->pluck('company_id') as $companyId) {
            $codes = ChartOfAccount::where('company_id', $companyId)->pluck('id', 'code')->all();
            if (!isset($codes['1000'], $codes['5000'])) continue; // not built from the standard chart
            DB::transaction(function () use ($template, $companyId, &$codes) {
                foreach ($template as $code => $account) {
                    if (isset($codes[$code])) {
                        // Retag only accounts still exactly as the template created them.
                        ChartOfAccount::where('company_id', $companyId)->where('code', $code)->where('name', $account['name'])
                            ->whereIn('sub_type', [$account['sub_type'], 'cogs', 'other_income', 'operating_expense'])
                            ->update(['sub_type' => $account['sub_type'], 'is_system' => $account['is_system']]);
                        continue;
                    }
                    $chart = (new ChartOfAccount)->forceFill(['company_id' => $companyId, 'code' => $code, 'name' => $account['name'],
                        'type' => $account['type'], 'sub_type' => $account['sub_type'], 'parent_id' => $codes[$account['parent_code']] ?? null,
                        'is_system' => $account['is_system'], 'description' => $account['description'], 'is_active' => true]);
                    $chart->save();
                    $codes[$code] = $chart->id;
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
