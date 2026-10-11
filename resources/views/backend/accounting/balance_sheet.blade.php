@extends('backend.layout.main')

@section('content')

<link rel="stylesheet" href="<?php echo asset('css/commercial-entry.css') . '?v=' . (file_exists(public_path('css/commercial-entry.css')) ? filemtime(public_path('css/commercial-entry.css')) : time()) ?>" type="text/css">
<link rel="stylesheet" href="<?php echo asset('css/commercial-workspace.css') . '?v=' . (file_exists(public_path('css/commercial-workspace.css')) ? filemtime(public_path('css/commercial-workspace.css')) : time()) ?>" type="text/css">
<link rel="stylesheet" href="<?php echo asset('css/zolo-erp-neo.css') . '?v=' . (file_exists(public_path('css/zolo-erp-neo.css')) ? filemtime(public_path('css/zolo-erp-neo.css')) : time()) ?>" type="text/css">

<style>
    .bs-workspace-view {
        padding: 12px 18px 24px;
        background: #f8fafc;
        min-height: calc(100vh - 40px);
    }
    body.dark-mode .bs-workspace-view {
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
        padding: 18px 20px;
    }
    body.dark-mode .desk-card-wrapper {
        background: #141c2e;
        border-color: #334155;
    }
    .bs-table {
        width: 100%;
        margin-bottom: 16px;
    }
    .bs-table td {
        padding: 6px 12px;
        font-size: 13px;
        vertical-align: middle;
    }
    .bs-table tfoot td {
        padding: 8px 12px;
        font-weight: 700;
        border-top: 1px solid #cbd5e1;
    }
    body.dark-mode .bs-table tfoot td {
        border-color: #475569;
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

<section class="bs-workspace-view">
    <!-- Top Command Bar -->
    <div class="comm-command-bar d-print-none">
        <div class="comm-title-group">
            <h1><i class="dripicons-document text-primary"></i> Balance Sheet Command Center</h1>
            <span class="comm-title-badge"><i class="dripicons-tag"></i> Financial Position (Assets = Liabilities + Equity)</span>
        </div>
        <ul class="comm-nav-pills">
            <li><a class="nav-link" href="{{ route('accounting.voucher.entry') }}"><i class="dripicons-pencil"></i> Voucher Entry · F9</a></li>
            <li><a class="nav-link" href="{{ route('accounting.journal-entries') }}"><i class="dripicons-align-justify"></i> Journal Entries</a></li>
            <li><a class="nav-link" href="{{ route('accounting.coa') }}"><i class="dripicons-network"></i> Chart of Accounts</a></li>
            <li><a class="nav-link" href="{{ route('accounting.general-ledger') }}"><i class="dripicons-view-thumb"></i> General Ledger</a></li>
            <li><a class="nav-link" href="{{ route('accounting.trial-balance') }}"><i class="dripicons-scale"></i> Trial Balance</a></li>
            <li><a class="nav-link" href="{{ route('accounting.profit-loss') }}"><i class="dripicons-graph-line"></i> P&amp;L</a></li>
            <li><a class="nav-link active" href="{{ route('accounting.balance-sheet') }}"><i class="dripicons-document"></i> Balance Sheet</a></li>
        </ul>
        <div class="comm-actions">
            <button type="button" class="btn btn-outline-secondary btn-action-pill" onclick="window.print()">
                <i class="dripicons-print"></i> Print / PDF
            </button>
        </div>
    </div>

    <!-- Filter Card -->
    <div class="desk-filter-card d-print-none">
        <form action="{{ route('accounting.balance-sheet') }}" method="GET" class="m-0">
            <div class="row align-items-end" style="margin: 0 -6px;">
                <div class="col-md-6" style="padding: 0 6px;">
                    <label class="font-weight-600 mb-1" style="font-size: 11px; text-transform: uppercase; color: #64748b;">As of Date</label>
                    <input type="date" name="as_of_date" class="form-control form-control-sm" value="{{ $asOfDate }}" style="height:32px; font-size:12px;">
                </div>
                <div class="col-md-6 d-flex" style="padding: 0 6px;">
                    <button type="submit" class="btn btn-sm btn-primary flex-grow-1 mr-2" style="height:32px; font-size:12px; font-weight:600;">
                        <i class="dripicons-search"></i> Update Statement
                    </button>
                    <a href="{{ route('accounting.balance-sheet') }}" class="btn btn-sm btn-outline-secondary" style="height:32px; font-size:12px; display:inline-flex; align-items:center;">Today</a>
                </div>
            </div>
        </form>
    </div>

    <!-- Equation Check Banner -->
    @if($report['is_balanced'])
        <div class="alert alert-success d-flex align-items-center mb-3 border-0 shadow-sm" style="background:#dcfce7; color:#15803d; border-radius: 8px;" role="alert">
            <i class="dripicons-checkmark font-weight-bold mr-2" style="font-size: 18px;"></i>
            <div>
                <strong>Accounting Equation Balanced:</strong> Total Assets (₹ {{ number_format($report['total_assets'], 2) }}) = Total Liabilities &amp; Equity (₹ {{ number_format($report['total_liabilities_and_equity'], 2) }}).
            </div>
        </div>
    @else
        <div class="alert alert-danger d-flex align-items-center mb-3 border-0 shadow-sm" style="background:#fee2e2; color:#b91c1c; border-radius: 8px;" role="alert">
            <i class="dripicons-warning font-weight-bold mr-2" style="font-size: 18px;"></i>
            <div>
                <strong>Equation Discrepancy:</strong> Difference of ₹ {{ number_format($report['difference'], 2) }} between Total Assets and Total Liabilities &amp; Equity.
            </div>
        </div>
    @endif

    <!-- KPI Summary Strip -->
    <div class="kpi-summary-strip">
        <div class="kpi-card">
            <div>
                <div class="kpi-label">TOTAL ASSETS</div>
                <div class="kpi-number" style="color: #0284c7;">₹ {{ number_format($report['total_assets'], 2) }}</div>
            </div>
            <div style="width:36px;height:36px;border-radius:6px;background:#e0f2fe;display:flex;align-items:center;justify-content:center;color:#0284c7;">
                <i class="dripicons-wallet" style="font-size:18px;"></i>
            </div>
        </div>
        <div class="kpi-card">
            <div>
                <div class="kpi-label">TOTAL LIABILITIES</div>
                <div class="kpi-number" style="color: #b91c1c;">₹ {{ number_format($report['total_liabilities'], 2) }}</div>
            </div>
            <div style="width:36px;height:36px;border-radius:6px;background:#fee2e2;display:flex;align-items:center;justify-content:center;color:#b91c1c;">
                <i class="dripicons-warning" style="font-size:18px;"></i>
            </div>
        </div>
        <div class="kpi-card">
            <div>
                <div class="kpi-label">TOTAL EQUITY</div>
                <div class="kpi-number" style="color: #7c3aed;">₹ {{ number_format($report['total_equity'], 2) }}</div>
            </div>
            <div style="width:36px;height:36px;border-radius:6px;background:#ede9fe;display:flex;align-items:center;justify-content:center;color:#7c3aed;">
                <i class="dripicons-graph-bar" style="font-size:18px;"></i>
            </div>
        </div>
        <div class="kpi-card">
            <div>
                <div class="kpi-label">EQUATION STATUS</div>
                <div class="kpi-number" style="font-size:14px; font-weight:600; color: {{ $report['is_balanced'] ? '#059669' : '#b91c1c' }};">
                    {{ $report['is_balanced'] ? 'A = L + E (Balanced)' : 'Diff: ₹ ' . number_format($report['difference'], 2) }}
                </div>
            </div>
            <div style="width:36px;height:36px;border-radius:6px;background:{{ $report['is_balanced'] ? '#d1fae5' : '#fee2e2' }};display:flex;align-items:center;justify-content:center;color:{{ $report['is_balanced'] ? '#059669' : '#b91c1c' }};">
                <i class="{{ $report['is_balanced'] ? 'dripicons-checkmark' : 'dripicons-warning' }}" style="font-size:18px;"></i>
            </div>
        </div>
    </div>

    <!-- Two Column Balance Sheet Layout -->
    <div class="row">
        <!-- ASSETS COLUMN -->
        <div class="col-md-6 mb-3">
            <div class="desk-card-wrapper h-100 d-flex flex-column justify-content-between">
                <div>
                    <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                        <h5 class="font-weight-bold mb-0" style="color: #0284c7; font-size: 15px;">ASSETS</h5>
                        <span class="badge badge-primary" style="font-size: 10px;">Resources Owned</span>
                    </div>
                    <table class="bs-table mb-4">
                        <tbody>
                            @forelse($report['assets'] as $asset)
                                <tr>
                                    <td style="width: 70%;">
                                        <span class="font-monospace text-muted mr-1">{{ $asset['code'] }}</span>
                                        <span class="font-weight-600">{{ $asset['name'] }}</span>
                                        <small class="text-muted d-block text-capitalize">{{ str_replace('_', ' ', $asset['sub_type']) }}</small>
                                    </td>
                                    <td class="text-right font-weight-bold font-monospace align-middle" style="width: 30%;">
                                        ₹ {{ number_format($asset['balance'], 2) }}
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="2" class="text-muted italic py-3">No assets recorded.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="p-3 rounded d-flex justify-content-between align-items-center" style="background:#f1f5f9; border-top: 2px solid #cbd5e1;">
                    <h6 class="font-weight-bold mb-0" style="color:#0f172a; font-size: 13px;">TOTAL ASSETS</h6>
                    <h5 class="font-weight-bold font-monospace mb-0" style="color:#0284c7; font-size: 17px;">₹ {{ number_format($report['total_assets'], 2) }}</h5>
                </div>
            </div>
        </div>

        <!-- LIABILITIES & EQUITY COLUMN -->
        <div class="col-md-6 mb-3">
            <div class="desk-card-wrapper h-100 d-flex flex-column justify-content-between">
                <div>
                    <!-- Liabilities Section -->
                    <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                        <h5 class="font-weight-bold mb-0" style="color: #b91c1c; font-size: 15px;">LIABILITIES</h5>
                        <span class="badge badge-danger" style="font-size: 10px;">Obligations Owed</span>
                    </div>
                    <table class="bs-table mb-3">
                        <tbody>
                            @forelse($report['liabilities'] as $liab)
                                <tr>
                                    <td style="width: 70%;">
                                        <span class="font-monospace text-muted mr-1">{{ $liab['code'] }}</span>
                                        <span class="font-weight-600">{{ $liab['name'] }}</span>
                                        <small class="text-muted d-block text-capitalize">{{ str_replace('_', ' ', $liab['sub_type']) }}</small>
                                    </td>
                                    <td class="text-right font-weight-bold font-monospace align-middle" style="width: 30%;">
                                        ₹ {{ number_format($liab['balance'], 2) }}
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="2" class="text-muted italic py-2">No liabilities recorded.</td></tr>
                            @endforelse
                        </tbody>
                        <tfoot>
                            <tr>
                                <td class="font-weight-bold">Total Liabilities:</td>
                                <td class="text-right text-danger font-monospace font-weight-bold">₹ {{ number_format($report['total_liabilities'], 2) }}</td>
                            </tr>
                        </tfoot>
                    </table>

                    <!-- Equity Section -->
                    <div class="d-flex justify-content-between align-items-center mt-4 mb-3 pb-2 border-bottom">
                        <h5 class="font-weight-bold mb-0" style="color: #7c3aed; font-size: 15px;">EQUITY</h5>
                        <span class="badge badge-info" style="font-size: 10px;">Owner Capital &amp; Earnings</span>
                    </div>
                    <table class="bs-table mb-2">
                        <tbody>
                            @forelse($report['equity'] as $eq)
                                <tr>
                                    <td style="width: 70%;">
                                        <span class="font-monospace text-muted mr-1">{{ $eq['code'] }}</span>
                                        <span class="font-weight-600">{{ $eq['name'] }}</span>
                                    </td>
                                    <td class="text-right font-weight-bold font-monospace align-middle" style="width: 30%;">
                                        ₹ {{ number_format($eq['balance'], 2) }}
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="2" class="text-muted italic py-2">No equity recorded.</td></tr>
                            @endforelse
                        </tbody>
                        <tfoot>
                            <tr>
                                <td class="font-weight-bold">Total Equity:</td>
                                <td class="text-right text-info font-monospace font-weight-bold">₹ {{ number_format($report['total_equity'], 2) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <div class="p-3 rounded d-flex justify-content-between align-items-center mt-3" style="background:#f1f5f9; border-top: 2px solid #cbd5e1;">
                    <h6 class="font-weight-bold mb-0" style="color:#0f172a; font-size: 13px;">TOTAL LIABILITIES &amp; EQUITY</h6>
                    <h5 class="font-weight-bold font-monospace mb-0" style="color:#0f172a; font-size: 17px;">₹ {{ number_format($report['total_liabilities_and_equity'], 2) }}</h5>
                </div>
            </div>
        </div>
    </div>
</section>

@endsection
