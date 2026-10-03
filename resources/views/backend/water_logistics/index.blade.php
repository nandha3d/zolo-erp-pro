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
        <!-- Header -->
        <div class="row align-items-center mb-4">
            <div class="col-md-7">
                <h3 class="font-weight-bold text-dark m-0">Water Supply &amp; Logistics ("DK Track")</h3>
                <p class="text-muted small m-0">Bulk water tanker fleet trip management (9KL/16KL/24KL) &amp; 20L can distribution with anti-theft tracking.</p>
            </div>
            <div class="col-md-5 text-right">
                <a href="{{ route('water-logistics.credit-aging') }}" class="btn btn-outline-danger btn-sm mr-2">
                    <i class="dripicons-time-reverse"></i> Credit Aging Board
                </a>
                <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#tankerTripModal">
                    <i class="dripicons-plus"></i> + Log Tanker Trip
                </button>
                <button type="button" class="btn btn-success btn-sm" data-toggle="modal" data-target="#canDeliveryModal">
                    <i class="dripicons-plus"></i> + 20L Can Delivery
                </button>
            </div>
        </div>

        <!-- KPI Metrics -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card border-0 shadow-sm rounded-lg p-3 bg-white">
                    <span class="small text-muted font-weight-600">Total Tanker Trips</span>
                    <h3 class="font-weight-bold text-dark m-0 mt-1">{{ number_format($totalTankerTrips) }}</h3>
                    <small class="text-info">Dispatched fleet trips</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm rounded-lg p-3 bg-white">
                    <span class="small text-muted font-weight-600">Total Water Delivered</span>
                    <h3 class="font-weight-bold text-primary m-0 mt-1">{{ number_format($totalWaterDispatchedKl, 1) }} KL</h3>
                    <small class="text-muted">{{ number_format($totalWaterDispatchedKl * 1000) }} Liters</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm rounded-lg p-3 bg-white">
                    <span class="small text-muted font-weight-600">20L Cans Distributed</span>
                    <h3 class="font-weight-bold text-success m-0 mt-1">{{ number_format($totalCanDeliveries) }} Cans</h3>
                    <small class="text-muted">Tata Ace routes active</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm rounded-lg p-3 bg-white">
                    <span class="small text-muted font-weight-600">Tanker Revenue Billed</span>
                    <h3 class="font-weight-bold text-dark m-0 mt-1">₹ {{ number_format($totalTankerRevenue, 2) }}</h3>
                    <small class="text-success">Synchronized with GL 4010</small>
                </div>
            </div>
        </div>

        <!-- Tabs: Tanker Trips vs 20L Can Manifests -->
        <div class="card border-0 shadow-sm rounded-lg">
            <div class="card-header bg-white border-0 py-3">
                <ul class="nav nav-tabs card-header-tabs" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active font-weight-bold" data-toggle="tab" href="#tab-tankers" role="tab">
                            <i class="dripicons-truck text-primary mr-1"></i> Bulk Water Tanker Trips (9KL / 16KL / 24KL)
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link font-weight-bold" data-toggle="tab" href="#tab-cans" role="tab">
                            <i class="dripicons-drop text-info mr-1"></i> 20L Can Route Distribution (Tata Ace)
                        </a>
                    </li>
                </ul>
            </div>
            <div class="card-body p-4">
                <div class="tab-content">
                    <!-- Tab 1: Tankers -->
                    <div class="tab-pane fade show active" id="tab-tankers" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="bg-light small uppercase">
                                    <tr>
                                        <th>Trip No</th>
                                        <th>Date</th>
                                        <th>Tanker</th>
                                        <th>Customer / Site</th>
                                        <th>KL Qty</th>
                                        <th>Rate</th>
                                        <th>Diesel</th>
                                        <th>Batta</th>
                                        <th>Site Receiver</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($trips as $trip)
                                        <tr>
                                            <td><span class="badge badge-secondary px-2 py-1 font-mono">{{ $trip->trip_number }}</span></td>
                                            <td>{{ $trip->trip_date }}</td>
                                            <td><strong>{{ $trip->tanker->vehicle_number ?? 'N/A' }}</strong> ({{ $trip->tanker->capacity_kl }}KL)</td>
                                            <td>{{ $trip->customer->name ?? 'N/A' }} <div class="small text-muted">{{ $trip->destination_site }}</div></td>
                                            <td class="font-weight-bold">{{ $trip->water_quantity_kl }} KL</td>
                                            <td>₹ {{ number_format($trip->trip_rate, 2) }}</td>
                                            <td class="text-danger">₹ {{ number_format($trip->diesel_expense, 2) }}</td>
                                            <td class="text-warning">₹ {{ number_format($trip->driver_batta, 2) }}</td>
                                            <td>{{ $trip->site_receiver_name ?: 'Signed' }}</td>
                                            <td><span class="badge badge-success px-2 py-1">{{ ucfirst($trip->status) }}</span></td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="10" class="text-center text-muted py-4">No tanker trips logged. Click "+ Log Tanker Trip" to record a delivery trip.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Tab 2: Cans -->
                    <div class="tab-pane fade" id="tab-cans" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="bg-light small uppercase">
                                    <tr>
                                        <th>Delivery No</th>
                                        <th>Date</th>
                                        <th>Route</th>
                                        <th>Customer</th>
                                        <th>Delivered</th>
                                        <th>Returned</th>
                                        <th>Total (₹)</th>
                                        <th>Paid (₹)</th>
                                        <th>Live Cans Held</th>
                                        <th>Mode</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($deliveries as $del)
                                        <tr>
                                            <td><span class="badge badge-secondary px-2 py-1 font-mono">{{ $del->delivery_no }}</span></td>
                                            <td>{{ $del->delivery_date }}</td>
                                            <td><strong>{{ $del->route->route_name ?? 'Route' }}</strong></td>
                                            <td>{{ $del->customer->name ?? 'Customer' }}</td>
                                            <td class="font-weight-bold text-success">+{{ $del->cans_delivered }}</td>
                                            <td class="font-weight-bold text-info">-{{ $del->empty_cans_returned }}</td>
                                            <td>₹ {{ number_format($del->total_amount, 2) }}</td>
                                            <td class="text-success font-weight-bold">₹ {{ number_format($del->paid_amount, 2) }}</td>
                                            <td><span class="badge badge-dark px-2 py-1">{{ $del->balance_cans_held }} Cans Held</span></td>
                                            <td><span class="badge badge-light border">{{ $del->payment_mode }}</span></td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="10" class="text-center text-muted py-4">No 20L can route deliveries recorded yet.</td>
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

<!-- Modal 1: Tanker Trip Modal -->
<div id="tankerTripModal" tabindex="-1" role="dialog" aria-hidden="true" class="modal fade text-left">
    <div role="document" class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <form action="{{ route('water-logistics.trip.store') }}" method="POST">
                @csrf
                <div class="modal-header bg-dark text-white border-0 py-3">
                    <h5 class="modal-title font-weight-bold">Log Water Tanker Trip Sheet</h5>
                    <button type="button" data-dismiss="modal" class="close text-white opacity-75"><span>&times;</span></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row">
                        <div class="col-md-6 form-group mb-3">
                            <label class="font-weight-600 small">Select Tanker Vehicle *</label>
                            <select name="tanker_id" class="form-control" required>
                                @foreach($tankers as $tnk)
                                    <option value="{{ $tnk->id }}">{{ $tnk->vehicle_number }} ({{ $tnk->capacity_kl }} KL)</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 form-group mb-3">
                            <label class="font-weight-600 small">Select Customer / Client *</label>
                            <select name="customer_id" class="form-control selectpicker" data-live-search="true" required>
                                @foreach($customers as $c)
                                    <option value="{{ $c->id }}">{{ $c->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 form-group mb-3">
                            <label class="font-weight-600 small">Destination Site *</label>
                            <input type="text" name="destination_site" class="form-control" placeholder="Factory / Site location" required>
                        </div>
                        <div class="col-md-6 form-group mb-3">
                            <label class="font-weight-600 small">Delivered Qty (KL) *</label>
                            <input type="number" step="0.1" min="0.5" name="water_quantity_kl" class="form-control" value="16.0" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 form-group mb-3">
                            <label class="font-weight-600 small">Trip Rate (₹) *</label>
                            <input type="number" step="any" name="trip_rate" class="form-control" value="2500" required>
                        </div>
                        <div class="col-md-4 form-group mb-3">
                            <label class="font-weight-600 small">Diesel (₹)</label>
                            <input type="number" step="any" name="diesel_expense" class="form-control" value="650">
                        </div>
                        <div class="col-md-4 form-group mb-3">
                            <label class="font-weight-600 small">Driver Batta (₹)</label>
                            <input type="number" step="any" name="driver_batta" class="form-control" value="300">
                        </div>
                    </div>

                    <div class="form-group mb-0">
                        <label class="font-weight-600 small">Site Receiver Name / Contact</label>
                        <input type="text" name="site_receiver_name" class="form-control" placeholder="Name of site engineer or incharge">
                    </div>
                </div>
                <div class="modal-footer border-0 p-3 bg-light">
                    <button type="button" class="btn btn-secondary px-4" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4 font-weight-bold">Submit Trip Sheet</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal 2: 20L Can Delivery Modal -->
<div id="canDeliveryModal" tabindex="-1" role="dialog" aria-hidden="true" class="modal fade text-left">
    <div role="document" class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <form action="{{ route('water-logistics.can.store') }}" method="POST">
                @csrf
                <div class="modal-header bg-dark text-white border-0 py-3">
                    <h5 class="modal-title font-weight-bold">Log 20L Can Route Delivery</h5>
                    <button type="button" data-dismiss="modal" class="close text-white opacity-75"><span>&times;</span></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row">
                        <div class="col-md-6 form-group mb-3">
                            <label class="font-weight-600 small">Delivery Route (Tata Ace) *</label>
                            <select name="route_id" class="form-control" required>
                                @foreach($routes as $rt)
                                    <option value="{{ $rt->id }}">{{ $rt->route_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 form-group mb-3">
                            <label class="font-weight-600 small">Customer *</label>
                            <select name="customer_id" class="form-control selectpicker" data-live-search="true" required>
                                @foreach($customers as $c)
                                    <option value="{{ $c->id }}">{{ $c->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 form-group mb-3">
                            <label class="font-weight-600 small">Cans Delivered *</label>
                            <input type="number" min="1" name="cans_delivered" class="form-control" value="10" required>
                        </div>
                        <div class="col-md-6 form-group mb-3">
                            <label class="font-weight-600 small">Empty Cans Returned *</label>
                            <input type="number" min="0" name="empty_cans_returned" class="form-control" value="8" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 form-group mb-3">
                            <label class="font-weight-600 small">Rate per Can (₹)</label>
                            <input type="number" step="any" name="can_rate" class="form-control" value="35.00" required>
                        </div>
                        <div class="col-md-4 form-group mb-3">
                            <label class="font-weight-600 small">Paid Amount (₹)</label>
                            <input type="number" step="any" name="paid_amount" class="form-control" value="350.00" required>
                        </div>
                        <div class="col-md-4 form-group mb-3">
                            <label class="font-weight-600 small">Payment Mode</label>
                            <select name="payment_mode" class="form-control">
                                <option value="Cash">Cash</option>
                                <option value="UPI">UPI / QR</option>
                                <option value="Credit">Corporate Credit</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 p-3 bg-light">
                    <button type="button" class="btn btn-secondary px-4" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success px-4 font-weight-bold">Confirm Can Delivery</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection
