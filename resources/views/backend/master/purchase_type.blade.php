@extends('backend.layout.main')

@section('content')
<x-success-message key="message" />
<x-error-message key="not_permitted" />

<section class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h3 class="font-weight-bold text-dark mb-0"><i class="dripicons-shopping-bag text-warning mr-2"></i> Purchase Type Master</h3>
            <small class="text-muted">Database-backed GST & commercial tax classifications for inward purchase bills</small>
        </div>
        <button class="btn btn-warning font-weight-bold" data-toggle="modal" data-target="#createModal">
            <i class="dripicons-plus mr-1"></i> Add Purchase Type
        </button>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="purchasetype-table">
                    <thead class="bg-light text-muted text-uppercase" style="font-size: 11px;">
                        <tr>
                            <th>Name</th>
                            <th>Code</th>
                            <th>Tax Nature</th>
                            <th>Tax Rate (%)</th>
                            <th>Default Purchase A/c</th>
                            <th>Status</th>
                            <th class="text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($purchaseTypes as $type)
                        <tr>
                            <td class="font-weight-bold text-dark">{{ $type->name }}</td>
                            <td><code>{{ $type->code ?? '—' }}</code></td>
                            <td>
                                <span class="badge badge-{{ $type->tax_nature === 'local' ? 'primary' : ($type->tax_nature === 'interstate' ? 'info' : ($type->tax_nature === 'import' ? 'danger' : 'secondary')) }}">
                                    {{ strtoupper($type->tax_nature) }}
                                </span>
                            </td>
                            <td class="font-weight-bold">{{ $type->tax_rate > 0 ? $type->tax_rate.'%' : 'Multi / Exempt' }}</td>
                            <td>{{ $type->purchaseAccount->name ?? 'Default Purchase A/c' }}</td>
                            <td>
                                <span class="badge badge-{{ $type->is_active ? 'success' : 'danger' }}">
                                    {{ $type->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="text-right">
                                <form action="{{ route('purchase-type.destroy', $type->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this purchase type?');">
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
                            <td colspan="7" class="text-center py-4 text-muted">No purchase types configured. Click "Add Purchase Type" to create one.</td>
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
            <form action="{{ route('purchase-type.store') }}" method="POST">
                @csrf
                <div class="modal-header bg-light">
                    <h5 class="modal-title font-weight-bold text-dark"><i class="dripicons-plus mr-1 text-warning"></i> Create Purchase Type</h5>
                    <button type="button" data-dismiss="modal" class="close">&times;</button>
                </div>
                <div class="modal-body p-4">
                    <div class="form-group">
                        <label class="font-weight-bold">Purchase Type Name *</label>
                        <input type="text" name="name" class="form-control" required placeholder="e.g. L/MultiTax, 18%-GST Inward, Import">
                    </div>
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold">Code</label>
                            <input type="text" name="code" class="form-control" placeholder="e.g. PGST18">
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold">Tax Nature *</label>
                            <select name="tax_nature" class="form-control" required>
                                <option value="local">Local (Intra-State)</option>
                                <option value="interstate">Interstate (IGST)</option>
                                <option value="import">Import</option>
                                <option value="exempted">Exempted / Nil Rated</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold">GST Rate (%)</label>
                            <input type="number" step="0.01" name="tax_rate" class="form-control" value="0">
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold">Purchase Ledger Account</label>
                            <select name="purchase_account_id" class="form-control selectpicker" data-live-search="true">
                                <option value="">Default Purchase A/c</option>
                                @foreach($accounts as $acc)
                                    <option value="{{ $acc->id }}">{{ $acc->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning font-weight-bold">Create Purchase Type</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
