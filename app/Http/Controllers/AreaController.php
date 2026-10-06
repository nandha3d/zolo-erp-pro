<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Services\Platform\CompanyContext;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AreaController extends Controller
{
    private function context(Request $request): ?CompanyContext
    {
        return $request->attributes->get(CompanyContext::class);
    }

    public function index(Request $request)
    {
        $context = $this->context($request);
        $areas = Area::when($context, fn($q) => $q->where('company_id', $context->companyId))
            ->orderBy('name')
            ->get();

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['data' => $areas]);
        }

        return view('backend.master.area', compact('areas'));
    }

    public function store(Request $request)
    {
        $context = $this->context($request);
        $companyId = $context ? $context->companyId : $request->company_id;

        $validated = $request->validate([
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('areas')->where('company_id', $companyId),
            ],
            'code' => 'nullable|string|max:50',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'pincode' => 'nullable|string|max:20',
        ]);

        $area = Area::create([
            'company_id' => $companyId,
            'name' => $validated['name'],
            'code' => $validated['code'] ?? null,
            'city' => $validated['city'] ?? null,
            'state' => $validated['state'] ?? null,
            'pincode' => $validated['pincode'] ?? null,
            'is_active' => true,
        ]);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => true, 'data' => $area, 'message' => 'Area created successfully.'], 201);
        }

        return redirect()->back()->with('message', 'Area created successfully.');
    }

    public function update(Request $request, $id)
    {
        $context = $this->context($request);
        $area = Area::when($context, fn($q) => $q->where('company_id', $context->companyId))->findOrFail($id);

        $validated = $request->validate([
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('areas')->where('company_id', $area->company_id)->ignore($id),
            ],
            'code' => 'nullable|string|max:50',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'pincode' => 'nullable|string|max:20',
            'is_active' => 'nullable|boolean',
        ]);

        $area->update($validated);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => true, 'data' => $area, 'message' => 'Area updated successfully.']);
        }

        return redirect()->back()->with('message', 'Area updated successfully.');
    }

    public function destroy(Request $request, $id)
    {
        $context = $this->context($request);
        $area = Area::when($context, fn($q) => $q->where('company_id', $context->companyId))->findOrFail($id);
        $area->delete();

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'Area deleted successfully.']);
        }

        return redirect()->back()->with('message', 'Area deleted successfully.');
    }
}
