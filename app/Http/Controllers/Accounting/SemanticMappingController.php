<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\ChartOfAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SemanticMappingController extends Controller
{
    public function index()
    {
        $mappings = DB::table('semantic_account_mappings')->get();
        $accounts = ChartOfAccount::where('is_active', true)->orderBy('code')->get();

        return view('backend.accounting.semantic_mappings', compact('mappings', 'accounts'));
    }

    public function update(Request $request)
    {
        $request->validate([
            'mappings' => 'required|array',
        ]);

        foreach ($request->mappings as $role => $accountId) {
            $account = ChartOfAccount::find($accountId);
            if ($account) {
                DB::table('semantic_account_mappings')->where('semantic_role', $role)->update([
                    'account_id' => $account->id,
                    'account_code' => $account->code,
                    'account_name' => $account->name,
                    'updated_at' => now(),
                ]);
            }
        }

        return redirect()->back()->with('message', 'Semantic accounting account mappings saved successfully. Future automatic ledger postings will use these updated accounts.');
    }
}
