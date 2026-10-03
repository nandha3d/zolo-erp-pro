<?php

namespace App\Services\Platform;

use App\Models\Accounting\FiscalYear;
use App\Models\Company;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CompanyContextResolver
{
    public function resolve(
        int $userId,
        ?int $companyId = null,
        ?int $branchId = null,
        ?int $financialYearId = null,
        ?string $businessDate = null,
    ): CompanyContext {
        $user = User::find($userId);
        if (!$user || !$user->is_active || $user->is_deleted) {
            throw new AuthorizationException('Active user required for company access.');
        }
        $memberships = DB::table('company_user')->where('user_id', $userId);
        if ($companyId === null) {
            $defaults = (clone $memberships)->where('is_default', true)->pluck('company_id');
            if ($defaults->count() !== 1) {
                throw ValidationException::withMessages(['company_id' => 'Select a company with an authorized membership.']);
            }
            $companyId = (int) $defaults->sole();
        }
        if (!(clone $memberships)->where('company_id', $companyId)->exists()) {
            throw new AuthorizationException('Company access denied.');
        }
        $company = Company::whereKey($companyId)->where('status', 'active')->first();
        if (!$company) {
            throw new AuthorizationException('Company access denied.');
        }

        $branches = $company->branches()->where('is_active', true)
            ->whereIn('id', DB::table('company_user_branches')->select('branch_id')
                ->where('company_id', $companyId)->where('user_id', $userId));
        if ($branchId === null) {
            $main = (clone $branches)->where('code', 'MAIN')->first();
            if (!$main) {
                throw ValidationException::withMessages(['branch_id' => 'Select an authorized branch.']);
            }
            $branchId = $main->id;
        } elseif (!(clone $branches)->whereKey($branchId)->exists()) {
            throw new AuthorizationException('Branch access denied.');
        }

        $years = $company->fiscalYears();
        if ($financialYearId !== null) {
            $year = $years->whereKey($financialYearId)->first();
            if (!$year) {
                throw new AuthorizationException('Financial year access denied.');
            }
            if ($businessDate !== null) {
                $this->assertWithinYear($year, $this->date($businessDate));
            }
        } else {
            $date = $businessDate === null ? CarbonImmutable::now($company->timezone)->toDateString() : $this->date($businessDate);
            $matches = $years->whereDate('start_date', '<=', $date)->whereDate('end_date', '>=', $date)->get();
            if ($matches->count() !== 1) {
                throw ValidationException::withMessages(['financial_year_id' => 'Business date must match exactly one company financial year.']);
            }
            $year = $matches->sole();
        }

        // Historical reads are allowed even when the year is closed.
        return new CompanyContext($companyId, $branchId, $year->id);
    }

    public function assertPostingDate(CompanyContext $context, string $businessDate): void
    {
        $year = FiscalYear::where('company_id', $context->companyId)->whereKey($context->financialYearId)->first();
        if (!$year) {
            throw new AuthorizationException('Financial year access denied.');
        }
        $date = $this->date($businessDate);
        $this->assertWithinYear($year, $date);
        if ($year->is_closed || $year->status !== 'open' || ($year->lock_date && $date <= $year->lock_date->toDateString())) {
            throw ValidationException::withMessages(['business_date' => 'Financial year or business date is locked for posting.']);
        }
    }

    private function date(string $value): string
    {
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D', $value, $parts)
            || !checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])) {
            throw ValidationException::withMessages(['business_date' => 'Business date must be a valid YYYY-MM-DD date.']);
        }

        return $value;
    }

    private function assertWithinYear(FiscalYear $year, string $date): void
    {
        if ($date < $year->start_date->toDateString() || $date > $year->end_date->toDateString()) {
            throw ValidationException::withMessages(['business_date' => 'Business date is outside the selected financial year.']);
        }
    }
}
