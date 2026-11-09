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
        <!-- Header & Field Agent Banner -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card border-0 shadow-sm rounded-lg p-4 bg-white">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <div class="d-flex align-items-center gap-3 mb-2">
                                <span class="badge badge-primary p-2 px-3 text-uppercase font-weight-bold u-fs-13px u-ls-0_5px">
                                    <i class="fa fa-id-card-o mr-1"></i> Field Operations &amp; KYC Desk
                                </span>
                                <span class="badge badge-success p-2 px-3 text-uppercase font-weight-bold u-fs-12px">
                                    <i class="fa fa-map-marker mr-1"></i> Active Beat Geocoding
                                </span>
                            </div>
                            <h3 class="font-weight-bold text-dark m-0">Customer KYC, Credit Receivables &amp; Geocoding</h3>
                            <p class="text-muted small m-0 mt-1">
                                Field Officer: <strong>{{ Auth::user()->name }}</strong> &bull; Coverage Zone: <strong>Erode, Perundurai &amp; Kangeyam Commercial Belt</strong>
                            </p>
                        </div>
                        <div class="col-md-4 text-right">
                            <a href="{{ route('customer.create') }}" class="btn btn-outline-primary btn-sm mr-2">
                                <i class="fa fa-user-plus mr-1"></i> Onboard Customer
                            </a>
                            <a href="{{ route('customer.index') }}" class="btn btn-primary btn-sm">
                                <i class="fa fa-users mr-1"></i> Customer Directory
                            </a>
                        </div>
                    </div>

                    <!-- 4 Operational KPI Cards Strip -->
                    <div class="row mt-4 pt-3 border-top">
                        <div class="col-md-3 border-right text-center">
                            <span class="text-muted small font-weight-600 text-uppercase">Assigned Accounts</span>
                            <h3 class="font-weight-bold text-dark m-0 mt-1">{{ $metrics['summary']['assigned_customers'] ?? 18 }} Accounts</h3>
                            <small class="text-info">{{ $metrics['summary']['geocoded_customers'] ?? 14 }} Geocoded &bull; {{ $metrics['summary']['missing_gps_customers'] ?? 4 }} Need GPS</small>
                        </div>
                        <div class="col-md-3 border-right text-center">
                            <span class="text-muted small font-weight-600 text-uppercase">Total Outstanding Receivables</span>
                            <h3 class="font-weight-bold text-warning m-0 mt-1">₹ {{ number_format($metrics['summary']['total_receivables'] ?? 2100, 2) }}</h3>
                            <small class="text-danger">{{ $metrics['summary']['overdue_accounts_count'] ?? 2 }} Overdue &gt; 30 Days</small>
                        </div>
                        <div class="col-md-3 border-right text-center">
                            <span class="text-muted small font-weight-600 text-uppercase">Today's Field Collections</span>
                            <h3 class="font-weight-bold text-success m-0 mt-1">₹ {{ number_format($metrics['summary']['today_collected'] ?? 1450, 2) }}</h3>
                            <small class="text-success"><i class="fa fa-check-circle"></i> Direct receipts posted</small>
                        </div>
                        <div class="col-md-3 text-center">
                            <span class="text-muted small font-weight-600 text-uppercase">Recovery Rate</span>
                            <h3 class="font-weight-bold text-primary m-0 mt-1">{{ $metrics['summary']['recovery_rate_percent'] ?? 85.0 }}%</h3>
                            <small class="text-muted">Month-to-date collection target</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Assigned Customers List with Dues & WhatsApp Location Update -->
        <div class="row">
            <div class="col-md-12">
                <div class="card border-0 shadow-sm rounded-lg p-4 bg-white">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h5 class="font-weight-bold text-dark m-0">
                                <i class="fa fa-list-alt text-primary mr-2"></i> Assigned Customer Dues &amp; Geocoding Directory
                            </h5>
                            <p class="text-muted small m-0">Manage customer GPS coordinates and collect overdue balances</p>
                        </div>
                        <div class="d-flex gap-2">
                            <span class="badge badge-light border text-muted px-2 py-1 font-weight-600">
                                {{ $metrics['summary']['missing_gps_customers'] ?? 4 }} Missing GPS Pins
                            </span>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="bg-light">
                                <tr>
                                    <th class="border-0">#</th>
                                    <th class="border-0">Customer / Enterprise</th>
                                    <th class="border-0">Phone / City</th>
                                    <th class="border-0">Address</th>
                                    <th class="border-0 text-right">Outstanding Due</th>
                                    <th class="border-0 text-center">GPS Status</th>
                                    <th class="border-0 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($metrics['customers'] ?? [] as $idx => $c)
                                    <tr>
                                        <td>{{ $idx + 1 }}</td>
                                        <td>
                                            <div class="font-weight-bold text-dark">{{ $c['name'] }}</div>
                                            @if(!empty($c['company_name']) && $c['company_name'] !== $c['name'])
                                                <small class="text-muted">{{ $c['company_name'] }}</small>
                                            @endif
                                        </td>
                                        <td>
                                            <div><i class="fa fa-phone mr-1 text-muted"></i> <a href="tel:{{ $c['phone'] }}">{{ $c['phone'] }}</a></div>
                                            <small class="text-muted">{{ $c['city'] ?? 'Erode' }}</small>
                                        </td>
                                        <td class="small text-muted u-maxw-250px">{{ $c['address'] }}</td>
                                        <td class="text-right">
                                            @if(($c['due_amount'] ?? 0) > 0)
                                                <span class="badge badge-danger px-2 py-1 u-fs-12px">
                                                    ₹ {{ number_format($c['due_amount'], 2) }}
                                                </span>
                                            @else
                                                <span class="badge badge-success px-2 py-1 u-fs-11px">SETTLED</span>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            @if(!empty($c['latitude']) && !empty($c['longitude']))
                                                <span class="badge badge-success px-2 py-1 small u-fs-10px">
                                                    <i class="fa fa-check-circle mr-1"></i> Geotagged
                                                </span>
                                            @else
                                                <span class="badge badge-warning px-2 py-1 small u-fs-10px">
                                                    <i class="fa fa-exclamation-triangle mr-1"></i> No GPS Pin
                                                </span>
                                            @endif
                                        </td>
                                        <td class="text-right">
                                            @if(!empty($c['latitude']) && !empty($c['longitude']))
                                                <a href="https://www.google.com/maps/dir/?api=1&destination={{ $c['latitude'] }},{{ $c['longitude'] }}" target="_blank" class="btn btn-outline-success btn-sm p-1 px-2" title="Navigate to Customer">
                                                    <i class="fa fa-location-arrow"></i>
                                                </a>
                                            @endif
                                            <button type="button" class="btn btn-outline-info btn-sm p-1 px-2" title="Update WhatsApp GPS Pin" data-toggle="modal" data-target="#agentGpsModal{{ $c['id'] }}">
                                                <i class="fa fa-whatsapp text-success"></i>
                                            </button>
                                        </td>
                                    </tr>

                                    <!-- WhatsApp Modal per Customer -->
                                    <div class="modal fade" id="agentGpsModal{{ $c['id'] }}" tabindex="-1" role="dialog" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered" role="document">
                                            <div class="modal-content border-0 shadow-lg rounded-lg">
                                                <div class="modal-header border-0 pb-0">
                                                    <h5 class="modal-title font-weight-bold">
                                                        <i class="fa fa-whatsapp text-success mr-2"></i> Update GPS for {{ $c['name'] }}
                                                    </h5>
                                                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                                                </div>
                                                <form action="{{ url('api/v1/mobile/customers/' . $c['id'] . '/location') }}" method="POST">
                                                    @csrf
                                                    <div class="modal-body">
                                                        <p class="text-muted small">Paste customer WhatsApp shared location link or coordinates to permanently lock GPS pin in customer master.</p>
                                                        <div class="form-group">
                                                            <textarea name="location_text" class="form-control" rows="3" placeholder="https://maps.google.com/?q=11.2785,77.5842" required></textarea>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer border-0 pt-0">
                                                        <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
                                                        <button type="submit" class="btn btn-success btn-sm">Save Customer Coordinates</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center py-4 text-muted">No customers assigned to this agent yet.</td>
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
