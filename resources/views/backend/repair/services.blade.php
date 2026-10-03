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
        <div class="d-flex align-items-center justify-content-between mb-4">
            <div>
                <h3 class="font-weight-bold mb-1" style="color: var(--neo-text-primary, #1e293b);">
                    <i class="dripicons-clipboard mr-2 text-primary"></i>Service Job Sheets
                </h3>
                <p class="text-muted mb-0 small">Manage electronics, mobile, computer &amp; appliance repair jobs</p>
            </div>
            <div>
                <button type="button" class="btn btn-primary btn-sm rounded-pill shadow-sm px-3" data-toggle="modal" data-target="#createJobModal">
                    <i class="dripicons-plus mr-1"></i> Create Job Sheet
                </button>
                <a href="{{ route('repair.dashboard') }}" class="btn btn-outline-secondary btn-sm rounded-pill px-3 ml-2">
                    <i class="dripicons-meter mr-1"></i> Repair Dashboard
                </a>
            </div>
        </div>

        <div class="card card-neo border-0 shadow-sm rounded-lg">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th>Job Sheet #</th>
                                <th>Customer</th>
                                <th>Category / Type</th>
                                <th>Brand &amp; Model</th>
                                <th>Defects</th>
                                <th>Spares / Labor</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th>Dates</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($services as $svc)
                                <tr>
                                    <td class="font-weight-bold text-primary">{{ $svc->job_sheet_no }}</td>
                                    <td>
                                        <div class="font-weight-bold">{{ $svc->customer_name }}</div>
                                        <small class="text-muted"><i class="dripicons-phone mr-1"></i>{{ $svc->phone_number }}</small>
                                    </td>
                                    <td>
                                        <span class="badge badge-light border">{{ $svc->device_type_name ?? 'Device' }}</span>
                                    </td>
                                    <td>
                                        <span class="font-weight-bold">{{ $svc->device_brand }}</span> {{ $svc->device_model }}
                                        @if($svc->imei_serial)
                                            <div class="text-muted" style="font-size: 0.8rem;">SN: {{ $svc->imei_serial }}</div>
                                        @endif
                                    </td>
                                    <td><small>{{ Str::limit($svc->defects_reported, 45) }}</small></td>
                                    <td>
                                        <small class="d-block text-muted">Parts: {{ $currency->code ?? '₹' }} {{ number_format($svc->spare_parts_cost, 2) }}</small>
                                        <small class="d-block text-muted">Labor: {{ $currency->code ?? '₹' }} {{ number_format($svc->labor_charge, 2) }}</small>
                                    </td>
                                    <td class="font-weight-bold text-dark">
                                        {{ $currency->code ?? '₹' }} {{ number_format($svc->total_charge > 0 ? $svc->total_charge : $svc->estimated_cost, 2) }}
                                    </td>
                                    <td>
                                        @if($svc->status == 'Completed' || $svc->status == 'Delivered')
                                            <span class="badge badge-success px-2 py-1">{{ $svc->status }}</span>
                                        @elseif($svc->status == 'Received')
                                            <span class="badge badge-info px-2 py-1">{{ $svc->status }}</span>
                                        @else
                                            <span class="badge badge-warning px-2 py-1">{{ $svc->status }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        <small class="d-block text-muted">Rec: {{ $svc->received_date }}</small>
                                        @if($svc->expected_completion_date)
                                            <small class="d-block text-info">Due: {{ $svc->expected_completion_date }}</small>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="text-center py-4 text-muted">
                                        No repair jobs logged yet. Click "Create Job Sheet" above.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if(method_exists($services, 'links'))
                <div class="card-footer bg-white border-0 py-3">
                    {{ $services->links() }}
                </div>
            @endif
        </div>
    </div>
</section>

{{-- Modal Create Service Job --}}
<div id="createJobModal" tabindex="-1" role="dialog" class="modal fade text-left">
    <div role="document" class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-lg">
            {!! Form::open(['route' => 'repair.services.store', 'method' => 'post']) !!}
            <div class="modal-header border-0 bg-light">
                <h5 class="modal-title font-weight-bold text-dark">
                    <i class="dripicons-clipboard mr-2 text-primary"></i>New Repair Job Sheet
                </h5>
                <button type="button" data-dismiss="modal" aria-label="Close" class="close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body p-4">
                <div class="row">
                    <div class="col-md-6 form-group">
                        <label class="font-weight-bold">Customer *</label>
                        <select name="customer_id" class="form-control selectpicker" data-live-search="true" required>
                            <option value="">Select Customer...</option>
                            @foreach($customers as $c)
                                <option value="{{ $c->id }}">{{ $c->name }} ({{ $c->phone_number }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6 form-group">
                        <label class="font-weight-bold">Device Category *</label>
                        <select name="device_type_id" class="form-control" required>
                            <option value="">Select Device Type...</option>
                            @foreach($deviceTypes as $dt)
                                <option value="{{ $dt->id }}">{{ $dt->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4 form-group">
                        <label class="font-weight-bold">Brand *</label>
                        <input type="text" name="device_brand" class="form-control" placeholder="e.g. Apple, Samsung, Dell" required>
                    </div>
                    <div class="col-md-4 form-group">
                        <label class="font-weight-bold">Model *</label>
                        <input type="text" name="device_model" class="form-control" placeholder="e.g. iPhone 15 Pro, Inspiron 15" required>
                    </div>
                    <div class="col-md-4 form-group">
                        <label class="font-weight-bold">Serial / IMEI</label>
                        <input type="text" name="imei_serial" class="form-control" placeholder="Serial or IMEI number">
                    </div>
                </div>

                <div class="form-group">
                    <label class="font-weight-bold">Defects / Problem Reported *</label>
                    <textarea name="defects_reported" class="form-control" rows="2" placeholder="Describe symptoms, screen crack, water damage, boot failure..." required></textarea>
                </div>

                <div class="row">
                    <div class="col-md-4 form-group">
                        <label class="font-weight-bold">Estimated Cost ({{ $currency->code ?? '₹' }})</label>
                        <input type="number" step="0.01" name="estimated_cost" class="form-control" placeholder="0.00">
                    </div>
                    <div class="col-md-4 form-group">
                        <label class="font-weight-bold">Spare Parts Cost ({{ $currency->code ?? '₹' }})</label>
                        <input type="number" step="0.01" name="spare_parts_cost" class="form-control" placeholder="0.00">
                    </div>
                    <div class="col-md-4 form-group">
                        <label class="font-weight-bold">Labor Charge ({{ $currency->code ?? '₹' }})</label>
                        <input type="number" step="0.01" name="labor_charge" class="form-control" placeholder="0.00">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 form-group">
                        <label class="font-weight-bold">Received Date</label>
                        <input type="date" name="received_date" class="form-control" value="{{ date('Y-m-d') }}">
                    </div>
                    <div class="col-md-6 form-group">
                        <label class="font-weight-bold">Expected Ready Date</label>
                        <input type="date" name="expected_completion_date" class="form-control">
                    </div>
                </div>

                <div class="form-group mb-0">
                    <label class="font-weight-bold">Technician Diagnosis / Notes</label>
                    <textarea name="technician_notes" class="form-control" rows="2" placeholder="Internal bench observations, checklist items..."></textarea>
                </div>
            </div>
            <div class="modal-footer border-0 bg-light">
                <button type="button" class="btn btn-secondary rounded-pill px-3" data-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary rounded-pill px-4">
                    <i class="dripicons-checkmark mr-1"></i> Save Job Sheet
                </button>
            </div>
            {{ Form::close() }}
        </div>
    </div>
</div>

@endsection
