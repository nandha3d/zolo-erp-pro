@extends('backend.layout.main')

@push('css')
<link rel="stylesheet" href="{{ asset('css/workspace-home.css') }}?v={{ @filemtime(public_path('css/workspace-home.css')) }}">
@endpush

@section('content')
<div class="zw zw-setup">
    @if(session('status'))<div class="zw-flash" role="status"><i class="fa fa-check-circle" aria-hidden="true"></i> {{ session('status') }}</div>@endif
    @if($errors->any())<div class="zw-flash is-error" role="alert"><strong>Check these fields</strong>@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif

    <div class="zw-page-head">
        <div><p class="zw-eyebrow"><a href="/workspace">Workspace</a> <span aria-hidden="true">›</span> Settings</p><h1>Company setup</h1><p class="zw-legal">{{ $company['legal_name'] }} · <code>{{ $company['code'] }}</code></p></div>
        <a class="zw-link" href="{{ route('settings.company') }}">Company profile (address, GSTIN, logo) →</a>
    </div>

    <section class="zw-panel"><h2>Business details</h2>
        <form method="post" action="/workspace/setup/company?company_id={{ $company['id'] }}">@csrf
            <div class="zw-fields">
                <label>Legal name<input name="legal_name" value="{{ old('legal_name',$company['legal_name']) }}" required maxlength="255"></label>
                <label>Trade name<input name="trade_name" value="{{ old('trade_name',$company['trade_name']) }}" maxlength="255"></label>
                <label>State code<input name="state_code" value="{{ old('state_code',$company['state_code']) }}" required maxlength="20"></label>
                <label>Currency<select name="base_currency_id" required>@foreach($currencies as $currency)<option value="{{ $currency->id }}" @selected($currency->id == $company['base_currency_id'])>{{ $currency->code }} · {{ $currency->name }}</option>@endforeach</select></label>
                <label>Preferred print format<select name="print_format">@foreach(['a4'=>'A4','thermal'=>'Thermal','dot_matrix'=>'Dot Matrix'] as $key=>$label)<option value="{{ $key }}" @selected($print_format === $key)>{{ $label }}</option>@endforeach</select></label>
            </div>
            <button class="zw-btn">Save business details</button>
        </form>
    </section>

    <div class="zw-setup-grid">
        <section class="zw-panel"><h2>Financial years</h2>
            <ul class="zw-list">@forelse($years as $year)<li>{{ $year->name }} <span class="zw-pill">{{ $year->status }}</span></li>@empty<li>No financial year yet.</li>@endforelse</ul>
            <p class="zw-hint">Closed years remain available for historical reads.</p>
            <a class="zw-link" href="/company/financial-years/setup?company_id={{ $company['id'] }}">Manage financial years →</a>
        </section>

        <section class="zw-panel"><h2>Branches</h2>
            <ul class="zw-list">@foreach($branches as $branch)<li>{{ $branch->name }}</li>@endforeach</ul>
            <details><summary>Add branch</summary>
                <form method="post" action="/workspace/setup/branch?company_id={{ $company['id'] }}">@csrf
                    <div class="zw-fields"><label>Code<input name="code" pattern="[A-Z0-9_-]+" required maxlength="50"></label><label>Name<input name="name" required maxlength="255"></label></div>
                    <button class="zw-btn">Add branch</button>
                </form>
            </details>
        </section>

        <section class="zw-panel"><h2>Warehouses</h2>
            <ul class="zw-list">@forelse($warehouses as $warehouse)<li>{{ $warehouse->name }}</li>@empty<li>Add a warehouse before receiving stock.</li>@endforelse</ul>
            <details><summary>Add warehouse</summary>
                <form method="post" action="/workspace/setup/warehouse?company_id={{ $company['id'] }}">@csrf
                    <div class="zw-fields"><label>Branch<select name="branch_id" required>@foreach($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach</select></label><label>Name<input name="name" required maxlength="255"></label><label>Phone<input name="phone" maxlength="50"></label><label>Email<input name="email" type="email" maxlength="255"></label><label class="zw-wide">Address<textarea name="address" required maxlength="1000"></textarea></label></div>
                    <button class="zw-btn">Add warehouse</button>
                </form>
            </details>
        </section>

        <section class="zw-panel"><h2>Business profile and capabilities</h2>
            <p class="zw-hint">Optional activation: {{ $activation_ready ? 'Foundation accepted' : 'Awaiting company isolation acceptance' }}. Opening data and production activation require reconciliation and reviewed acceptance.</p>
            <ul class="zw-list">@foreach($capabilities as $key=>$state)@if($state['enabled'])<li>{{ \App\Services\Platform\CapabilityCatalog::DEFINITIONS[$key][0] }}</li>@endif
            @endforeach</ul>
            <a class="zw-link" href="/accounting/semantic-mappings">Account mappings →</a>
        </section>
    </div>
</div>
@endsection
