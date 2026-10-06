@extends('backend.layout.main')

@section('content')
<x-success-message key="message" />
<x-error-message key="not_permitted" />

<section class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h3 class="font-weight-bold text-dark mb-0"><i class="dripicons-briefcase text-success mr-2"></i> Sale Type Master</h3>
            <small class="text-muted">Database-backed GST & commercial tax classifications for sales bills</small>
        </div>
        <button class="btn btn-success" data-toggle="modal" data-target="#createModal">
            <i class="dripicons-plus mr-1"></i> Add Sale Type
        </button>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="saletype-table">
                    <thead class="bg-light text-muted text-uppercase" style="font-size: 11px;">
                        <tr>
                            <th>Name</th>
                            <th>Code</th>
                            <th>Tax Nature</th>
                            <th>Tax Rate (%)</th>
                            <th>Default Sales A/c</th>
                            <th>Status</th>
                            <th class="text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($saleTypes as $type)
                        <tr>
                            <td class="font-weight-bold text-dark">{{ $type->name }}</td>
                            <td><code>{{ $type->code ?? '—' }}</code></td>
                            <td>
                                <span class="badge badge-{{ $type->tax_nature === 'local' ? 'primary' : ($type->tax_nature === 'interstate' ? 'info' : ($type->tax_nature === 'export' ? 'warning' : 'secondary')) }}">
                                    {{ strtoupper($type->tax_nature) }}
                                </span>
                            </td>
                            <td class="font-weight-bold">{{ $type->tax_rate > 0 ? $type->tax_rate.'%' : 'Multi / Exempt' }}</td>
                            <td>{{ $type->salesAccount->name ?? 'Default Sales A/c' }}</td>
                            <td>
                                <span class="badge badge-{{ $type->is_active ? 'success' : 'danger' }}">
                                    {{ $type->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="text-right">
                                <form action="{{ route('sale-type.destroy', $type->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this sale type?');">
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
                            <td colspan="7" class="text-center py-4 text-muted">No sale types configured. Click "Add Sale Type" to create one.</td>
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
            <form action="{{ route('sale-type.store') }}" method="POST">
                @csrf
                <div class="modal-header bg-light">
                    <h5 class="modal-title font-weight-bold text-dark"><i class="dripicons-plus mr-1 text-success"></i> Create Sale Type</h5>
                    <button type="button" data-dismiss="modal" class="close">&times;</button>
                </div>
                <div class="modal-body p-4">
                    <div class="form-group">
                        <label class="font-weight-bold">Sale Type Name *</label>
                        <input type="text" name="name" class="form-control" required placeholder="e.g. 18%-GST, 5%-IGST, Export, SEZ">
                    </div>
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold">Code</label>
                            <input type="text" name="code" class="form-control" placeholder="e.g. GST18">
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold">Tax Nature *</label>
                            <select name="tax_nature" class="form-control" required>
                                <option value="local">Local (Intra-State)</option>
                                <option value="interstate">Interstate (IGST)</option>
                                <option value="export">Export</option>
                                <option value="sez">SEZ Supply</option>
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
                            <label class="font-weight-bold">Sales Ledger Account</label>
                            <select name="sales_account_id" class="form-control selectpicker" data-live-search="true">
                                <option value="">Default Sales A/c</option>
                                @foreach($accounts as $acc)
                                    <option value="{{ $acc->id }}">{{ $acc->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success font-weight-bold">Create Sale Type</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
