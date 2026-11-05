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
    <x-accounting-command-bar active="inventory-close" title="Periodic inventory close" purpose="Preview stored stock at current product cost. Closing entries cannot be posted from this screen.">
            <button type="button" class="btn btn-secondary btn-action-pill" disabled aria-disabled="true">
                Inventory Close Unavailable
            </button>
    </x-accounting-command-bar>

    <div class="desk-filter-card d-print-none">
        <form method="GET" action="{{ route('accounting.inventory-close') }}" class="row align-items-end">
            <div class="col-md-8"><label for="inventory-close-warehouse">Warehouse</label><select id="inventory-close-warehouse" name="warehouse_id" class="form-control"><option value="">All authorized warehouses</option>@foreach($warehouses as $warehouse)<option value="{{ $warehouse->id }}" @selected((string)$warehouseId === (string)$warehouse->id)>{{ $warehouse->name }}</option>@endforeach</select></div>
            <div class="col-md-4 d-flex u-gap-4px"><button type="submit" class="btn btn-primary">Apply filters</button><a href="{{ route('accounting.inventory-close') }}" class="btn btn-outline-secondary">Reset</a><button type="button" class="btn btn-outline-secondary" data-erp-print>Print / PDF</button></div>
        </form>
    </div>
    <div class="alert alert-info" role="note">This preview uses stored stock quantities, not a physical count. Quantity totals may combine different units. Historical closing valuation and ledger reconciliation are unavailable, so inventory-close posting remains disabled.</div>

    <!-- Metric KPI Cards -->
    <div class="kpi-summary-strip">
        <div class="kpi-card">
            <div>
                <div class="kpi-label">STORED QUANTITY</div>
                <div class="kpi-number">{{ number_format($totalQty, 2) }}</div>
            </div>
            <div class="z-icon-tile z-tone-info">
                <i class="dripicons-box u-fs-18px"></i>
            </div>
        </div>
        <div class="kpi-card">
            <div>
                <div class="kpi-label">CURRENT COST VALUE</div>
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
                <caption class="sr-only">Periodic inventory close</caption>
                <thead>
                    <tr>
                        <th scope="col">Product Details</th>
                        <th scope="col">Warehouse</th>
                        <th scope="col" class="text-right">Quantity</th>
                        <th scope="col" class="text-right">Unit Cost</th>
                        <th scope="col" class="text-right">Line Valuation</th>
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
                            <td colspan="5" class="text-center text-muted py-4">No stored stock matches the selected warehouse.</td>
                        </tr>
                    @endforelse
                </tbody>
                <tfoot class="u-bg-surface-3 u-fw-700">
                    <tr>
                        <th scope="col" colspan="2">Total current cost value:</th>
                        <th scope="col" class="text-right font-monospace">{{ number_format($totalQty, 2) }}</th>
                        <th scope="col"></th>
                        <th scope="col" class="text-right text-primary font-monospace u-fs-14px">₹ {{ number_format($totalValuation, 2) }}</th>
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
