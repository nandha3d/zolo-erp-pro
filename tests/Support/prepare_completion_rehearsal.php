<?php

// Creates a disposable sample database. This is never customer or production acceptance evidence.
if (getenv('ERP_TEST_MYSQL') !== '1' || getenv('ERP_TEST_MYSQL_DATABASE') !== 'zolo_test_completion_uat') {
    throw new RuntimeException('Rehearsal preparation requires ERP_TEST_MYSQL=1 and zolo_test_completion_uat. This database is replaced.');
}

require __DIR__.'/legacy_mysql_bootstrap.php';

use App\Services\Accounting\LedgerReconciliationService;
use App\Services\Inventory\InventoryReconciliationService;
use App\Services\Platform\CompanyContext;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (!app()->environment('testing') || DB::connection()->getDatabaseName() !== 'zolo_test_completion_uat') {
    throw new RuntimeException('Refusing to prepare a rehearsal outside the named testing database.');
}

$root = base_path('scratch/completion-rehearsal');
if (!is_dir($root)) {
    mkdir($root, 0700, true);
}
$password = bin2hex(random_bytes(20));

DB::transaction(function () use ($password) {
    foreach (['users', 'customers', 'suppliers', 'billers', 'warehouses'] as $table) {
        $columns = Schema::getColumnListing($table);
        foreach (DB::table($table)->orderBy('id')->get() as $row) {
            $values = [];
            foreach (['name', 'company_name', 'email', 'phone', 'phone_number', 'address', 'city', 'state', 'country', 'tax_no', 'vat_number'] as $field) {
                if (!in_array($field, $columns, true)) {
                    continue;
                }
                $values[$field] = match ($field) {
                    'email' => $table.'-'.$row->id.'@example.invalid',
                    'phone', 'phone_number' => '0000000000',
                    'tax_no', 'vat_number' => null,
                    default => 'Sample '.$table.' '.$row->id,
                };
            }
            if ($table === 'users') {
                $values['password'] = Hash::make($password);
                if (in_array('remember_token', $columns, true)) {
                    $values['remember_token'] = null;
                }
            }
            DB::table($table)->where('id', $row->id)->update($values);
        }
    }
});

$company = DB::table('companies')->where('code', 'DEFAULT')->first();
DB::table('companies')->where('id', $company->id)->update(['legal_name' => 'Sample UAT Company', 'trade_name' => 'Sample UAT Company']);
$branch = DB::table('company_branches')->where('company_id', $company->id)->orderBy('id')->first();
$year = DB::table('fiscal_years')->where('company_id', $company->id)->orderByDesc('id')->first();
$context = $year ? new CompanyContext($company->id, $branch->id, $year->id) : null;

// Record the exact sample source before opening stock. Preserve inconsistent source quantities for review.
$hash = hash_init('sha256');
$counts = [];
foreach (collect(Schema::getTables())->pluck('name')->sort()->values() as $table) {
    $counts[$table] = DB::table($table)->count();
    hash_update($hash, $table."\n");
    $primary = collect(Schema::getIndexes($table))->firstWhere('primary', true)['columns'] ?? [];
    $query = DB::table($table);
    foreach ($primary as $column) {
        $query->orderBy($column);
    }
    foreach ($query->cursor() as $row) {
        hash_update($hash, json_encode($row, JSON_THROW_ON_ERROR)."\n");
    }
}
$sourceHash = hash_final($hash);
$openingResult = Artisan::call('erp:stock-opening', ['--date' => $year?->end_date ?? '2026-10-05']);
$openingOutput = Artisan::output();

// Synthetic setup an administrator would do at go-live: posting-role mappings, a default series per document
// type, and an opening inventory journal against owner capital so ledger value and accounting agree.
// Nothing here is derived from customer data; a real cutover needs reviewed mappings and a reviewed opening offset.
$setup = [];
if ($context) {
    app(App\Services\Accounting\SemanticAccountResolver::class)->seedCompany($company->id);
    foreach (['sale' => 'SAL', 'purchase' => 'PUR', 'journal' => 'JE', 'sale_payment' => 'REC', 'purchase_payment' => 'PAY'] as $type => $prefix) {
        app(App\Services\Platform\DocumentNumberService::class)->configure($context, [
            'document_type' => $type, 'code' => 'MAIN', 'prefix' => $prefix.'-'.$year->id.'-', 'padding' => 6,
        ], 1);
        $setup['series'][] = $type;
    }
    $value = App\Support\LedgerAmount::units(DB::table('stock_movement_lines')->where('company_id', $company->id)->sum('value'));
    $inventory = app(App\Services\Accounting\SemanticAccountResolver::class)->resolve('inventory', $context);
    if ($value > 0) {
        $capital = DB::table('chart_of_accounts')->where('company_id', $company->id)->where('code', '3010')->value('id');
        // Manual vouchers need manual-posting accounts; a real cutover posts opening values through erp:import-opening.
        DB::table('chart_of_accounts')->whereIn('id', [$inventory->id, $capital])->update(['allow_manual_posting' => true]);
        app(App\Services\Accounting\AccountingPostingService::class)->postJournalEntry([
            'reference_type' => 'manual', 'reference_no' => 'SAMPLE-OPENING-STOCK',
            'entry_date' => $year->start_date, 'description' => 'Synthetic sample opening inventory', 'created_by' => 1,
            'posting_key' => 'sample-opening-stock', 'idempotency_key' => 'sample-opening-stock',
        ], [
            ['chart_of_account_id' => $inventory->id, 'debit' => App\Support\LedgerAmount::decimal($value)],
            ['chart_of_account_id' => $capital, 'credit' => App\Support\LedgerAmount::decimal($value)],
        ], $context);
        $setup['opening_inventory_value'] = App\Support\LedgerAmount::decimal($value);
    }
}
$stockDifferences = app(InventoryReconciliationService::class)->differences($company->id);
$accounting = $context ? app(LedgerReconciliationService::class)->reconcile($context, false, 1) : null;
$record = [
    'created_at_utc' => gmdate('c'),
    'source_kind' => 'synthetic repository seed; not customer retained data',
    'database' => DB::connection()->getDatabaseName(),
    'source_rows_sha256' => $sourceHash,
    'row_counts_before_stock_opening' => $counts,
    'company_id' => $company->id,
    'branch_id' => $branch->id,
    'financial_year_id' => $year?->id,
    'stock_opening_exit_code' => $openingResult,
    'stock_opening_output' => $openingOutput,
    'synthetic_setup' => $setup,
    'stock_differences' => $stockDifferences,
    'accounting_reconciliation' => $accounting,
    'customer_acceptance' => 'pending real reviewer',
    'provider_acceptance' => 'pending real provider',
    'printer_acceptance' => 'pending target hardware',
    'production_activation' => false,
];
file_put_contents($root.'/manifest.json', json_encode($record, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n");
file_put_contents($root.'/access.json', json_encode([
    'environment' => 'local disposable sample only',
    'admin_email' => DB::table('users')->where('id', 1)->value('email'),
    'password' => $password,
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n");
echo 'Sample rehearsal created. Private evidence: '.$root.'/manifest.json'.PHP_EOL;
echo 'Unresolved stock differences: '.count($stockDifferences).PHP_EOL;
echo 'Customer, accountant, provider and hardware acceptance remain pending.'.PHP_EOL;
