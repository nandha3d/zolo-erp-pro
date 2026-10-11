@extends('backend.layout.main')

@section('content')

<link rel="stylesheet" href="<?php echo asset('css/commercial-entry.css') . '?v=' . (file_exists(public_path('css/commercial-entry.css')) ? filemtime(public_path('css/commercial-entry.css')) : time()) ?>" type="text/css">
<link rel="stylesheet" href="<?php echo asset('css/commercial-workspace.css') . '?v=' . (file_exists(public_path('css/commercial-workspace.css')) ? filemtime(public_path('css/commercial-workspace.css')) : time()) ?>" type="text/css">
<link rel="stylesheet" href="<?php echo asset('css/zolo-erp-neo.css') . '?v=' . (file_exists(public_path('css/zolo-erp-neo.css')) ? filemtime(public_path('css/zolo-erp-neo.css')) : time()) ?>" type="text/css">

<style>
    .ledger-workspace-view {
        padding: 12px 18px 24px;
        background: #f8fafc;
        min-height: calc(100vh - 40px);
    }
    body.dark-mode .ledger-workspace-view {
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
    .ledger-grid-table {
        border-collapse: collapse !important;
        width: 100% !important;
    }
    .ledger-grid-table thead th {
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
    body.dark-mode .ledger-grid-table thead th {
        background: #090d16 !important;
        color: #f8fafc !important;
    }
    .ledger-grid-table tbody tr:nth-child(even) {
        background: #f8fafc;
    }
    .ledger-grid-table tbody tr:nth-child(odd) {
        background: #ffffff;
    }
    body.dark-mode .ledger-grid-table tbody tr:nth-child(even) {
        background: #141c2c;
    }
    body.dark-mode .ledger-grid-table tbody tr:nth-child(odd) {
        background: #1e293b;
    }
    .ledger-grid-table tbody tr:hover {
        background: #e2e8f0 !important;
    }
    body.dark-mode .ledger-grid-table tbody tr:hover {
        background: #27354f !important;
    }
    .ledger-grid-table td {
        padding: 9px 12px !important;
        border-bottom: 1px solid #e2e8f0 !important;
        vertical-align: middle !important;
        font-size: 12.5px;
    }
    body.dark-mode .ledger-grid-table td {
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

<section class="ledger-workspace-view">
    <!-- Top Command Bar -->
    <div class="comm-command-bar d-print-none">
        <div class="comm-title-group">
            <h1><i class="dripicons-view-thumb text-primary"></i> General Ledger Command Center</h1>
            <span class="comm-title-badge"><i class="dripicons-tag"></i> Chronological Audit &amp; Running Balance</span>
        </div>
        <ul class="comm-nav-pills">
            <li><a class="nav-link" href="{{ route('accounting.voucher.entry') }}"><i class="dripicons-pencil"></i> Voucher Entry · F9</a></li>
            <li><a class="nav-link" href="{{ route('accounting.journal-entries') }}"><i class="dripicons-align-justify"></i> Journal Entries</a></li>
            <li><a class="nav-link" href="{{ route('accounting.coa') }}"><i class="dripicons-network"></i> Chart of Accounts</a></li>
            <li><a class="nav-link active" href="{{ route('accounting.general-ledger') }}"><i class="dripicons-view-thumb"></i> General Ledger</a></li>
            <li><a class="nav-link" href="{{ route('accounting.trial-balance') }}"><i class="dripicons-scale"></i> Trial Balance</a></li>
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
        <form action="{{ route('accounting.general-ledger') }}" method="GET" class="m-0">
            <div class="row align-items-end" style="margin: 0 -6px;">
                <div class="col-md-4" style="padding: 0 6px;">
                    <label class="font-weight-600 mb-1" style="font-size: 11px; text-transform: uppercase; color: #64748b;">Select Account</label>
                    <select name="account_id" class="form-control form-control-sm" required style="height:32px; font-size:12px;">
                        @foreach($accounts as $acc)
                            <option value="{{ $acc->id }}" {{ $accountId == $acc->id ? 'selected' : '' }}>
                                {{ $acc->code }} - {{ $acc->name }} ({{ ucfirst($acc->type) }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3" style="padding: 0 6px;">
                    <label class="font-weight-600 mb-1" style="font-size: 11px; text-transform: uppercase; color: #64748b;">Start Date</label>
                    <input type="date" name="start_date" class="form-control form-control-sm" value="{{ $startDate }}" style="height:32px; font-size:12px;">
                </div>
                <div class="col-md-3" style="padding: 0 6px;">
                    <label class="font-weight-600 mb-1" style="font-size: 11px; text-transform: uppercase; color: #64748b;">End Date</label>
                    <input type="date" name="end_date" class="form-control form-control-sm" value="{{ $endDate }}" style="height:32px; font-size:12px;">
                </div>
                <div class="col-md-2 d-flex" style="padding: 0 6px;">
                    <button type="submit" class="btn btn-sm btn-primary flex-grow-1" style="height:32px; font-size:12px; font-weight:600;">
                        <i class="dripicons-search"></i> Search
                    </button>
                </div>
            </div>
        </form>
    </div>

    @if($report)
        <div class="d-flex justify-content-between align-items-center mb-2 px-1">
            <a href="{{ route('accounting.monthly-ledger', $accountId) }}" class="btn btn-sm btn-outline-info font-weight-bold" style="font-size: 11px; height: 26px; padding: 2px 10px;">
                <i class="dripicons-calendar mr-1"></i> View Monthly Breakup
            </a>
            <div class="text-muted" style="font-size: 11px;">
                Account Type: <span class="badge badge-primary" style="text-transform: uppercase;">{{ $report['account']->type }}</span>
            </div>
        </div>

        <!-- Account Summary Header Cards -->
        <div class="kpi-summary-strip">
            <div class="kpi-card">
                <div>
                    <div class="kpi-label">OPENING BALANCE</div>
                    <div class="kpi-number">₹ {{ number_format($report['opening_balance'], 2) }}</div>
                </div>
                <div style="width:36px;height:36px;border-radius:6px;background:#f1f5f9;display:flex;align-items:center;justify-content:center;color:#475569;">
                    <i class="dripicons-flag" style="font-size:18px;"></i>
                </div>
            </div>
            <div class="kpi-card">
                <div>
                    <div class="kpi-label">PERIOD DEBITS</div>
                    <div class="kpi-number" style="color: #0284c7;">₹ {{ number_format($report['total_debit'], 2) }}</div>
                </div>
                <div style="width:36px;height:36px;border-radius:6px;background:#e0f2fe;display:flex;align-items:center;justify-content:center;color:#0284c7;">
                    <i class="dripicons-arrow-up" style="font-size:18px;"></i>
                </div>
            </div>
            <div class="kpi-card">
                <div>
                    <div class="kpi-label">PERIOD CREDITS</div>
                    <div class="kpi-number" style="color: #d97706;">₹ {{ number_format($report['total_credit'], 2) }}</div>
                </div>
                <div style="width:36px;height:36px;border-radius:6px;background:#fef3c7;display:flex;align-items:center;justify-content:center;color:#d97706;">
                    <i class="dripicons-arrow-down" style="font-size:18px;"></i>
                </div>
            </div>
            <div class="kpi-card">
                <div>
                    <div class="kpi-label">CLOSING BALANCE</div>
                    <div class="kpi-number" style="color: #059669;">₹ {{ number_format($report['closing_balance'], 2) }}</div>
                </div>
                <div style="width:36px;height:36px;border-radius:6px;background:#d1fae5;display:flex;align-items:center;justify-content:center;color:#059669;">
                    <i class="dripicons-checkmark" style="font-size:18px;"></i>
                </div>
            </div>
        </div>

        <!-- Ledger Transactions Table -->
        <div class="desk-card-wrapper">
            <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                <h5 class="font-weight-bold mb-0" style="color:#0f172a; font-size:15px;">
                    {{ $report['account']->code }} - {{ $report['account']->name }}
                </h5>
                <span class="badge badge-pill badge-soft-primary" style="font-size: 11px; padding: 4px 8px;">
                    {{ ucfirst($report['account']->type) }} &bull; {{ str_replace('_', ' ', $report['account']->sub_type) }}
                </span>
            </div>
            <div class="table-responsive">
                <table class="table ledger-grid-table mb-0">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Entry #</th>
                            <th>Reference</th>
                            <th>Memo / Description</th>
                            <th class="text-right">Debit</th>
                            <th class="text-right">Credit</th>
                            <th class="text-right">Running Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Opening Balance Row -->
                        <tr style="background:#f1f5f9; font-weight:700;">
                            <td>{{ $startDate ?: '-' }}</td>
                            <td colspan="3" class="text-muted">OPENING BALANCE</td>
                            <td class="text-right">-</td>
                            <td class="text-right">-</td>
                            <td class="text-right font-monospace">₹ {{ number_format($report['opening_balance'], 2) }}</td>
                        </tr>
                        @forelse($report['transactions'] as $tx)
                            <tr>
                                <td>{{ $tx['date'] }}</td>
                                <td class="font-monospace font-weight-bold text-primary">{{ $tx['entry_number'] }}</td>
                                <td>
                                    <span class="badge badge-light border text-uppercase" style="font-size: 10px; padding: 2px 5px;">
                                        {{ $tx['reference_type'] }}
                                    </span>
                                    @if($tx['reference_no'])
                                        <small class="text-muted d-block">{{ $tx['reference_no'] }}</small>
                                    @endif
                                </td>
                                <td>{{ $tx['memo'] }}</td>
                                <td class="text-right font-weight-600 font-monospace">{{ $tx['debit'] > 0 ? '₹ ' . number_format($tx['debit'], 2) : '-' }}</td>
                                <td class="text-right font-weight-600 font-monospace">{{ $tx['credit'] > 0 ? '₹ ' . number_format($tx['credit'], 2) : '-' }}</td>
                                <td class="text-right font-weight-bold text-dark font-monospace">₹ {{ number_format($tx['running_balance'], 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">No transactions found for this account in the selected date range.</td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot style="background: #f1f5f9; font-weight: 700; color: #0f172a;">
                        <tr>
                            <td colspan="4" class="text-right" style="font-size:11.5px;">PERIOD TOTALS:</td>
                            <td class="text-right font-monospace" style="color: #0284c7;">₹ {{ number_format($report['total_debit'], 2) }}</td>
                            <td class="text-right font-monospace" style="color: #d97706;">₹ {{ number_format($report['total_credit'], 2) }}</td>
                            <td class="text-right font-monospace" style="color: #059669;">₹ {{ number_format($report['closing_balance'], 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    @endif
</section>

@endsection
