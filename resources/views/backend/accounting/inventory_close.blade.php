@extends('backend.layout.main')
@section('content')

@if(session()->has('message'))
    <div class="alert alert-success alert-dismissible text-center">
        <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        {{ session()->get('message') }}
    </div>
@endif

<section class="forms">
    <div class="container-fluid">
        <div class="row align-items-center mb-4">
            <div class="col-md-7">
                <h3 class="font-weight-bold text-dark m-0">Periodic Inventory Valuation Close</h3>
                <p class="text-muted small m-0">Current stock preview for the authorized branch. Inventory-close posting is blocked until the inventory ledger supports dated valuation and reconciliation.</p>
            </div>
            <div class="col-md-5 text-right">
                <button type="button" class="btn btn-secondary" disabled aria-disabled="true">Inventory Close Unavailable</button>
            </div>
        </div>

        <!-- Metric KPI Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card border-0 shadow-sm rounded-lg p-3 bg-white">
                    <span class="small text-muted font-weight-600">Total Physical Units</span>
                    <h3 class="font-weight-bold text-dark m-0 mt-1">{{ number_format($totalQty, 2) }}</h3>
                    <small class="text-info">Authorized branch warehouses</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm rounded-lg p-3 bg-white">
                    <span class="small text-muted font-weight-600">Physical Stock Valuation</span>
                    <h3 class="font-weight-bold text-primary m-0 mt-1">₹ {{ number_format($totalValuation, 2) }}</h3>
                    <small class="text-muted">Current product cost</small>
                </div>
            </div>
        </div>

        <!-- Calculation Basis Card (Matches Image 27) -->
        <div class="card border-0 shadow-sm rounded-lg mb-4 bg-light">
            <div class="card-body p-3">
                <h6 class="font-weight-bold text-dark mb-2"><i class="dripicons-information text-primary mr-1"></i> Inventory Valuation Basis & Policy</h6>
                <div class="row small text-muted">
                    <div class="col-md-4">
                        <strong>Valuation:</strong> Current operational snapshot
                    </div>
                    <div class="col-md-4">
                        <strong>Quantity Basis:</strong> Current stored quantities for owned products in authorized warehouses.
                    </div>
                    <div class="col-md-4">
                        <strong>Cost Basis:</strong> Current product master cost. Historical valuation and reconciliation are unavailable.
                    </div>
                </div>
            </div>
        </div>

        <!-- Inventory Stock Lines Table (Matches Image 28) -->
        <div class="card border-0 shadow-sm rounded-lg">
            <div class="card-body p-4">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="close-table">
                        <thead class="bg-light text-muted uppercase small">
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
                                        <div class="small text-muted">{{ $line->product->code ?? '' }}</div>
                                    </td>
                                    <td>{{ $line->warehouse->name ?? 'Main Warehouse' }}</td>
                                    <td class="text-right font-weight-bold">{{ number_format($line->qty, 2) }}</td>
                                    <td class="text-right">{{ number_format($line->product->cost ?? 0, 2) }}</td>
                                    <td class="text-right font-weight-bold text-dark">{{ number_format($line->valuation, 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">No active warehouse inventory stocks found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                        <tfoot>
                            <tr class="bg-light font-weight-bold">
                                <th colspan="2">Total Physical Inventory Valuation:</th>
                                <th class="text-right">{{ number_format($totalQty, 2) }}</th>
                                <th></th>
                                <th class="text-right text-primary">₹ {{ number_format($totalValuation, 2) }}</th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
</section>

@endsection
