@extends('backend.layout.main')

@section('content')

<link rel="stylesheet" href="<?php echo asset('css/commercial-entry.css') . '?v=' . (file_exists(public_path('css/commercial-entry.css')) ? filemtime(public_path('css/commercial-entry.css')) : time()) ?>" type="text/css">
<link rel="stylesheet" href="<?php echo asset('css/commercial-workspace.css') . '?v=' . (file_exists(public_path('css/commercial-workspace.css')) ? filemtime(public_path('css/commercial-workspace.css')) : time()) ?>" type="text/css">
<link rel="stylesheet" href="<?php echo asset('css/zolo-erp-neo.css') . '?v=' . (file_exists(public_path('css/zolo-erp-neo.css')) ? filemtime(public_path('css/zolo-erp-neo.css')) : time()) ?>" type="text/css">

<style>
    .monthly-workspace-view {
        padding: 12px 18px 24px;
        background: #f8fafc;
        min-height: calc(100vh - 40px);
    }
    body.dark-mode .monthly-workspace-view {
        background: #0b0f19;
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
    .monthly-table {
        border-collapse: collapse !important;
        width: 100% !important;
    }
    .monthly-table thead th {
        background: var(--z-table-head-bg, #022051) !important;
        color: var(--z-table-head-text, #ffffff) !important;
        font-size: 10.5px !important;
        font-weight: 700 !important;
        text-transform: uppercase !important;
        letter-spacing: 0.04em !important;
        padding: 9px 12px !important;
        border: none !important;
    }
    body.dark-mode .monthly-table thead th {
        background: var(--z-table-head-bg, #022051) !important;
        color: var(--z-table-head-text, #ffffff) !important;
    }
    .monthly-table tbody tr:nth-child(even) {
        background: var(--z-table-stripe, #f8fafc);
    }
    .monthly-table tbody tr:nth-child(odd) {
        background: var(--z-surface, #ffffff);
    }
    body.dark-mode .monthly-table tbody tr:nth-child(even) {
        background: #141c2c;
    }
    body.dark-mode .monthly-table tbody tr:nth-child(odd) {
        background: #1e293b;
    }
    .monthly-table tbody tr:hover {
        background: #e2e8f0 !important;
    }
    body.dark-mode .monthly-table tbody tr:hover {
        background: #27354f !important;
    }
    .monthly-table td {
        padding: 9px 12px !important;
        border-bottom: 1px solid #e2e8f0 !important;
        vertical-align: middle !important;
        font-size: 12.5px;
    }
    body.dark-mode .monthly-table td {
        border-color: #334155 !important;
        color: #e2e8f0;
    }
</style>

<section class="monthly-workspace-view">
    <!-- Top Command Bar -->
    <div class="comm-command-bar">
        <div class="comm-title-group">
            <h1><i class="dripicons-calendar text-primary"></i> Monthly Ledger: {{ $report['account']->code }} - {{ $report['account']->name }}</h1>
            <span class="comm-title-badge"><i class="dripicons-tag"></i> Financial Year Breakdown</span>
        </div>
        <ul class="comm-nav-pills">
            <li><a class="nav-link" href="{{ route('accounting.voucher.entry') }}"><i class="dripicons-pencil"></i> Voucher Entry · F9</a></li>
            <li><a class="nav-link" href="{{ route('accounting.general-ledger', ['account_id' => $report['account']->id]) }}"><i class="dripicons-view-thumb"></i> General Ledger</a></li>
            <li><a class="nav-link active" href="#"><i class="dripicons-calendar"></i> Monthly Breakup</a></li>
            <li><a class="nav-link" href="{{ route('accounting.coa') }}"><i class="dripicons-network"></i> Chart of Accounts</a></li>
        </ul>
        <div class="comm-actions">
            <a href="{{ route('accounting.general-ledger', ['account_id' => $report['account']->id]) }}" class="btn btn-outline-secondary btn-sm font-weight-bold" style="height:28px; font-size:11px; display:inline-flex; align-items:center; gap:4px;">
                <i class="dripicons-arrow-thin-left"></i> Back to Ledger
            </a>
        </div>
    </div>

    <!-- Monthly Summary Table -->
    <div class="desk-card-wrapper mt-3">
        <div class="table-responsive">
            <table class="table monthly-table mb-0">
                <thead>
                    <tr>
                        <th scope="col">Month</th>
                        <th scope="col" class="text-right">Debit</th>
                        <th scope="col" class="text-right">Credit</th>
                        <th scope="col" class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($report['months'] as $month)
                        <tr>
                            <td class="font-weight-600">{{ $month['label'] }}</td>
                            <td class="text-right font-monospace font-weight-600" style="color: #0284c7;">
                                {{ (float)$month['debit'] > 0 ? '₹ ' . number_format((float)$month['debit'], 2) : '-' }}
                            </td>
                            <td class="text-right font-monospace font-weight-600" style="color: #d97706;">
                                {{ (float)$month['credit'] > 0 ? '₹ ' . number_format((float)$month['credit'], 2) : '-' }}
                            </td>
                            <td class="text-center">
                                <a href="{{ route('accounting.general-ledger', ['account_id' => $report['account']->id, 'start_date' => $month['start_date'], 'end_date' => $month['end_date']]) }}" class="btn btn-sm btn-outline-primary" style="height:26px; font-size:11px; padding:2px 10px; border-radius:4px;">
                                    Open Ledger
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</section>

@endsection
