<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\PurchaseType;
use App\Services\Platform\CompanyContext;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PurchaseTypeController extends Controller
{
    private function context(Request $request): ?CompanyContext
    {
        return $request->attributes->get(CompanyContext::class);
    }

    public function index(Request $request)
    {
        $context = $this->context($request);
        $purchaseTypes = PurchaseType::when($context, fn($q) => $q->where('company_id', $context->companyId))
            ->with(['purchaseAccount', 'cgstAccount', 'sgstAccount', 'igstAccount'])
            ->orderBy('name')
            ->get();

        $accounts = Account::when($context, fn($q) => $q->where('company_id', $context->companyId))
            ->where('is_active', true)
            ->get(['id', 'name']);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['data' => $purchaseTypes]);
        }

        return view('backend.master.purchase_type', compact('purchaseTypes', 'accounts'));
    }

    public function store(Request $request)
    {
        $context = $this->context($request);
        $companyId = $context ? $context->companyId : $request->company_id;

        $validated = $request->validate([
            'name' => [
                'required', 'string', 'max:150',
                Rule::unique('purchase_types')->where('company_id', $companyId),
            ],
            'code' => 'nullable|string|max:50',
            'tax_nature' => 'required|in:local,interstate,import,exempted',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
            'purchase_account_id' => 'nullable|integer',
            'cgst_account_id' => 'nullable|integer',
            'sgst_account_id' => 'nullable|integer',
            'igst_account_id' => 'nullable|integer',
        ]);

        $purchaseType = PurchaseType::create([
            'company_id' => $companyId,
            'name' => $validated['name'],
            'code' => $validated['code'] ?? null,
            'tax_nature' => $validated['tax_nature'],
            'tax_rate' => $validated['tax_rate'] ?? 0,
            'purchase_account_id' => $validated['purchase_account_id'] ?? null,
            'cgst_account_id' => $validated['cgst_account_id'] ?? null,
            'sgst_account_id' => $validated['sgst_account_id'] ?? null,
            'igst_account_id' => $validated['igst_account_id'] ?? null,
            'is_active' => true,
        ]);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => true, 'data' => $purchaseType, 'message' => 'Purchase Type created successfully.'], 201);
        }

        return redirect()->back()->with('message', 'Purchase Type created successfully.');
    }

    public function update(Request $request, $id)
    {
        $context = $this->context($request);
        $purchaseType = PurchaseType::when($context, fn($q) => $q->where('company_id', $context->companyId))->findOrFail($id);

        $validated = $request->validate([
            'name' => [
                'required', 'string', 'max:150',
                Rule::unique('purchase_types')->where('company_id', $purchaseType->company_id)->ignore($id),
            ],
            'code' => 'nullable|string|max:50',
            'tax_nature' => 'required|in:local,interstate,import,exempted',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
            'purchase_account_id' => 'nullable|integer',
            'cgst_account_id' => 'nullable|integer',
            'sgst_account_id' => 'nullable|integer',
            'igst_account_id' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
        ]);

        $purchaseType->update($validated);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => true, 'data' => $purchaseType, 'message' => 'Purchase Type updated successfully.']);
        }

        return redirect()->back()->with('message', 'Purchase Type updated successfully.');
    }

    public function destroy(Request $request, $id)
    {
        $context = $this->context($request);
        $purchaseType = PurchaseType::when($context, fn($q) => $q->where('company_id', $context->companyId))->findOrFail($id);
        $purchaseType->delete();

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'Purchase Type deleted successfully.']);
        }

        return redirect()->back()->with('message', 'Purchase Type deleted successfully.');
    }
}
