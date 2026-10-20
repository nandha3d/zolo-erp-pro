<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Middleware\RequireCompanyPermission;
use App\Models\Accounting\AccountOpenItem;
use App\Models\Accounting\CollectionEvent;
use App\Models\Accounting\CollectionRecord;
use App\Models\Accounting\JournalEntry;
use App\Services\Accounting\CollectionService;
use App\Services\Platform\CompanyContextResolver;
use App\Support\Database\SchemaCache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CollectionController extends Controller
{
    public function __construct()
    {
        $this->middleware(RequireCompanyPermission::class.':accounting.reports.view,core.accounting');
    }

    public function index(Request $request)
    {
        $context = app(CompanyContextResolver::class)->forActor();
        $installed = app(SchemaCache::class)->hasTable(DB::connection(), 'collection_records')
            && app(SchemaCache::class)->hasTable(DB::connection(), 'collection_events');
        $request->validate(['status' => 'nullable|in:planned,completed,cancelled,presented,cleared,bounced',
            'kind' => 'nullable|in:follow_up,pdc,reminder', 'party_id' => 'nullable|integer|min:1',
            'from' => 'nullable|date_format:Y-m-d', 'until' => 'nullable|date_format:Y-m-d|after_or_equal:from']);
        if ($request->filled('party_id')) { \App\Models\Customer::forCompany($context)->findOrFail($request->party_id); }
        $items = AccountOpenItem::forCompany($context)->where('branch_id', $context->branchId)->where('party_type', 'customer')
            ->where('original_amount', '>', 0)->get()->keyBy('id');
        $parties = \App\Models\Customer::forCompany($context)->whereIn('id', $items->pluck('party_id'))->orderBy('name')->get()->keyBy('id');
        $records = $installed ? CollectionRecord::forCompany($context)->where('branch_id', $context->branchId)
            ->whereIn('open_item_id', $items->keys())
            ->when($request->filled('party_id'), fn ($q) => $q->whereIn('open_item_id', $items->where('party_id', $request->party_id)->keys()))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('kind'), fn ($q) => $q->where('kind', $request->kind))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('action_date', '>=', $request->from))
            ->when($request->filled('until'), fn ($q) => $q->whereDate('action_date', '<=', $request->until))
            ->orderBy('action_date')->orderBy('id')->get() : collect();
        $events = $installed ? CollectionEvent::forCompany($context)->whereIn('collection_record_id', $records->pluck('id'))->orderByDesc('id')->get()->groupBy('collection_record_id') : collect();
        $receipts = JournalEntry::forCompany($context)->where('branch_id', $context->branchId)->where('status', 'posted')->where('voucher_type', 'receipt')
            ->whereNotIn('id', JournalEntry::forCompany($context)->whereNotNull('reversal_of_id')->select('reversal_of_id'))->orderByDesc('entry_date')->get();
        $validReceipts = $receipts->modelKeys();
        foreach ($records as $record) {
            $valid = $record->status !== 'cleared' || in_array((int) $record->receipt_entry_id, $validReceipts, true);
            $record->setAttribute('receipt_valid', $valid);
            $record->setAttribute('effective_status', $valid ? $record->status : 'receipt_reversed');
            $record->setAttribute('receipt_warning', $valid ? null : 'Linked receipt is missing or reversed. Review this PDC; recorded clearance history is retained.');
        }
        if ($request->expectsJson()) { return response()->json(compact('installed', 'records', 'events')); }
        $canWrite = true;
        try { \App\Services\Accounting\AccountingAccess::assert($context); }
        catch (\Illuminate\Auth\Access\AuthorizationException) { $canWrite = false; }
        return view('backend.accounting.collections', compact('installed', 'records', 'events', 'items', 'parties', 'receipts', 'canWrite'));
    }

    public function store(Request $request)
    {
        $record = app(CollectionService::class)->save($request->all(), app(CompanyContextResolver::class)->forActor());
        return $request->expectsJson() ? response()->json(['data' => $record]) : redirect()->route('accounting.collections')->with('message', 'Collection record saved.');
    }

    public function update(Request $request, int $id)
    {
        $record = app(CollectionService::class)->save($request->all(), app(CompanyContextResolver::class)->forActor(), $id);
        return $request->expectsJson() ? response()->json(['data' => $record]) : redirect()->route('accounting.collections')->with('message', 'Collection record updated.');
    }

    public function preview(Request $request)
    {
        return response()->json(app(CollectionService::class)->preview($request->all(), app(CompanyContextResolver::class)->forActor()));
    }
}
