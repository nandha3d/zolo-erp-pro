<?php

use App\Models\Company;
use App\Services\Platform\CompanyDirectoryService;
use Illuminate\Database\Migrations\Migration;

/** Companies created before starter masters existed get a customer group, category, biller, Cash/Bank accounts and Optech masters. */
return new class extends Migration
{
    public function up(): void
    {
        foreach (Company::all() as $company) {
            app(CompanyDirectoryService::class)->installStarterMasters($company);
        }
    }

    public function down(): void
    {
        // Masters may already be used by documents; nothing to undo safely.
    }
};
