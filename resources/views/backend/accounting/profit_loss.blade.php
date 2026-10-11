@extends('backend.layout.main')

@section('content')

<link rel="stylesheet" href="<?php echo asset('css/commercial-entry.css') . '?v=' . (file_exists(public_path('css/commercial-entry.css')) ? filemtime(public_path('css/commercial-entry.css')) : time()) ?>" type="text/css">
<link rel="stylesheet" href="<?php echo asset('css/commercial-workspace.css') . '?v=' . (file_exists(public_path('css/commercial-workspace.css')) ? filemtime(public_path('css/commercial-workspace.css')) : time()) ?>" type="text/css">
<link rel="stylesheet" href="<?php echo asset('css/zolo-erp-neo.css') . '?v=' . (file_exists(public_path('css/zolo-erp-neo.css')) ? filemtime(public_path('css/zolo-erp-neo.css')) : time()) ?>" type="text/css">

<style>
    .pl-workspace-view {
        padding: 12px 18px 24px;
        background: #f8fafc;
        min-height: calc(100vh - 40px);
    }
    body.dark-mode .pl-workspace-view {
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
    .statement-section-title {
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: #0f172a;
        border-bottom: 2px solid #e2e8f0;
        padding-bottom: 6px;
        margin-bottom: 10px;
    }
    body.dark-mode .statement-section-title {
        color: #f8fafc;
        border-color: #334155;
    }
    .statement-table {
        width: 100%;
        margin-bottom: 16px;
    }
    .statement-table td {
        padding: 6px 12px;
        font-size: 13px;
        vertical-align: middle;
    }
    .statement-table tfoot td {
        padding: 8px 12px;
        font-weight: 700;
        background: #f8fafc;
        border-top: 1px solid #cbd5e1;
        border-bottom: 1px solid #cbd5e1;
    }
    body.dark-mode .statement-table tfoot td {
        background: #1e293b;
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

<section class="pl-workspace-view">
    <!-- Top Command Bar -->
    <div class="comm-command-bar d-print-none">
        <div class="comm-title-group">
            <h1><i class="dripicons-graph-line text-primary"></i> Profit &amp; Loss Command Center</h1>
            <span class="comm-title-badge"><i class="dripicons-tag"></i> Income Statement: Revenue, COGS &amp; Net Margins</span>
        </div>
        <ul class="comm-nav-pills">
            <li><a class="nav-link" href="{{ route('accounting.voucher.entry') }}"><i class="dripicons-pencil"></i> Voucher Entry · F9</a></li>
            <li><a class="nav-link" href="{{ route('accounting.journal-entries') }}"><i class="dripicons-align-justify"></i> Journal Entries</a></li>
            <li><a class="nav-link" href="{{ route('accounting.coa') }}"><i class="dripicons-network"></i> Chart of Accounts</a></li>
            <li><a class="nav-link" href="{{ route('accounting.general-ledger') }}"><i class="dripicons-view-thumb"></i> General Ledger</a></li>
            <li><a class="nav-link" href="{{ route('accounting.trial-balance') }}"><i class="dripicons-scale"></i> Trial Balance</a></li>
            <li><a class="nav-link active" href="{{ route('accounting.profit-loss') }}"><i class="dripicons-graph-line"></i> P&amp;L</a></li>
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
        <form action="{{ route('accounting.profit-loss') }}" method="GET" class="m-0">
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
                    <a href="{{ route('accounting.profit-loss') }}" class="btn btn-sm btn-outline-secondary" style="height:32px; font-size:12px; display:inline-flex; align-items:center;">Reset</a>
                </div>
            </div>
        </form>
    </div>

    <!-- KPI Summary Cards -->
    <div class="kpi-summary-strip">
        <div class="kpi-card">
            <div>
                <div class="kpi-label">TOTAL REVENUE</div>
                <div class="kpi-number" style="color:#059669;">₹ {{ number_format($report['total_revenue'], 2) }}</div>
            </div>
            <div style="width:36px;height:36px;border-radius:6px;background:#d1fae5;display:flex;align-items:center;justify-content:center;color:#059669;">
                <i class="dripicons-arrow-up" style="font-size:18px;"></i>
            </div>
        </div>
        <div class="kpi-card">
            <div>
                <div class="kpi-label">COST OF GOODS SOLD</div>
                <div class="kpi-number" style="color:#d97706;">₹ {{ number_format($report['total_cogs'], 2) }}</div>
            </div>
            <div style="width:36px;height:36px;border-radius:6px;background:#fef3c7;display:flex;align-items:center;justify-content:center;color:#d97706;">
                <i class="dripicons-cart" style="font-size:18px;"></i>
            </div>
        </div>
        <div class="kpi-card">
            <div>
                <div class="kpi-label">GROSS PROFIT</div>
                <div class="kpi-number" style="color:#0284c7;">₹ {{ number_format($report['gross_profit'], 2) }}</div>
            </div>
            <div style="width:36px;height:36px;border-radius:6px;background:#e0f2fe;display:flex;align-items:center;justify-content:center;color:#0284c7;">
                <i class="dripicons-graph-bar" style="font-size:18px;"></i>
            </div>
        </div>
        <div class="kpi-card">
            <div>
                <div class="kpi-label">NET {{ $report['net_income'] >= 0 ? 'PROFIT' : 'LOSS' }}</div>
                <div class="kpi-number" style="color: {{ $report['net_income'] >= 0 ? '#059669' : '#b91c1c' }};">
                    ₹ {{ number_format($report['net_income'], 2) }}
                </div>
            </div>
            <div style="width:36px;height:36px;border-radius:6px;background:{{ $report['net_income'] >= 0 ? '#d1fae5' : '#fee2e2' }};display:flex;align-items:center;justify-content:center;color:{{ $report['net_income'] >= 0 ? '#059669' : '#b91c1c' }};">
                <i class="{{ $report['net_income'] >= 0 ? 'dripicons-checkmark' : 'dripicons-warning' }}" style="font-size:18px;"></i>
            </div>
        </div>
    </div>

    <!-- Detailed Statement Card -->
    <div class="desk-card-wrapper">
        <div class="text-center mb-4 pb-2 border-bottom">
            <h4 class="font-weight-bold mb-1" style="color:#0f172a;">{{ $general_setting->site_title ?? 'zoloERP' }}</h4>
            <h5 class="text-muted mb-1" style="font-size:15px;">Statement of Profit &amp; Loss</h5>
            <small class="text-muted">Reporting Period: {{ $startDate ?: 'Inception' }} to {{ $endDate ?: 'Present' }}</small>
        </div>

        <!-- 1. Operating Revenue -->
        <div class="statement-section-title">1. Operating Revenue</div>
        <table class="statement-table">
            <tbody>
                @forelse($report['revenues'] as $rev)
                    <tr>
                        <td style="width: 70%;" class="font-weight-600">
                            <span class="font-monospace text-muted mr-1">{{ $rev['code'] }}</span> {{ $rev['name'] }}
                        </td>
                        <td class="text-right font-weight-bold font-monospace" style="width: 30%;">
                            ₹ {{ number_format($rev['amount'], 2) }}
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="2" class="text-muted italic py-2">No revenue recorded in this period.</td></tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr>
                    <td class="font-weight-bold">Total Operating Revenue:</td>
                    <td class="text-right text-success font-monospace font-weight-bold" style="font-size:14px;">
                        ₹ {{ number_format($report['total_revenue'], 2) }}
                    </td>
                </tr>
            </tfoot>
        </table>

        <!-- 2. Cost of Goods Sold -->
        <div class="statement-section-title mt-4">2. Cost of Goods Sold (COGS)</div>
        <table class="statement-table">
            <tbody>
                @forelse($report['cogs'] as $cg)
                    <tr>
                        <td style="width: 70%;" class="font-weight-600">
                            <span class="font-monospace text-muted mr-1">{{ $cg['code'] }}</span> {{ $cg['name'] }}
                        </td>
                        <td class="text-right font-weight-bold font-monospace" style="width: 30%;">
                            ₹ {{ number_format($cg['amount'], 2) }}
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="2" class="text-muted italic py-2">No cost of goods sold recorded.</td></tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr>
                    <td class="font-weight-bold">Total Cost of Goods Sold:</td>
                    <td class="text-right text-warning font-monospace font-weight-bold" style="font-size:14px;">
                        (₹ {{ number_format($report['total_cogs'], 2) }})
                    </td>
                </tr>
            </tfoot>
        </table>

        <!-- Gross Profit Highlight -->
        <div class="d-flex justify-content-between align-items-center p-3 mb-4 rounded" style="background:#e0f2fe; border: 1px solid #bae6fd;">
            <h5 class="font-weight-bold mb-0" style="color: #0369a1; font-size: 14px;">GROSS PROFIT</h5>
            <h4 class="font-weight-bold font-monospace mb-0" style="color: #0369a1; font-size: 18px;">
                ₹ {{ number_format($report['gross_profit'], 2) }}
            </h4>
        </div>

        <!-- 3. Operating Expenses -->
        <div class="statement-section-title mt-4">3. Operating Expenses</div>
        <table class="statement-table">
            <tbody>
                @forelse($report['operating_expenses'] as $exp)
                    <tr>
                        <td style="width: 70%;" class="font-weight-600">
                            <span class="font-monospace text-muted mr-1">{{ $exp['code'] }}</span> {{ $exp['name'] }}
                        </td>
                        <td class="text-right font-weight-bold font-monospace" style="width: 30%;">
                            ₹ {{ number_format($exp['amount'], 2) }}
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="2" class="text-muted italic py-2">No operating expenses recorded.</td></tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr>
                    <td class="font-weight-bold">Total Operating Expenses:</td>
                    <td class="text-right text-danger font-monospace font-weight-bold" style="font-size:14px;">
                        (₹ {{ number_format($report['total_operating_expenses'], 2) }})
                    </td>
                </tr>
            </tfoot>
        </table>

        <!-- Net Income Highlight -->
        <div class="d-flex justify-content-between align-items-center p-3 rounded mt-3" style="{{ $report['net_income'] >= 0 ? 'background:#dcfce7; border: 1px solid #86efac;' : 'background:#fee2e2; border: 1px solid #fca5a5;' }}">
            <div>
                <h5 class="font-weight-bold mb-0" style="color: {{ $report['net_income'] >= 0 ? '#15803d' : '#b91c1c' }}; font-size: 15px;">
                    NET {{ $report['net_income'] >= 0 ? 'PROFIT' : 'LOSS' }} FOR PERIOD
                </h5>
                <small class="text-muted">Transferable to Balance Sheet Retained Earnings</small>
            </div>
            <h3 class="font-weight-bold font-monospace mb-0" style="color: {{ $report['net_income'] >= 0 ? '#15803d' : '#b91c1c' }}; font-size: 22px;">
                ₹ {{ number_format($report['net_income'], 2) }}
            </h3>
        </div>
    </div>
</section>

@endsection
