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
                    <i class="dripicons-tags mr-2 text-primary"></i>Project Categories
                </h3>
                <p class="text-muted mb-0 small">Classify internal projects, client contracts &amp; engineering jobs</p>
            </div>
            <div>
                <button type="button" class="btn btn-primary btn-sm rounded-pill shadow-sm px-3" data-toggle="modal" data-target="#createCategoryModal">
                    <i class="dripicons-plus mr-1"></i> Add Category
                </button>
                <a href="{{ route('project-management.projects') }}" class="btn btn-outline-secondary btn-sm rounded-pill px-3 ml-2">
                    <i class="dripicons-briefcase mr-1"></i> Projects List
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
                                <th>Category Name</th>
                                <th>Description</th>
                                <th>Active Projects</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($categories as $key => $cat)
                                <tr>
                                    <td>{{ $key + 1 }}</td>
                                    <td class="font-weight-bold text-dark">
                                        <i class="dripicons-folder text-primary mr-2"></i>
                                        {{ $cat->name }}
                                    </td>
                                    <td>{{ $cat->description ?? '-' }}</td>
                                    <td>
                                        <span class="badge badge-info px-2 py-1">{{ $cat->projects_count }} Projects</span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center py-4 text-muted">
                                        No project categories created yet. Click "Add Category".
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

{{-- Modal Create Category --}}
<div id="createCategoryModal" tabindex="-1" role="dialog" class="modal fade text-left">
    <div role="document" class="modal-dialog">
        <div class="modal-content border-0 shadow-lg rounded-lg">
            {!! Form::open(['route' => 'project-management.categories.store', 'method' => 'post']) !!}
            <div class="modal-header border-0 bg-light">
                <h5 class="modal-title font-weight-bold text-dark">
                    <i class="dripicons-plus mr-2 text-primary"></i>Add Project Category
                </h5>
                <button type="button" data-dismiss="modal" aria-label="Close" class="close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body p-4">
                <div class="form-group">
                    <label class="font-weight-bold">Category Name *</label>
                    <input type="text" name="name" class="form-control" placeholder="e.g. Software Development, Civil Engineering, IoT Deployments" required>
                </div>
                <div class="form-group mb-0">
                    <label class="font-weight-bold">Description</label>
                    <textarea name="description" class="form-control" rows="2" placeholder="Brief scope and details"></textarea>
                </div>
            </div>
            <div class="modal-footer border-0 bg-light">
                <button type="button" class="btn btn-secondary rounded-pill px-3" data-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary rounded-pill px-4">Save Category</button>
            </div>
            {{ Form::close() }}
        </div>
    </div>
</div>

@endsection
