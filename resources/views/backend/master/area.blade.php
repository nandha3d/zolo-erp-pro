@extends('backend.layout.main')

@section('content')

@push('css')
<link rel="stylesheet" href="{{ versioned_asset('css/product-workspace.css') }}" type="text/css">
<link rel="stylesheet" href="{{ versioned_asset('css/pages/parties-workspace.css') }}" type="text/css">
@endpush

<x-success-message key="message" />
<x-error-message key="not_permitted" />

<x-party-command-bar active="areas" title="Area / Route Master" badge="Geographical Zones, Markets &amp; Delivery Routes">
    <button class="btn btn-primary" data-toggle="modal" data-target="#createModal">
        <i class="dripicons-plus"></i> Add Area
    </button>
</x-party-command-bar>

<div class="area-workspace-view">
    @php
        $totalAreas = $areas->count();
        $activeAreas = $areas->where('is_active', true)->count();
        $citiesCount = $areas->pluck('city')->filter()->unique()->count();
    @endphp

    <!-- KPI Summary Strip -->
    <div class="kpi-summary-strip">
        <div class="kpi-card">
            <div>
                <div class="kpi-label">Total Areas &amp; Routes</div>
                <div class="kpi-number">{{ $totalAreas }}</div>
            </div>
            <div class="u-bg-primary-soft u-c-primary u-w-38px u-h-38px u-r-6px u-d-flex u-items-center u-justify-center">
                <i class="dripicons-location u-fs-18px"></i>
            </div>
        </div>
        <div class="kpi-card">
            <div>
                <div class="kpi-label">Active Territories</div>
                <div class="kpi-number u-c-success">{{ $activeAreas }}</div>
            </div>
            <div class="u-bg-success-soft u-c-success u-w-38px u-h-38px u-r-6px u-d-flex u-items-center u-justify-center">
                <i class="dripicons-checkmark u-fs-18px"></i>
            </div>
        </div>
        <div class="kpi-card">
            <div>
                <div class="kpi-label">Cities / Hubs Covered</div>
                <div class="kpi-number u-c-info">{{ $citiesCount }}</div>
            </div>
            <div class="u-bg-info-soft u-c-info u-w-38px u-h-38px u-r-6px u-d-flex u-items-center u-justify-center">
                <i class="dripicons-map u-fs-18px"></i>
            </div>
        </div>
    </div>

    <!-- Desk Table Card -->
    <div class="desk-card-wrapper">
        <div class="table-responsive">
            <table class="table table-hover mb-0" id="area-table">
                <thead>
                    <tr>
                        <th>Area Name</th>
                        <th>Code</th>
                        <th>City</th>
                        <th>State</th>
                        <th>Pincode</th>
                        <th>Status</th>
                        <th class="not-exported text-right">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($areas as $area)
                    <tr>
                        <td class="font-weight-bold">{{ $area->name }}</td>
                        <td><code>{{ $area->code ?? '—' }}</code></td>
                        <td>{{ $area->city ?? '—' }}</td>
                        <td>{{ $area->state ?? '—' }}</td>
                        <td>{{ $area->pincode ?? '—' }}</td>
                        <td>
                            <span class="badge badge-{{ $area->is_active ? 'success' : 'secondary' }}">
                                {{ $area->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td class="text-right">
                            <button type="button" class="btn btn-sm btn-outline-primary js-master-edit" title="Edit" data-update-url="{{ route('area.update', $area->id) }}" data-record='@json($area)'>
                                <i class="dripicons-pencil"></i>
                            </button>
                            <form action="{{ route('area.destroy', $area->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this area?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                    <i class="dripicons-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted">No areas configured. Click "Add Area" to create one.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Create / Edit Modal -->
<div id="createModal" tabindex="-1" role="dialog" class="modal fade text-left">
    <div role="document" class="modal-dialog modal-md">
        <div class="modal-content border-0 shadow">
            <form action="{{ route('area.store') }}" method="POST">
                @csrf
                <div class="modal-header party-modal-header d-flex justify-content-between align-items-center">
                    <h5 class="modal-title font-weight-bold"><i class="dripicons-plus mr-1 text-primary"></i> Create Area</h5>
                    <button type="button" data-dismiss="modal" class="close">&times;</button>
                </div>
                <div class="modal-body p-4">
                    <div class="party-form-group mb-3">
                        <label class="font-weight-bold">Area Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required placeholder="e.g. Coimbatore Central, Tirupur North">
                    </div>
                    <div class="row">
                        <div class="col-md-6 party-form-group mb-3">
                            <label class="font-weight-bold">Area Code</label>
                            <input type="text" name="code" class="form-control" placeholder="e.g. CBE-01">
                        </div>
                        <div class="col-md-6 party-form-group mb-3">
                            <label class="font-weight-bold">Pincode</label>
                            <input type="text" name="pincode" class="form-control" placeholder="e.g. 641001">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 party-form-group mb-3">
                            <label class="font-weight-bold">City</label>
                            <input type="text" name="city" class="form-control" placeholder="e.g. Coimbatore">
                        </div>
                        <div class="col-md-6 party-form-group mb-3">
                            <label class="font-weight-bold">State</label>
                            <input type="text" name="state" class="form-control" placeholder="e.g. Tamil Nadu">
                        </div>
                    </div>
                    <div class="party-form-group mb-3">
                        <label class="font-weight-bold">Description / Route Notes</label>
                        <textarea name="description" rows="2" class="form-control" placeholder="Optional notes for salesmen delivery routes"></textarea>
                    </div>
                    @include('backend.master.partials.active-toggle')
                </div>
                <div class="modal-footer bg-light border-top d-flex justify-content-end gap-2">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary font-weight-bold">Create Area</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script type="text/javascript">
    $("ul#parties").siblings('a').attr('aria-expanded','true');
    $("ul#parties").addClass("show");
    $("ul#parties #area-list-menu").addClass("active");
</script>
@endpush

@include('backend.master.partials.edit-script', ['editTitle' => 'Edit Area'])
