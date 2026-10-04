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
use App\Services\Platform\CompanyContextResolver;

class JournalEntryController extends Controller
{
    protected AccountingService $accountingService;

    public function __construct(AccountingService $accountingService)
    {
        $this->accountingService = $accountingService;
    }

    public function index(Request $request)
    {
        $context = app(CompanyContextResolver::class)->forActor();
        $request->validate(['start_date' => 'nullable|date_format:Y-m-d', 'end_date' => 'nullable|date_format:Y-m-d|after_or_equal:start_date']);
        $query = JournalEntry::forCompany($context)->with(['creator' => fn ($q) => $q->whereIn('id', \Illuminate\Support\Facades\DB::table('company_user')->select('user_id')->where('company_id', $context->companyId))]);

        if ($request->filled('reference_type')) {
            $query->where('reference_type', $request->reference_type);
        }
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereDate('entry_date', '>=', $request->start_date)->whereDate('entry_date', '<=', $request->end_date);
        }

        $entries = $query->orderBy('id', 'desc')->paginate(25);
        $accounts = ChartOfAccount::forCompany($context)->where('is_active', true)->orderBy('code')->get();

        return view('backend.accounting.journal_entries', compact('entries', 'accounts'));
    }

    public function show(Request $request, $id)
    {
        $context = app(CompanyContextResolver::class)->forActor();
        $entry = JournalEntry::withOwnedItems($context)->with(['creator' => fn ($q) => $q->whereIn('id', \Illuminate\Support\Facades\DB::table('company_user')->select('user_id')->where('company_id', $context->companyId))])->findOrFail($id);
        return response()->json($entry);
    }

    public function store(Request $request)
    {
        $this->validate($request, [
            'entry_date' => 'required|date_format:Y-m-d',
            'description' => 'required|string|max:500',
            'items' => 'required|array|min:2',
            'items.*.chart_of_account_id' => 'required|integer|min:1',
            'items.*.debit' => 'nullable|numeric|min:0',
            'items.*.credit' => 'nullable|numeric|min:0',
            'items.*.memo' => 'nullable|string|max:255',
        ]);

        try {
            $this->accountingService->postJournalEntry([
                'entry_date' => $request->entry_date,
                'description' => $request->description,
                'reference_type' => 'manual',
                'created_by' => Auth::id(),
            ], $request->items, app(CompanyContextResolver::class)->forActor());

            return redirect()->back()->with('message', 'Journal Entry posted successfully.');
        } catch (\Illuminate\Validation\ValidationException | \Illuminate\Auth\Access\AuthorizationException $e) {
            throw $e;
        } catch (Exception $e) {
            return redirect()->back()->with('not_permitted', $e->getMessage());
        }
    }
}
