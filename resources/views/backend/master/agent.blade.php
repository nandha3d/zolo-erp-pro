@extends('backend.layout.main')

@section('content')

@push('css')
<link rel="stylesheet" href="{{ versioned_asset('css/product-workspace.css') }}" type="text/css">
<link rel="stylesheet" href="{{ versioned_asset('css/pages/parties-workspace.css') }}" type="text/css">
@endpush

<x-success-message key="message" />
<x-error-message key="not_permitted" />

<x-party-command-bar active="agents" title="Agent / Broker Master" badge="Commission Agents, Intermediaries &amp; Brokers">
    <button class="btn btn-primary" data-toggle="modal" data-target="#createModal">
        <i class="dripicons-plus"></i> Add Agent / Broker
    </button>
</x-party-command-bar>

<div class="agent-workspace-view">
    @php
        $totalAgents = $agents->count();
        $activeAgents = $agents->where('is_active', true)->count();
        $commissionAgents = $agents->where('commission_rate', '>', 0)->count();
    @endphp

    <!-- KPI Summary Strip -->
    <div class="kpi-summary-strip">
        <div class="kpi-card">
            <div>
                <div class="kpi-label">Total Agents / Brokers</div>
                <div class="kpi-number">{{ $totalAgents }}</div>
            </div>
            <div class="u-bg-primary-soft u-c-primary u-w-38px u-h-38px u-r-6px u-d-flex u-items-center u-justify-center">
                <i class="dripicons-briefcase u-fs-18px"></i>
            </div>
        </div>
        <div class="kpi-card">
            <div>
                <div class="kpi-label">Active Intermediaries</div>
                <div class="kpi-number u-c-success">{{ $activeAgents }}</div>
            </div>
            <div class="u-bg-success-soft u-c-success u-w-38px u-h-38px u-r-6px u-d-flex u-items-center u-justify-center">
                <i class="dripicons-checkmark u-fs-18px"></i>
            </div>
        </div>
        <div class="kpi-card">
            <div>
                <div class="kpi-label">Commission Enrolled</div>
                <div class="kpi-number u-c-info">{{ $commissionAgents }}</div>
            </div>
            <div class="u-bg-info-soft u-c-info u-w-38px u-h-38px u-r-6px u-d-flex u-items-center u-justify-center">
                <i class="dripicons-trophy u-fs-18px"></i>
            </div>
        </div>
    </div>

    <!-- Desk Table Card -->
    <div class="desk-card-wrapper">
        <div class="table-responsive">
            <table class="table table-hover mb-0" id="agent-table">
                <thead>
                    <tr>
                        <th>Agent Name</th>
                        <th>Code</th>
                        <th>Phone</th>
                        <th>Email</th>
                        <th>Commission Rate</th>
                        <th>Linked Account</th>
                        <th>Status</th>
                        <th class="not-exported text-right">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($agents as $agent)
                    <tr>
                        <td class="font-weight-bold">{{ $agent->name }}</td>
                        <td><code>{{ $agent->code ?? '—' }}</code></td>
                        <td>{{ $agent->phone ?? '—' }}</td>
                        <td>{{ $agent->email ?? '—' }}</td>
                        <td>{{ $agent->commission_rate > 0 ? $agent->commission_rate.'%' : '0.00%' }}</td>
                        <td>{{ $agent->account->name ?? '—' }}</td>
                        <td>
                            <span class="badge badge-{{ $agent->is_active ? 'success' : 'secondary' }}">
                                {{ $agent->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td class="text-right">
                            <button type="button" class="btn btn-sm btn-outline-primary js-master-edit" title="Edit" data-update-url="{{ route('agent.update', $agent->id) }}" data-record='@json($agent)'>
                                <i class="dripicons-pencil"></i>
                            </button>
                            <form action="{{ route('agent.destroy', $agent->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this agent?');">
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
                        <td colspan="8" class="text-center py-4 text-muted">No agents configured. Click "Add Agent / Broker" to create one.</td>
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
            <form action="{{ route('agent.store') }}" method="POST">
                @csrf
                <div class="modal-header party-modal-header d-flex justify-content-between align-items-center">
                    <h5 class="modal-title font-weight-bold"><i class="dripicons-plus mr-1 text-primary"></i> Create Agent / Broker</h5>
                    <button type="button" data-dismiss="modal" class="close">&times;</button>
                </div>
                <div class="modal-body p-4">
                    <div class="party-form-group mb-3">
                        <label class="font-weight-bold">Agent Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required placeholder="e.g. Ramesh Broker, Direct Sales">
                    </div>
                    <div class="row">
                        <div class="col-md-6 party-form-group mb-3">
                            <label class="font-weight-bold">Agent Code</label>
                            <input type="text" name="code" class="form-control" placeholder="e.g. AG-01">
                        </div>
                        <div class="col-md-6 party-form-group mb-3">
                            <label class="font-weight-bold">Commission Rate (%)</label>
                            <input type="number" step="0.01" name="commission_rate" class="form-control" value="0">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 party-form-group mb-3">
                            <label class="font-weight-bold">Phone Number</label>
                            <input type="text" name="phone" class="form-control" placeholder="Mobile / WhatsApp">
                        </div>
                        <div class="col-md-6 party-form-group mb-3">
                            <label class="font-weight-bold">Email</label>
                            <input type="email" name="email" class="form-control" placeholder="agent@example.com">
                        </div>
                    </div>
                    <div class="party-form-group mb-3">
                        <label class="font-weight-bold">Commission Payable Account</label>
                        <select name="account_id" class="form-control selectpicker" data-live-search="true">
                            <option value="">Select Ledger Account</option>
                            @foreach($accounts as $acc)
                                <option value="{{ $acc->id }}">{{ $acc->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="party-form-group mb-3">
                        <label class="font-weight-bold">Address</label>
                        <textarea name="address" rows="2" class="form-control" placeholder="Office / Communication Address"></textarea>
                    </div>
                    @include('backend.master.partials.active-toggle')
                </div>
                <div class="modal-footer bg-light border-top d-flex justify-content-end gap-2">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary font-weight-bold">Create Agent</button>
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
    $("ul#parties #agent-list-menu").addClass("active");
</script>
@endpush

@include('backend.master.partials.edit-script', ['editTitle' => 'Edit Agent / Broker'])
