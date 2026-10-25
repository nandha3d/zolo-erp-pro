@extends('backend.layout.main')
@section('content')

<link rel="stylesheet" href="<?php echo asset('css/commercial-entry.css') . '?v=' . (file_exists(public_path('css/commercial-entry.css')) ? filemtime(public_path('css/commercial-entry.css')) : time()) ?>" type="text/css">
<link rel="stylesheet" href="<?php echo asset('css/commercial-workspace.css') . '?v=' . (file_exists(public_path('css/commercial-workspace.css')) ? filemtime(public_path('css/commercial-workspace.css')) : time()) ?>" type="text/css">
<link rel="stylesheet" href="<?php echo asset('css/zolo-erp-neo.css') . '?v=' . (file_exists(public_path('css/zolo-erp-neo.css')) ? filemtime(public_path('css/zolo-erp-neo.css')) : time()) ?>" type="text/css">

<style>
    .cf-workspace-view {
        padding: 12px 18px 24px;
        background: #f8fafc;
        min-height: calc(100vh - 40px);
    }
    body.dark-mode .cf-workspace-view {
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
    .cf-table {
        border-collapse: collapse !important;
        width: 100% !important;
    }
    .cf-table thead th {
        background: var(--z-table-head-bg, #022051) !important;
        color: var(--z-table-head-text, #ffffff) !important;
        font-size: 10.5px !important;
        font-weight: 700 !important;
        text-transform: uppercase !important;
        letter-spacing: 0.04em !important;
        padding: 9px 12px !important;
        border: none !important;
    }
    body.dark-mode .cf-table thead th {
        background: var(--z-table-head-bg, #022051) !important;
        color: var(--z-table-head-text, #ffffff) !important;
    }
    .cf-table td {
        padding: 9px 12px !important;
        border-bottom: 1px solid #e2e8f0 !important;
        vertical-align: middle !important;
        font-size: 13px;
    }
    body.dark-mode .cf-table td {
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

<section class="cf-workspace-view">
    <!-- Top Command Bar -->
    <div class="comm-command-bar d-print-none">
        <div class="comm-title-group">
            <h1><i class="dripicons-pulse text-primary"></i> Statement of Cash Flows</h1>
            <span class="comm-title-badge"><i class="dripicons-tag"></i> GAAP Operating, Investing &amp; Financing Cash Analysis</span>
        </div>
        <ul class="comm-nav-pills">
            <li><a class="nav-link" href="{{ route('accounting.voucher.entry') }}"><i class="dripicons-pencil"></i> Voucher Entry · F9</a></li>
            <li><a class="nav-link" href="{{ route('accounting.journal-entries') }}"><i class="dripicons-align-justify"></i> Journal Entries</a></li>
            <li><a class="nav-link" href="{{ route('accounting.coa') }}"><i class="dripicons-network"></i> Chart of Accounts</a></li>
            <li><a class="nav-link" href="{{ route('accounting.general-ledger') }}"><i class="dripicons-view-thumb"></i> General Ledger</a></li>
            <li><a class="nav-link" href="{{ route('accounting.trial-balance') }}"><i class="dripicons-scale"></i> Trial Balance</a></li>
            <li><a class="nav-link" href="{{ route('accounting.profit-loss') }}"><i class="dripicons-graph-line"></i> P&amp;L</a></li>
            <li><a class="nav-link" href="{{ route('accounting.balance-sheet') }}"><i class="dripicons-document"></i> Balance Sheet</a></li>
        </ul>
        <div class="comm-actions">
            <button onclick="window.print();" class="btn btn-outline-secondary btn-action-pill">
                <i class="dripicons-print"></i> Print Statement
            </button>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="desk-filter-card d-print-none">
        <form action="{{ route('accounting.cash-flow') }}" method="GET" class="m-0">
            <div class="row align-items-end" style="margin: 0 -6px;">
                <div class="col-md-4" style="padding: 0 6px;">
                    <label class="font-weight-600 mb-1" style="font-size: 11px; text-transform: uppercase; color: #64748b;">From Date</label>
                    <input type="date" name="start_date" class="form-control form-control-sm" value="{{ $startDate }}" style="height:32px; font-size:12px;">
                </div>
                <div class="col-md-4" style="padding: 0 6px;">
                    <label class="font-weight-600 mb-1" style="font-size: 11px; text-transform: uppercase; color: #64748b;">To Date</label>
                    <input type="date" name="end_date" class="form-control form-control-sm" value="{{ $endDate }}" style="height:32px; font-size:12px;">
                </div>
                <div class="col-md-4" style="padding: 0 6px;">
                    <button type="submit" class="btn btn-primary btn-sm w-100" style="height:32px; font-size:12px; font-weight:600;">
                        <i class="dripicons-search"></i> Generate Cash Flow
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- KPI Summary Strip -->
    <div class="kpi-summary-strip">
        <div class="kpi-card">
            <div>
                <div class="kpi-label">OPERATING INFLOWS</div>
                <div class="kpi-number" style="color: #059669;">+ ₹ {{ number_format($operatingDebits, 2) }}</div>
            </div>
            <div style="width:36px;height:36px;border-radius:6px;background:#d1fae5;display:flex;align-items:center;justify-content:center;color:#059669;">
                <i class="dripicons-arrow-up" style="font-size:18px;"></i>
            </div>
        </div>
        <div class="kpi-card">
            <div>
                <div class="kpi-label">OPERATING OUTFLOWS</div>
                <div class="kpi-number" style="color: #b91c1c;">- ₹ {{ number_format($operatingCredits, 2) }}</div>
            </div>
            <div style="width:36px;height:36px;border-radius:6px;background:#fee2e2;display:flex;align-items:center;justify-content:center;color:#b91c1c;">
                <i class="dripicons-arrow-down" style="font-size:18px;"></i>
            </div>
        </div>
        <div class="kpi-card">
            <div>
                <div class="kpi-label">NET OPERATING CASH</div>
                <div class="kpi-number" style="color: {{ $netOperatingCash >= 0 ? '#059669' : '#b91c1c' }};">
                    {{ $netOperatingCash >= 0 ? '+' : '' }}₹ {{ number_format($netOperatingCash, 2) }}
                </div>
            </div>
            <div style="width:36px;height:36px;border-radius:6px;background:{{ $netOperatingCash >= 0 ? '#d1fae5' : '#fee2e2' }};display:flex;align-items:center;justify-content:center;color:{{ $netOperatingCash >= 0 ? '#059669' : '#b91c1c' }};">
                <i class="dripicons-pulse" style="font-size:18px;"></i>
            </div>
        </div>
        <div class="kpi-card">
            <div>
                <div class="kpi-label">NET CASH FLOW</div>
                <div class="kpi-number" style="color: {{ $netCashFlow >= 0 ? '#059669' : '#b91c1c' }};">
                    {{ $netCashFlow >= 0 ? '+' : '' }}₹ {{ number_format($netCashFlow, 2) }}
                </div>
            </div>
            <div style="width:36px;height:36px;border-radius:6px;background:{{ $netCashFlow >= 0 ? '#e0f2fe' : '#fee2e2' }};display:flex;align-items:center;justify-content:center;color:{{ $netCashFlow >= 0 ? '#0284c7' : '#b91c1c' }};">
                <i class="dripicons-wallet" style="font-size:18px;"></i>
            </div>
        </div>
    </div>

    <!-- Statement Table -->
    <div class="desk-card-wrapper">
        <div class="table-responsive">
            <table class="table cf-table mb-0">
                <thead>
                    <tr>
                        <th>Cash Flow Activity Category</th>
                        <th class="text-right" style="width: 250px;">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- 1. Operating Activities -->
                    <tr style="background:#f1f5f9; font-weight:700; text-transform:uppercase;">
                        <td colspan="2"><i class="dripicons-pulse text-primary mr-1"></i> 1. Cash Flows from Operating Activities</td>
                    </tr>
                    <tr>
                        <td class="pl-4 font-weight-500">Cash Received from Customers &amp; Sales</td>
                        <td class="text-right text-success font-weight-bold font-monospace">+ ₹ {{ number_format($operatingDebits, 2) }}</td>
                    </tr>
                    <tr>
                        <td class="pl-4 font-weight-500">Cash Paid to Suppliers, Inventory &amp; Operating Expenses</td>
                        <td class="text-right text-danger font-weight-bold font-monospace">- ₹ {{ number_format($operatingCredits, 2) }}</td>
                    </tr>
                    <tr style="background: #f8fafc; font-weight: 700;">
                        <td class="pl-3 font-weight-bold">Net Cash Provided by (Used in) Operating Activities</td>
                        <td class="text-right font-monospace font-weight-bold {{ $netOperatingCash >= 0 ? 'text-success' : 'text-danger' }}">
                            {{ $netOperatingCash >= 0 ? '+' : '' }}₹ {{ number_format($netOperatingCash, 2) }}
                        </td>
                    </tr>

                    <!-- 2. Investing Activities -->
                    <tr style="background:#f1f5f9; font-weight:700; text-transform:uppercase;">
                        <td colspan="2"><i class="dripicons-graph-pie text-info mr-1"></i> 2. Cash Flows from Investing Activities</td>
                    </tr>
                    <tr>
                        <td class="pl-4 font-weight-500">Capital Equipment &amp; Fixed Asset Acquisitions</td>
                        <td class="text-right font-weight-bold text-muted font-monospace">₹ 0.00</td>
                    </tr>
                    <tr style="background: #f8fafc; font-weight: 700;">
                        <td class="pl-3 font-weight-bold">Net Cash Provided by (Used in) Investing Activities</td>
                        <td class="text-right text-muted font-monospace font-weight-bold">₹ 0.00</td>
                    </tr>

                    <!-- 3. Financing Activities -->
                    <tr style="background:#f1f5f9; font-weight:700; text-transform:uppercase;">
                        <td colspan="2"><i class="dripicons-wallet text-warning mr-1"></i> 3. Cash Flows from Financing Activities</td>
                    </tr>
                    <tr>
                        <td class="pl-4 font-weight-500">Owner Equity Contributions / Loan Borrowings</td>
                        <td class="text-right font-weight-bold text-muted font-monospace">₹ 0.00</td>
                    </tr>
                    <tr style="background: #f8fafc; font-weight: 700;">
                        <td class="pl-3 font-weight-bold">Net Cash Provided by (Used in) Financing Activities</td>
                        <td class="text-right text-muted font-monospace font-weight-bold">₹ 0.00</td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr style="background: #0f172a; color: #fff;">
                        <td><h6 class="m-0 font-weight-bold" style="color: #fff; font-size: 14px;">Net Increase / (Decrease) in Cash and Cash Equivalents</h6></td>
                        <td class="text-right font-monospace">
                            <h5 class="m-0 font-weight-bold {{ $netCashFlow >= 0 ? 'text-success' : 'text-danger' }}" style="font-size: 16px;">
                                {{ $netCashFlow >= 0 ? '+' : '' }}₹ {{ number_format($netCashFlow, 2) }}
                            </h5>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</section>

@endsection
