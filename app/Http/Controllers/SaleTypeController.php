<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\SaleType;
use App\Services\Platform\CompanyContext;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SaleTypeController extends Controller
{
    private function context(Request $request): ?CompanyContext
    {
        return $request->attributes->get(CompanyContext::class);
    }

    public function index(Request $request)
    {
        $context = $this->context($request);
        $saleTypes = SaleType::when($context, fn($q) => $q->where('company_id', $context->companyId))
            ->with(['salesAccount', 'cgstAccount', 'sgstAccount', 'igstAccount'])
            ->orderBy('name')
            ->get();

        $accounts = Account::when($context, fn($q) => $q->where('company_id', $context->companyId))
            ->where('is_active', true)
            ->get(['id', 'name']);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['data' => $saleTypes]);
        }

        return view('backend.master.sale_type', compact('saleTypes', 'accounts'));
    }

    public function store(Request $request)
    {
        $context = $this->context($request);
        $companyId = $context ? $context->companyId : $request->company_id;

        $validated = $request->validate([
            'name' => [
                'required', 'string', 'max:150',
                Rule::unique('sale_types')->where('company_id', $companyId),
            ],
            'code' => 'nullable|string|max:50',
            'tax_nature' => 'required|in:local,interstate,export,sez,exempted',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
            'sales_account_id' => 'nullable|integer',
            'cgst_account_id' => 'nullable|integer',
            'sgst_account_id' => 'nullable|integer',
            'igst_account_id' => 'nullable|integer',
        ]);

        $saleType = SaleType::create([
            'company_id' => $companyId,
            'name' => $validated['name'],
            'code' => $validated['code'] ?? null,
            'tax_nature' => $validated['tax_nature'],
            'tax_rate' => $validated['tax_rate'] ?? 0,
            'sales_account_id' => $validated['sales_account_id'] ?? null,
            'cgst_account_id' => $validated['cgst_account_id'] ?? null,
            'sgst_account_id' => $validated['sgst_account_id'] ?? null,
            'igst_account_id' => $validated['igst_account_id'] ?? null,
            'is_active' => true,
        ]);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => true, 'data' => $saleType, 'message' => 'Sale Type created successfully.'], 201);
        }

        return redirect()->back()->with('message', 'Sale Type created successfully.');
    }

    public function update(Request $request, $id)
    {
        $context = $this->context($request);
        $saleType = SaleType::when($context, fn($q) => $q->where('company_id', $context->companyId))->findOrFail($id);

        $validated = $request->validate([
            'name' => [
                'required', 'string', 'max:150',
                Rule::unique('sale_types')->where('company_id', $saleType->company_id)->ignore($id),
            ],
            'code' => 'nullable|string|max:50',
            'tax_nature' => 'required|in:local,interstate,export,sez,exempted',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
            'sales_account_id' => 'nullable|integer',
            'cgst_account_id' => 'nullable|integer',
            'sgst_account_id' => 'nullable|integer',
            'igst_account_id' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
        ]);

        $saleType->update($validated);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => true, 'data' => $saleType, 'message' => 'Sale Type updated successfully.']);
        }

        return redirect()->back()->with('message', 'Sale Type updated successfully.');
    }

    public function destroy(Request $request, $id)
    {
        $context = $this->context($request);
        $saleType = SaleType::when($context, fn($q) => $q->where('company_id', $context->companyId))->findOrFail($id);
        $saleType->delete();

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'Sale Type deleted successfully.']);
        }

        return redirect()->back()->with('message', 'Sale Type deleted successfully.');
    }
}
