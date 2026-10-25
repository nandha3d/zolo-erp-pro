@extends('backend.layout.main')
@section('content')


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
            <button type="submit" form="mapping-form" class="btn btn-primary btn-action-pill u-bg-primary u-bc-primary u-c-xffffff">
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
            <div class="z-icon-tile z-tone-info">
                <i class="dripicons-gear u-fs-18px"></i>
            </div>
        </div>
        <div class="kpi-card">
            <div>
                <div class="kpi-label">ROLES MAPPED</div>
                <div class="kpi-number u-c-success">{{ $mappedRoles }}</div>
            </div>
            <div class="z-icon-tile z-tone-success">
                <i class="dripicons-checkmark u-fs-18px"></i>
            </div>
        </div>
        <div class="kpi-card">
            <div>
                <div class="kpi-label">SETUP REQUIRED</div>
                <div class="kpi-number {{ $unmappedRoles > 0 ? 'u-c-danger' : 'u-c-success' }}">{{ $unmappedRoles }}</div>
            </div>
            <div class="z-icon-tile {{ $unmappedRoles > 0 ? 'z-tone-danger' : 'z-tone-success' }}">
                <i class="{{ $unmappedRoles > 0 ? 'dripicons-warning' : 'dripicons-checkmark' }} u-fs-18px"></i>
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
                            <th class="u-w-380px">Assigned General Ledger Account</th>
                            <th class="text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($mappings as $map)
                            <tr>
                                <td>
                                    <span class="badge badge-dark px-2 py-1 font-monospace text-uppercase u-fs-11px">
                                        {{ str_replace('_', ' ', $map->semantic_role) }}
                                    </span>
                                </td>
                                <td>
                                    <span class="badge badge-info px-2 py-1 text-uppercase u-fs-10px">{{ $map->category }}</span>
                                </td>
                                <td class="small text-muted u-fs-11_5px">{{ $map->description }}</td>
                                <td>
                                    <select name="mappings[{{ $map->semantic_role }}]" class="form-control form-control-sm u-h-32px u-fs-12px">
                                        @foreach($accounts as $acc)
                                            <option value="{{ $acc->id }}" {{ $map->account_id == $acc->id ? 'selected' : '' }}>
                                                {{ $acc->code }} — {{ $acc->name }} ({{ ucfirst($acc->type) }})
                                            </option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="text-center">
                                    <span class="badge {{ $map->account_code ? 'badge-success' : 'badge-warning' }} px-2 py-1 {{ $map->account_code ? 'u-bg-success-soft u-c-success u-b-1px-solid-success-border' : 'u-bg-warning-soft u-c-warning u-b-1px-solid-warning-border' }}">
                                        {{ $map->account_code ? 'Mapped' : 'Setup Required' }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="text-right pt-2 border-top">
                <button type="submit" class="btn btn-primary px-4 btn-sm font-weight-bold u-bg-primary u-bc-primary u-h-32px">Save Changes</button>
            </div>
        </form>
    </div>
</section>

@endsection

@push('css')
    <link rel="stylesheet" href="<?php echo asset('css/commercial-entry.css') . '?v=' . (file_exists(public_path('css/commercial-entry.css')) ? filemtime(public_path('css/commercial-entry.css')) : time()) ?>" type="text/css">
    <link rel="stylesheet" href="<?php echo asset('css/commercial-workspace.css') . '?v=' . (file_exists(public_path('css/commercial-workspace.css')) ? filemtime(public_path('css/commercial-workspace.css')) : time()) ?>" type="text/css">
    <link rel="stylesheet" href="<?php echo asset('css/zolo-erp-neo.css') . '?v=' . (file_exists(public_path('css/zolo-erp-neo.css')) ? filemtime(public_path('css/zolo-erp-neo.css')) : time()) ?>" type="text/css">
    <link rel="stylesheet" href="{{ versioned_asset('css/pages/accounting-semantic-mappings.css') }}">
@endpush
