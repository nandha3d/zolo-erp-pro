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
@else
<div class="comm-command-bar">
    <div class="comm-title-group">
        <h1><i class="dripicons-swap text-primary"></i> @yield('title', 'Returns & Compliance')</h1>
        <span class="comm-title-badge"><i class="dripicons-shield"></i> Sales &amp; Purchase Control · Company {{ $context->companyId ?? 1 }} / Branch {{ $context->branchId ?? 1 }}</span>
    </div>
    <ul class="comm-nav-pills">
        <li><a class="nav-link {{ request()->is('*compliance/returns*') || request()->is('*return-purchase*') || request()->is('*return-sale*') ? 'active' : '' }}" href="{{ url('/compliance/returns') }}"><i class="dripicons-return"></i> Returns &amp; Notes</a></li>
        <li><a class="nav-link {{ request()->is('*compliance/gst*') ? 'active' : '' }}" href="{{ url('/compliance/gst/report') }}"><i class="dripicons-graph-bar"></i> GST Review</a></li>
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
