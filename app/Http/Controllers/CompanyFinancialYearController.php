<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Services\Platform\CompanyContextResolver;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CompanyFinancialYearController extends Controller
{
    public function __construct(private CompanyContextResolver $resolver)
    {
    }

    public function index(Request $request)
    {
        $company = $this->company($request);
        $years = $company->fiscalYears()->orderBy('start_date')->get();
        $today = CarbonImmutable::now($company->timezone)->toDateString();
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(['company_id' => $company->id, 'business_date' => $today, 'financial_years' => $years]);
        }

        return view('backend.company.financial_year_setup', compact('company', 'years', 'today'));
    }

    public function store(Request $request)
    {
        $company = $this->company($request);
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'start_date' => 'required|date_format:Y-m-d|after_or_equal:1000-01-01',
            'end_date' => 'required|date_format:Y-m-d|after_or_equal:start_date',
        ]);
        $year = DB::transaction(function () use ($company, $data) {
            // Serialize all setup writes for this company, including creation into an empty FY table.
            Company::whereKey($company->id)->lockForUpdate()->firstOrFail();
            if ($company->fiscalYears()->whereDate('start_date', '<=', $data['end_date'])
                ->whereDate('end_date', '>=', $data['start_date'])->exists()) {
                throw ValidationException::withMessages(['start_date' => 'Financial year dates overlap an existing company year.']);
            }

            return $company->fiscalYears()->create($data + ['status' => 'open', 'is_closed' => false]);
        });
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(['financial_year' => $year], 201);
        }

        return redirect()->route('company.financial-years.setup', ['company_id' => $company->id])
            ->with('status', 'Financial year created.');
    }

    private function company(Request $request): Company
    {
        $value = $request->header('X-Company-ID') ?? $request->query('company_id')
            ?? ($request->hasSession() ? $request->session()->get('company_id') : null);
        $id = $value === null ? null : filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) {
            throw ValidationException::withMessages(['company_id' => 'Company ID must be a positive integer.']);
        }
        $company = $this->resolver->authorizedCompany($request->user()->id, $id);
        if (!$this->resolver->canManageFinancialYears($request->user()->id, $company->id)) {
            throw new AuthorizationException('Company financial-year administration denied.');
        }

        return $company;
    }
}
