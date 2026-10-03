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
                    <i class="dripicons-checklist mr-2 text-primary"></i>Project Tasks &amp; Milestones
                </h3>
                <p class="text-muted mb-0 small">Track action items, priority, assignments, and due dates</p>
            </div>
            <div>
                <button type="button" class="btn btn-primary btn-sm rounded-pill shadow-sm px-3" data-toggle="modal" data-target="#createTaskModal">
                    <i class="dripicons-plus mr-1"></i> New Task
                </button>
                <a href="{{ route('project-management.projects') }}" class="btn btn-outline-secondary btn-sm rounded-pill px-3 ml-2">
                    <i class="dripicons-briefcase mr-1"></i> Projects
                </a>
            </div>
        </div>

        <div class="card card-neo border-0 shadow-sm rounded-lg">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th>Task Title</th>
                                <th>Project</th>
                                <th>Assigned To</th>
                                <th>Due Date</th>
                                <th>Priority</th>
                                <th>Status</th>
                                <th>Quick Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($tasks as $t)
                                <tr>
                                    <td class="font-weight-bold text-dark">{{ $t->title }}</td>
                                    <td>
                                        <span class="badge badge-light border">{{ $t->project_title }}</span>
                                    </td>
                                    <td>
                                        @if($t->assignee_name)
                                            <i class="dripicons-user mr-1 text-muted"></i>{{ $t->assignee_name }}
                                        @else
                                            <span class="text-muted">Unassigned</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($t->due_date)
                                            <small class="text-muted"><i class="dripicons-calendar mr-1"></i>{{ $t->due_date }}</small>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($t->priority == 'Urgent' || $t->priority == 'High')
                                            <span class="badge badge-danger px-2 py-1">{{ $t->priority }}</span>
                                        @elseif($t->priority == 'Medium')
                                            <span class="badge badge-warning px-2 py-1">{{ $t->priority }}</span>
                                        @else
                                            <span class="badge badge-info px-2 py-1">{{ $t->priority }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($t->status == 'Done')
                                            <span class="badge badge-success px-2 py-1">Done</span>
                                        @elseif($t->status == 'In Progress')
                                            <span class="badge badge-primary px-2 py-1">In Progress</span>
                                        @elseif($t->status == 'Review')
                                            <span class="badge badge-info px-2 py-1">Review</span>
                                        @else
                                            <span class="badge badge-secondary px-2 py-1">Todo</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            @if($t->status != 'Done')
                                                <button class="btn btn-outline-success btn-sm btn-status" data-id="{{ $t->id }}" data-status="Done" title="Mark Done">
                                                    <i class="dripicons-checkmark"></i>
                                                </button>
                                            @endif
                                            @if($t->status != 'In Progress')
                                                <button class="btn btn-outline-primary btn-sm btn-status" data-id="{{ $t->id }}" data-status="In Progress" title="In Progress">
                                                    <i class="dripicons-clockwise"></i>
                                                </button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-muted">
                                        No tasks created yet. Click "New Task" to create one.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if(method_exists($tasks, 'links'))
                <div class="card-footer bg-white border-0 py-3">
                    {{ $tasks->links() }}
                </div>
            @endif
        </div>
    </div>
</section>

{{-- Modal Create Task --}}
<div id="createTaskModal" tabindex="-1" role="dialog" class="modal fade text-left">
    <div role="document" class="modal-dialog">
        <div class="modal-content border-0 shadow-lg rounded-lg">
            {!! Form::open(['route' => 'project-management.tasks.store', 'method' => 'post']) !!}
            <div class="modal-header border-0 bg-light">
                <h5 class="modal-title font-weight-bold text-dark">
                    <i class="dripicons-checklist mr-2 text-primary"></i>Create Task
                </h5>
                <button type="button" data-dismiss="modal" aria-label="Close" class="close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body p-4">
                <div class="form-group">
                    <label class="font-weight-bold">Task Title *</label>
                    <input type="text" name="title" class="form-control" placeholder="e.g. Tanker calibration test, Cafe POS receipt printing setup" required>
                </div>

                <div class="form-group">
                    <label class="font-weight-bold">Project *</label>
                    <select name="project_id" class="form-control selectpicker" data-live-search="true" required>
                        <option value="">Select Project...</option>
                        @foreach($projects as $proj)
                            <option value="{{ $proj->id }}">{{ $proj->title }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="row">
                    <div class="col-md-6 form-group">
                        <label class="font-weight-bold">Assigned User</label>
                        <select name="assigned_to" class="form-control selectpicker" data-live-search="true">
                            <option value="">Unassigned</option>
                            @foreach($users as $u)
                                <option value="{{ $u->id }}">{{ $u->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6 form-group">
                        <label class="font-weight-bold">Due Date</label>
                        <input type="date" name="due_date" class="form-control">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 form-group">
                        <label class="font-weight-bold">Priority</label>
                        <select name="priority" class="form-control">
                            <option value="Low">Low</option>
                            <option value="Medium" selected>Medium</option>
                            <option value="High">High</option>
                            <option value="Urgent">Urgent</option>
                        </select>
                    </div>
                    <div class="col-md-6 form-group">
                        <label class="font-weight-bold">Status</label>
                        <select name="status" class="form-control">
                            <option value="Todo" selected>Todo</option>
                            <option value="In Progress">In Progress</option>
                            <option value="Review">Review</option>
                            <option value="Done">Done</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 bg-light">
                <button type="button" class="btn btn-secondary rounded-pill px-3" data-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary rounded-pill px-4">
                    <i class="dripicons-checkmark mr-1"></i> Save Task
                </button>
            </div>
            {{ Form::close() }}
        </div>
    </div>
</div>

@push('scripts')
<script>
    $(document).on('click', '.btn-status', function() {
        var id = $(this).data('id');
        var status = $(this).data('status');
        $.post("{{ url('project-management/tasks') }}/" + id + "/status", {
            _token: "{{ csrf_token() }}",
            status: status
        }, function(res) {
            location.reload();
        });
    });
</script>
@endpush

@endsection
