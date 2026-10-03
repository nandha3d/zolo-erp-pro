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
                <p class="text-muted small m-0">Review operational physical warehouse stock valuation, weighted average unit costs, and post period-end reconciliation entries.</p>
            </div>
            <div class="col-md-5 text-right">
                <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#postCloseModal">
                    <i class="dripicons-lock"></i> Post Inventory Period Close
                </button>
            </div>
        </div>

        <!-- Metric KPI Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card border-0 shadow-sm rounded-lg p-3 bg-white">
                    <span class="small text-muted font-weight-600">Total Physical Units</span>
                    <h3 class="font-weight-bold text-dark m-0 mt-1">{{ number_format($totalQty, 2) }}</h3>
                    <small class="text-info">Across all warehouses</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm rounded-lg p-3 bg-white">
                    <span class="small text-muted font-weight-600">Physical Stock Valuation</span>
                    <h3 class="font-weight-bold text-primary m-0 mt-1">₹ {{ number_format($totalValuation, 2) }}</h3>
                    <small class="text-muted">Weighted avg. cost basis</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm rounded-lg p-3 bg-white">
                    <span class="small text-muted font-weight-600">General Ledger Balance (1040)</span>
                    <h3 class="font-weight-bold text-dark m-0 mt-1">₹ {{ number_format($glBalance, 2) }}</h3>
                    <small class="text-muted">Current book value</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm rounded-lg p-3 bg-white">
                    <span class="small text-muted font-weight-600">Valuation Variance</span>
                    <h3 class="font-weight-bold {{ $variance >= 0 ? 'text-success' : 'text-danger' }} m-0 mt-1">
                        {{ $variance >= 0 ? '+' : '' }}₹ {{ number_format($variance, 2) }}
                    </h3>
                    <small class="{{ $variance == 0 ? 'text-success' : 'text-warning' }}">
                        {{ $variance == 0 ? '100% Balanced' : 'Adjustment needed' }}
                    </small>
                </div>
            </div>
        </div>

        <!-- Calculation Basis Card (Matches Image 27) -->
        <div class="card border-0 shadow-sm rounded-lg mb-4 bg-light">
            <div class="card-body p-3">
                <h6 class="font-weight-bold text-dark mb-2"><i class="dripicons-information text-primary mr-1"></i> Inventory Valuation Basis & Policy</h6>
                <div class="row small text-muted">
                    <div class="col-md-4">
                        <strong>Valuation Date:</strong> {{ $valuationDate }}
                    </div>
                    <div class="col-md-4">
                        <strong>Quantity Basis:</strong> Current verified quantities stored per product and warehouse.
                    </div>
                    <div class="col-md-4">
                        <strong>Cost Basis:</strong> Weighted average of non-deleted purchase invoices with recorded product cost fallback.
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

<!-- Post Close Modal -->
<div id="postCloseModal" tabindex="-1" role="dialog" aria-labelledby="postCloseModalLabel" aria-hidden="true" class="modal fade text-left">
    <div role="document" class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <form action="{{ route('accounting.inventory-close.post') }}" method="POST">
                @csrf
                <input type="hidden" name="valuation_date" value="{{ $valuationDate }}">
                <input type="hidden" name="total_quantity" value="{{ $totalQty }}">
                <input type="hidden" name="total_valuation" value="{{ $totalValuation }}">
                <input type="hidden" name="warehouse_id" value="{{ $warehouseId }}">

                <div class="modal-header bg-dark text-white border-0 py-3">
                    <h5 id="postCloseModalLabel" class="modal-title font-weight-bold">Post Periodic Inventory Close</h5>
                    <button type="button" data-dismiss="modal" aria-label="Close" class="close text-white opacity-75"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body p-4">
                    <p class="text-muted small mb-3">Posting will lock the current period's stock valuation of <strong>₹ {{ number_format($totalValuation, 2) }}</strong> and automatically generate an adjustment entry to balance General Ledger Account 1040 (Inventory Asset).</p>

                    <div class="alert alert-secondary py-2 small mb-3">
                        <div><strong>Current Book Balance:</strong> ₹ {{ number_format($glBalance, 2) }}</div>
                        <div><strong>New Closed Valuation:</strong> ₹ {{ number_format($totalValuation, 2) }}</div>
                        <div><strong>Adjustment Amount:</strong> {{ $variance >= 0 ? '+' : '' }}₹ {{ number_format($variance, 2) }}</div>
                    </div>

                    <div class="form-group mb-0">
                        <label class="font-weight-600 small">Closing Notes</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="e.g. End of Month Inventory Physical Audit Closing"></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 p-3 bg-light">
                    <button type="button" class="btn btn-secondary px-4" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4 font-weight-bold">Confirm & Post Close</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection
