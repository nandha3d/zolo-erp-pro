@extends('backend.layout.main')

@push('css')
<link rel="stylesheet" href="{{ asset('css/workspace-home.css') }}?v={{ @filemtime(public_path('css/workspace-home.css')) }}">
@endpush

@section('content')
@php
    $industries = collect(\App\Services\Platform\CapabilityCatalog::PROFILES)->map(fn ($profile) => $profile[0])->all();
    $sections = collect($directory['groups'])->map(fn ($group) => $group + ['independent' => false])
        ->when(count($directory['ungrouped']), fn ($all) => $all->push(['id' => null, 'name' => 'Independent companies', 'code' => null, 'companies' => $directory['ungrouped'], 'independent' => true]))
        ->filter(fn ($section) => count($section['companies']))->values();
    $current = $sections->flatMap(fn ($section) => $section['companies'])->firstWhere('id', $company->id);
    $industry = $current['industry'] ?? 'general_trading';
    $name = $company->trade_name ?: $company->legal_name;
    $initials = fn (string $text) => collect(preg_split('/\s+/', trim($text)))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('');
    $logo = $company->logo && is_file(public_path($company->logo)) ? asset($company->logo) : null;
    $modules = [
        'Sales' => ['fa-shopping-cart', 'Bills, quotations and delivery challans'],
        'Purchases' => ['fa-truck', 'Purchase bills, orders and goods received'],
        'Inventory' => ['fa-cubes', 'Stock, transfers and adjustments'],
        'Accounts' => ['fa-book', 'Vouchers, receipts and payments'],
        'Manufacturing' => ['fa-industry', 'Bills of material and production'],
        'Job Work' => ['fa-random', 'Send to and receive from job workers'],
        'Projects' => ['fa-briefcase', 'Sites, installation and service'],
        'Reports' => ['fa-bar-chart', 'Trial balance and statements'],
        'Settings' => ['fa-cog', 'Branches, warehouses and financial years'],
    ];
    $otherCount = $sections->sum(fn ($section) => count($section['companies'])) - 1;
@endphp
<div class="zw ind-{{ $industry }}">
    @if(session('status'))<div class="zw-flash" role="status"><i class="fa fa-check-circle" aria-hidden="true"></i> {{ session('status') }}</div>@endif
    @if($errors->any())<div class="zw-flash is-error" role="alert">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif

    <div class="zw-hero">
        <div class="zw-identity">
            <span class="zw-mark" aria-hidden="true">@if($logo)<img src="{{ $logo }}" alt="">@else{{ $initials($name) }}@endif</span>
            <div class="zw-identity-text">
                <p class="zw-eyebrow">@if($company->group){{ $company->group->name }} <span aria-hidden="true">›</span> @endif<code>{{ $company->code }}</code></p>
                <h1>{{ $name }}</h1>
                <p class="zw-tags">
                    <span class="zw-chip">{{ $industries[$industry] ?? 'General Trading' }}@if(!empty($current['subtype'])) · {{ \App\Services\Industry\IndustryCatalog::subtypeLabel($current['subtype']) }}@endif</span>
                    @if($company->trade_name && $company->legal_name !== $company->trade_name)<span class="zw-legal">{{ $company->legal_name }}</span>@endif
                </p>
            </div>
        </div>
        <form class="zw-context" method="post" action="/workspace/context" aria-label="Active branch and financial year" data-autosubmit>
            @csrf
            <input type="hidden" name="company_id" value="{{ $company->id }}">
            <span class="zw-context-title">Active branch and financial year</span>
            <div class="zw-context-fields">
                @if($branches->count() > 1)
                    <label><span>Branch</span><select name="branch_id">@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected($branch->id === $context->branchId)>{{ $branch->name }}</option>@endforeach</select></label>
                @else
                    <input type="hidden" name="branch_id" value="{{ $context->branchId }}">
                    <div class="zw-static"><span>Branch</span><strong>{{ optional($branches->first())->name }}</strong></div>
                @endif
                <label><span>Financial year</span><select name="financial_year_id">@foreach($years as $year)<option value="{{ $year->id }}" @selected($year->id === $context->financialYearId)>{{ $year->name }}{{ $year->status !== 'open' ? ' · '.$year->status : '' }}</option>@endforeach</select></label>
            </div>
            <noscript><button class="zw-btn">Apply</button></noscript>
        </form>
    </div>

    <section class="zw-section" aria-labelledby="zw-modules">
        <div class="zw-section-head"><h2 id="zw-modules">Workspaces</h2><span>{{ $name }}</span></div>
        <div class="zw-modules">
            @forelse($navigation as $item)
                @php([$icon, $hint] = $modules[$item['label']] ?? ['fa-th-large', 'Open workspace'])
                <a class="zw-module" href="{{ $item['url'] }}">
                    <span class="zw-module-icon" aria-hidden="true"><i class="fa {{ $icon }}"></i></span>
                    <span class="zw-module-text"><strong>{{ $item['label'] }}</strong><small>{{ $hint }}</small></span>
                    <span class="zw-module-go" aria-hidden="true">→</span>
                </a>
            @empty
                <p class="zw-empty">Ask your company administrator to enable the workspaces your role needs.</p>
            @endforelse
        </div>
    </section>

    <section class="zw-section" aria-labelledby="zw-companies">
        <div class="zw-section-head">
            <h2 id="zw-companies">Switch company</h2>
            <span>{{ $otherCount > 0 ? $otherCount.' other '.\Illuminate\Support\Str::plural('company', $otherCount) : 'No other companies' }}</span>
            <a class="zw-link" href="{{ route('company.select', ['manage' => 1, 'return' => 'workspace']) }}">Manage companies</a>
        </div>
        @foreach($sections as $section)
            <div class="zw-group">
                <p class="zw-group-name">{{ $section['name'] }}@if($section['code']) · <code>{{ $section['code'] }}</code>@endif</p>
                <div class="zw-companies">
                    @foreach($section['companies'] as $entry)
                        @php($isCurrent = $entry['id'] === $company->id)
                        <form method="post" action="/workspace/context" class="zw-company ind-{{ $entry['industry'] }} {{ $isCurrent ? 'is-current' : '' }}">
                            @csrf
                            <input type="hidden" name="company_id" value="{{ $entry['id'] }}">
                            <div class="zw-company-head">
                                <span class="zw-company-mark" aria-hidden="true">{{ $initials($entry['name']) }}</span>
                                <div><strong>{{ $entry['name'] }}</strong><small>{{ $industries[$entry['industry']] ?? 'General Trading' }} · <code>{{ $entry['code'] }}</code></small></div>
                            </div>
                            @if($isCurrent)
                                <span class="zw-current">Current company</span>
                            @elseif(count($entry['branches']) === 0)
                                <span class="zw-note">No branch access</span>
                            @else
                                <div class="zw-company-fields">
                                    @if(count($entry['branches']) > 1)
                                        <select name="branch_id" aria-label="Branch for {{ $entry['name'] }}">@foreach($entry['branches'] as $branch)<option value="{{ $branch['id'] }}">{{ $branch['name'] }}</option>@endforeach</select>
                                    @else
                                        <input type="hidden" name="branch_id" value="{{ $entry['branches'][0]['id'] }}">
                                    @endif
                                    @if(count($entry['years']) > 1)
                                        <select name="financial_year_id" aria-label="Financial year for {{ $entry['name'] }}">@foreach($entry['years'] as $year)<option value="{{ $year['id'] }}" @selected($year['id'] === $entry['current_year_id'])>{{ $year['name'] }}</option>@endforeach</select>
                                    @elseif(count($entry['years']) === 1)
                                        <input type="hidden" name="financial_year_id" value="{{ $entry['years'][0]['id'] }}">
                                    @endif
                                    <button class="zw-btn" type="submit">Switch <span aria-hidden="true">→</span></button>
                                </div>
                            @endif
                        </form>
                    @endforeach
                </div>
            </div>
        @endforeach
    </section>
</div>
@endsection

@push('scripts')
<script>
    // Delegated through jQuery so changes made through bootstrap-select menus submit too.
    $(document).on('change', 'form[data-autosubmit] select', function () { this.form.classList.add('is-busy'); this.form.submit(); });
</script>
@endpush
