<?php

namespace App\Console\Commands;

use App\Services\Accounting\SemanticAccountResolver;
use App\Services\Platform\CompanyContextResolver;
use Illuminate\Console\Command;

class SeedAccountingMappings extends Command
{
    protected $signature = 'erp:account-mappings {--company= : Company ID} {--actor= : Authorized administrator ID}';
    protected $description = 'Create missing semantic role mappings from unambiguous company leaf accounts without changing existing mappings.';

    public function handle(): int
    {
        if (!$this->option('company') || !$this->option('actor')) {
            $this->error('Supply --company and --actor.');
            return self::FAILURE;
        }
        $resolver = app(CompanyContextResolver::class);
        $company = $resolver->authorizedCompany((int) $this->option('actor'), (int) $this->option('company'));
        if (!$resolver->canManageFinancialYears((int) $this->option('actor'), $company->id)) {
            $this->error('Company administrator required.');
            return self::FAILURE;
        }
        app(SemanticAccountResolver::class)->seedCompany($company->id);
        $this->info('Missing roles created. Review unmapped roles before posting.');
        return self::SUCCESS;
    }
}
