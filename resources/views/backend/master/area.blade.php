@extends('backend.layout.main')

@section('content')
<x-success-message key="message" />
<x-error-message key="not_permitted" />

<section class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h3 class="font-weight-bold text-dark mb-0"><i class="dripicons-location text-info mr-2"></i> Area Master</h3>
            <small class="text-muted">Database-backed geographical zones and markets for customers, suppliers and salesmen routes</small>
        </div>
        <button class="btn btn-info font-weight-bold" data-toggle="modal" data-target="#createModal">
            <i class="dripicons-plus mr-1"></i> Add Area
        </button>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="area-table">
                    <thead class="bg-light text-muted text-uppercase" style="font-size: 11px;">
                        <tr>
                            <th>Area Name</th>
                            <th>Code</th>
                            <th>City</th>
                            <th>State</th>
                            <th>Pincode</th>
                            <th>Status</th>
                            <th class="text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($areas as $area)
                        <tr>
                            <td class="font-weight-bold text-dark">{{ $area->name }}</td>
                            <td><code>{{ $area->code ?? '—' }}</code></td>
                            <td>{{ $area->city ?? '—' }}</td>
                            <td>{{ $area->state ?? '—' }}</td>
                            <td>{{ $area->pincode ?? '—' }}</td>
                            <td>
                                <span class="badge badge-{{ $area->is_active ? 'success' : 'danger' }}">
                                    {{ $area->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="text-right">
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
</section>

<!-- Create Modal -->
<div id="createModal" tabindex="-1" role="dialog" class="modal fade text-left">
    <div role="document" class="modal-dialog modal-md">
        <div class="modal-content border-0 shadow">
            <form action="{{ route('area.store') }}" method="POST">
                @csrf
                <div class="modal-header bg-light">
                    <h5 class="modal-title font-weight-bold text-dark"><i class="dripicons-plus mr-1 text-info"></i> Create Area</h5>
                    <button type="button" data-dismiss="modal" class="close">&times;</button>
                </div>
                <div class="modal-body p-4">
                    <div class="form-group">
                        <label class="font-weight-bold">Area Name *</label>
                        <input type="text" name="name" class="form-control" required placeholder="e.g. Coimbatore Central, Tirupur North">
                    </div>
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold">Area Code</label>
                            <input type="text" name="code" class="form-control" placeholder="e.g. CBE-01">
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold">Pincode</label>
                            <input type="text" name="pincode" class="form-control" placeholder="e.g. 641001">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold">City</label>
                            <input type="text" name="city" class="form-control" placeholder="e.g. Coimbatore">
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold">State</label>
                            <input type="text" name="state" class="form-control" placeholder="e.g. Tamil Nadu">
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-info font-weight-bold">Create Area</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
