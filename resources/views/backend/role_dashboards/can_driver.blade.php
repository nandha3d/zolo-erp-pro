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
        <!-- Header & Route Beat Banner -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card border-0 shadow-sm rounded-lg p-4 bg-white">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <div class="d-flex align-items-center gap-3 mb-2">
                                <span class="badge badge-success p-2 px-3 text-uppercase font-weight-bold u-fs-13px u-ls-0_5px">
                                    <i class="fa fa-truck mr-1"></i> {{ $manifest['vehicle']['vehicle_number'] ?? 'TN-58-WA-101' }}
                                </span>
                                <span class="badge badge-info p-2 px-3 text-uppercase font-weight-bold u-fs-12px">
                                    <i class="fa fa-road mr-1"></i> {{ $manifest['route']['name'] ?? 'Erode Commercial Route Beat A' }}
                                </span>
                            </div>
                            <h3 class="font-weight-bold text-dark m-0">20L Packaged Water Distribution Manifest</h3>
                            <p class="text-muted small m-0 mt-1">
                                Driver: <strong>{{ $manifest['driver_name'] ?? 'M. Senthil' }}</strong> ({{ $manifest['driver_phone'] ?? '9842100002' }}) &bull; Morning Departure: 06:30 AM
                            </p>
                        </div>
                        <div class="col-md-4 text-right">
                            <a href="{{ route('fleet.tracking') }}" class="btn btn-outline-primary btn-sm mr-2">
                                <i class="fa fa-map-marker mr-1"></i> Live Route GPS
                            </a>
                            <a href="{{ route('water-logistics.index') }}" class="btn btn-success btn-sm">
                                <i class="fa fa-plus mr-1"></i> Issue Delivery Slip
                            </a>
                        </div>
                    </div>

                    <!-- Can Inventory Counters Strip -->
                    <div class="row mt-4 pt-3 border-top">
                        <div class="col-md-2 border-right text-center">
                            <span class="text-muted small font-weight-600 text-uppercase">Loaded Morning</span>
                            <h4 class="font-weight-bold text-dark m-0 mt-1">{{ $manifest['inventory']['loaded_cans'] ?? 150 }}</h4>
                            <small class="text-muted">Full 20L Jars</small>
                        </div>
                        <div class="col-md-2 border-right text-center">
                            <span class="text-muted small font-weight-600 text-uppercase">Delivered</span>
                            <h4 class="font-weight-bold text-success m-0 mt-1">{{ $manifest['inventory']['delivered_cans'] ?? 85 }}</h4>
                            <small class="text-muted">Jars Supplied</small>
                        </div>
                        <div class="col-md-2 border-right text-center">
                            <span class="text-muted small font-weight-600 text-uppercase">Empty Collected</span>
                            <h4 class="font-weight-bold text-primary m-0 mt-1">{{ $manifest['inventory']['empty_cans_collected'] ?? 82 }}</h4>
                            <small class="text-muted">Returned Empty</small>
                        </div>
                        <div class="col-md-2 border-right text-center">
                            <span class="text-muted small font-weight-600 text-uppercase">Return Ratio</span>
                            <h4 class="font-weight-bold text-info m-0 mt-1">{{ $manifest['inventory']['empty_return_ratio'] ?? '96.5%' }}</h4>
                            <small class="text-muted">Audit Threshold: &gt;90%</small>
                        </div>
                        <div class="col-md-2 border-right text-center">
                            <span class="text-muted small font-weight-600 text-uppercase">Remaining in Van</span>
                            <h4 class="font-weight-bold text-warning m-0 mt-1">{{ $manifest['inventory']['remaining_cans'] ?? 65 }}</h4>
                            <small class="text-muted">Available Stock</small>
                        </div>
                        <div class="col-md-2 text-center">
                            <span class="text-muted small font-weight-600 text-uppercase">Route Cash in Van</span>
                            <h4 class="font-weight-bold text-success m-0 mt-1">₹ {{ number_format($manifest['inventory']['cash_collected'] ?? 3400, 2) }}</h4>
                            <small class="text-muted">Ready for surrender</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Assigned Route Delivery Stops -->
        <div class="row">
            <div class="col-md-12">
                <div class="card border-0 shadow-sm rounded-lg p-4 bg-white">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h5 class="font-weight-bold text-dark m-0">
                                <i class="fa fa-list-ol text-success mr-2"></i> Route Beat Customer Stops ({{ count($manifest['deliveries'] ?? []) }})
                            </h5>
                            <p class="text-muted small m-0">Sequential beat delivery schedule with customer geolocations</p>
                        </div>
                        <button type="button" class="btn btn-outline-info btn-sm" onclick="window.print()">
                            <i class="fa fa-print mr-1"></i> Print Delivery Sheet
                        </button>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="bg-light">
                                <tr>
                                    <th class="border-0">#</th>
                                    <th class="border-0">Customer / Enterprise</th>
                                    <th class="border-0">Address / Beat Point</th>
                                    <th class="border-0 text-center">Delivered (20L)</th>
                                    <th class="border-0 text-center">Empty Due</th>
                                    <th class="border-0 text-right">Bill Amount</th>
                                    <th class="border-0 text-center">Status</th>
                                    <th class="border-0 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($manifest['deliveries'] ?? [] as $idx => $stop)
                                    <tr>
                                        <td><strong>{{ $idx + 1 }}</strong></td>
                                        <td>
                                            <div class="font-weight-bold text-dark">{{ $stop['customer_name'] }}</div>
                                            <small class="text-muted"><i class="fa fa-phone mr-1"></i> <a href="tel:{{ $stop['phone'] }}">{{ $stop['phone'] }}</a></small>
                                        </td>
                                        <td>
                                            <div class="small text-muted">{{ $stop['address'] }}</div>
                                            @if(!empty($stop['latitude']) && !empty($stop['longitude']))
                                                <span class="badge badge-success px-2 py-1 small u-fs-10px">
                                                    <i class="fa fa-map-marker mr-1"></i> GPS Locked
                                                </span>
                                            @else
                                                <span class="badge badge-warning px-2 py-1 small u-fs-10px">
                                                    <i class="fa fa-exclamation-triangle mr-1"></i> Need GPS Pin
                                                </span>
                                            @endif
                                        </td>
                                        <td class="text-center font-weight-bold text-success">{{ $stop['cans_delivered'] ?? 10 }} Cans</td>
                                        <td class="text-center font-weight-bold text-muted">{{ $stop['empty_cans_returned'] ?? 10 }} Jars</td>
                                        <td class="text-right font-weight-bold text-dark">₹ {{ number_format($stop['total_amount'] ?? 350, 2) }}</td>
                                        <td class="text-center">
                                            <span class="badge badge-success px-2 py-1 text-uppercase">{{ $stop['status'] ?? 'Delivered' }}</span>
                                        </td>
                                        <td class="text-right">
                                            @if(!empty($stop['google_maps_url']))
                                                <a href="{{ $stop['google_maps_url'] }}" target="_blank" class="btn btn-outline-success btn-sm p-1 px-2" title="Navigate">
                                                    <i class="fa fa-location-arrow"></i>
                                                </a>
                                            @endif
                                            <button type="button" class="btn btn-outline-secondary btn-sm p-1 px-2" title="WhatsApp Location Pin" data-toggle="modal" data-target="#whatsappLocationModalStop{{ $stop['customer_id'] }}">
                                                <i class="fa fa-whatsapp text-success"></i>
                                            </button>
                                        </td>
                                    </tr>

                                    <!-- WhatsApp Modal per stop -->
                                    <div class="modal fade" id="whatsappLocationModalStop{{ $stop['customer_id'] }}" tabindex="-1" role="dialog" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered" role="document">
                                            <div class="modal-content border-0 shadow-lg rounded-lg">
                                                <div class="modal-header border-0 pb-0">
                                                    <h5 class="modal-title font-weight-bold">
                                                        <i class="fa fa-whatsapp text-success mr-2"></i> Update GPS for {{ $stop['customer_name'] }}
                                                    </h5>
                                                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                                                </div>
                                                <form action="{{ url('api/v1/mobile/customers/' . $stop['customer_id'] . '/location') }}" method="POST">
                                                    @csrf
                                                    <div class="modal-body">
                                                        <p class="text-muted small">Paste customer WhatsApp shared Google Maps link to lock delivery location for future orders.</p>
                                                        <div class="form-group">
                                                            <textarea name="location_text" class="form-control" rows="3" placeholder="https://maps.google.com/?q=lat,lng" required></textarea>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer border-0 pt-0">
                                                        <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
                                                        <button type="submit" class="btn btn-success btn-sm">Save GPS Pin</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center py-4 text-muted">No route deliveries scheduled for today.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

@endsection
