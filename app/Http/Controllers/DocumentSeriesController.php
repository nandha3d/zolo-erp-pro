<?php

namespace App\Http\Controllers;

use App\Models\DocumentSeries;
use App\Services\Platform\{CompanyContext, CompanyContextResolver, DocumentNumberService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\{Rule, ValidationException};

/** Master Registers › Voucher Series (Ctrl+F9) for the signed-in branch and financial year. Rules live in DocumentNumberService. */
class DocumentSeriesController extends Controller
{
    private function context(Request $request): CompanyContext
    {
        $context = $request->attributes->get(CompanyContext::class);
        abort_unless($context, 403, 'Choose a company first.');
        return $context;
    }

    public function index(Request $request)
    {
        $context = $this->context($request);
        $query = DocumentSeries::where('company_id', $context->companyId)->where('branch_id', $context->branchId)
            ->where('financial_year_id', $context->financialYearId);
        if ($request->filled('document_type')) {
            $query->where('document_type', $request->document_type);
        }
        $series = $query->orderBy('document_type')->orderBy('code')->get();
        $used = DB::table('document_number_reservations')->whereIn('series_id', $series->pluck('id'))->distinct()->pluck('series_id')->flip();
        $series->each(fn ($row) => $row->setAttribute('used', isset($used[$row->id])));

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['data' => $series]);
        }

        return view('backend.master.series', ['series' => $series, 'types' => DocumentNumberService::types(),
            'canManage' => app(CompanyContextResolver::class)->canManageFinancialYears($request->user()->id, $context->companyId)]);
    }

    public function store(Request $request)
    {
        $context = $this->context($request);
        $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);
        $validated = $request->validate([
            'document_type' => ['required', Rule::in(DocumentNumberService::types())],
            'code' => ['required', 'string', 'max:50', Rule::unique('document_series')->where(fn ($query) => $query->where('company_id', $context->companyId)
                ->where('branch_id', $context->branchId)->where('financial_year_id', $context->financialYearId)->where('document_type', $request->input('document_type')))],
            'prefix' => 'nullable|string|max:100',
            'suffix' => 'nullable|string|max:50',
            'next_number' => 'required|integer|min:1',
            'padding' => 'required|integer|min:1|max:18',
        ]);
        app(DocumentNumberService::class)->configure($context, ['is_default' => $request->boolean('is_default'),
            'prefix' => $validated['prefix'] ?? '', 'suffix' => $validated['suffix'] ?? ''] + $validated, $request->user()->id);

        return $this->done($request, 'Voucher series created.');
    }

    public function update(Request $request, $id)
    {
        app(DocumentNumberService::class)->updateSeries((int) $id, $request->only(['prefix', 'suffix', 'padding', 'next_number'])
            + ['is_default' => $request->boolean('is_default')], $this->context($request), $request->user()->id);

        return $this->done($request, 'Voucher series updated. New documents use it; posted documents keep their numbers.');
    }

    public function destroy(Request $request, $id)
    {
        $context = $this->context($request);
        abort_unless(app(CompanyContextResolver::class)->canManageFinancialYears($request->user()->id, $context->companyId), 403, 'Company administrator required.');
        $series = DocumentSeries::where('company_id', $context->companyId)->findOrFail($id);
        if (DB::table('document_number_reservations')->where('series_id', $series->id)->exists()) {
            throw ValidationException::withMessages(['series' => 'This series has numbered documents and stays for their history. Make another series the default instead.']);
        }
        $series->delete();

        return $this->done($request, 'Voucher series deleted.');
    }

    private function done(Request $request, string $message)
    {
        return $request->ajax() || $request->wantsJson()
            ? response()->json(['success' => true, 'message' => $message])
            : redirect()->route('document-series.index')->with('message', $message);
    }
}
