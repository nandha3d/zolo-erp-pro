@extends('backend.layout.main')

@section('content')
<x-success-message key="message" />
<x-error-message key="not_permitted" />

<section class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h3 class="font-weight-bold text-dark mb-0"><i class="dripicons-list text-primary mr-2"></i> Voucher Series (Ctl+F9)</h3>
            <small class="text-muted">Database-backed voucher series numbering, prefixes, and reset rules across Sales, Purchases, Vouchers and Returns</small>
        </div>
        <button class="btn btn-primary font-weight-bold" data-toggle="modal" data-target="#createModal">
            <i class="dripicons-plus mr-1"></i> Add Voucher Series
        </button>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="series-table">
                    <thead class="bg-light text-muted text-uppercase" style="font-size: 11px;">
                        <tr>
                            <th>Series Type</th>
                            <th>Series Name</th>
                            <th>Prefix</th>
                            <th>Suffix</th>
                            <th>Next No</th>
                            <th>Padding</th>
                            <th>Reset Policy</th>
                            <th>Default</th>
                            <th class="text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($series as $ser)
                        <tr>
                            <td>
                                <span class="badge badge-secondary text-uppercase">{{ str_replace('_', ' ', $ser->document_type) }}</span>
                            </td>
                            <td class="font-weight-bold text-dark">{{ $ser->code }}</td>
                            <td><code>{{ $ser->prefix ?: '—' }}</code></td>
                            <td><code>{{ $ser->suffix ?: '—' }}</code></td>
                            <td class="font-weight-bold text-primary">{{ $ser->next_number }}</td>
                            <td>{{ $ser->padding }}</td>
                            <td class="text-capitalize">{{ str_replace('_', ' ', $ser->reset_policy) }}</td>
                            <td>
                                @if($ser->is_default)
                                    <span class="badge badge-success">Yes</span>
                                @else
                                    <span class="badge badge-light text-muted">No</span>
                                @endif
                            </td>
                            <td class="text-right">
                                <form action="{{ route('document-series.destroy', $ser->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this series?');">
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
                            <td colspan="9" class="text-center py-4 text-muted">No voucher series configured. Click "Add Voucher Series" to create one.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>

<!-- Modal -->
<div class="modal fade" id="createModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content border-0 shadow">
            <form action="{{ route('document-series.store') }}" method="POST">
                @csrf
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title font-weight-bold"><i class="dripicons-plus mr-1"></i> Add Voucher Series</h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label class="font-weight-bold">Series Type *</label>
                        <select name="document_type" class="form-control" required>
                            <option value="sale">Sales (F2)</option>
                            <option value="purchase">Purchase (F12)</option>
                            <option value="quotation">Quotation (Alt+F10)</option>
                            <option value="sale_payment">Receipt (F6)</option>
                            <option value="purchase_payment">Payment (F5)</option>
                            <option value="journal">Journal (F7)</option>
                            <option value="sale_credit_note">Sales Return / Cr Note</option>
                            <option value="purchase_debit_note">Purchase Return / Dr Note</option>
                            <option value="delivery">Delivery Challan</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="font-weight-bold">Series Name / Code *</label>
                        <input type="text" name="code" class="form-control" placeholder="e.g. SALES, MJ-22, WHOLESALE" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold">Prefix</label>
                            <input type="text" name="prefix" class="form-control" placeholder="e.g. MJ-, INV-">
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold">Suffix</label>
                            <input type="text" name="suffix" class="form-control" placeholder="e.g. /26-27">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold">Start / Next No *</label>
                            <input type="number" name="next_number" class="form-control" value="1" min="1" required>
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold">Zero Padding</label>
                            <input type="number" name="padding" class="form-control" value="0" min="0" max="10">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold">Reset Policy</label>
                            <select name="reset_policy" class="form-control">
                                <option value="financial_year">Reset Each Financial Year</option>
                                <option value="monthly">Reset Each Month</option>
                                <option value="never">Never Reset</option>
                            </select>
                        </div>
                        <div class="col-md-6 form-group d-flex align-items-center pt-4">
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" name="is_default" value="1" class="custom-control-input" id="isDefaultCheck">
                                <label class="custom-control-label font-weight-bold" for="isDefaultCheck">Default for Type</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary font-weight-bold">Save Series</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
