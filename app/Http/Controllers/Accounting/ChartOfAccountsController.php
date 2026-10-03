<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\ChartOfAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Auth;

class ChartOfAccountsController extends Controller
{
    public function index()
    {
        $accounts = ChartOfAccount::with('children')
            ->whereNull('parent_id')
            ->orderBy('code')
            ->get();

        $allAccounts = ChartOfAccount::where('is_active', true)->orderBy('code')->get();

        return view('backend.accounting.chart_of_accounts', compact('accounts', 'allAccounts'));
    }

    public function store(Request $request)
    {
        $this->validate($request, [
            'code' => 'required|string|max:50|unique:chart_of_accounts,code',
            'name' => 'required|string|max:255',
            'type' => 'required|in:asset,liability,equity,revenue,expense',
            'sub_type' => 'required|string',
            'parent_id' => 'nullable|exists:chart_of_accounts,id',
            'opening_balance' => 'nullable|numeric',
        ]);

        $openingBalance = (float) ($request->opening_balance ?? 0);

        ChartOfAccount::create([
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
        ]);

        return redirect()->back()->with('message', 'Account created successfully.');
    }

    public function update(Request $request, $id)
    {
        $account = ChartOfAccount::findOrFail($id);

        $this->validate($request, [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $account->update([
            'name' => $request->name,
            'description' => $request->description,
        ]);

        return redirect()->back()->with('message', 'Account updated successfully.');
    }
}
