<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\ChartOfAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\ERP\CompanyWriteGuard;
use App\Services\Platform\CompanyContextResolver;

class ChartOfAccountsController extends Controller
{
    public function index(Request $request)
    {
        $context = app(CompanyContextResolver::class)->forActor();
        $accounts = ChartOfAccount::forCompany($context)->with(['children' => fn ($q) => $q->forCompany($context)])
            ->whereNull('parent_id')
            ->orderBy('code')
            ->get();

        $allAccounts = ChartOfAccount::forCompany($context)->where('is_active', true)->orderBy('code')->get();

        return view('backend.accounting.chart_of_accounts', compact('accounts', 'allAccounts'));
    }

    public function store(Request $request)
    {
        $context = app(CompanyContextResolver::class)->forActor();
        abort_unless(app(CompanyContextResolver::class)->canManageFinancialYears($request->user()->id, $context->companyId), 403);
        $this->validate($request, [
            'code' => ['required', 'string', 'max:50', \Illuminate\Validation\Rule::unique('chart_of_accounts', 'code')->where('company_id', $context->companyId)],
            'name' => 'required|string|max:255',
            'type' => 'required|in:asset,liability,equity,revenue,expense',
            'sub_type' => 'required|string|max:100',
            'description' => 'nullable|string',
            'parent_id' => 'nullable|integer|min:1',
            'opening_balance' => 'nullable|numeric|in:0',
        ]);

        $openingBalance = (float) ($request->opening_balance ?? 0);

        DB::transaction(function () use ($request, $openingBalance, $context) {
            $guard = app(CompanyWriteGuard::class);
            $guard->begin($context, null);
            if ($request->filled('parent_id')) {
                $parent = $guard->owned(ChartOfAccount::class, $request->parent_id, $context, 'parent_id');
                abort_unless($parent->is_active && $parent->type === $request->type, 422, 'Parent account must be active and have the same account type.');
            }
            (new ChartOfAccount)->forceFill([
            'company_id' => $context->companyId,
            'code' => $request->code,
            'name' => $request->name,
            'type' => $request->type,
            'sub_type' => $request->sub_type,
            'parent_id' => $request->parent_id,
            'opening_balance' => $openingBalance,
            'current_balance' => $openingBalance,
            'description' => $request->description,
            'is_system' => false,
            'is_active' => true,
        ])->save();
        });

        return redirect()->back()->with('message', 'Account created successfully.');
    }

    public function update(Request $request, $id)
    {
        $context = app(CompanyContextResolver::class)->forActor();
        abort_unless(app(CompanyContextResolver::class)->canManageFinancialYears($request->user()->id, $context->companyId), 403);
        $this->validate($request, [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        DB::transaction(function () use ($request, $id, $context) {
            app(CompanyWriteGuard::class)->begin($context, null);
            $account = ChartOfAccount::forCompany($context)->whereKey($id)->lockForUpdate()->firstOrFail();
            $account->update([
            'name' => $request->name,
            'description' => $request->description,
        ]);
        });

        return redirect()->back()->with('message', 'Account updated successfully.');
    }

    public function destroy(Request $request, $id)
    {
        $context = app(CompanyContextResolver::class)->forActor();
        abort_unless(app(CompanyContextResolver::class)->canManageFinancialYears($request->user()->id, $context->companyId), 403);

        $blocker = DB::transaction(function () use ($id, $context) {
            app(CompanyWriteGuard::class)->begin($context, null);
            $account = ChartOfAccount::forCompany($context)->whereKey($id)->lockForUpdate()->firstOrFail();
            $blocker = $this->deleteBlocker($account);
            if (!$blocker) {
                $account->delete();
            }
            return $blocker;
        });

        return $blocker
            ? redirect()->back()->with('not_permitted', 'Account not deleted: '.$blocker)
            : redirect()->back()->with('message', 'Account deleted successfully.');
    }

    /** Only an unused, user-created account may be deleted; anything the ledger points at stays. */
    private function deleteBlocker(ChartOfAccount $account): ?string
    {
        if ($account->is_system) {
            return 'system accounts are required by posting.';
        }
        if (ChartOfAccount::where('parent_id', $account->id)->exists()) {
            return 'it has sub-accounts.';
        }
        if ($account->journalItems()->exists() || DB::table('account_open_items')->where('account_id', $account->id)->exists()) {
            return 'it has postings.';
        }
        if (DB::table('accounts')->where('chart_of_account_id', $account->id)->exists()) {
            return 'a cash/bank payment account is linked to it.';
        }
        if (DB::table('semantic_account_mappings')->where('company_id', $account->company_id)->where('account_id', $account->id)->exists()) {
            return 'it is mapped to a posting role in Account Mappings.';
        }
        if (abs((float) $account->current_balance) > 0.004) {
            return 'its balance is not zero.';
        }
        return null;
    }
}
