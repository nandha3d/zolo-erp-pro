@extends('backend.layout.main')
@section('content')

<link rel="stylesheet" href="<?php echo asset('css/commercial-entry.css') . '?v=' . (file_exists(public_path('css/commercial-entry.css')) ? filemtime(public_path('css/commercial-entry.css')) : time()) ?>" type="text/css">
<link rel="stylesheet" href="<?php echo asset('css/commercial-workspace.css') . '?v=' . (file_exists(public_path('css/commercial-workspace.css')) ? filemtime(public_path('css/commercial-workspace.css')) : time()) ?>" type="text/css">
<link rel="stylesheet" href="<?php echo asset('css/zolo-erp-neo.css') . '?v=' . (file_exists(public_path('css/zolo-erp-neo.css')) ? filemtime(public_path('css/zolo-erp-neo.css')) : time()) ?>" type="text/css">

<style>
    .inv-close-workspace-view {
        padding: 12px 18px 24px;
        background: #f8fafc;
        min-height: calc(100vh - 40px);
    }
    body.dark-mode .inv-close-workspace-view {
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
    .inv-table {
        border-collapse: collapse !important;
        width: 100% !important;
    }
    .inv-table thead th {
        background: var(--z-table-head-bg, #022051) !important;
        color: var(--z-table-head-text, #ffffff) !important;
        font-size: 10.5px !important;
        font-weight: 700 !important;
        text-transform: uppercase !important;
        letter-spacing: 0.04em !important;
        padding: 9px 12px !important;
        border: none !important;
    }
    body.dark-mode .inv-table thead th {
        background: var(--z-table-head-bg, #022051) !important;
        color: var(--z-table-head-text, #ffffff) !important;
    }
    .inv-table tbody tr:nth-child(even) {
        background: var(--z-table-stripe, #f8fafc);
    }
    .inv-table tbody tr:nth-child(odd) {
        background: var(--z-surface, #ffffff);
    }
    body.dark-mode .inv-table tbody tr:nth-child(even) {
        background: #141c2c;
    }
    body.dark-mode .inv-table tbody tr:nth-child(odd) {
        background: #1e293b;
    }
    .inv-table tbody tr:hover {
        background: #e2e8f0 !important;
    }
    body.dark-mode .inv-table tbody tr:hover {
        background: #27354f !important;
    }
    .inv-table td {
        padding: 9px 12px !important;
        border-bottom: 1px solid #e2e8f0 !important;
        vertical-align: middle !important;
        font-size: 12.5px;
    }
    body.dark-mode .inv-table td {
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

<section class="inv-close-workspace-view">
    <!-- Top Command Bar -->
    <div class="comm-command-bar">
        <div class="comm-title-group">
            <h1><i class="dripicons-archive text-primary"></i> Periodic Inventory Close Command Center</h1>
            <span class="comm-title-badge"><i class="dripicons-tag"></i> Stock Valuation Preview for Authorized Branch</span>
        </div>
        <ul class="comm-nav-pills">
            <li><a class="nav-link" href="{{ route('accounting.voucher.entry') }}"><i class="dripicons-pencil"></i> Voucher Entry · F9</a></li>
            <li><a class="nav-link" href="{{ route('accounting.journal-entries') }}"><i class="dripicons-align-justify"></i> Journal Entries</a></li>
            <li><a class="nav-link" href="{{ route('accounting.coa') }}"><i class="dripicons-network"></i> Chart of Accounts</a></li>
            <li><a class="nav-link" href="{{ route('accounting.general-ledger') }}"><i class="dripicons-view-thumb"></i> General Ledger</a></li>
            <li><a class="nav-link active" href="{{ route('accounting.inventory-close') }}"><i class="dripicons-archive"></i> Periodic Close</a></li>
            <li><a class="nav-link" href="{{ route('accounting.semantic-mappings') }}"><i class="dripicons-gear"></i> Semantic Mappings</a></li>
        </ul>
        <div class="comm-actions">
            <button type="button" class="btn btn-secondary btn-action-pill" disabled aria-disabled="true">
                Inventory Close Unavailable
            </button>
        </div>
    </div>

    <!-- Metric KPI Cards -->
    <div class="kpi-summary-strip">
        <div class="kpi-card">
            <div>
                <div class="kpi-label">TOTAL PHYSICAL UNITS</div>
                <div class="kpi-number">{{ number_format($totalQty, 2) }}</div>
            </div>
            <div style="width:36px;height:36px;border-radius:6px;background:#e0f2fe;display:flex;align-items:center;justify-content:center;color:#0284c7;">
                <i class="dripicons-box" style="font-size:18px;"></i>
            </div>
        </div>
        <div class="kpi-card">
            <div>
                <div class="kpi-label">PHYSICAL STOCK VALUATION</div>
                <div class="kpi-number" style="color: var(--z-primary, #7d287e);">₹ {{ number_format($totalValuation, 2) }}</div>
            </div>
            <div style="width:36px;height:36px;border-radius:6px;background:var(--z-primary-soft, #fbf4fb);display:flex;align-items:center;justify-content:center;color:var(--z-primary, #7d287e);">
                <i class="dripicons-wallet" style="font-size:18px;"></i>
            </div>
        </div>
        <div class="kpi-card">
            <div>
                <div class="kpi-label">VALUATION BASIS</div>
                <div class="kpi-number" style="font-size:14px; font-weight:600; color:#059669;">Current Product Cost</div>
            </div>
            <div style="width:36px;height:36px;border-radius:6px;background:#d1fae5;display:flex;align-items:center;justify-content:center;color:#059669;">
                <i class="dripicons-checkmark" style="font-size:18px;"></i>
            </div>
        </div>
        <div class="kpi-card">
            <div>
                <div class="kpi-label">POSTING GATE</div>
                <div class="kpi-number" style="font-size:14px; font-weight:600; color:#d97706;">Preview Only</div>
            </div>
            <div style="width:36px;height:36px;border-radius:6px;background:#fef3c7;display:flex;align-items:center;justify-content:center;color:#d97706;">
                <i class="dripicons-lock" style="font-size:18px;"></i>
            </div>
        </div>
    </div>

    <!-- Calculation Basis Card -->
    <div class="card border-0 rounded-lg mb-3" style="background:#f1f5f9; border: 1px solid #cbd5e1 !important; border-radius: 8px;">
        <div class="card-body p-3">
            <h6 class="font-weight-bold mb-2" style="font-size: 13px; color: #0f172a;"><i class="dripicons-information text-primary mr-1"></i> Inventory Valuation Basis &amp; Policy</h6>
            <div class="row small text-muted" style="font-size: 11.5px;">
                <div class="col-md-4">
                    <strong>Valuation:</strong> Current operational snapshot for authorized warehouses.
                </div>
                <div class="col-md-4">
                    <strong>Quantity Basis:</strong> Real-time stored stock for company-owned products.
                </div>
                <div class="col-md-4">
                    <strong>Cost Basis:</strong> Current product master cost. Historical valuation and reconciliation are unavailable.
                </div>
            </div>
        </div>
    </div>

    <!-- Inventory Stock Lines Table -->
    <div class="desk-card-wrapper">
        <div class="table-responsive">
            <table class="table inv-table align-middle mb-0" id="close-table">
                <thead>
                    <tr>
                        <th>Product Details</th>
                        <th>Warehouse</th>
                        <th class="text-right">Quantity</th>
                        <th class="text-right">Unit Cost</th>
                        <th class="text-right">Line Valuation</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($stockLines as $line)
                        <tr>
                            <td>
                                <strong>{{ $line->product->name ?? 'N/A' }}</strong>
                                <div class="small text-muted font-monospace">{{ $line->product->code ?? '' }}</div>
                            </td>
                            <td>{{ $line->warehouse->name ?? 'Main Warehouse' }}</td>
                            <td class="text-right font-weight-bold font-monospace">{{ number_format($line->qty, 2) }}</td>
                            <td class="text-right font-monospace">₹ {{ number_format($line->product->cost ?? 0, 2) }}</td>
                            <td class="text-right font-weight-bold text-dark font-monospace">₹ {{ number_format($line->valuation, 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">No active warehouse inventory stocks found.</td>
                        </tr>
                    @endforelse
                </tbody>
                <tfoot style="background:#f1f5f9; font-weight:700;">
                    <tr>
                        <th colspan="2">Total Physical Inventory Valuation:</th>
                        <th class="text-right font-monospace">{{ number_format($totalQty, 2) }}</th>
                        <th></th>
                        <th class="text-right text-primary font-monospace" style="font-size:14px;">₹ {{ number_format($totalValuation, 2) }}</th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</section>

@endsection
