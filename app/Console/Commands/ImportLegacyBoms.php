<?php

namespace App\Console\Commands;

use App\Services\Manufacturing\BomService;
use App\Services\Platform\CompanyContextResolver;
use Illuminate\Console\Command;

class ImportLegacyBoms extends Command
{
    protected $signature = 'erp:import-boms {--company=} {--branch=} {--year=} {--actor=} {--dry-run}';
    protected $description = 'Validate legacy recipe arrays and import immutable BOM versions without changing source recipes or stock';

    public function handle(BomService $service, CompanyContextResolver $resolver): int
    {
        try {
            foreach (['company', 'branch', 'year', 'actor'] as $field) {
                if (filter_var($this->option($field), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) throw new \InvalidArgumentException('Explicit company, branch, year and authorized actor IDs are required.');
            }
            $context = $resolver->resolve((int) $this->option('actor'), (int) $this->option('company'), (int) $this->option('branch'), (int) $this->option('year'));
            $result = $service->importLegacy($context, (int) $this->option('actor'), (bool) $this->option('dry-run'));
            $this->info(json_encode($result, JSON_THROW_ON_ERROR));
            return self::SUCCESS;
        } catch (\Throwable $error) { $this->error($error->getMessage()); return self::FAILURE; }
    }
}
