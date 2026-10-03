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
            <div class="col-md-6">
                <h3 class="font-weight-bold text-dark m-0">Damage Stock Management</h3>
                <p class="text-muted small m-0">Log, track and account for inventory damages, spoilage and write-offs.</p>
            </div>
            <div class="col-md-6 text-right">
                <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#createDamageModal">
                    <i class="dripicons-plus"></i> + Add Damage Stock
                </button>
            </div>
        </div>

        <div class="card border-0 shadow-sm rounded-lg">
            <div class="card-body p-4">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="damage-table">
                        <thead class="bg-light text-muted uppercase small">
                            <tr>
                                <th>Date</th>
                                <th>Reference</th>
                                <th>Warehouse</th>
                                <th>Product</th>
                                <th>Quantity</th>
                                <th>Unit Cost</th>
                                <th>Total Loss Value</th>
                                <th>Reason</th>
                                <th>Recorded By</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($damageStocks as $item)
                                <tr>
                                    <td>{{ $item->created_at->format('Y-m-d H:i') }}</td>
                                    <td><span class="badge badge-secondary px-2 py-1 font-mono">{{ $item->reference_no }}</span></td>
                                    <td>{{ $item->warehouse->name ?? 'N/A' }}</td>
                                    <td><strong>{{ $item->product->name ?? 'N/A' }}</strong></td>
                                    <td><span class="text-danger font-weight-bold">-{{ number_format($item->qty, 2) }}</span></td>
                                    <td>{{ number_format($item->unit_cost, 2) }}</td>
                                    <td><span class="text-danger font-weight-bold">{{ number_format($item->total_loss, 2) }}</span></td>
                                    <td><span class="badge badge-warning px-2 py-1">{{ $item->reason }}</span></td>
                                    <td>{{ $item->user->name ?? 'System' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="text-center text-muted py-4">No damaged stock records found. Click "+ Add Damage Stock" to log damaged or expired items.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Add Damage Modal -->
<div id="createDamageModal" tabindex="-1" role="dialog" aria-labelledby="createDamageModalLabel" aria-hidden="true" class="modal fade text-left">
    <div role="document" class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <form action="{{ route('damage-stock.store') }}" method="POST">
                @csrf
                <div class="modal-header bg-dark text-white border-0 py-3">
                    <h5 id="createDamageModalLabel" class="modal-title font-weight-bold">Log Damaged / Expired Stock</h5>
                    <button type="button" data-dismiss="modal" aria-label="Close" class="close text-white opacity-75"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body p-4">
                    <p class="text-muted small mb-3">Damaged items will be instantly deducted from warehouse inventory and posted as a loss in double-entry accounting.</p>
                    
                    <div class="form-group mb-3">
                        <label class="font-weight-600 small">Warehouse *</label>
                        <select name="warehouse_id" class="form-control selectpicker" data-live-search="true" required>
                            <option value="">Select Warehouse...</option>
                            @foreach($warehouses as $wh)
                                <option value="{{ $wh->id }}">{{ $wh->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group mb-3">
                        <label class="font-weight-600 small">Product *</label>
                        <select name="product_id" class="form-control selectpicker" data-live-search="true" required>
                            <option value="">Select Product...</option>
                            @foreach($products as $prod)
                                <option value="{{ $prod->id }}">{{ $prod->name }} ({{ $prod->code }}) — Cost: {{ number_format($prod->cost, 2) }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="row">
                        <div class="col-md-6 form-group mb-3">
                            <label class="font-weight-600 small">Damaged Quantity *</label>
                            <input type="number" step="any" min="0.01" name="qty" class="form-control" placeholder="Quantity" required>
                        </div>
                        <div class="col-md-6 form-group mb-3">
                            <label class="font-weight-600 small">Reason *</label>
                            <select name="reason" class="form-control" required>
                                <option value="Damaged">Damaged in Transit/Handling</option>
                                <option value="Expired">Expired / Spoiled</option>
                                <option value="Spillage">Spillage / Leakage</option>
                                <option value="Theft">Shrinkage / Theft</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group mb-0">
                        <label class="font-weight-600 small">Notes</label>
                        <textarea name="note" class="form-control" rows="2" placeholder="Optional details regarding the loss incident"></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 p-3 bg-light">
                    <button type="button" class="btn btn-secondary px-4" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger px-4 font-weight-bold">Confirm Write-Off</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection
