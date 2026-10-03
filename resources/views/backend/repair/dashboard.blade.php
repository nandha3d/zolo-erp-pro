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
                    <i class="dripicons-wrench mr-2 text-primary"></i>Repair &amp; Service Center
                </h3>
                <p class="text-muted mb-0 small">zoloERP Device Repair &amp; RMA Service Command Center</p>
            </div>
            <div>
                <a href="{{ route('repair.services') }}" class="btn btn-primary btn-sm rounded-pill shadow-sm px-3">
                    <i class="dripicons-plus mr-1"></i> New Service Job
                </a>
                <a href="{{ route('repair.device-types') }}" class="btn btn-outline-secondary btn-sm rounded-pill px-3 ml-2">
                    <i class="dripicons-gear mr-1"></i> Device Types
                </a>
            </div>
        </div>

        {{-- Metric Cards --}}
        <div class="row">
            <div class="col-md-3">
                <div class="card card-neo border-0 shadow-sm rounded-lg p-3 mb-4" style="border-left: 4px solid #6366f1 !important;">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span class="text-muted text-uppercase small font-weight-bold">Total Service Jobs</span>
                            <h3 class="font-weight-bold mt-2 mb-0" style="color: #6366f1;">{{ $totalJobs }}</h3>
                        </div>
                        <div class="rounded-circle p-3" style="background: rgba(99, 102, 241, 0.1);">
                            <i class="dripicons-clipboard" style="font-size: 1.5rem; color: #6366f1;"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card card-neo border-0 shadow-sm rounded-lg p-3 mb-4" style="border-left: 4px solid #f59e0b !important;">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span class="text-muted text-uppercase small font-weight-bold">In-Progress / Bench</span>
                            <h3 class="font-weight-bold mt-2 mb-0" style="color: #f59e0b;">{{ $inProgressJobs }}</h3>
                        </div>
                        <div class="rounded-circle p-3" style="background: rgba(245, 158, 11, 0.1);">
                            <i class="dripicons-hourglass" style="font-size: 1.5rem; color: #f59e0b;"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card card-neo border-0 shadow-sm rounded-lg p-3 mb-4" style="border-left: 4px solid #10b981 !important;">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span class="text-muted text-uppercase small font-weight-bold">Completed Jobs</span>
                            <h3 class="font-weight-bold mt-2 mb-0" style="color: #10b981;">{{ $completedJobs }}</h3>
                        </div>
                        <div class="rounded-circle p-3" style="background: rgba(16, 185, 129, 0.1);">
                            <i class="dripicons-checkmark" style="font-size: 1.5rem; color: #10b981;"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card card-neo border-0 shadow-sm rounded-lg p-3 mb-4" style="border-left: 4px solid #3b82f6 !important;">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span class="text-muted text-uppercase small font-weight-bold">Service Revenue</span>
                            <h3 class="font-weight-bold mt-2 mb-0" style="color: #3b82f6;">
                                {{ $currency->code ?? '₹' }} {{ number_format($totalRevenue, 2) }}
                            </h3>
                        </div>
                        <div class="rounded-circle p-3" style="background: rgba(59, 130, 246, 0.1);">
                            <i class="dripicons-wallet" style="font-size: 1.5rem; color: #3b82f6;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Recent Service Jobs Table --}}
        <div class="card card-neo border-0 shadow-sm rounded-lg">
            <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
                <h5 class="font-weight-bold mb-0 text-dark">
                    <i class="dripicons-list mr-2 text-muted"></i>Recent Job Sheets
                </h5>
                <a href="{{ route('repair.services') }}" class="btn btn-link btn-sm text-primary">View All Jobs &rarr;</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th>Job Sheet #</th>
                                <th>Customer</th>
                                <th>Device Details</th>
                                <th>Defect Reported</th>
                                <th>Est. Cost</th>
                                <th>Status</th>
                                <th>Received Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentJobs as $job)
                                <tr>
                                    <td class="font-weight-bold text-primary">{{ $job->job_sheet_no }}</td>
                                    <td>
                                        <span class="font-weight-bold">{{ $job->customer_name }}</span><br>
                                        <small class="text-muted">{{ $job->phone_number }}</small>
                                    </td>
                                    <td>
                                        <span class="badge badge-light border">{{ $job->device_brand }}</span>
                                        <span>{{ $job->device_model }}</span>
                                        @if($job->imei_serial)
                                            <br><small class="text-muted">SN: {{ $job->imei_serial }}</small>
                                        @endif
                                    </td>
                                    <td><small>{{ Str::limit($job->defects_reported, 40) }}</small></td>
                                    <td class="font-weight-bold">
                                        {{ $currency->code ?? '₹' }} {{ number_format($job->total_charge > 0 ? $job->total_charge : $job->estimated_cost, 2) }}
                                    </td>
                                    <td>
                                        @if($job->status == 'Completed' || $job->status == 'Delivered')
                                            <span class="badge badge-success px-2 py-1">{{ $job->status }}</span>
                                        @elseif($job->status == 'Received')
                                            <span class="badge badge-info px-2 py-1">{{ $job->status }}</span>
                                        @else
                                            <span class="badge badge-warning px-2 py-1">{{ $job->status }}</span>
                                        @endif
                                    </td>
                                    <td><small class="text-muted">{{ $job->received_date }}</small></td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-muted">
                                        <i class="dripicons-inbox display-4 text-muted mb-2 d-block"></i>
                                        No repair jobs logged yet. Click "New Service Job" to create one.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</section>

@endsection
