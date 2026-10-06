@extends('backend.layout.main')

@section('content')
<x-success-message key="message" />
<x-error-message key="not_permitted" />

<section class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h3 class="font-weight-bold text-dark mb-0"><i class="dripicons-user-group text-primary mr-2"></i> Agent / Broker Master</h3>
            <small class="text-muted">Manage commission agents, brokers and intermediaries for Sales and Purchases ("Through")</small>
        </div>
        <button class="btn btn-primary font-weight-bold" data-toggle="modal" data-target="#createModal">
            <i class="dripicons-plus mr-1"></i> Add Agent / Broker
        </button>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="agent-table">
                    <thead class="bg-light text-muted text-uppercase" style="font-size: 11px;">
                        <tr>
                            <th>Agent Name</th>
                            <th>Code</th>
                            <th>Phone</th>
                            <th>Email</th>
                            <th>Commission Rate</th>
                            <th>Linked Account</th>
                            <th>Status</th>
                            <th class="text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($agents as $agent)
                        <tr>
                            <td class="font-weight-bold text-dark">{{ $agent->name }}</td>
                            <td><code>{{ $agent->code ?? '—' }}</code></td>
                            <td>{{ $agent->phone ?? '—' }}</td>
                            <td>{{ $agent->email ?? '—' }}</td>
                            <td>{{ $agent->commission_rate > 0 ? $agent->commission_rate.'%' : '0.00%' }}</td>
                            <td>{{ $agent->account->name ?? '—' }}</td>
                            <td>
                                <span class="badge badge-{{ $agent->is_active ? 'success' : 'danger' }}">
                                    {{ $agent->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="text-right">
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
</section>

<!-- Create Modal -->
<div id="createModal" tabindex="-1" role="dialog" class="modal fade text-left">
    <div role="document" class="modal-dialog modal-md">
        <div class="modal-content border-0 shadow">
            <form action="{{ route('agent.store') }}" method="POST">
                @csrf
                <div class="modal-header bg-light">
                    <h5 class="modal-title font-weight-bold text-dark"><i class="dripicons-plus mr-1 text-primary"></i> Create Agent / Broker</h5>
                    <button type="button" data-dismiss="modal" class="close">&times;</button>
                </div>
                <div class="modal-body p-4">
                    <div class="form-group">
                        <label class="font-weight-bold">Agent Name *</label>
                        <input type="text" name="name" class="form-control" required placeholder="e.g. Ramesh Broker, Direct Sales">
                    </div>
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold">Agent Code</label>
                            <input type="text" name="code" class="form-control" placeholder="e.g. AG-01">
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold">Commission Rate (%)</label>
                            <input type="number" step="0.01" name="commission_rate" class="form-control" value="0">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold">Phone Number</label>
                            <input type="text" name="phone" class="form-control" placeholder="Mobile / WhatsApp">
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold">Email</label>
                            <input type="email" name="email" class="form-control" placeholder="agent@example.com">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="font-weight-bold">Commission Payable Account</label>
                        <select name="account_id" class="form-control selectpicker" data-live-search="true">
                            <option value="">Select Ledger Account</option>
                            @foreach($accounts as $acc)
                                <option value="{{ $acc->id }}">{{ $acc->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="font-weight-bold">Address</label>
                        <textarea name="address" rows="2" class="form-control" placeholder="Address"></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary font-weight-bold">Create Agent</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
