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
                    <i class="dripicons-gear mr-2 text-primary"></i>Device Categories &amp; Types
                </h3>
                <p class="text-muted mb-0 small">Configured hardware device classifications for repair service</p>
            </div>
            <div>
                <button type="button" class="btn btn-primary btn-sm rounded-pill shadow-sm px-3" data-toggle="modal" data-target="#createDeviceTypeModal">
                    <i class="dripicons-plus mr-1"></i> Add Device Type
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
                                <th>#</th>
                                <th>Device Type Name</th>
                                <th>Description</th>
                                <th>Icon</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($types as $key => $type)
                                <tr>
                                    <td>{{ $key + 1 }}</td>
                                    <td class="font-weight-bold text-dark">
                                        <i class="{{ $type->icon ?? 'dripicons-device-mobile' }} text-primary mr-2"></i>
                                        {{ $type->name }}
                                    </td>
                                    <td>{{ $type->description ?? '-' }}</td>
                                    <td><code>{{ $type->icon ?? 'dripicons-device-mobile' }}</code></td>
                                    <td>
                                        <span class="badge badge-success px-2 py-1">Active</span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-muted">
                                        No device types configured. Click "Add Device Type".
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

{{-- Modal Create Device Type --}}
<div id="createDeviceTypeModal" tabindex="-1" role="dialog" class="modal fade text-left">
    <div role="document" class="modal-dialog">
        <div class="modal-content border-0 shadow-lg rounded-lg">
            {!! Form::open(['route' => 'repair.device-types.store', 'method' => 'post']) !!}
            <div class="modal-header border-0 bg-light">
                <h5 class="modal-title font-weight-bold text-dark">
                    <i class="dripicons-plus mr-2 text-primary"></i>Add Device Type
                </h5>
                <button type="button" data-dismiss="modal" aria-label="Close" class="close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body p-4">
                <div class="form-group">
                    <label class="font-weight-bold">Device Type Name *</label>
                    <input type="text" name="name" class="form-control" placeholder="e.g. Smartphone, Laptop, Tablet, Smart TV" required>
                </div>
                <div class="form-group">
                    <label class="font-weight-bold">Description</label>
                    <textarea name="description" class="form-control" rows="2" placeholder="Short description of this category"></textarea>
                </div>
                <div class="form-group mb-0">
                    <label class="font-weight-bold">Icon Class</label>
                    <input type="text" name="icon" class="form-control" value="dripicons-device-mobile" placeholder="dripicons-device-mobile">
                </div>
            </div>
            <div class="modal-footer border-0 bg-light">
                <button type="button" class="btn btn-secondary rounded-pill px-3" data-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary rounded-pill px-4">Save Device Type</button>
            </div>
            {{ Form::close() }}
        </div>
    </div>
</div>

@endsection
