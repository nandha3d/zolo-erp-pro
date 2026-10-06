@extends('backend.layout.main')

@section('content')
<x-success-message key="message" />
<x-error-message key="not_permitted" />

<section class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h3 class="font-weight-bold text-dark mb-0"><i class="dripicons-article text-secondary mr-2"></i> Standard Remarks Master</h3>
            <small class="text-muted">Predefined terms, clauses and invoice remarks for fast insertion during billing</small>
        </div>
        <button class="btn btn-secondary font-weight-bold" data-toggle="modal" data-target="#createModal">
            <i class="dripicons-plus mr-1"></i> Add Remark
        </button>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="remark-table">
                    <thead class="bg-light text-muted text-uppercase" style="font-size: 11px;">
                        <tr>
                            <th>Title</th>
                            <th>Type</th>
                            <th>Remark Text</th>
                            <th>Default</th>
                            <th>Status</th>
                            <th class="text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($remarks as $rm)
                        <tr>
                            <td class="font-weight-bold text-dark">{{ $rm->title }}</td>
                            <td>
                                <span class="badge badge-info">
                                    {{ strtoupper($rm->type) }}
                                </span>
                            </td>
                            <td>{{ Str::limit($rm->remark, 70) }}</td>
                            <td>
                                <span class="badge badge-{{ $rm->is_default ? 'success' : 'light text-muted' }}">
                                    {{ $rm->is_default ? 'Default' : 'No' }}
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-{{ $rm->is_active ? 'success' : 'danger' }}">
                                    {{ $rm->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="text-right">
                                <form action="{{ route('standard-remark.destroy', $rm->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this remark?');">
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
                            <td colspan="6" class="text-center py-4 text-muted">No standard remarks configured. Click "Add Remark" to create one.</td>
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
            <form action="{{ route('standard-remark.store') }}" method="POST">
                @csrf
                <div class="modal-header bg-light">
                    <h5 class="modal-title font-weight-bold text-dark"><i class="dripicons-plus mr-1 text-secondary"></i> Create Standard Remark</h5>
                    <button type="button" data-dismiss="modal" class="close">&times;</button>
                </div>
                <div class="modal-body p-4">
                    <div class="row">
                        <div class="col-md-7 form-group">
                            <label class="font-weight-bold">Remark Title *</label>
                            <input type="text" name="title" class="form-control" required placeholder="e.g. Standard Terms, Payment Clause">
                        </div>
                        <div class="col-md-5 form-group">
                            <label class="font-weight-bold">Applicable To *</label>
                            <select name="type" class="form-control" required>
                                <option value="all">All Documents</option>
                                <option value="sale">Sales Only</option>
                                <option value="purchase">Purchase Only</option>
                                <option value="voucher">Vouchers Only</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="font-weight-bold">Remark Content *</label>
                        <textarea name="remark" rows="3" class="form-control" required placeholder="Enter full remark text to appear on invoice..."></textarea>
                    </div>
                    <div class="custom-control custom-checkbox pt-2">
                        <input type="checkbox" class="custom-control-input" id="is_default" name="is_default" value="1">
                        <label class="custom-control-label font-weight-bold" for="is_default">Auto-apply as default remark</label>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-dark font-weight-bold">Create Remark</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
