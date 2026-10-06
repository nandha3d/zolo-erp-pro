<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Agent;
use App\Services\Platform\CompanyContext;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AgentController extends Controller
{
    private function context(Request $request): ?CompanyContext
    {
        return $request->attributes->get(CompanyContext::class);
    }

    public function index(Request $request)
    {
        $context = $this->context($request);
        $agents = Agent::when($context, fn($q) => $q->where('company_id', $context->companyId))
            ->with('account')
            ->orderBy('name')
            ->get();

        $accounts = Account::when($context, fn($q) => $q->where('company_id', $context->companyId))
            ->where('is_active', true)
            ->get(['id', 'name']);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['data' => $agents]);
        }

        return view('backend.master.agent', compact('agents', 'accounts'));
    }

    public function store(Request $request)
    {
        $context = $this->context($request);
        $companyId = $context ? $context->companyId : $request->company_id;

        $validated = $request->validate([
            'name' => [
                'required', 'string', 'max:150',
                Rule::unique('agents')->where('company_id', $companyId),
            ],
            'code' => 'nullable|string|max:50',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:100',
            'address' => 'nullable|string|max:500',
            'commission_rate' => 'nullable|numeric|min:0|max:100',
            'account_id' => 'nullable|integer',
        ]);

        $agent = Agent::create([
            'company_id' => $companyId,
            'name' => $validated['name'],
            'code' => $validated['code'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'email' => $validated['email'] ?? null,
            'address' => $validated['address'] ?? null,
            'commission_rate' => $validated['commission_rate'] ?? 0,
            'account_id' => $validated['account_id'] ?? null,
            'is_active' => true,
        ]);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => true, 'data' => $agent, 'message' => 'Agent created successfully.'], 201);
        }

        return redirect()->back()->with('message', 'Agent created successfully.');
    }

    public function update(Request $request, $id)
    {
        $context = $this->context($request);
        $agent = Agent::when($context, fn($q) => $q->where('company_id', $context->companyId))->findOrFail($id);

        $validated = $request->validate([
            'name' => [
                'required', 'string', 'max:150',
                Rule::unique('agents')->where('company_id', $agent->company_id)->ignore($id),
            ],
            'code' => 'nullable|string|max:50',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:100',
            'address' => 'nullable|string|max:500',
            'commission_rate' => 'nullable|numeric|min:0|max:100',
            'account_id' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
        ]);

        $agent->update($validated);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => true, 'data' => $agent, 'message' => 'Agent updated successfully.']);
        }

        return redirect()->back()->with('message', 'Agent updated successfully.');
    }

    public function destroy(Request $request, $id)
    {
        $context = $this->context($request);
        $agent = Agent::when($context, fn($q) => $q->where('company_id', $context->companyId))->findOrFail($id);
        $agent->delete();

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'Agent deleted successfully.']);
        }

        return redirect()->back()->with('message', 'Agent deleted successfully.');
    }
}
