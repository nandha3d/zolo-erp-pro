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
        <!-- Header & Tanker Banner -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card border-0 shadow-sm rounded-lg p-4 bg-white">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <div class="d-flex align-items-center gap-3 mb-2">
                                <span class="badge badge-info p-2 px-3 text-uppercase font-weight-bold u-fs-13px u-ls-0_5px">
                                    <i class="fa fa-truck mr-1"></i> {{ $manifest['vehicle']['vehicle_number'] ?? 'TN-58-BW-2001' }}
                                </span>
                                <span class="badge badge-success p-2 px-3 text-uppercase font-weight-bold u-fs-12px">
                                    <i class="fa fa-lock mr-1"></i> Anti-Siphon Valve Secured
                                </span>
                            </div>
                            <h3 class="font-weight-bold text-dark m-0">Bulk Tanker Operational Dispatch &amp; Telemetry</h3>
                            <p class="text-muted small m-0 mt-1">
                                {{ $manifest['vehicle']['model'] ?? 'Tata LPT 2518 (20KL Dual Chamber)' }} &bull; Driver: <strong>{{ $manifest['driver_name'] ?? 'K. Murugan' }}</strong> ({{ $manifest['driver_phone'] ?? '9842100001' }})
                            </p>
                        </div>
                        <div class="col-md-4 text-right">
                            <a href="{{ route('fleet.tracking') }}" class="btn btn-outline-primary btn-sm mr-2">
                                <i class="fa fa-crosshairs mr-1"></i> Fleet Radar Telematics
                            </a>
                            <a href="{{ route('water-logistics.index') }}" class="btn btn-primary btn-sm">
                                <i class="fa fa-file-text-o mr-1"></i> Tanker Trip Sheets
                            </a>
                        </div>
                    </div>

                    <!-- Live Meter & Odometer Strip -->
                    <div class="row mt-4 pt-3 border-top">
                        <div class="col-md-3 border-right text-center">
                            <span class="text-muted small font-weight-600 text-uppercase">Current Odometer</span>
                            <h4 class="font-weight-bold text-dark m-0 mt-1 font-monospace">{{ number_format($manifest['vehicle']['current_odometer'] ?? 73513) }} KM</h4>
                            <small class="text-muted">GPS verified calibration</small>
                        </div>
                        <div class="col-md-3 border-right text-center">
                            <span class="text-muted small font-weight-600 text-uppercase">Today's Completed Trips</span>
                            <h4 class="font-weight-bold text-primary m-0 mt-1">{{ $manifest['stats']['trips_today'] ?? 4 }} Trips</h4>
                            <small class="text-muted">Trips executed today</small>
                        </div>
                        <div class="col-md-3 border-right text-center">
                            <span class="text-muted small font-weight-600 text-uppercase">Water Decanted Today</span>
                            <h4 class="font-weight-bold text-info m-0 mt-1">{{ number_format($manifest['stats']['total_kl'] ?? 48, 1) }} KL</h4>
                            <small class="text-muted">{{ number_format(($manifest['stats']['total_kl'] ?? 48) * 1000) }} Litres Delivered</small>
                        </div>
                        <div class="col-md-3 text-center">
                            <span class="text-muted small font-weight-600 text-uppercase">Decanting Flow Telemetry</span>
                            <h4 class="font-weight-bold text-success m-0 mt-1"><i class="fa fa-dashboard mr-1"></i> 0 L/min</h4>
                            <small class="text-success"><i class="fa fa-check-circle"></i> Valve closed / Idle</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Active Destination Site & Turn-by-Turn Card -->
        <div class="row mb-4">
            <div class="col-md-8">
                <div class="card border-0 shadow-sm rounded-lg p-4 bg-white h-100">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="font-weight-bold text-dark m-0">
                            <i class="fa fa-map-marker text-danger mr-2"></i> Active Assigned Customer Site
                        </h5>
                        @if(!empty($manifest['active_trip']['trip_number']))
                            <span class="badge badge-light border text-primary font-weight-bold px-2 py-1">
                                Trip #{{ $manifest['active_trip']['trip_number'] }}
                            </span>
                        @endif
                    </div>

                    @if(!empty($manifest['active_trip']))
                        <div class="p-3 rounded-lg mb-3 u-bg-surface-2 u-b-1px-solid-border">
                            <div class="row align-items-center">
                                <div class="col-md-8">
                                    <h4 class="font-weight-bold text-dark m-0">{{ $manifest['active_trip']['customer_name'] }}</h4>
                                    <p class="text-muted small m-0 mt-1">
                                        <i class="fa fa-building-o mr-1"></i> Destination: <strong>{{ $manifest['active_trip']['destination_site'] ?? 'Industrial Unit' }}</strong>
                                    </p>
                                    <p class="text-muted small m-0 mt-1">
                                        <i class="fa fa-map-pin mr-1"></i> {{ $manifest['active_trip']['address'] }}
                                    </p>
                                    <p class="text-muted small m-0 mt-1">
                                        <i class="fa fa-phone mr-1"></i> Receiver Contact: <a href="tel:{{ $manifest['active_trip']['phone'] }}">{{ $manifest['active_trip']['phone'] }}</a>
                                    </p>
                                </div>
                                <div class="col-md-4 text-right">
                                    <div class="mb-2">
                                        <span class="badge badge-pill badge-primary px-3 py-2 u-fs-13px">
                                            <i class="fa fa-tint mr-1"></i> {{ number_format($manifest['active_trip']['water_decanted_liters'] ?? 16000) }} Litres ({{ $manifest['active_trip']['water_quantity_kl'] ?? 16 }} KL)
                                        </span>
                                    </div>
                                    @if(!empty($manifest['active_trip']['google_maps_url']))
                                        <a href="{{ $manifest['active_trip']['google_maps_url'] }}" target="_blank" class="btn btn-success btn-sm btn-block">
                                            <i class="fa fa-location-arrow mr-1"></i> 1-Tap Google Maps Navigation
                                        </a>
                                    @else
                                        <span class="badge badge-warning p-2 d-block text-center">GPS Pin Missing</span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <!-- Trip Milestones & Form -->
                        <div class="row pt-2">
                            <div class="col-md-4">
                                <div class="p-3 rounded border text-center">
                                    <span class="small text-muted font-weight-600">Start Odometer</span>
                                    <h5 class="font-weight-bold text-dark m-0 mt-1">{{ $manifest['active_trip']['start_km'] ?? 73513 }} KM</h5>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="p-3 rounded border text-center">
                                    <span class="small text-muted font-weight-600">Base Water Freight</span>
                                    <h5 class="font-weight-bold text-dark m-0 mt-1">₹ {{ number_format($manifest['active_trip']['trip_rate'] ?? 2400, 2) }}</h5>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="p-3 rounded border text-center">
                                    <span class="small text-muted font-weight-600">Diesel Expense</span>
                                    <h5 class="font-weight-bold text-dark m-0 mt-1">₹ {{ number_format($manifest['active_trip']['diesel_expense'] ?? 500, 2) }}</h5>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4 d-flex gap-2">
                            <button type="button" class="btn btn-outline-info flex-grow-1" data-toggle="modal" data-target="#whatsappLocationModal">
                                <i class="fa fa-whatsapp mr-1 text-success"></i> Update Customer GPS via WhatsApp Pin
                            </button>
                            <a href="{{ route('water-logistics.index') }}" class="btn btn-primary flex-grow-1">
                                <i class="fa fa-check-circle mr-1"></i> Complete Trip &amp; Post Invoice
                            </a>
                        </div>
                    @else
                        <div class="text-center py-5">
                            <i class="fa fa-check-circle-o text-success u-fs-48px"></i>
                            <h5 class="font-weight-bold mt-3">All Assigned Trips Completed</h5>
                            <p class="text-muted small">No pending bulk water deliveries currently assigned to this tanker.</p>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Flow Telemetry & Safety Panel -->
            <div class="col-md-4">
                <div class="card border-0 shadow-sm rounded-lg p-4 bg-white h-100">
                    <h5 class="font-weight-bold text-dark mb-3">
                        <i class="fa fa-shield text-primary mr-2"></i> Onboard Sensor Telemetry
                    </h5>
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <span class="text-muted"><i class="fa fa-tachometer mr-2"></i> Vehicle Speed</span>
                            <span class="font-weight-bold text-dark">0 km/h (Stationary)</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <span class="text-muted"><i class="fa fa-battery-three-quarters mr-2 text-info"></i> ECU Battery</span>
                            <span class="font-weight-bold text-dark">24.2 V (Healthy)</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <span class="text-muted"><i class="fa fa-filter mr-2 text-warning"></i> Fuel Tank Level</span>
                            <span class="font-weight-bold text-dark">75% (150 Litres)</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <span class="text-muted"><i class="fa fa-lock mr-2 text-success"></i> Anti-Theft Valve</span>
                            <span class="badge badge-success px-2 py-1">SECURED</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <span class="text-muted"><i class="fa fa-compass mr-2 text-primary"></i> GPS vs Odometer Diff</span>
                            <span class="font-weight-bold text-success">0.0% (Verified)</span>
                        </li>
                    </ul>

                    <div class="mt-4 p-3 rounded u-bg-success-soft u-b-1px-solid-success-border">
                        <small class="text-success font-weight-600">
                            <i class="fa fa-info-circle mr-1"></i> Anti-siphon lock automatically releases only when geofence reaches registered customer delivery coordinates.
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- WhatsApp Location Modal -->
<div class="modal fade" id="whatsappLocationModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content border-0 shadow-lg rounded-lg">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title font-weight-bold">
                    <i class="fa fa-whatsapp text-success mr-2"></i> Update Customer GPS Location
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form action="{{ url('api/v1/mobile/customers/' . ($manifest['active_trip']['customer_id'] ?? 1) . '/location') }}" method="POST" id="whatsappLocationForm">
                @csrf
                <div class="modal-body">
                    <p class="text-muted small">
                        Paste the location pin or Google Maps share link sent by customer on WhatsApp (e.g. <code>https://maps.google.com/?q=11.2785,77.5842</code>).
                    </p>
                    <div class="form-group">
                        <label class="small font-weight-600 text-muted">Customer Name</label>
                        <input type="text" class="form-control" value="{{ $manifest['active_trip']['customer_name'] ?? 'Customer Site' }}" readonly>
                    </div>
                    <div class="form-group">
                        <label class="small font-weight-600 text-muted">WhatsApp Shared Text / Google Maps URL</label>
                        <textarea name="location_text" id="location_text" class="form-control" rows="3" placeholder="Paste WhatsApp location text or Google Maps link here..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success btn-sm">
                        <i class="fa fa-check mr-1"></i> Save Coordinates to Customer Master
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection
