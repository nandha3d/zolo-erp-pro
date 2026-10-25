@extends('backend.layout.main')

@section('content')
<link rel="stylesheet" href="{{ asset('css/commercial-entry.css') }}?v={{ file_exists(public_path('css/commercial-entry.css')) ? filemtime(public_path('css/commercial-entry.css')) : time() }}" type="text/css">
<link rel="stylesheet" href="{{ asset('css/commercial-workspace.css') }}?v={{ file_exists(public_path('css/commercial-workspace.css')) ? filemtime(public_path('css/commercial-workspace.css')) : time() }}" type="text/css">
<link rel="stylesheet" href="{{ asset('css/zolo-erp-neo.css') }}?v={{ file_exists(public_path('css/zolo-erp-neo.css')) ? filemtime(public_path('css/zolo-erp-neo.css')) : time() }}" type="text/css">
<link rel="stylesheet" href="{{ asset('css/compliance.css') }}?v={{ file_exists(public_path('css/compliance.css')) ? filemtime(public_path('css/compliance.css')) : time() }}" type="text/css">

<style>
    .compliance-wrapper {
        padding: 12px 18px 24px;
        background: #f8fafc;
        min-height: calc(100vh - 40px);
    }
    body.dark-mode .compliance-wrapper {
        background: #0b0f19;
    }
    .compliance-wrapper .notice {
        padding: 10px 14px;
        border-radius: 6px;
        margin-bottom: 14px;
        font-size: 13px;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .compliance-wrapper .notice:not(.error) {
        background: #ecfdf5;
        border: 1px solid #a7f3d0;
        color: #065f46;
    }
    .compliance-wrapper .notice.error {
        background: #fef2f2;
        border: 1px solid #fecaca;
        color: #991b1b;
    }
    .compliance-desk-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
        padding: 16px 18px;
        margin-bottom: 16px;
    }
    body.dark-mode .compliance-desk-card {
        background: #141c2e;
        border-color: #334155;
    }
    /* Card, KPI, form and table pieces shared by GST review and Tax & document settings. */
    .cx-lead { font-size: 12.5px; color: #64748b; margin: 0 0 10px; }
    .cx-grid { display: grid; gap: 12px; grid-template-columns: repeat(auto-fit, minmax(min(100%, 320px), 1fr)); align-items: start; }
    .cx-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 14px; box-shadow: 0 1px 3px rgba(15, 23, 42, .04); min-width: 0; }
    .cx-card h2 { font-size: 13.5px; font-weight: 700; color: #0f172a; margin: 0 0 4px; display: flex; align-items: center; gap: 6px; }
    .cx-card h2 i { color: #7c3aed; }
    .cx-card > p, .cx-help { font-size: 12px; color: #64748b; margin: 0 0 10px; }
    .cx-form { display: grid; gap: 8px 12px; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); align-items: end; }
    .cx-form .cx-span { grid-column: 1 / -1; }
    .cx-form label, .cx-field label { display: block; font-size: 11.5px; font-weight: 600; color: #334155; margin: 0 0 3px; }
    .cx-form input:not([type=checkbox]):not([type=hidden]), .cx-form select, .cx-filter input, .cx-filter select {
        width: 100%; height: 32px; font-size: 13px; padding: 4px 8px; border: 1px solid #cbd5e1; border-radius: 6px; background: #fff; color: #0f172a; }
    .cx-form .bootstrap-select { width: 100% !important; }
    .cx-form .bootstrap-select > .dropdown-toggle { height: 32px; font-size: 13px; padding: 4px 8px; border: 1px solid #cbd5e1; border-radius: 6px; background: #fff; }
    .cx-actions { display: flex; gap: 8px; align-items: center; justify-content: flex-end; }
    .cx-btn { height: 32px; padding: 0 14px; border-radius: 6px; font-size: 12.5px; font-weight: 600; border: 1px solid #7c3aed; background: #7c3aed; color: #fff; display: inline-flex; align-items: center; gap: 5px; white-space: nowrap; text-decoration: none; cursor: pointer; }
    .cx-btn:hover { background: #6d28d9; color: #fff; text-decoration: none; }
    .cx-btn.ghost { background: #fff; color: #5b21b6; border-color: #c4b5fd; }
    .cx-btn.ghost:hover { background: #f5f3ff; color: #5b21b6; }
    .cx-btn.danger { background: #b91c1c; border-color: #b91c1c; }
    .cx-kv { display: grid; grid-template-columns: auto 1fr; gap: 4px 12px; font-size: 12.5px; margin: 0; }
    .cx-kv dt { color: #64748b; font-weight: 500; }
    .cx-kv dd { margin: 0; color: #0f172a; font-weight: 600; overflow-wrap: anywhere; }
    .cx-pill { display: inline-block; font-size: 11px; font-weight: 700; padding: 1px 8px; border-radius: 999px; background: #ede9fe; color: #6d28d9; white-space: nowrap; }
    .cx-pill.ok { background: #dcfce7; color: #166534; }
    .cx-pill.warn { background: #fef3c7; color: #92400e; }
    .cx-pill.muted { background: #f1f5f9; color: #475569; }
    .cx-kpis { display: grid; gap: 10px; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); margin-bottom: 10px; }
    .cx-kpi { background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 9px 12px; }
    .cx-kpi span { display: block; font-size: 10px; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; color: #64748b; }
    .cx-kpi b { display: block; font-size: 17px; color: #0f172a; font-variant-numeric: tabular-nums; }
    .cx-kpi.accent { border-color: #c4b5fd; background: #faf5ff; }
    .cx-filter { display: flex; gap: 8px; align-items: end; flex-wrap: wrap; }
    .cx-filter .cx-field { width: 160px; }
    .cx-table-wrap { overflow: auto; border: 1px solid #e2e8f0; border-radius: 8px; background: #fff; }
    .cx-table { width: 100%; border-collapse: collapse; font-size: 12.5px; margin: 0; }
    .cx-table th { position: sticky; top: 0; z-index: 1; background: #0f172a; color: #e2e8f0; font-size: 10.5px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; padding: 7px 10px; text-align: left; white-space: nowrap; }
    .cx-table td { padding: 6px 10px; border-bottom: 1px solid #f1f5f9; vertical-align: top; }
    .cx-table .n { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .cx-table tfoot td { font-weight: 700; background: #f8fafc; border-top: 1px solid #e2e8f0; }
    .cx-table tr.cx-detail td { background: #fafafa; color: #475569; font-size: 12px; }
    .cx-table .cx-toggle { cursor: pointer; color: #6d28d9; font-weight: 600; border: 0; background: none; padding: 0; }
    .cx-tabs { display: flex; gap: 4px; margin: 0 0 8px; padding: 3px; background: #f1f5f9; border-radius: 8px; width: max-content; max-width: 100%; overflow-x: auto; }
    .cx-tabs button { border: 0; background: none; padding: 4px 12px; font-size: 12.5px; font-weight: 600; color: #475569; border-radius: 6px; white-space: nowrap; }
    .cx-tabs button.active { background: #7c3aed; color: #fff; }
    .cx-tabs button .cx-count { font-size: 10.5px; opacity: .8; margin-left: 4px; }
    body.dark-mode .cx-card, body.dark-mode .cx-kpi, body.dark-mode .cx-table-wrap { background: #141c2e; border-color: #334155; }
    body.dark-mode .cx-card h2, body.dark-mode .cx-kpi b, body.dark-mode .cx-kv dd { color: #f8fafc; }
    body.dark-mode .cx-form label, body.dark-mode .cx-field label { color: #94a3b8; }
    body.dark-mode .cx-form input:not([type=checkbox]):not([type=hidden]), body.dark-mode .cx-form select, body.dark-mode .cx-filter input { background: #0f172a; border-color: #334155; color: #e2e8f0; }
    body.dark-mode .cx-table td { border-color: #1e293b; }
    body.dark-mode .cx-table tfoot td, body.dark-mode .cx-table tr.cx-detail td, body.dark-mode .cx-tabs { background: #0f172a; }
    body.dark-mode .cx-kpi.accent { background: #1e1b2e; border-color: #6d28d9; }
</style>

@if(!empty($side))
{{-- Opened from the Purchase or Sales command center: stay inside it. --}}
@section('navbar-center')
<div class="comm-navbar-center-wrap d-flex align-items-center w-100" style="min-width:0; gap:8px;">
    <div class="comm-navbar-title-group">
        <span class="badge comm-title-badge">
            <i class="{{ $side === 'sale' ? 'dripicons-cart text-primary' : 'dripicons-download text-success' }}"></i> {{ $side === 'sale' ? 'Sales' : 'Purchase' }} Command Center
        </span>
        <span style="color:#cbd5e1;font-size:12px;font-weight:600;">/</span>
        <span class="comm-doc-title">{{ $side === 'sale' ? 'Sales returns (credit notes)' : 'Purchase returns (debit notes)' }}</span>
    </div>
</div>
@endsection
@include('backend.partials.command-center-nav', ['kind' => $side, 'current' => 'returns', 'withDrafts' => false])
@elseif(!empty($trade))
<div class="comm-command-bar">
    <div class="comm-title-group">
        <h1><i class="dripicons-store text-primary"></i> @yield('title', 'Trade')</h1>
        <span class="comm-title-badge"><i class="dripicons-network-3"></i> Agents, routes and job work for customers</span>
    </div>
    <ul class="comm-nav-pills">
        <li><a class="nav-link {{ request()->is('*trade/brokerage*') ? 'active' : '' }}" href="{{ url('/trade/brokerage') }}"><i class="dripicons-user-group"></i> Brokerage</a></li>
        <li><a class="nav-link {{ request()->is('*trade/routes*') ? 'active' : '' }}" href="{{ url('/trade/routes') }}"><i class="dripicons-map"></i> Route sheet</a></li>
        <li><a class="nav-link {{ request()->is('*trade/customer-jobs*') ? 'active' : '' }}" href="{{ url('/trade/customer-jobs') }}"><i class="dripicons-swap"></i> Job work for customers</a></li>
    </ul>
</div>
@else
<div class="comm-command-bar">
    <div class="comm-title-group">
        <h1><i class="dripicons-swap text-primary"></i> @yield('title', 'Returns & Compliance')</h1>
        <span class="comm-title-badge"><i class="dripicons-shield"></i> Sales &amp; Purchase Control · Company {{ $context->companyId ?? 1 }} / Branch {{ $context->branchId ?? 1 }}</span>
    </div>
    <ul class="comm-nav-pills">
        <li><a class="nav-link {{ request()->is('*compliance/returns*') || request()->is('*return-purchase*') || request()->is('*return-sale*') ? 'active' : '' }}" href="{{ url('/compliance/returns') }}"><i class="dripicons-return"></i> Returns &amp; Notes</a></li>
        <li><a class="nav-link {{ request()->is('*compliance/gst/report*') ? 'active' : '' }}" href="{{ url('/compliance/gst/report') }}"><i class="dripicons-graph-bar"></i> GST Review</a></li>
        <li><a class="nav-link {{ request()->is('*compliance/gst/returns*') ? 'active' : '' }}" href="{{ url('/compliance/gst/returns') }}"><i class="dripicons-document"></i> GST Returns</a></li>
        <li><a class="nav-link {{ request()->is('*compliance/setup*') ? 'active' : '' }}" href="{{ url('/compliance/setup') }}"><i class="dripicons-gear"></i> Settings</a></li>
    </ul>
    <div class="comm-actions">
        <a href="{{ route('sales.index') }}" class="btn btn-outline-secondary btn-sm py-1 px-3" style="font-size:11.5px; border-radius:4px;"><i class="dripicons-cart"></i> Sales Bills</a>
        <a href="{{ route('purchases.index') }}" class="btn btn-outline-secondary btn-sm py-1 px-3" style="font-size:11.5px; border-radius:4px;"><i class="dripicons-card"></i> Purchase Bills</a>
    </div>
</div>
@endif

<div class="compliance-wrapper">
    @if(session('message'))
        <div class="notice" role="status">
            <i class="dripicons-checkmark text-success"></i> {{ session('message') }}
        </div>
    @endif
    @if($errors->any())
        <div class="notice error" role="alert">
            <i class="dripicons-warning text-danger"></i>
            <div>
                <strong>Review these fields:</strong>
                <ul class="mb-0 mt-1 pl-3">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    @yield('compliance_body')
</div>
@endsection
