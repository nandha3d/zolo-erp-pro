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
                <h3 class="font-weight-bold text-dark m-0">Bookings &amp; Service Reservations</h3>
                <p class="text-muted small m-0">Schedule appointments, assign tables or service bays, and manage customer bookings.</p>
            </div>
            <div class="col-md-5 text-right">
                <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#createBookingModal">
                    <i class="dripicons-plus"></i> + Add Booking
                </button>
            </div>
        </div>

        <div class="card border-0 shadow-sm rounded-lg">
            <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
                <ul class="nav nav-pills" role="tablist">
                    <li class="nav-item mr-2">
                        <a class="nav-link active px-3 py-1 font-weight-bold" data-toggle="pill" href="#tab-list">List View</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link px-3 py-1 font-weight-bold" data-toggle="pill" href="#tab-cal">Calendar View</a>
                    </li>
                </ul>
            </div>
            <div class="card-body p-4">
                <div class="tab-content">
                    <div class="tab-pane fade show active" id="tab-list">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="bg-light small uppercase">
                                    <tr>
                                        <th>Booking No</th>
                                        <th>Date &amp; Time</th>
                                        <th>Customer</th>
                                        <th>Service / Purpose</th>
                                        <th>Table / Bay</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($bookings as $b)
                                        <tr>
                                            <td><span class="badge badge-secondary px-2 py-1 font-mono">{{ $b->booking_no }}</span></td>
                                            <td><strong>{{ $b->booking_date }}</strong> <span class="badge badge-light border">{{ $b->time_slot }}</span></td>
                                            <td>{{ $b->customer_name }} <div class="small text-muted">{{ $b->phone_number }}</div></td>
                                            <td>{{ $b->service_type }}</td>
                                            <td>{{ $b->table_id ? 'Table #'.$b->table_id : 'General Bay' }}</td>
                                            <td><span class="badge badge-success px-2 py-1">{{ $b->status }}</span></td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="text-center text-muted py-4">No reservations booked. Click "+ Add Booking" to schedule a customer slot.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="tab-pane fade text-center py-5" id="tab-cal">
                        <i class="dripicons-calendar text-primary" style="font-size: 40px; opacity: 0.5;"></i>
                        <h6 class="font-weight-bold text-dark mt-3">Interactive Schedule Matrix</h6>
                        <p class="text-muted small">All appointments are synchronized with real-time staff rosters and operational hours.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Create Booking Modal (Matches Image 35) -->
<div id="createBookingModal" tabindex="-1" role="dialog" aria-hidden="true" class="modal fade text-left">
    <div role="document" class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <form action="{{ route('bookings.store') }}" method="POST">
                @csrf
                <div class="modal-header bg-dark text-white border-0 py-3">
                    <h5 class="modal-title font-weight-bold">Add Booking Reservation</h5>
                    <button type="button" data-dismiss="modal" class="close text-white opacity-75"><span>&times;</span></button>
                </div>
                <div class="modal-body p-4">
                    <div class="form-group mb-3">
                        <label class="font-weight-600 small">Warehouse / Branch *</label>
                        <select name="warehouse_id" class="form-control" required>
                            @foreach($warehouses as $wh)
                                <option value="{{ $wh->id }}">{{ $wh->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group mb-3">
                        <label class="font-weight-600 small">Customer *</label>
                        <select name="customer_id" class="form-control selectpicker" data-live-search="true" required>
                            @foreach($customers as $c)
                                <option value="{{ $c->id }}">{{ $c->name }} ({{ $c->phone_number }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="row">
                        <div class="col-md-6 form-group mb-3">
                            <label class="font-weight-600 small">Booking Date *</label>
                            <input type="date" name="booking_date" class="form-control" value="{{ now()->toDateString() }}" required>
                        </div>
                        <div class="col-md-6 form-group mb-3">
                            <label class="font-weight-600 small">Time Slot *</label>
                            <select name="time_slot" class="form-control" required>
                                <option value="09:00 AM">09:00 AM</option>
                                <option value="10:30 AM" selected>10:30 AM</option>
                                <option value="12:00 PM">12:00 PM</option>
                                <option value="02:30 PM">02:30 PM</option>
                                <option value="04:00 PM">04:00 PM</option>
                                <option value="06:30 PM">06:30 PM</option>
                                <option value="08:00 PM">08:00 PM</option>
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 form-group mb-3">
                            <label class="font-weight-600 small">Service / Purpose</label>
                            <input type="text" name="service_type" class="form-control" placeholder="e.g. Table Dinner, Device Repair Diagnosis" value="General">
                        </div>
                        <div class="col-md-6 form-group mb-3">
                            <label class="font-weight-600 small">Table (Optional)</label>
                            <select name="table_id" class="form-control">
                                <option value="">None</option>
                                @foreach($tables as $tbl)
                                    <option value="{{ $tbl->id }}">{{ $tbl->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="form-group mb-0">
                        <label class="font-weight-600 small">Notes / Customer Requests</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="Special requirements, guests count"></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 p-3 bg-light">
                    <button type="button" class="btn btn-secondary px-4" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4 font-weight-bold">Confirm Booking</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection
