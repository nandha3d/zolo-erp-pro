<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\CompanyBranch;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class BackfillCompanyContext extends Command
{
    protected $signature = 'erp:backfill-company-context {--dry-run : Report without changing any data}';

    protected $description = 'Assign audited legacy core records to DEFAULT company and MAIN branch';

    private const TABLES = [
        'categories', 'brands', 'units', 'customer_groups', 'products', 'customers',
        'suppliers', 'warehouses', 'billers', 'sales', 'product_sales', 'purchases',
        'product_purchases', 'payments', 'product_warehouse', 'transfers', 'product_transfer',
        'returns', 'product_returns', 'return_purchases', 'purchase_product_return',
        'adjustments', 'product_adjustments', 'stock_counts', 'expenses', 'accounts',
        'chart_of_accounts', 'fiscal_years', 'journal_entries', 'journal_items',
        'semantic_account_mappings', 'inventory_closes',
    ];

    // Only audited references are checked; other operational tables are a later package.
    private const REFERENCES = [
        'products' => ['category_id' => 'categories', 'brand_id' => 'brands', 'unit_id' => 'units'],
        'customers' => ['customer_group_id' => 'customer_groups'],
        'sales' => ['customer_id' => 'customers', 'warehouse_id' => 'warehouses', 'biller_id' => 'billers'],
        'purchases' => ['supplier_id' => 'suppliers', 'warehouse_id' => 'warehouses'],
        'product_sales' => ['sale_id' => 'sales', 'product_id' => 'products'],
        'product_purchases' => ['purchase_id' => 'purchases', 'product_id' => 'products'],
        'payments' => ['sale_id' => 'sales', 'purchase_id' => 'purchases', 'account_id' => 'accounts'],
        'product_warehouse' => ['product_id' => 'products', 'warehouse_id' => 'warehouses'],
        'transfers' => ['from_warehouse_id' => 'warehouses', 'to_warehouse_id' => 'warehouses'],
        'product_transfer' => ['transfer_id' => 'transfers', 'product_id' => 'products'],
        'returns' => ['sale_id' => 'sales', 'customer_id' => 'customers', 'warehouse_id' => 'warehouses'],
        'product_returns' => ['return_id' => 'returns', 'product_id' => 'products'],
        'return_purchases' => ['purchase_id' => 'purchases', 'supplier_id' => 'suppliers', 'warehouse_id' => 'warehouses'],
        'purchase_product_return' => ['return_id' => 'return_purchases', 'product_id' => 'products'],
        'adjustments' => ['warehouse_id' => 'warehouses'],
        'product_adjustments' => ['adjustment_id' => 'adjustments', 'product_id' => 'products'],
        'journal_items' => ['journal_entry_id' => 'journal_entries', 'chart_of_account_id' => 'chart_of_accounts'],
        'chart_of_accounts' => ['parent_id' => 'chart_of_accounts'],
        'semantic_account_mappings' => ['account_id' => 'chart_of_accounts'],
        'inventory_closes' => ['warehouse_id' => 'warehouses', 'journal_entry_id' => 'journal_entries'],
    ];

    // Existing digital/service items and opening-stock payments intentionally use zero.
    private const ZERO_IS_UNSET = ['products.unit_id', 'payments.account_id'];

    public function handle(): int
    {
        foreach (['companies', 'company_branches', 'company_user', 'company_user_branches', 'users', 'warehouses', 'fiscal_years'] as $table) {
            if (!Schema::hasTable($table)) {
                $this->error('Company migrations must run first.');
                return self::FAILURE;
            }
        }

        $default = Company::where('code', 'DEFAULT')->first();
        if (Company::count() > ($default ? 1 : 0)) {
            $this->error('Legacy backfill requires a single DEFAULT company. Review ownership before adding other companies.');
            return self::FAILURE;
        }
        if ($default && $default->base_currency_id !== null
            && (!Schema::hasTable('currencies')
                || !DB::table('currencies')->where('id', $default->base_currency_id)->exists())) {
            $this->error('DEFAULT company currency ID does not exist in currencies. No data changed.');
            return self::FAILURE;
        }

        $tables = [];
        $rows = [];
        foreach (self::TABLES as $table) {
            if (!Schema::hasTable($table)) {
                $rows[] = [$table, 'absent', 'skipped'];
                continue;
            }
            if (!Schema::hasColumn($table, 'company_id')) {
                $this->error("Missing {$table}.company_id; run the core company-key migration.");
                return self::FAILURE;
            }
            $tables[] = $table;
            $rows[] = [$table, DB::table($table)->count(), DB::table($table)->whereNull('company_id')->count()];
        }
        $this->table(['Table', 'Rows', 'Unassigned'], $rows);

        $errors = $this->validateLegacyData($tables, $default?->id ?? 0);
        if ($errors) {
            foreach ($errors as $error) {
                $this->error($error);
            }
            $this->error('No data changed. Resolve reported ownership/schema problems before backfill.');
            return self::FAILURE;
        }
        try {
            $defaults = $default ? [] : $this->companyDefaults();
        } catch (RuntimeException $error) {
            $this->error($error->getMessage());
            return self::FAILURE;
        }

        foreach (['sales', 'purchases'] as $table) {
            if (in_array($table, $tables, true)) {
                $count = DB::table($table)->whereDate('created_at', '1970-01-01')->count();
                $this->line("{$table}: {$count} legacy opening-date records; dates and FY assignment remain unchanged.");
            }
        }

        if ($this->option('dry-run')) {
            $this->info('Dry run: no writes. DEFAULT/MAIN and missing memberships will be created during backfill.');
            return self::SUCCESS;
        }
        if (!$this->laravel->isDownForMaintenance()) {
            $this->error('Real backfill requires maintenance mode and paused workers/scheduler. Run php artisan down first.');
            return self::FAILURE;
        }

        DB::transaction(function () use ($tables, $defaults) {
            $company = Company::firstOrCreate(['code' => 'DEFAULT'], $defaults);
            $branch = CompanyBranch::firstOrCreate(['company_id' => $company->id, 'code' => 'MAIN'], ['name' => 'Main branch']);

            // Recheck after acquiring the company lock, before any legacy write.
            DB::table('companies')->where('id', $company->id)->lockForUpdate()->first();
            if (Company::count() !== 1 || $this->validateLegacyData($tables, $company->id)) {
                throw new RuntimeException('Ownership changed during backfill; transaction rolled back.');
            }
            foreach ($tables as $table) {
                DB::table($table)->whereNull('company_id')->orderBy('id')->chunkById(500, function ($rows) use ($table, $company) {
                    DB::table($table)->whereIn('id', $rows->pluck('id'))->whereNull('company_id')
                        ->update(['company_id' => $company->id]);
                });
            }
            DB::table('warehouses')->where('company_id', $company->id)->whereNull('branch_id')
                ->update(['branch_id' => $branch->id]);
            DB::table('fiscal_years')->where('company_id', $company->id)->where('is_closed', true)
                ->where('status', 'open')->update(['status' => 'closed']);

            DB::table('users')->orderBy('id')->chunkById(500, function ($users) use ($company, $branch) {
                foreach ($users as $user) {
                    DB::table('company_user')->updateOrInsert(
                        ['company_id' => $company->id, 'user_id' => $user->id],
                        ['is_default' => true]
                    );
                    DB::table('company_user_branches')->updateOrInsert([
                        'company_id' => $company->id, 'user_id' => $user->id, 'branch_id' => $branch->id,
                    ]);
                }
            });
            foreach ($tables as $table) {
                if (DB::table($table)->whereNull('company_id')->exists()) {
                    throw new RuntimeException("{$table} still has unassigned records; transaction rolled back.");
                }
            }
        });

        $this->info('Backfill complete for audited core tables. Isolation is not activated; keep other companies inactive until cutover checks pass.');
        return self::SUCCESS;
    }

    private function validateLegacyData(array $tables, int $defaultId): array
    {
        $errors = [];
        foreach ($tables as $table) {
            $count = DB::table($table)->whereNotNull('company_id')->where('company_id', '<>', $defaultId)->count();
            if ($count) {
                $errors[] = "{$table}: {$count} rows have unexpected company ownership.";
            }
            foreach (self::REFERENCES[$table] ?? [] as $column => $parent) {
                if (!Schema::hasColumn($table, $column)) {
                    continue;
                }
                $zeroIsUnset = in_array($table.'.'.$column, self::ZERO_IS_UNSET, true);
                if (!in_array($parent, $tables, true)) {
                    $references = DB::table($table)->whereNotNull($column);
                    if ($zeroIsUnset) {
                        $references->where($column, '<>', 0);
                    }
                    if ($references->exists()) {
                        $errors[] = "{$table}.{$column}: referenced table {$parent} is unavailable.";
                    }
                    continue;
                }
                $references = DB::table($table.' as source')->leftJoin($parent.' as parent', 'source.'.$column, '=', 'parent.id')
                    ->whereNotNull('source.'.$column)->whereNull('parent.id');
                if ($zeroIsUnset) {
                    $references->where('source.'.$column, '<>', 0);
                }
                $orphans = $references->count();
                if ($orphans) {
                    $errors[] = "{$table}.{$column}: {$orphans} orphan references.";
                }
            }
        }
        if (in_array('warehouses', $tables, true)) {
            $invalidBranches = DB::table('warehouses as source')
                ->leftJoin('company_branches as branch', 'source.branch_id', '=', 'branch.id')
                ->whereNotNull('source.branch_id')
                ->where(function ($query) use ($defaultId) {
                    $query->whereNull('branch.id')->orWhere('branch.company_id', '<>', $defaultId);
                })->count();
            if ($invalidBranches) {
                $errors[] = "warehouses: {$invalidBranches} invalid branch assignments.";
            }
        }
        if (in_array('fiscal_years', $tables, true)) {
            $invalid = DB::table('fiscal_years')->whereColumn('start_date', '>', 'end_date')->count();
            $overlaps = DB::table('fiscal_years as first')->join('fiscal_years as second', function ($join) {
                $join->on('first.id', '<', 'second.id')->on('first.start_date', '<=', 'second.end_date')
                    ->on('first.end_date', '>=', 'second.start_date');
            })->count();
            if ($invalid || $overlaps) {
                $errors[] = "fiscal_years: {$invalid} invalid ranges, {$overlaps} overlapping pairs; existing dates require review.";
            }
        }

        return $errors;
    }

    private function companyDefaults(): array
    {
        $settings = Schema::hasTable('general_settings') ? DB::table('general_settings')->latest()->first() : null;
        $timezone = $settings->timezone ?? config('app.timezone', 'UTC');
        if (!in_array($timezone, timezone_identifiers_list(), true)) {
            throw new RuntimeException('General settings contain an invalid timezone.');
        }
        $currencyId = isset($settings->currency) && ctype_digit((string) $settings->currency)
            ? (int) $settings->currency : null;
        if ($currencyId !== null && (!Schema::hasTable('currencies')
            || !DB::table('currencies')->where('id', $currencyId)->exists())) {
            throw new RuntimeException('General settings currency ID does not exist in currencies.');
        }

        return [
            'legal_name' => ($settings->company_name ?? null) ?: ($settings->site_title ?? config('app.name', 'zoloERP')),
            'trade_name' => $settings->site_title ?? null,
            'base_currency_id' => $currencyId,
            'timezone' => $timezone,
            'status' => 'active',
        ];
    }
}
