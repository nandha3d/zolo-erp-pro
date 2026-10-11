@extends('backend.layout.main')

@section('content')

<link rel="stylesheet" href="<?php echo asset('css/commercial-entry.css') . '?v=' . (file_exists(public_path('css/commercial-entry.css')) ? filemtime(public_path('css/commercial-entry.css')) : time()) ?>" type="text/css">
<link rel="stylesheet" href="<?php echo asset('css/commercial-workspace.css') . '?v=' . (file_exists(public_path('css/commercial-workspace.css')) ? filemtime(public_path('css/commercial-workspace.css')) : time()) ?>" type="text/css">
<link rel="stylesheet" href="<?php echo asset('css/zolo-erp-neo.css') . '?v=' . (file_exists(public_path('css/zolo-erp-neo.css')) ? filemtime(public_path('css/zolo-erp-neo.css')) : time()) ?>" type="text/css">

<style>
    .tb-workspace-view {
        padding: 12px 18px 24px;
        background: #f8fafc;
        min-height: calc(100vh - 40px);
    }
    body.dark-mode .tb-workspace-view {
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
    .desk-filter-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 10px 14px;
        margin-bottom: 14px;
        box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03);
    }
    body.dark-mode .desk-filter-card {
        background: #141c2e;
        border-color: #334155;
    }
    .desk-card-wrapper {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
        padding: 14px 16px;
    }
    body.dark-mode .desk-card-wrapper {
        background: #141c2e;
        border-color: #334155;
    }
    .tb-grid-table {
        border-collapse: collapse !important;
        width: 100% !important;
    }
    .tb-grid-table thead th {
        background: #0f172a !important;
        color: #ffffff !important;
        font-size: 10.5px !important;
        font-weight: 700 !important;
        text-transform: uppercase !important;
        letter-spacing: 0.04em !important;
        padding: 9px 12px !important;
        border: none !important;
        white-space: nowrap;
    }
    body.dark-mode .tb-grid-table thead th {
        background: #090d16 !important;
        color: #f8fafc !important;
    }
    .tb-grid-table tbody tr:nth-child(even) {
        background: #f8fafc;
    }
    .tb-grid-table tbody tr:nth-child(odd) {
        background: #ffffff;
    }
    body.dark-mode .tb-grid-table tbody tr:nth-child(even) {
        background: #141c2c;
    }
    body.dark-mode .tb-grid-table tbody tr:nth-child(odd) {
        background: #1e293b;
    }
    .tb-grid-table tbody tr:hover {
        background: #e2e8f0 !important;
    }
    body.dark-mode .tb-grid-table tbody tr:hover {
        background: #27354f !important;
    }
    .tb-grid-table td {
        padding: 9px 12px !important;
        border-bottom: 1px solid #e2e8f0 !important;
        vertical-align: middle !important;
        font-size: 12.5px;
    }
    body.dark-mode .tb-grid-table td {
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

<section class="tb-workspace-view">
    <!-- Top Command Bar -->
    <div class="comm-command-bar d-print-none">
        <div class="comm-title-group">
            <h1><i class="dripicons-scale text-primary"></i> Trial Balance Command Center</h1>
            <span class="comm-title-badge"><i class="dripicons-tag"></i> General Ledger Ending Balances Verification</span>
        </div>
        <ul class="comm-nav-pills">
            <li><a class="nav-link" href="{{ route('accounting.voucher.entry') }}"><i class="dripicons-pencil"></i> Voucher Entry · F9</a></li>
            <li><a class="nav-link" href="{{ route('accounting.journal-entries') }}"><i class="dripicons-align-justify"></i> Journal Entries</a></li>
            <li><a class="nav-link" href="{{ route('accounting.coa') }}"><i class="dripicons-network"></i> Chart of Accounts</a></li>
            <li><a class="nav-link" href="{{ route('accounting.general-ledger') }}"><i class="dripicons-view-thumb"></i> General Ledger</a></li>
            <li><a class="nav-link active" href="{{ route('accounting.trial-balance') }}"><i class="dripicons-scale"></i> Trial Balance</a></li>
            <li><a class="nav-link" href="{{ route('accounting.profit-loss') }}"><i class="dripicons-graph-line"></i> P&amp;L</a></li>
            <li><a class="nav-link" href="{{ route('accounting.balance-sheet') }}"><i class="dripicons-document"></i> Balance Sheet</a></li>
        </ul>
        <div class="comm-actions">
            <button type="button" class="btn btn-outline-secondary btn-action-pill" onclick="window.print()">
                <i class="dripicons-print"></i> Print / PDF
            </button>
        </div>
    </div>

    <!-- Filter Card -->
    <div class="desk-filter-card d-print-none">
        <form action="{{ route('accounting.trial-balance') }}" method="GET" class="m-0">
            <div class="row align-items-end" style="margin: 0 -6px;">
                <div class="col-md-4" style="padding: 0 6px;">
                    <label class="font-weight-600 mb-1" style="font-size: 11px; text-transform: uppercase; color: #64748b;">Start Date</label>
                    <input type="date" name="start_date" class="form-control form-control-sm" value="{{ $startDate }}" style="height:32px; font-size:12px;">
                </div>
                <div class="col-md-4" style="padding: 0 6px;">
                    <label class="font-weight-600 mb-1" style="font-size: 11px; text-transform: uppercase; color: #64748b;">End Date</label>
                    <input type="date" name="end_date" class="form-control form-control-sm" value="{{ $endDate }}" style="height:32px; font-size:12px;">
                </div>
                <div class="col-md-4 d-flex" style="padding: 0 6px;">
                    <button type="submit" class="btn btn-sm btn-primary flex-grow-1 mr-2" style="height:32px; font-size:12px; font-weight:600;">
                        <i class="dripicons-search"></i> Generate Report
                    </button>
                    <a href="{{ route('accounting.trial-balance') }}" class="btn btn-sm btn-outline-secondary" style="height:32px; font-size:12px; display:inline-flex; align-items:center;">Reset</a>
                </div>
            </div>
        </form>
    </div>

    <!-- Validation Banner -->
    @if($report['is_balanced'])
        <div class="alert alert-success d-flex align-items-center mb-3 border-0 shadow-sm" style="background:#dcfce7; color:#15803d; border-radius: 8px;" role="alert">
            <i class="dripicons-checkmark font-weight-bold mr-2" style="font-size: 18px;"></i>
            <div>
                <strong>Balanced Trial Balance:</strong> Total Debits (₹ {{ number_format($report['total_debit'], 2) }}) exactly equal Total Credits (₹ {{ number_format($report['total_credit'], 2) }}).
            </div>
        </div>
    @else
        <div class="alert alert-danger d-flex align-items-center mb-3 border-0 shadow-sm" style="background:#fee2e2; color:#b91c1c; border-radius: 8px;" role="alert">
            <i class="dripicons-warning font-weight-bold mr-2" style="font-size: 18px;"></i>
            <div>
                <strong>Trial Balance Out of Balance:</strong> Difference of ₹ {{ number_format($report['difference'], 2) }} detected between debits and credits.
            </div>
        </div>
    @endif

    <!-- KPI Summary Strip -->
    <div class="kpi-summary-strip">
        <div class="kpi-card">
            <div>
                <div class="kpi-label">TOTAL DEBITS</div>
                <div class="kpi-number" style="color:#0284c7;">₹ {{ number_format($report['total_debit'], 2) }}</div>
            </div>
            <div style="width:36px;height:36px;border-radius:6px;background:#e0f2fe;display:flex;align-items:center;justify-content:center;color:#0284c7;">
                <i class="dripicons-arrow-up" style="font-size:18px;"></i>
            </div>
        </div>
        <div class="kpi-card">
            <div>
                <div class="kpi-label">TOTAL CREDITS</div>
                <div class="kpi-number" style="color:#059669;">₹ {{ number_format($report['total_credit'], 2) }}</div>
            </div>
            <div style="width:36px;height:36px;border-radius:6px;background:#d1fae5;display:flex;align-items:center;justify-content:center;color:#059669;">
                <i class="dripicons-arrow-down" style="font-size:18px;"></i>
            </div>
        </div>
        <div class="kpi-card">
            <div>
                <div class="kpi-label">DIFFERENCE</div>
                <div class="kpi-number" style="color: {{ $report['is_balanced'] ? '#059669' : '#b91c1c' }};">
                    ₹ {{ number_format($report['difference'], 2) }}
                </div>
            </div>
            <div style="width:36px;height:36px;border-radius:6px;background:{{ $report['is_balanced'] ? '#d1fae5' : '#fee2e2' }};display:flex;align-items:center;justify-content:center;color:{{ $report['is_balanced'] ? '#059669' : '#b91c1c' }};">
                <i class="{{ $report['is_balanced'] ? 'dripicons-checkmark' : 'dripicons-warning' }}" style="font-size:18px;"></i>
            </div>
        </div>
        <div class="kpi-card">
            <div>
                <div class="kpi-label">INTEGRITY STATUS</div>
                <div class="kpi-number" style="font-size:14px; font-weight:600; color: {{ $report['is_balanced'] ? '#059669' : '#b91c1c' }};">
                    {{ $report['is_balanced'] ? 'Fully Balanced' : 'Out of Balance' }}
                </div>
            </div>
            <div style="width:36px;height:36px;border-radius:6px;background:#f1f5f9;display:flex;align-items:center;justify-content:center;color:#475569;">
                <i class="dripicons-scale" style="font-size:18px;"></i>
            </div>
        </div>
    </div>

    <!-- Trial Balance Table Container -->
    <div class="desk-card-wrapper">
        <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
            <div>
                <h5 class="font-weight-bold mb-0" style="color:#0f172a; font-size:15px;">{{ $general_setting->site_title ?? 'zoloERP' }} &bull; Trial Balance Statement</h5>
                @if($startDate || $endDate)
                    <small class="text-muted">Period: {{ $startDate ?: 'Inception' }} to {{ $endDate ?: 'Present' }}</small>
                @endif
            </div>
            <span class="badge {{ $report['is_balanced'] ? 'badge-success' : 'badge-danger' }}" style="font-size: 11px; padding: 4px 8px; {{ $report['is_balanced'] ? 'background:#dcfce7; color:#15803d; border:1px solid #bbf7d0;' : 'background:#fee2e2; color:#b91c1c;' }}">
                {{ $report['is_balanced'] ? 'Balanced Statement' : 'Discrepancy Detected' }}
            </span>
        </div>
        <div class="table-responsive">
            <table class="table tb-grid-table mb-0">
                <thead>
                    <tr>
                        <th style="width: 15%;">Account Code</th>
                        <th>Account Name</th>
                        <th style="width: 15%;">Type</th>
                        <th style="width: 20%;" class="text-right">Debit Balance</th>
                        <th style="width: 20%;" class="text-right">Credit Balance</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($report['accounts'] as $row)
                        <tr>
                            <td class="font-monospace font-weight-bold text-primary">{{ $row['code'] }}</td>
                            <td class="font-weight-600">
                                <a href="{{ route('accounting.general-ledger', ['account_id' => $row['id']]) }}" class="text-dark">
                                    {{ $row['name'] }}
                                </a>
                            </td>
                            <td>
                                <span class="badge badge-pill 
                                    @if($row['type'] == 'asset') badge-soft-primary
                                    @elseif($row['type'] == 'liability') badge-soft-danger
                                    @elseif($row['type'] == 'equity') badge-soft-info
                                    @elseif($row['type'] == 'revenue') badge-soft-success
                                    @else badge-soft-warning @endif"
                                    style="padding: 3px 7px; font-size: 10.5px;">
                                    {{ ucfirst($row['type']) }}
                                </span>
                            </td>
                            <td class="text-right font-weight-bold font-monospace">
                                {{ $row['debit'] > 0 ? '₹ ' . number_format($row['debit'], 2) : '-' }}
                            </td>
                            <td class="text-right font-weight-bold font-monospace">
                                {{ $row['credit'] > 0 ? '₹ ' . number_format($row['credit'], 2) : '-' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center py-4 text-muted">No account balances found for the selected period.</td>
                        </tr>
                    @endforelse
                </tbody>
                <tfoot style="background: #f1f5f9; font-weight: 700; color: #0f172a;">
                    <tr>
                        <td colspan="3" class="text-right" style="font-size: 11.5px;">TOTALS:</td>
                        <td class="text-right font-monospace" style="color: #0284c7;">₹ {{ number_format($report['total_debit'], 2) }}</td>
                        <td class="text-right font-monospace" style="color: #059669;">₹ {{ number_format($report['total_credit'], 2) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</section>

@endsection
