<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\JournalItem;
use App\Models\Accounting\ChartOfAccount;
use App\Services\Accounting\AccountingService;
use Illuminate\Http\Request;
use Exception;
use Auth;

class JournalEntryController extends Controller
{
    protected AccountingService $accountingService;

    public function __construct(AccountingService $accountingService)
    {
        $this->accountingService = $accountingService;
    }

    public function index(Request $request)
    {
        $query = JournalEntry::with('creator');

        if ($request->filled('reference_type')) {
            $query->where('reference_type', $request->reference_type);
        }
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('entry_date', [$request->start_date, $request->end_date]);
        }

        $entries = $query->orderBy('id', 'desc')->paginate(25);
        $accounts = ChartOfAccount::where('is_active', true)->orderBy('code')->get();

        return view('backend.accounting.journal_entries', compact('entries', 'accounts'));
    }

    public function show($id)
    {
        $entry = JournalEntry::with(['items.account', 'creator'])->findOrFail($id);
        return response()->json($entry);
    }

    public function store(Request $request)
    {
        $this->validate($request, [
            'entry_date' => 'required|date',
            'description' => 'required|string|max:500',
            'items' => 'required|array|min:2',
            'items.*.chart_of_account_id' => 'required|exists:chart_of_accounts,id',
            'items.*.debit' => 'nullable|numeric|min:0',
            'items.*.credit' => 'nullable|numeric|min:0',
            'items.*.memo' => 'nullable|string|max:255',
        ]);

        try {
            $this->accountingService->postJournalEntry([
                'entry_date' => $request->entry_date,
                'description' => $request->description,
                'reference_type' => 'manual',
                'created_by' => Auth::id() ?: 1,
            ], $request->items);

            return redirect()->back()->with('message', 'Journal Entry posted successfully.');
        } catch (Exception $e) {
            return redirect()->back()->with('not_permitted', $e->getMessage());
        }
    }
}
