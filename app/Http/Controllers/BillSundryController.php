<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\BillSundry;
use App\Services\Platform\CompanyContext;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BillSundryController extends Controller
{
    private function context(Request $request): ?CompanyContext
    {
        return $request->attributes->get(CompanyContext::class);
    }

    public function index(Request $request)
    {
        $context = $this->context($request);
        $nature = $request->query('nature'); // sales, purchase, both

        $billSundries = BillSundry::when($context, fn($q) => $q->where('company_id', $context->companyId))
            ->when($nature, fn($q) => $q->where(fn($sub) => $sub->where('nature', $nature)->orWhere('nature', 'both')))
            ->with(['account', 'cgstAccount', 'sgstAccount', 'igstAccount', 'cessAccount'])
            ->orderBy('name')
            ->get();

        $accounts = Account::when($context, fn($q) => $q->where('company_id', $context->companyId))
            ->where('is_active', true)
            ->get(['id', 'name']);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['data' => $billSundries]);
        }

        return view('backend.master.bill_sundry', compact('billSundries', 'accounts'));
    }

    public function store(Request $request)
    {
        $context = $this->context($request);
        $companyId = $context ? $context->companyId : $request->company_id;

        $validated = $request->validate([
            'name' => [
                'required', 'string', 'max:150',
                Rule::unique('bill_sundries')->where('company_id', $companyId),
            ],
            'nature' => 'required|in:sales,purchase,both',
            'calculation_type' => 'required|in:percentage,amount',
            'default_value' => 'nullable|numeric|min:0',
            'affect_cost' => 'nullable|boolean',
            'calculate_before_tax' => 'nullable|boolean',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
            'account_id' => 'nullable|integer',
            'cgst_account_id' => 'nullable|integer',
            'sgst_account_id' => 'nullable|integer',
            'igst_account_id' => 'nullable|integer',
            'cess_account_id' => 'nullable|integer',
        ]);

        $billSundry = BillSundry::create([
            'company_id' => $companyId,
            'name' => $validated['name'],
            'nature' => $validated['nature'],
            'calculation_type' => $validated['calculation_type'],
            'default_value' => $validated['default_value'] ?? 0,
            'affect_cost' => $request->boolean('affect_cost'),
            'calculate_before_tax' => $request->boolean('calculate_before_tax'),
            'tax_rate' => $validated['tax_rate'] ?? 0,
            'account_id' => $validated['account_id'] ?? null,
            'cgst_account_id' => $validated['cgst_account_id'] ?? null,
            'sgst_account_id' => $validated['sgst_account_id'] ?? null,
            'igst_account_id' => $validated['igst_account_id'] ?? null,
            'cess_account_id' => $validated['cess_account_id'] ?? null,
            'is_active' => true,
        ]);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => true, 'data' => $billSundry, 'message' => 'Bill Sundry created successfully.'], 201);
        }

        return redirect()->back()->with('message', 'Bill Sundry created successfully.');
    }

    public function update(Request $request, $id)
    {
        $context = $this->context($request);
        $billSundry = BillSundry::when($context, fn($q) => $q->where('company_id', $context->companyId))->findOrFail($id);

        $validated = $request->validate([
            'name' => [
                'required', 'string', 'max:150',
                Rule::unique('bill_sundries')->where('company_id', $billSundry->company_id)->ignore($id),
            ],
            'nature' => 'required|in:sales,purchase,both',
            'calculation_type' => 'required|in:percentage,amount',
            'default_value' => 'nullable|numeric|min:0',
            'affect_cost' => 'nullable|boolean',
            'calculate_before_tax' => 'nullable|boolean',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
            'account_id' => 'nullable|integer',
            'cgst_account_id' => 'nullable|integer',
            'sgst_account_id' => 'nullable|integer',
            'igst_account_id' => 'nullable|integer',
            'cess_account_id' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['affect_cost'] = $request->boolean('affect_cost');
        $validated['calculate_before_tax'] = $request->boolean('calculate_before_tax');

        $billSundry->update($validated);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => true, 'data' => $billSundry, 'message' => 'Bill Sundry updated successfully.']);
        }

        return redirect()->back()->with('message', 'Bill Sundry updated successfully.');
    }

    public function destroy(Request $request, $id)
    {
        $context = $this->context($request);
        $billSundry = BillSundry::when($context, fn($q) => $q->where('company_id', $context->companyId))->findOrFail($id);
        $billSundry->delete();

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'Bill Sundry deleted successfully.']);
        }

        return redirect()->back()->with('message', 'Bill Sundry deleted successfully.');
    }
}
