@extends('backend.layout.main')
@section('content')


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
            <div class="z-icon-tile z-tone-info">
                <i class="dripicons-box u-fs-18px"></i>
            </div>
        </div>
        <div class="kpi-card">
            <div>
                <div class="kpi-label">PHYSICAL STOCK VALUATION</div>
                <div class="kpi-number u-c-primary">₹ {{ number_format($totalValuation, 2) }}</div>
            </div>
            <div class="z-icon-tile z-tone-primary">
                <i class="dripicons-wallet u-fs-18px"></i>
            </div>
        </div>
        <div class="kpi-card">
            <div>
                <div class="kpi-label">VALUATION BASIS</div>
                <div class="kpi-number u-fs-14px u-fw-600 u-c-success">Current Product Cost</div>
            </div>
            <div class="z-icon-tile z-tone-success">
                <i class="dripicons-checkmark u-fs-18px"></i>
            </div>
        </div>
        <div class="kpi-card">
            <div>
                <div class="kpi-label">POSTING GATE</div>
                <div class="kpi-number u-fs-14px u-fw-600 u-c-warning">Preview Only</div>
            </div>
            <div class="z-icon-tile z-tone-warning">
                <i class="dripicons-lock u-fs-18px"></i>
            </div>
        </div>
    </div>

    <!-- Calculation Basis Card -->
    <div class="card border-0 rounded-lg mb-3 u-bg-surface-3 u-b-1px-solid-border-strong u-r-8px">
        <div class="card-body p-3">
            <h6 class="font-weight-bold mb-2 u-fs-13px u-c-text"><i class="dripicons-information text-primary mr-1"></i> Inventory Valuation Basis &amp; Policy</h6>
            <div class="row small text-muted u-fs-11_5px">
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
                <tfoot class="u-bg-surface-3 u-fw-700">
                    <tr>
                        <th colspan="2">Total Physical Inventory Valuation:</th>
                        <th class="text-right font-monospace">{{ number_format($totalQty, 2) }}</th>
                        <th></th>
                        <th class="text-right text-primary font-monospace u-fs-14px">₹ {{ number_format($totalValuation, 2) }}</th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</section>

@endsection

@push('css')
    <link rel="stylesheet" href="<?php echo asset('css/commercial-entry.css') . '?v=' . (file_exists(public_path('css/commercial-entry.css')) ? filemtime(public_path('css/commercial-entry.css')) : time()) ?>" type="text/css">
    <link rel="stylesheet" href="<?php echo asset('css/commercial-workspace.css') . '?v=' . (file_exists(public_path('css/commercial-workspace.css')) ? filemtime(public_path('css/commercial-workspace.css')) : time()) ?>" type="text/css">
    <link rel="stylesheet" href="<?php echo asset('css/zolo-erp-neo.css') . '?v=' . (file_exists(public_path('css/zolo-erp-neo.css')) ? filemtime(public_path('css/zolo-erp-neo.css')) : time()) ?>" type="text/css">
    <link rel="stylesheet" href="{{ versioned_asset('css/pages/accounting-inventory-close.css') }}">
@endpush
