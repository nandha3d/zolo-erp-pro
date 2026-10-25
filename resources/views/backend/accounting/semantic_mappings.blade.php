@extends('backend.layout.main')
@section('content')

<link rel="stylesheet" href="<?php echo asset('css/commercial-entry.css') . '?v=' . (file_exists(public_path('css/commercial-entry.css')) ? filemtime(public_path('css/commercial-entry.css')) : time()) ?>" type="text/css">
<link rel="stylesheet" href="<?php echo asset('css/commercial-workspace.css') . '?v=' . (file_exists(public_path('css/commercial-workspace.css')) ? filemtime(public_path('css/commercial-workspace.css')) : time()) ?>" type="text/css">
<link rel="stylesheet" href="<?php echo asset('css/zolo-erp-neo.css') . '?v=' . (file_exists(public_path('css/zolo-erp-neo.css')) ? filemtime(public_path('css/zolo-erp-neo.css')) : time()) ?>" type="text/css">

<style>
    .semantic-workspace-view {
        padding: 12px 18px 24px;
        background: #f8fafc;
        min-height: calc(100vh - 40px);
    }
    body.dark-mode .semantic-workspace-view {
        background: #0b0f19;
    }
    .kpi-summary-strip {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 12px;
        margin-bottom: 14px;
    }
    .kpi-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 12px 16px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03);
    }
    body.dark-mode .kpi-card {
        background: #141c2e;
        border-color: #334155;
    }
    .kpi-card .kpi-label {
        font-size: 10px;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        font-weight: 700;
        color: #64748b;
        margin-bottom: 2px;
    }
    .kpi-card .kpi-number {
        font-size: 19px;
        font-weight: 700;
        color: #0f172a;
    }
    body.dark-mode .kpi-card .kpi-number {
        color: #f8fafc;
    }
    .desk-card-wrapper {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
        padding: 18px 20px;
    }
    body.dark-mode .desk-card-wrapper {
        background: #141c2e;
        border-color: #334155;
    }
    .semantic-table {
        border-collapse: collapse !important;
        width: 100% !important;
    }
    .semantic-table thead th {
        background: var(--z-table-head-bg, #022051) !important;
        color: var(--z-table-head-text, #ffffff) !important;
        font-size: 10.5px !important;
        font-weight: 700 !important;
        text-transform: uppercase !important;
        letter-spacing: 0.04em !important;
        padding: 9px 12px !important;
        border: none !important;
    }
    body.dark-mode .semantic-table thead th {
        background: var(--z-table-head-bg, #022051) !important;
        color: var(--z-table-head-text, #ffffff) !important;
    }
    .semantic-table tbody tr:nth-child(even) {
        background: var(--z-table-stripe, #f8fafc);
    }
    .semantic-table tbody tr:nth-child(odd) {
        background: var(--z-surface, #ffffff);
    }
    body.dark-mode .semantic-table tbody tr:nth-child(even) {
        background: #141c2c;
    }
    body.dark-mode .semantic-table tbody tr:nth-child(odd) {
        background: #1e293b;
    }
    .semantic-table tbody tr:hover {
        background: #e2e8f0 !important;
    }
    body.dark-mode .semantic-table tbody tr:hover {
        background: #27354f !important;
    }
    .semantic-table td {
        padding: 9px 12px !important;
        border-bottom: 1px solid #e2e8f0 !important;
        vertical-align: middle !important;
        font-size: 12.5px;
    }
    body.dark-mode .semantic-table td {
        border-color: #334155 !important;
        color: #e2e8f0;
    }
    .btn-action-pill {
        height: 28px !important;
        font-size: 11px !important;
        padding: 0 12px !important;
        border-radius: 4px !important;
        font-weight: 600 !important;
        display: inline-flex !important;
        align-items: center !important;
        gap: 5px !important;
    }
</style>

@if(session()->has('message'))
    <div class="alert alert-success alert-dismissible text-center">
        <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        {{ session()->get('message') }}
    </div>
@endif

@php
    $totalRoles = count($mappings);
    $mappedRoles = $mappings->whereNotNull('account_code')->count();
    $unmappedRoles = $totalRoles - $mappedRoles;
@endphp

<section class="semantic-workspace-view">
    <!-- Top Command Bar -->
    <div class="comm-command-bar">
        <div class="comm-title-group">
            <h1><i class="dripicons-gear text-primary"></i> Semantic Account Mappings</h1>
            <span class="comm-title-badge"><i class="dripicons-tag"></i> Automated Double-Entry Posting Configuration</span>
        </div>
        <ul class="comm-nav-pills">
            <li><a class="nav-link" href="{{ route('accounting.voucher.entry') }}"><i class="dripicons-pencil"></i> Voucher Entry · F9</a></li>
            <li><a class="nav-link" href="{{ route('accounting.journal-entries') }}"><i class="dripicons-align-justify"></i> Journal Entries</a></li>
            <li><a class="nav-link" href="{{ route('accounting.coa') }}"><i class="dripicons-network"></i> Chart of Accounts</a></li>
            <li><a class="nav-link" href="{{ route('accounting.general-ledger') }}"><i class="dripicons-view-thumb"></i> General Ledger</a></li>
            <li><a class="nav-link" href="{{ route('accounting.inventory-close') }}"><i class="dripicons-archive"></i> Periodic Close</a></li>
            <li><a class="nav-link active" href="{{ route('accounting.semantic-mappings') }}"><i class="dripicons-gear"></i> Semantic Mappings</a></li>
        </ul>
        <div class="comm-actions">
            <button type="submit" form="mapping-form" class="btn btn-primary btn-action-pill" style="background:var(--z-primary, #7d287e); border-color:var(--z-primary, #7d287e); color:#fff;">
                <i class="dripicons-checkmark"></i> Save Account Mappings
            </button>
        </div>
    </div>

    <!-- KPI Summary Strip -->
    <div class="kpi-summary-strip">
        <div class="kpi-card">
            <div>
                <div class="kpi-label">TOTAL SEMANTIC ROLES</div>
                <div class="kpi-number">{{ $totalRoles }}</div>
            </div>
            <div style="width:36px;height:36px;border-radius:6px;background:#e0f2fe;display:flex;align-items:center;justify-content:center;color:#0284c7;">
                <i class="dripicons-gear" style="font-size:18px;"></i>
            </div>
        </div>
        <div class="kpi-card">
            <div>
                <div class="kpi-label">ROLES MAPPED</div>
                <div class="kpi-number" style="color: #059669;">{{ $mappedRoles }}</div>
            </div>
            <div style="width:36px;height:36px;border-radius:6px;background:#d1fae5;display:flex;align-items:center;justify-content:center;color:#059669;">
                <i class="dripicons-checkmark" style="font-size:18px;"></i>
            </div>
        </div>
        <div class="kpi-card">
            <div>
                <div class="kpi-label">SETUP REQUIRED</div>
                <div class="kpi-number" style="color: {{ $unmappedRoles > 0 ? '#b91c1c' : '#059669' }};">{{ $unmappedRoles }}</div>
            </div>
            <div style="width:36px;height:36px;border-radius:6px;background:{{ $unmappedRoles > 0 ? '#fee2e2' : '#d1fae5' }};display:flex;align-items:center;justify-content:center;color:{{ $unmappedRoles > 0 ? '#b91c1c' : '#059669' }};">
                <i class="{{ $unmappedRoles > 0 ? 'dripicons-warning' : 'dripicons-checkmark' }}" style="font-size:18px;"></i>
            </div>
        </div>
    </div>

    <!-- Mappings Table Form Container -->
    <div class="desk-card-wrapper">
        <form action="{{ route('accounting.semantic-mappings.update') }}" method="POST" id="mapping-form">
            @csrf
            <div class="table-responsive">
                <table class="table semantic-table align-middle mb-3">
                    <thead>
                        <tr>
                            <th>Semantic ERP Role</th>
                            <th>Category</th>
                            <th>Description</th>
                            <th style="width: 380px;">Assigned General Ledger Account</th>
                            <th class="text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($mappings as $map)
                            <tr>
                                <td>
                                    <span class="badge badge-dark px-2 py-1 font-monospace text-uppercase" style="font-size: 11px;">
                                        {{ str_replace('_', ' ', $map->semantic_role) }}
                                    </span>
                                </td>
                                <td>
                                    <span class="badge badge-info px-2 py-1 text-uppercase" style="font-size: 10px;">{{ $map->category }}</span>
                                </td>
                                <td class="small text-muted" style="font-size: 11.5px;">{{ $map->description }}</td>
                                <td>
                                    <select name="mappings[{{ $map->semantic_role }}]" class="form-control form-control-sm" style="height:32px; font-size:12px;">
                                        @foreach($accounts as $acc)
                                            <option value="{{ $acc->id }}" {{ $map->account_id == $acc->id ? 'selected' : '' }}>
                                                {{ $acc->code }} — {{ $acc->name }} ({{ ucfirst($acc->type) }})
                                            </option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="text-center">
                                    <span class="badge {{ $map->account_code ? 'badge-success' : 'badge-warning' }} px-2 py-1" style="{{ $map->account_code ? 'background:#dcfce7; color:#15803d; border:1px solid #bbf7d0;' : 'background:#fef3c7; color:#d97706; border:1px solid #fde68a;' }}">
                                        {{ $map->account_code ? 'Mapped' : 'Setup Required' }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="text-right pt-2 border-top">
                <button type="submit" class="btn btn-primary px-4 btn-sm font-weight-bold" style="background:var(--z-primary, #7d287e); border-color:var(--z-primary, #7d287e); height:32px;">Save Changes</button>
            </div>
        </form>
    </div>
</section>

@endsection
