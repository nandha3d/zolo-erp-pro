<?php

namespace App\Console\Commands;

use App\Services\Deployment\ErpHealthService;
use App\Services\Platform\CompanyContextResolver;
use Illuminate\Console\Command;

class ErpHealth extends Command
{
    protected $signature = 'erp:health {--company=} {--branch=} {--year=} {--actor=} {--deployment : Also require production configuration and backup/restore acceptance}';
    protected $description = 'Read-only company/FY, mappings, numbering, capability, stock, accounting and deployment health';
    public function handle(ErpHealthService $health): int
    {
        foreach (['company', 'branch', 'year', 'actor'] as $key) if (!filter_var($this->option($key), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])) {
            $this->error('Supply positive --company, --branch, --year and --actor IDs.'); return self::FAILURE;
        }
        try {
            $actor = (int) $this->option('actor');
            $context = app(CompanyContextResolver::class)->resolve($actor, (int) $this->option('company'), (int) $this->option('branch'), (int) $this->option('year'));
            $result = $health->inspect($context, $actor, (bool) $this->option('deployment'));
            $this->line(json_encode($result, JSON_THROW_ON_ERROR)); return $result['ok'] ? self::SUCCESS : self::FAILURE;
        } catch (\Throwable $error) { $this->error('Health inspection failed. Check authorized company context and database availability.'); return self::FAILURE; }
    }
}
