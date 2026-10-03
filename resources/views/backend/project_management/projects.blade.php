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
                    <i class="dripicons-briefcase mr-2 text-primary"></i>Projects &amp; Engagements
                </h3>
                <p class="text-muted mb-0 small">Manage client delivery milestones, budgets, and project lifecycles</p>
            </div>
            <div>
                <button type="button" class="btn btn-primary btn-sm rounded-pill shadow-sm px-3" data-toggle="modal" data-target="#createProjectModal">
                    <i class="dripicons-plus mr-1"></i> New Project
                </button>
                <a href="{{ route('project-management.tasks') }}" class="btn btn-outline-secondary btn-sm rounded-pill px-3 ml-2">
                    <i class="dripicons-checklist mr-1"></i> Tasks
                </a>
                <a href="{{ route('project-management.categories') }}" class="btn btn-outline-secondary btn-sm rounded-pill px-3 ml-2">
                    <i class="dripicons-tags mr-1"></i> Categories
                </a>
            </div>
        </div>

        {{-- Project Stat Cards --}}
        <div class="row">
            <div class="col-md-3">
                <div class="card card-neo border-0 shadow-sm rounded-lg p-3 mb-4" style="border-left: 4px solid #6366f1 !important;">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span class="text-muted text-uppercase small font-weight-bold">Total Projects</span>
                            <h3 class="font-weight-bold mt-2 mb-0" style="color: #6366f1;">{{ $stats['total'] }}</h3>
                        </div>
                        <div class="rounded-circle p-3" style="background: rgba(99, 102, 241, 0.1);">
                            <i class="dripicons-folder" style="font-size: 1.5rem; color: #6366f1;"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card card-neo border-0 shadow-sm rounded-lg p-3 mb-4" style="border-left: 4px solid #3b82f6 !important;">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span class="text-muted text-uppercase small font-weight-bold">In Progress</span>
                            <h3 class="font-weight-bold mt-2 mb-0" style="color: #3b82f6;">{{ $stats['in_progress'] }}</h3>
                        </div>
                        <div class="rounded-circle p-3" style="background: rgba(59, 130, 246, 0.1);">
                            <i class="dripicons-clockwise" style="font-size: 1.5rem; color: #3b82f6;"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card card-neo border-0 shadow-sm rounded-lg p-3 mb-4" style="border-left: 4px solid #10b981 !important;">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span class="text-muted text-uppercase small font-weight-bold">Completed</span>
                            <h3 class="font-weight-bold mt-2 mb-0" style="color: #10b981;">{{ $stats['completed'] }}</h3>
                        </div>
                        <div class="rounded-circle p-3" style="background: rgba(16, 185, 129, 0.1);">
                            <i class="dripicons-checkmark" style="font-size: 1.5rem; color: #10b981;"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card card-neo border-0 shadow-sm rounded-lg p-3 mb-4" style="border-left: 4px solid #f59e0b !important;">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span class="text-muted text-uppercase small font-weight-bold">Total Budget</span>
                            <h3 class="font-weight-bold mt-2 mb-0" style="color: #f59e0b;">
                                {{ $currency->code ?? '₹' }} {{ number_format($stats['total_budget'], 2) }}
                            </h3>
                        </div>
                        <div class="rounded-circle p-3" style="background: rgba(245, 158, 11, 0.1);">
                            <i class="dripicons-wallet" style="font-size: 1.5rem; color: #f59e0b;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Projects Table --}}
        <div class="card card-neo border-0 shadow-sm rounded-lg">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th>Project Title</th>
                                <th>Category</th>
                                <th>Client</th>
                                <th>Start Date</th>
                                <th>Deadline</th>
                                <th>Budget</th>
                                <th>Progress</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($projects as $p)
                                <tr>
                                    <td class="font-weight-bold text-dark">
                                        {{ $p->title }}
                                        @if($p->description)
                                            <div class="text-muted" style="font-size: 0.8rem;">{{ Str::limit($p->description, 50) }}</div>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge badge-light border">{{ $p->category_name ?? 'General' }}</span>
                                    </td>
                                    <td>{{ $p->client_name ?? 'Internal' }}</td>
                                    <td>{{ $p->start_date }}</td>
                                    <td>{{ $p->deadline ?? '-' }}</td>
                                    <td class="font-weight-bold">
                                        {{ $currency->code ?? '₹' }} {{ number_format($p->budget, 2) }}
                                    </td>
                                    <td style="min-width: 140px;">
                                        <div class="d-flex align-items-center">
                                            <div class="progress flex-grow-1 mr-2" style="height: 6px;">
                                                <div class="progress-bar bg-primary" role="progressbar" style="width: {{ $p->progress_percent }}%;"></div>
                                            </div>
                                            <small class="font-weight-bold">{{ $p->progress_percent }}%</small>
                                        </div>
                                    </td>
                                    <td>
                                        @if($p->status == 'Completed')
                                            <span class="badge badge-success px-2 py-1">Completed</span>
                                        @elseif($p->status == 'In Progress')
                                            <span class="badge badge-primary px-2 py-1">In Progress</span>
                                        @elseif($p->status == 'On Hold')
                                            <span class="badge badge-warning px-2 py-1">On Hold</span>
                                        @else
                                            <span class="badge badge-secondary px-2 py-1">{{ $p->status }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center py-4 text-muted">
                                        No projects created yet. Click "New Project" to add one.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if(method_exists($projects, 'links'))
                <div class="card-footer bg-white border-0 py-3">
                    {{ $projects->links() }}
                </div>
            @endif
        </div>
    </div>
</section>

{{-- Modal Create Project --}}
<div id="createProjectModal" tabindex="-1" role="dialog" class="modal fade text-left">
    <div role="document" class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-lg">
            {!! Form::open(['route' => 'project-management.projects.store', 'method' => 'post']) !!}
            <div class="modal-header border-0 bg-light">
                <h5 class="modal-title font-weight-bold text-dark">
                    <i class="dripicons-briefcase mr-2 text-primary"></i>Create New Project
                </h5>
                <button type="button" data-dismiss="modal" aria-label="Close" class="close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body p-4">
                <div class="form-group">
                    <label class="font-weight-bold">Project Title *</label>
                    <input type="text" name="title" class="form-control" placeholder="e.g. ERP Implementation, Tanker IoT Telematics Rollout" required>
                </div>

                <div class="row">
                    <div class="col-md-6 form-group">
                        <label class="font-weight-bold">Category *</label>
                        <select name="category_id" class="form-control" required>
                            <option value="">Select Category...</option>
                            @foreach($categories as $cat)
                                <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6 form-group">
                        <label class="font-weight-bold">Client / Customer</label>
                        <select name="client_id" class="form-control selectpicker" data-live-search="true">
                            <option value="">Internal / No Client</option>
                            @foreach($clients as $c)
                                <option value="{{ $c->id }}">{{ $c->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4 form-group">
                        <label class="font-weight-bold">Start Date *</label>
                        <input type="date" name="start_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                    </div>
                    <div class="col-md-4 form-group">
                        <label class="font-weight-bold">Target Deadline</label>
                        <input type="date" name="deadline" class="form-control">
                    </div>
                    <div class="col-md-4 form-group">
                        <label class="font-weight-bold">Budget ({{ $currency->code ?? '₹' }})</label>
                        <input type="number" step="0.01" name="budget" class="form-control" placeholder="0.00">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 form-group">
                        <label class="font-weight-bold">Initial Status</label>
                        <select name="status" class="form-control">
                            <option value="Not Started">Not Started</option>
                            <option value="In Progress">In Progress</option>
                            <option value="On Hold">On Hold</option>
                            <option value="Completed">Completed</option>
                        </select>
                    </div>
                    <div class="col-md-6 form-group">
                        <label class="font-weight-bold">Initial Progress %</label>
                        <input type="number" min="0" max="100" name="progress_percent" class="form-control" value="0">
                    </div>
                </div>

                <div class="form-group mb-0">
                    <label class="font-weight-bold">Project Scope / Description</label>
                    <textarea name="description" class="form-control" rows="3" placeholder="Brief scope of deliverables and notes..."></textarea>
                </div>
            </div>
            <div class="modal-footer border-0 bg-light">
                <button type="button" class="btn btn-secondary rounded-pill px-3" data-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary rounded-pill px-4">
                    <i class="dripicons-checkmark mr-1"></i> Save Project
                </button>
            </div>
            {{ Form::close() }}
        </div>
    </div>
</div>

@endsection
