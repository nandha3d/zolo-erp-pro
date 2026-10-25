@php
    $scopeCompany = request()->attributes->get(\App\Services\Platform\CompanyContext::class)?->companyId ?? session('company_id');
    $scopeBranches = $scopeCompany ? \App\Models\CompanyBranch::where('company_id', $scopeCompany)->where('is_active', true)->count() : 0;
@endphp
@if($scopeBranches > 1)
    {{-- Company-wide statements need access to every branch; otherwise the report stays on the current branch. --}}
    <select name="branch_scope" class="form-control form-control-sm mr-2 u-h-32px u-fs-12px u-maxw-150px" aria-label="Branches">
        <option value="">This branch</option>
        <option value="all" @selected(request('branch_scope') === 'all')>All branches</option>
    </select>
@endif
