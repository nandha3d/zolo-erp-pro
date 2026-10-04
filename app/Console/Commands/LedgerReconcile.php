<?php

namespace App\Console\Commands;

use App\Services\Accounting\LedgerReconciliationService;
use App\Services\Platform\CompanyContextResolver;
use Illuminate\Console\Command;

class LedgerReconcile extends Command
{
    protected $signature = 'erp:ledger-reconcile {--company= : Company ID} {--actor= : Authorized administrator ID} {--branch= : Authorized branch ID} {--year= : Financial year ID} {--rebuild : Rebuild cached account balances} {--force : Confirm rebuilding projections}';
    protected $description = 'Compare company journal balances with account projections and AR/AP open items.';

    public function handle(): int
    {
        if (!$this->option('company') || !$this->option('actor') || ($this->option('rebuild') && !$this->option('force'))) {
            $this->error('Supply --company and --actor; --rebuild also requires --force.');
            return self::FAILURE;
        }
        $context = app(CompanyContextResolver::class)->resolve((int) $this->option('actor'), (int) $this->option('company'),
            $this->option('branch') ? (int) $this->option('branch') : null, $this->option('year') ? (int) $this->option('year') : null);
        $result = app(LedgerReconciliationService::class)->reconcile($context, (bool) $this->option('rebuild'), (int) $this->option('actor'));
        $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        return $result['is_reconciled'] ? self::SUCCESS : self::FAILURE;
    }
}
