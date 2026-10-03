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
                <h3 class="font-weight-bold text-dark m-0">Sale Exchange List</h3>
                <p class="text-muted small m-0">Handle customer returns and replacement exchanges in a unified seamless transaction.</p>
            </div>
            <div class="col-md-6 text-right">
                <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#exchangePromptModal">
                    <i class="dripicons-plus"></i> + Add Sale Exchange
                </button>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="card border-0 shadow-sm rounded-lg mb-4">
            <div class="card-body p-3">
                <form action="{{ route('exchange.index') }}" method="GET" class="row align-items-end">
                    <div class="col-md-4 form-group mb-0">
                        <label class="small font-weight-600 text-muted">Warehouse</label>
                        <select name="warehouse_id" class="form-control form-control-sm">
                            <option value="">All Warehouses</option>
                            @foreach($warehouses as $wh)
                                <option value="{{ $wh->id }}" {{ request('warehouse_id') == $wh->id ? 'selected' : '' }}>{{ $wh->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3 form-group mb-0">
                        <label class="small font-weight-600 text-muted">Start Date</label>
                        <input type="date" name="start_date" class="form-control form-control-sm" value="{{ request('start_date') }}">
                    </div>
                    <div class="col-md-3 form-group mb-0">
                        <label class="small font-weight-600 text-muted">End Date</label>
                        <input type="date" name="end_date" class="form-control form-control-sm" value="{{ request('end_date') }}">
                    </div>
                    <div class="col-md-2 form-group mb-0">
                        <button type="submit" class="btn btn-dark btn-sm btn-block">Filter</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card border-0 shadow-sm rounded-lg">
            <div class="card-body p-4">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="exchange-table">
                        <thead class="bg-light text-muted uppercase small">
                            <tr>
                                <th>Date</th>
                                <th>Exchange Ref</th>
                                <th>Original Sale</th>
                                <th>Warehouse</th>
                                <th>Customer</th>
                                <th>Returned Value</th>
                                <th>Exchanged Value</th>
                                <th>Difference</th>
                                <th>Payment</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($exchanges as $ex)
                                <tr>
                                    <td>{{ $ex->created_at->format('Y-m-d H:i') }}</td>
                                    <td><span class="badge badge-secondary px-2 py-1 font-mono">{{ $ex->reference_no }}</span></td>
                                    <td>{{ $ex->originalSale->reference_no ?? 'Direct' }}</td>
                                    <td>{{ $ex->warehouse->name ?? 'N/A' }}</td>
                                    <td><strong>{{ $ex->customer->name ?? 'Walk-in' }}</strong></td>
                                    <td><span class="text-danger font-weight-bold">-{{ number_format($ex->returned_total, 2) }}</span></td>
                                    <td><span class="text-success font-weight-bold">+{{ number_format($ex->exchanged_total, 2) }}</span></td>
                                    <td>
                                        @if($ex->difference_amount > 0)
                                            <span class="badge badge-success px-2 py-1">+{{ number_format($ex->difference_amount, 2) }} (Collected)</span>
                                        @elseif($ex->difference_amount < 0)
                                            <span class="badge badge-danger px-2 py-1">{{ number_format($ex->difference_amount, 2) }} (Refunded)</span>
                                        @else
                                            <span class="badge badge-secondary px-2 py-1">Even (0.00)</span>
                                        @endif
                                    </td>
                                    <td>{{ $ex->payment_method ?? 'Cash' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="text-center text-muted py-4">No product exchanges recorded. Click "+ Add Sale Exchange" to start.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Exchange Reference Prompt Modal (Matches Image 22) -->
<div id="exchangePromptModal" tabindex="-1" role="dialog" aria-labelledby="exchangePromptModalLabel" aria-hidden="true" class="modal fade text-left">
    <div role="document" class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <form action="{{ route('exchange.create') }}" method="GET">
                <div class="modal-header bg-dark text-white border-0 py-3">
                    <h5 id="exchangePromptModalLabel" class="modal-title font-weight-bold">Add Sale Exchange</h5>
                    <button type="button" data-dismiss="modal" aria-label="Close" class="close text-white opacity-75"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body p-4">
                    <div class="alert alert-info py-2 small mb-3">
                        You can enter a Sale Reference to exchange items from a specific sale, or continue without it to open a blank Exchange page.
                    </div>
                    
                    <div class="form-group mb-0">
                        <label class="font-weight-600 small">Sale Reference (Optional)</label>
                        <input type="text" name="reference_no" class="form-control" placeholder="Example: posr-20260820-015728">
                    </div>
                </div>
                <div class="modal-footer border-0 p-3 bg-light">
                    <button type="button" class="btn btn-secondary px-4" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4 font-weight-bold">Proceed to Exchange</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection
