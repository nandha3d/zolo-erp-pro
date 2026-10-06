<?php

namespace App\Http\Controllers;

use App\Models\DocumentSeries;
use App\Services\Platform\CompanyContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DocumentSeriesController extends Controller
{
    private function context(Request $request): ?CompanyContext
    {
        return $request->attributes->get(CompanyContext::class);
    }

    public function index(Request $request)
    {
        $context = $this->context($request);
        $companyId = $context ? $context->companyId : (auth()->user()->company_id ?? 1);

        $query = DocumentSeries::where('company_id', $companyId);

        if ($request->filled('document_type')) {
            $query->where('document_type', $request->document_type);
        }

        $series = $query->orderBy('document_type')->orderBy('code')->get();

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['data' => $series]);
        }

        return view('backend.master.series', compact('series'));
    }

    public function store(Request $request)
    {
        $context = $this->context($request);
        $companyId = $context ? $context->companyId : (auth()->user()->company_id ?? 1);
        $branchId = $context ? $context->branchId : (DB::table('company_branches')->where('company_id', $companyId)->value('id') ?? 1);
        $fyId = $context ? $context->financialYearId : (DB::table('fiscal_years')->where('company_id', $companyId)->value('id') ?? 1);

        $validated = $request->validate([
            'document_type' => 'required|string|max:50',
            'code' => [
                'required', 'string', 'max:50',
                Rule::unique('document_series')->where(function ($query) use ($companyId, $branchId, $fyId) {
                    return $query->where('company_id', $companyId)
                        ->where('branch_id', $branchId)
                        ->where('financial_year_id', $fyId);
                }),
            ],
            'prefix' => 'nullable|string|max:100',
            'suffix' => 'nullable|string|max:50',
            'next_number' => 'required|integer|min:1',
            'padding' => 'nullable|integer|min:1|max:18',
            'reset_policy' => 'nullable|string|in:financial_year,monthly,never',
            'is_default' => 'nullable|boolean',
        ]);

        $series = DocumentSeries::create([
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'financial_year_id' => $fyId,
            'document_type' => $validated['document_type'],
            'code' => $validated['code'],
            'prefix' => $validated['prefix'] ?? '',
            'suffix' => $validated['suffix'] ?? '',
            'next_number' => $validated['next_number'],
            'padding' => max(1, (int) ($validated['padding'] ?? 5)),
            'reset_policy' => $validated['reset_policy'] ?? 'financial_year',
            'is_default' => !empty($validated['is_default']),
        ]);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => true, 'data' => $series, 'message' => 'Voucher series created successfully.'], 201);
        }

        return redirect()->route('document-series.index')->with('message', 'Voucher series created successfully.');
    }

    public function update(Request $request, $id)
    {
        $context = $this->context($request);
        $companyId = $context ? $context->companyId : (auth()->user()->company_id ?? 1);

        $series = DocumentSeries::where('company_id', $companyId)->findOrFail($id);

        $validated = $request->validate([
            'prefix' => 'nullable|string|max:100',
            'suffix' => 'nullable|string|max:50',
            'next_number' => 'required|integer|min:1',
            'padding' => 'nullable|integer|min:0|max:10',
            'reset_policy' => 'nullable|string|in:financial_year,monthly,never',
            'is_default' => 'nullable|boolean',
        ]);

        $series->update([
            'prefix' => $validated['prefix'] ?? '',
            'suffix' => $validated['suffix'] ?? '',
            'next_number' => $validated['next_number'],
            'padding' => $validated['padding'] ?? 0,
            'reset_policy' => $validated['reset_policy'] ?? 'financial_year',
            'is_default' => !empty($validated['is_default']),
        ]);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => true, 'data' => $series, 'message' => 'Voucher series updated successfully.']);
        }

        return redirect()->route('document-series.index')->with('message', 'Voucher series updated successfully.');
    }

    public function destroy(Request $request, $id)
    {
        $context = $this->context($request);
        $companyId = $context ? $context->companyId : (auth()->user()->company_id ?? 1);

        $series = DocumentSeries::where('company_id', $companyId)->findOrFail($id);
        $series->delete();

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'Voucher series deleted successfully.']);
        }

        return redirect()->route('document-series.index')->with('message', 'Voucher series deleted successfully.');
    }
}
