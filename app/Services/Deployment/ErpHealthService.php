<?php

namespace App\Services\Deployment;

use App\Models\Accounting\FiscalYear;
use App\Services\Accounting\LedgerReconciliationService;
use App\Services\Accounting\SemanticAccountResolver;
use App\Services\Inventory\InventoryReconciliationService;
use App\Services\Platform\CapabilityCatalog;
use App\Services\Platform\CapabilityService;
use App\Services\Platform\CompanyContext;
use App\Services\Platform\CompanyContextResolver;
use App\Support\LedgerAmount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ErpHealthService
{
    public function inspect(CompanyContext $context, int $actor, bool $deployment = false): array
    {
        $resolver = app(CompanyContextResolver::class); $context = $resolver->forActor($context, $actor);
        if (!$resolver->canManageFinancialYears($actor, $context->companyId)) throw new \Illuminate\Auth\Access\AuthorizationException('Company administrator required for health inspection.');
        $checks = [];
        $check = function (string $key, callable $inspect) use (&$checks) {
            try { $result = $inspect(); $checks[$key] = ['ok' => $result === true, 'details' => $result === true ? 'Passed' : $result]; }
            catch (\Throwable $error) { $checks[$key] = ['ok' => false, 'details' => 'Check failed; review configuration or run the owned reconciliation command.']; }
        };
        $check('schema', fn () => collect(['companies', 'company_branches', 'fiscal_years', 'document_series', 'stock_movements',
            'stock_movement_lines', 'account_open_items', 'account_allocations', 'journal_entries', 'journal_items', 'semantic_account_mappings'])
            ->every(fn ($table) => Schema::hasTable($table)) ?: 'Required foundation tables are missing.');
        $check('storage', fn () => is_writable(storage_path('app')) && is_writable(storage_path('framework')) ?: 'Application storage is not writable.');
        $check('financial_year', function () use ($context) {
            $year = FiscalYear::where('company_id', $context->companyId)->findOrFail($context->financialYearId);
            return !$year->is_closed && $year->status === 'open' ?: 'Selected financial year is closed for posting.';
        });
        $roles = ['ar', 'ap', 'cash', 'inventory', 'sales', 'cogs'];
        if (config('compliance.enabled')) $roles = array_merge($roles, ['output_tax_cgst', 'output_tax_sgst', 'output_tax_igst', 'input_tax_cgst', 'input_tax_sgst', 'input_tax_igst', 'rounding']);
        if (config('operations.enabled')) $roles = array_merge($roles, ['production_costs', 'damage']);
        $check('account_mappings', function () use ($context, $roles) {
            $missing = [];
            foreach ($roles as $role) {
                try { app(SemanticAccountResolver::class)->resolve($role, $context); }
                catch (\Throwable $error) { $missing[] = $role; }
            }
            return $missing ? ['missing_or_invalid_roles' => $missing] : true;
        });
        $check('document_series', function () use ($context) {
            $types = ['sale', 'purchase', 'journal', 'sale_payment', 'purchase_payment'];
            if (config('operations.enabled')) $types = array_merge($types, ['production', 'job_work_order', 'job_work_dispatch', 'job_work_receipt']);
            $configured = DB::table('document_series')->where('company_id', $context->companyId)->where('branch_id', $context->branchId)
                ->where('financial_year_id', $context->financialYearId)->where('is_default', true)->where('next_number', '>', 0)->pluck('document_type')->all();
            $missing = array_values(array_diff($types, $configured));
            return $missing ? ['missing_types' => $missing] : true;
        });
        $check('capability_dependencies', function () use ($context) {
            $service = app(CapabilityService::class); $snapshot = $service->snapshot($context->companyId);
            $configured = DB::table('company_capabilities as state')->join('capabilities as c', 'c.id', '=', 'state.capability_id')
                ->where('company_id', $context->companyId)->where('enabled', true)->pluck('c.key');
            $invalid = [];
            foreach ($configured as $key) foreach ($service->dependencies($key) as $dependency) if (empty($snapshot[$dependency]['enabled'])) $invalid[] = $key;
            return $invalid ? ['invalid_dependencies' => array_values(array_unique($invalid))] : true;
        });
        $check('stock_reconciliation', fn () => app(InventoryReconciliationService::class)->differences($context->companyId) === [] ?: 'Stock ledger differs from quantity projections.');
        $check('accounting_reconciliation', fn () => app(LedgerReconciliationService::class)->reconcile($context, false, $actor)['is_reconciled'] ?: 'Trial balance, account projections or AR/AP control balances differ.');
        $check('inventory_value', function () use ($context) {
            $account = app(SemanticAccountResolver::class)->resolve('inventory', $context);
            return LedgerAmount::units(DB::table('stock_movement_lines')->where('company_id', $context->companyId)->sum('value')) === LedgerAmount::units($account->current_balance)
                ?: 'Inventory accounting value differs from the stock ledger.';
        });
        if ($deployment) {
            $check('production_configuration', fn () => app()->environment('production') && !config('app.debug') && filled(config('app.key')) ?: 'Production environment, disabled debug and application key are required.');
            $check('foundation_acceptance', fn () => CapabilityCatalog::OPTIONAL_ACTIVATION_READY ?: 'Full company isolation acceptance remains closed.');
            $check('backup_and_restore', function () {
                $receipt = app(BackupService::class)->latestReceipt();
                return $receipt && $receipt['offsite_verified'] && $receipt['restore_verified']
                    && strtotime($receipt['completed_at']) >= now()->subHours(config('deployment.backup_max_age_hours'))->timestamp
                    ?: 'A fresh verified off-site backup and matching restore rehearsal are required.';
            });
            $check('delivery_worker', fn () => !config('compliance.enabled') || config('deployment.dispatch_worker_confirmed', false) ?: 'Confirm the delivery scheduler/worker and provider UAT before activation.');
        }
        $delivery = Schema::hasTable('document_dispatch_logs') ? DB::table('document_dispatch_logs')->where('company_id', $context->companyId)->where('branch_id', $context->branchId)->groupBy('status')->selectRaw('status, COUNT(*) as total')->pluck('total', 'status')->all() : [];
        return ['delivery_counts' => $delivery, 'company_id' => $context->companyId, 'branch_id' => $context->branchId, 'financial_year_id' => $context->financialYearId,
            'deployment' => $deployment, 'ok' => collect($checks)->every(fn ($check) => $check['ok']), 'checks' => $checks];
    }
}
