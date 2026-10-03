@extends('backend.layout.main')
@section('content')

<section class="forms">
    <div class="container-fluid">
        <div class="row align-items-center mb-4">
            <div class="col-md-8 d-flex align-items-center">
                @if(!empty($general_setting->site_logo))
                    <img src="{{url('logo', $general_setting->site_logo)}}" class="mr-3 rounded" style="max-height: 44px; max-width: 140px; object-fit: contain;" alt="{{$general_setting->site_title ?? 'Logo'}}">
                @endif
                <div>
                    <h3 class="font-weight-bold text-dark m-0">Corporate B2B Credit Aging &amp; Overdue Recovery Board</h3>
                    <p class="text-muted small m-0">Track 15-day and 30-day corporate billing cycles (Textile Mills, Factories, Colleges) to recover stalled credit.</p>
                </div>
            </div>
            <div class="col-md-4 text-right">
                <a href="{{ route('water-logistics.index') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="dripicons-arrow-thin-left"></i> Back to Water Logistics
                </a>
            </div>
        </div>

        <!-- Aging Buckets Overview -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card border-0 shadow-sm rounded-lg p-3 bg-white border-left border-success" style="border-left-width: 4px !important;">
                    <span class="small text-muted font-weight-600">0 – 30 Days (Current Term)</span>
                    <h3 class="font-weight-bold text-success m-0 mt-1">₹ {{ number_format(collect($agingData)->where('bucket', '0-30')->sum('total_due'), 2) }}</h3>
                    <small class="text-muted">Standard ongoing billing cycle</small>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm rounded-lg p-3 bg-white border-left border-warning" style="border-left-width: 4px !important;">
                    <span class="small text-muted font-weight-600">31 – 60 Days (Due For Follow-Up)</span>
                    <h3 class="font-weight-bold text-warning m-0 mt-1">₹ {{ number_format(collect($agingData)->where('bucket', '31-60')->sum('total_due'), 2) }}</h3>
                    <small class="text-muted">Statement reminder dispatched</small>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm rounded-lg p-3 bg-white border-left border-danger" style="border-left-width: 4px !important;">
                    <span class="small text-muted font-weight-600">60+ Days (Critical Stalled Overdue)</span>
                    <h3 class="font-weight-bold text-danger m-0 mt-1">₹ {{ number_format(collect($agingData)->where('bucket', '60+')->sum('total_due'), 2) }}</h3>
                    <small class="text-danger font-weight-bold">Immediate recovery action required</small>
                </div>
            </div>
        </div>

        <!-- Client Aging Table -->
        <div class="card border-0 shadow-sm rounded-lg">
            <div class="card-body p-4">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light text-muted uppercase small">
                            <tr>
                                <th>Corporate Client</th>
                                <th>Contact / Phone</th>
                                <th>Days Outstanding</th>
                                <th>Aging Category</th>
                                <th class="text-right">Total Outstanding Balance</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($agingData as $item)
                                <tr>
                                    <td><strong>{{ $item['customer']->name }}</strong></td>
                                    <td>{{ $item['customer']->phone_number ?: 'N/A' }}</td>
                                    <td><span class="font-weight-bold">{{ $item['days_outstanding'] }} Days</span></td>
                                    <td>
                                        @if($item['bucket'] == '0-30')
                                            <span class="badge badge-success px-2 py-1">0 – 30 Days (Current)</span>
                                        @elseif($item['bucket'] == '31-60')
                                            <span class="badge badge-warning px-2 py-1">31 – 60 Days (Due)</span>
                                        @else
                                            <span class="badge badge-danger px-2 py-1">60+ Days (Critical)</span>
                                        @endif
                                    </td>
                                    <td class="text-right font-weight-bold text-danger">₹ {{ number_format($item['total_due'], 2) }}</td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-outline-primary btn-sm px-3" onclick="alert('Statement reminder sent to {{ $item['customer']->name }}')">
                                            <i class="dripicons-message"></i> Send Statement
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">No overdue corporate client balances detected. All accounts are up-to-date!</td>
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
