<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\ChartOfAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\Platform\CompanyContextResolver;
use App\Services\ERP\CompanyWriteGuard;
use Illuminate\Validation\ValidationException;

class SemanticMappingController extends Controller
{
    public function index(Request $request)
    {
        $context = app(CompanyContextResolver::class)->forActor();
        $mappings = DB::table('semantic_account_mappings')->where('company_id', $context->companyId)->get();
        foreach ($mappings as $mapping) {
            $account = ChartOfAccount::forCompany($context)->find($mapping->account_id);
            $mapping->account_code = $account?->code;
            $mapping->account_name = $account?->name;
        }
        $accounts = ChartOfAccount::forCompany($context)->where('is_active', true)->orderBy('code')->get();

        return view('backend.accounting.semantic_mappings', compact('mappings', 'accounts'));
    }

    public function update(Request $request)
    {
        $context = app(CompanyContextResolver::class)->forActor();
        abort_unless(app(CompanyContextResolver::class)->canManageFinancialYears($request->user()->id, $context->companyId), 403);
        $request->validate([
            'mappings' => 'required|array|min:1',
            'mappings.*' => 'required|integer|min:1',
        ]);

        DB::transaction(function () use ($request, $context) {
            $guard = app(CompanyWriteGuard::class);
            $guard->begin($context, null);
            foreach ($request->mappings as $role => $accountId) {
                $mapping = DB::table('semantic_account_mappings')->where('company_id', $context->companyId)
                    ->where('semantic_role', $role)->lockForUpdate()->first();
                if (!$mapping) {
                    throw ValidationException::withMessages(['mappings' => 'Select an existing company accounting role.']);
                }
                $account = $guard->owned(ChartOfAccount::class, $accountId, $context, 'mappings');
                if (!$account->is_active) {
                    throw ValidationException::withMessages(['mappings' => 'Mapping account must be active.']);
                }
                $resolver = app(\App\Services\Accounting\SemanticAccountResolver::class);
                try {
                    $resolver->validate($role, $account);
                } catch (\InvalidArgumentException $error) {
                    throw ValidationException::withMessages(['mappings' => $error->getMessage()]);
                }
                $control = $resolver->role($role);
                if (in_array($control, ['ar', 'ap', 'cash', 'bank'], true)) {
                    $account->forceFill(['control_type' => $control, 'allow_manual_posting' => !in_array($control, ['ar', 'ap'], true)])->save();
                }
                DB::table('semantic_account_mappings')->where('id', $mapping->id)->where('company_id', $context->companyId)->update([
                    'account_id' => $account->id, 'account_code' => $account->code, 'account_name' => $account->name,
                    'updated_at' => now(),
                ]);
            }
        });

        return redirect()->back()->with('message', 'Company account mappings saved. New automatic postings use these mappings.');
    }
}
