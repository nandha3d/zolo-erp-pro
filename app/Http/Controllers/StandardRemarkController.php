<?php

namespace App\Http\Controllers;

use App\Models\StandardRemark;
use App\Services\Platform\CompanyContext;
use Illuminate\Http\Request;

class StandardRemarkController extends Controller
{
    private function context(Request $request): ?CompanyContext
    {
        return $request->attributes->get(CompanyContext::class);
    }

    public function index(Request $request)
    {
        $context = $this->context($request);
        $type = $request->query('type'); // sale, purchase, voucher, all

        $remarks = StandardRemark::when($context, fn($q) => $q->where('company_id', $context->companyId))
            ->when($type, fn($q) => $q->where(fn($sub) => $sub->where('type', $type)->orWhere('type', 'all')))
            ->orderByDesc('is_default')
            ->orderBy('title')
            ->get();

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['data' => $remarks]);
        }

        return view('backend.master.standard_remark', compact('remarks'));
    }

    public function store(Request $request)
    {
        $context = $this->context($request);
        $companyId = $context ? $context->companyId : $request->company_id;

        $validated = $request->validate([
            'title' => 'required|string|max:100',
            'type' => 'required|in:sale,purchase,voucher,all',
            'remark' => 'required|string|max:1000',
            'is_default' => 'nullable|boolean',
        ]);

        $remark = StandardRemark::create([
            'company_id' => $companyId,
            'title' => $validated['title'],
            'type' => $validated['type'],
            'remark' => $validated['remark'],
            'is_default' => $request->boolean('is_default'),
            'is_active' => true,
        ]);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => true, 'data' => $remark, 'message' => 'Standard remark created successfully.'], 201);
        }

        return redirect()->back()->with('message', 'Standard remark created successfully.');
    }

    public function update(Request $request, $id)
    {
        $context = $this->context($request);
        $remark = StandardRemark::when($context, fn($q) => $q->where('company_id', $context->companyId))->findOrFail($id);

        $validated = $request->validate([
            'title' => 'required|string|max:100',
            'type' => 'required|in:sale,purchase,voucher,all',
            'remark' => 'required|string|max:1000',
            'is_default' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['is_default'] = $request->boolean('is_default');
        $remark->update($validated);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => true, 'data' => $remark, 'message' => 'Standard remark updated successfully.']);
        }

        return redirect()->back()->with('message', 'Standard remark updated successfully.');
    }

    public function destroy(Request $request, $id)
    {
        $context = $this->context($request);
        $remark = StandardRemark::when($context, fn($q) => $q->where('company_id', $context->companyId))->findOrFail($id);
        $remark->delete();

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'Standard remark deleted successfully.']);
        }

        return redirect()->back()->with('message', 'Standard remark deleted successfully.');
    }
}
