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
            <div class="col-md-7 d-flex align-items-center">
                @if(!empty($general_setting->site_logo))
                    <img src="{{url('logo', $general_setting->site_logo)}}" class="mr-3 rounded" style="max-height: 48px; max-width: 140px; object-fit: contain;" alt="{{$general_setting->site_title ?? 'Logo'}}">
                @endif
                <div>
                    <h3 class="font-weight-bold text-dark m-0">Bakery &amp; Cafe Operations Hub</h3>
                    <p class="text-muted small m-0">Fast counter order tracking, daily raw materials consumption, and evening 3-minute cash drawer reconciliation.</p>
                </div>
            </div>
            <div class="col-md-5 text-right">
                <a href="{{ route('cafe.pos') }}" class="btn btn-primary btn-sm mr-2">
                    <i class="dripicons-shopping-bag"></i> Launch Cafe Quick POS
                </a>
                <button type="button" class="btn btn-info btn-sm mr-2" data-toggle="modal" data-target="#rawMaterialModal">
                    <i class="dripicons-plus"></i> + Log Raw Materials
                </button>
                <button type="button" class="btn btn-dark btn-sm" data-toggle="modal" data-target="#drawerModal">
                    <i class="dripicons-wallet"></i> + Evening Drawer Reconcile
                </button>
            </div>
        </div>

        <div class="row">
            <!-- Left Column: Daily Raw Materials -->
            <div class="col-md-6">
                <div class="card border-0 shadow-sm rounded-lg mb-4">
                    <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
                        <h5 class="m-0 font-weight-bold text-dark"><i class="dripicons-archive text-info mr-1"></i> Daily Ingredient Consumption</h5>
                        <small class="text-muted">Flour, sugar, butter, coffee, milk</small>
                    </div>
                    <div class="card-body p-4">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="bg-light small uppercase">
                                    <tr>
                                        <th>Date</th>
                                        <th>Ingredient</th>
                                        <th class="text-right">Opening</th>
                                        <th class="text-right">Consumed</th>
                                        <th class="text-right">Closing</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($rawMaterials as $mat)
                                        <tr>
                                            <td>{{ $mat->log_date }}</td>
                                            <td><strong>{{ $mat->item_name }}</strong></td>
                                            <td class="text-right">{{ number_format($mat->opening_stock, 2) }} {{ $mat->unit_code }}</td>
                                            <td class="text-right text-danger font-weight-bold">-{{ number_format($mat->consumed_today, 2) }}</td>
                                            <td class="text-right text-success font-weight-bold">{{ number_format($mat->closing_stock, 2) }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="text-center text-muted py-4">No daily ingredient logs recorded yet.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Evening Cash Drawer Reconciliations -->
            <div class="col-md-6">
                <div class="card border-0 shadow-sm rounded-lg mb-4">
                    <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
                        <h5 class="m-0 font-weight-bold text-dark"><i class="dripicons-wallet text-success mr-1"></i> Evening Cash &amp; UPI Drawer Closures</h5>
                        <small class="text-muted">Discrepancy audit</small>
                    </div>
                    <div class="card-body p-4">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="bg-light small uppercase">
                                    <tr>
                                        <th>Date</th>
                                        <th>System Sales</th>
                                        <th>Physical Counted</th>
                                        <th>Variance</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($cashDrawers as $drw)
                                        <tr>
                                            <td>{{ $drw->drawer_date }}</td>
                                            <td>₹ {{ number_format($drw->total_system_sales, 2) }}</td>
                                            <td class="font-weight-bold">₹ {{ number_format($drw->total_physical_collected, 2) }}</td>
                                            <td>
                                                <span class="font-weight-bold {{ $drw->discrepancy_amount >= 0 ? 'text-success' : 'text-danger' }}">
                                                    {{ $drw->discrepancy_amount >= 0 ? '+' : '' }}₹ {{ number_format($drw->discrepancy_amount, 2) }}
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge badge-{{ $drw->status == 'balanced' ? 'success' : ($drw->status == 'over' ? 'info' : 'danger') }} px-2 py-1 text-capitalize">
                                                    {{ $drw->status }}
                                                </span>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="text-center text-muted py-4">No drawer reconciliations logged yet.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Modal: Raw Materials -->
<div id="rawMaterialModal" tabindex="-1" role="dialog" aria-hidden="true" class="modal fade text-left">
    <div role="document" class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <form action="{{ route('cafe.raw-material.store') }}" method="POST">
                @csrf
                <div class="modal-header bg-dark text-white border-0 py-3">
                    <h5 class="modal-title font-weight-bold">Log Daily Raw Material Consumption</h5>
                    <button type="button" data-dismiss="modal" class="close text-white opacity-75"><span>&times;</span></button>
                </div>
                <div class="modal-body p-4">
                    <div class="form-group mb-3">
                        <label class="font-weight-600 small">Select Raw Material / Ingredient *</label>
                        <select name="product_id" class="form-control selectpicker" data-live-search="true" required>
                            @foreach($products as $prod)
                                <option value="{{ $prod->id }}">{{ $prod->name }} (In Stock: {{ $prod->qty }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="row">
                        <div class="col-md-6 form-group mb-3">
                            <label class="font-weight-600 small">Morning Starting Stock (kg/units) *</label>
                            <input type="number" step="any" name="opening_stock" class="form-control" value="25.00" required>
                        </div>
                        <div class="col-md-6 form-group mb-3">
                            <label class="font-weight-600 small">Consumed Today (kg/units) *</label>
                            <input type="number" step="any" min="0.01" name="consumed_today" class="form-control" placeholder="Qty consumed" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 p-3 bg-light">
                    <button type="button" class="btn btn-secondary px-4" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-info px-4 font-weight-bold">Deduct &amp; Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Evening Drawer Reconcile -->
<div id="drawerModal" tabindex="-1" role="dialog" aria-hidden="true" class="modal fade text-left">
    <div role="document" class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <form action="{{ route('cafe.drawer.store') }}" method="POST">
                @csrf
                <div class="modal-header bg-dark text-white border-0 py-3">
                    <h5 class="modal-title font-weight-bold">Evening Cash &amp; UPI Drawer Reconciliation</h5>
                    <button type="button" data-dismiss="modal" class="close text-white opacity-75"><span>&times;</span></button>
                </div>
                <div class="modal-body p-4">
                    <div class="form-group mb-3">
                        <label class="font-weight-600 small">Morning Opening Cash Float (₹) *</label>
                        <input type="number" step="any" name="opening_float" class="form-control" value="1000.00" required>
                    </div>

                    <div class="row">
                        <div class="col-md-6 form-group mb-3">
                            <label class="font-weight-600 small">Physical Cash Counted (₹) *</label>
                            <input type="number" step="any" name="physical_cash_counted" class="form-control" placeholder="Total cash in till" required>
                        </div>
                        <div class="col-md-6 form-group mb-3">
                            <label class="font-weight-600 small">UPI / QR Settlement (₹) *</label>
                            <input type="number" step="any" name="upi_settlement_counted" class="form-control" placeholder="Total UPI received" required>
                        </div>
                    </div>

                    <div class="form-group mb-0">
                        <label class="font-weight-600 small">Discrepancy Explanation (if any)</label>
                        <input type="text" name="notes" class="form-control" placeholder="e.g. Small change shortage or refund">
                    </div>
                </div>
                <div class="modal-footer border-0 p-3 bg-light">
                    <button type="button" class="btn btn-secondary px-4" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4 font-weight-bold">Finalize Drawer Close</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection
