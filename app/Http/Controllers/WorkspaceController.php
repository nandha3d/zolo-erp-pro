<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Warehouse;
use App\Services\Platform\CapabilityCatalog;
use App\Services\Platform\CapabilityService;
use App\Services\Platform\CompanyContext;
use App\Services\Platform\CompanyContextResolver;
use App\Services\Platform\CompanySetupService;
use App\Services\Platform\WorkspaceNavigation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WorkspaceController extends Controller
{
    public function companies(Request $request)
    {
        return response()->json(['data' => app(CompanySetupService::class)->choices($request->user()->id)]);
    }

    public function index(Request $request)
    {
        $context = $request->attributes->get(CompanyContext::class);
        $actor = $request->user()->id;
        $data = ['context' => $context, 'company' => Company::findOrFail($context->companyId),
            'navigation' => app(WorkspaceNavigation::class)->items($context, $actor),
            'choices' => app(CompanySetupService::class)->choices($actor),
            'branches' => DB::table('company_branches')->where('company_id', $context->companyId)->where('is_active', true)
                ->whereIn('id', DB::table('company_user_branches')->where('company_id', $context->companyId)->where('user_id', $actor)->select('branch_id'))->get(['id', 'name']),
            'years' => DB::table('fiscal_years')->where('company_id', $context->companyId)->orderByDesc('start_date')->get(['id', 'name', 'status'])];
        return $request->is('api/*') ? response()->json(['data' => $data['navigation'], 'context' => $context]) : view('backend.workspace.home', $data);
    }

    public function select(Request $request)
    {
        $data = $request->validate(['company_id' => 'required|integer|min:1', 'branch_id' => 'required|integer|min:1', 'financial_year_id' => 'required|integer|min:1']);
        $context = app(CompanyContextResolver::class)->resolve($request->user()->id, $data['company_id'], $data['branch_id'], $data['financial_year_id']);
        $request->session()->put(['company_id' => $context->companyId, 'branch_id' => $context->branchId, 'financial_year_id' => $context->financialYearId]);
        return redirect('/workspace')->with('status', 'Active branch and financial year updated.');
    }

    public function setup(Request $request)
    {
        $company = app(CompanySetupService::class)->company($request->user()->id, $this->companyId($request));
        $branches = $company->branches()->where('is_active', true)->whereIn('id', DB::table('company_user_branches')
            ->where('company_id', $company->id)->where('user_id', $request->user()->id)->select('branch_id'))->get(['id', 'name']);
        $data = ['company' => $company->only(['id', 'code', 'legal_name', 'trade_name', 'state_code', 'base_currency_id']),
            'print_format' => $company->settings_json['print_format'] ?? 'a4',
            'branches' => $branches, 'years' => $company->fiscalYears()->get(['id', 'name', 'start_date', 'end_date', 'status']),
            'warehouses' => Warehouse::where('company_id', $company->id)->whereIn('branch_id', $branches->pluck('id'))->get(['id', 'branch_id', 'name']),
            'currencies' => DB::table('currencies')->get(['id', 'code', 'name']),
            'capabilities' => app(CapabilityService::class)->snapshot($company->id),
            'activation_ready' => CapabilityCatalog::OPTIONAL_ACTIVATION_READY];
        return $request->is('api/*') ? response()->json(['data' => $data]) : view('backend.workspace.setup', $data);
    }

    public function save(Request $request, string $section)
    {
        $service = app(CompanySetupService::class);
        $record = match ($section) {
            'company' => $service->update($request->user()->id, $this->companyId($request), $request->all()),
            'branch' => $service->branch($request->user()->id, $this->companyId($request), $request->all()),
            'warehouse' => $service->location($request->user()->id, $this->companyId($request), $request->all()),
        };
        return $request->is('api/*') ? response()->json(['data' => ['id' => $record->id]], 200)
            : back()->with('status', 'Setup saved.');
    }

    private function companyId(Request $request): ?int
    {
        $value = $request->header('X-Company-ID') ?? $request->query('company_id') ?? ($request->hasSession() ? $request->session()->get('company_id') : null);
        return $value === null ? null : validator(['company_id' => $value], ['company_id' => 'required|integer|min:1'])->validate()['company_id'];
    }
}
