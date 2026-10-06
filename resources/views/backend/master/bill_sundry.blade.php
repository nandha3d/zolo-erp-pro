@extends('backend.layout.main')

@section('content')
<x-success-message key="message" />
<x-error-message key="not_permitted" />

<section class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h3 class="font-weight-bold text-dark mb-0"><i class="dripicons-tag text-primary mr-2"></i> Bill Sundry Master</h3>
            <small class="text-muted">Manage additional billing charges, freight, discounts and rounding rules with linked accounts</small>
        </div>
        <button class="btn btn-primary" data-toggle="modal" data-target="#createModal">
            <i class="dripicons-plus mr-1"></i> Add Bill Sundry
        </button>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="sundry-table">
                    <thead class="bg-light text-muted text-uppercase" style="font-size: 11px;">
                        <tr>
                            <th>Name</th>
                            <th>Nature</th>
                            <th>Type</th>
                            <th>Default Value</th>
                            <th>Affect Cost</th>
                            <th>Before Tax</th>
                            <th>Tax Rate</th>
                            <th>Linked Account</th>
                            <th>Status</th>
                            <th class="text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($billSundries as $sundry)
                        <tr>
                            <td class="font-weight-bold text-dark">{{ $sundry->name }}</td>
                            <td>
                                <span class="badge badge-{{ $sundry->nature === 'sales' ? 'info' : ($sundry->nature === 'purchase' ? 'warning' : 'secondary') }}">
                                    {{ ucfirst($sundry->nature) }}
                                </span>
                            </td>
                            <td>{{ $sundry->calculation_type === 'percentage' ? 'Percentage (%)' : 'Lump Sum Amount' }}</td>
                            <td>{{ number_format($sundry->default_value, 2) }}</td>
                            <td>
                                <span class="badge badge-{{ $sundry->affect_cost ? 'success' : 'light text-muted' }}">
                                    {{ $sundry->affect_cost ? 'Yes' : 'No' }}
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-{{ $sundry->calculate_before_tax ? 'primary' : 'light text-muted' }}">
                                    {{ $sundry->calculate_before_tax ? 'Yes' : 'No' }}
                                </span>
                            </td>
                            <td>{{ $sundry->tax_rate > 0 ? $sundry->tax_rate.'%' : '—' }}</td>
                            <td>{{ $sundry->account->name ?? '—' }}</td>
                            <td>
                                <span class="badge badge-{{ $sundry->is_active ? 'success' : 'danger' }}">
                                    {{ $sundry->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="text-right">
                                <form action="{{ route('bill-sundry.destroy', $sundry->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this bill sundry?');">
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
                            <td colspan="10" class="text-center py-4 text-muted">No bill sundries configured. Click "Add Bill Sundry" to create one.</td>
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
    <div role="document" class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow">
            <form action="{{ route('bill-sundry.store') }}" method="POST">
                @csrf
                <div class="modal-header bg-light">
                    <h5 class="modal-title font-weight-bold text-dark"><i class="dripicons-plus mr-1 text-primary"></i> Create Bill Sundry</h5>
                    <button type="button" data-dismiss="modal" class="close">&times;</button>
                </div>
                <div class="modal-body p-4">
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold">Sundry Name *</label>
                            <input type="text" name="name" class="form-control" required placeholder="e.g. Freight Charges, Trade Discount">
                        </div>
                        <div class="col-md-3 form-group">
                            <label class="font-weight-bold">Nature *</label>
                            <select name="nature" class="form-control" required>
                                <option value="both">Both (Sales & Purchase)</option>
                                <option value="sales">Sales Only</option>
                                <option value="purchase">Purchase Only</option>
                            </select>
                        </div>
                        <div class="col-md-3 form-group">
                            <label class="font-weight-bold">Calculation Type *</label>
                            <select name="calculation_type" class="form-control" required>
                                <option value="percentage">Percentage (%)</option>
                                <option value="amount">Fixed Amount</option>
                            </select>
                        </div>
                        <div class="col-md-4 form-group">
                            <label class="font-weight-bold">Default Value</label>
                            <input type="number" step="0.0001" name="default_value" class="form-control" value="0">
                        </div>
                        <div class="col-md-4 form-group">
                            <label class="font-weight-bold">GST Tax Rate (%)</label>
                            <input type="number" step="0.01" name="tax_rate" class="form-control" value="0" placeholder="0">
                        </div>
                        <div class="col-md-4 form-group">
                            <label class="font-weight-bold">Accounting Ledger Head</label>
                            <select name="account_id" class="form-control selectpicker" data-live-search="true">
                                <option value="">Select Ledger Account</option>
                                @foreach($accounts as $acc)
                                    <option value="{{ $acc->id }}">{{ $acc->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 form-group pt-3">
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input" id="affect_cost" name="affect_cost" value="1">
                                <label class="custom-control-label font-weight-bold" for="affect_cost">Affect Item Cost / Sales Rate</label>
                                <small class="form-text text-muted">When checked, this charge is added to/deducted from material unit cost</small>
                            </div>
                        </div>
                        <div class="col-md-6 form-group pt-3">
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input" id="calculate_before_tax" name="calculate_before_tax" value="1">
                                <label class="custom-control-label font-weight-bold" for="calculate_before_tax">Calculate Before Tax</label>
                                <small class="form-text text-muted">When checked, tax is calculated on amount after this sundry is applied</small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary font-weight-bold">Create Bill Sundry</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
