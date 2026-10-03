@extends('backend.layout.main')

@section('content')

@if(session()->has('message'))
    <div class="alert alert-success alert-dismissible text-center"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>{{ session()->get('message') }}</div>
@endif
@if(session()->has('not_permitted'))
    <div class="alert alert-danger alert-dismissible text-center"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>{{ session()->get('not_permitted') }}</div>
@endif

<section class="forms">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="font-weight-bold text-dark mb-1">General Journal Entries</h3>
                <p class="text-muted mb-0">Audit log of all automated double-entry postings and manual journal vouchers.</p>
            </div>
            <div>
                <button type="button" class="btn btn-primary neo-btn-glow" data-toggle="modal" data-target="#createVoucherModal">
                    <i class="dripicons-plus mr-1"></i> New Journal Voucher
                </button>
            </div>
        </div>

        <!-- Filter Card -->
        <div class="card neo-card shadow-sm border-0 mb-4">
            <div class="card-body p-3">
                <form action="{{ route('accounting.journal-entries') }}" method="GET" class="form-row align-items-end">
                    <div class="col-md-3">
                        <label class="font-weight-600 mb-1" style="font-size: 13px;">Reference Type</label>
                        <select name="reference_type" class="form-control form-control-sm">
                            <option value="">-- All Types --</option>
                            <option value="sale" {{ request('reference_type') == 'sale' ? 'selected' : '' }}>Sale / Invoices</option>
                            <option value="purchase" {{ request('reference_type') == 'purchase' ? 'selected' : '' }}>Purchase Orders</option>
                            <option value="payment" {{ request('reference_type') == 'payment' ? 'selected' : '' }}>Payments</option>
                            <option value="expense" {{ request('reference_type') == 'expense' ? 'selected' : '' }}>Expenses</option>
                            <option value="manual" {{ request('reference_type') == 'manual' ? 'selected' : '' }}>Manual Vouchers</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="font-weight-600 mb-1" style="font-size: 13px;">Start Date</label>
                        <input type="date" name="start_date" class="form-control form-control-sm" value="{{ request('start_date') }}">
                    </div>
                    <div class="col-md-3">
                        <label class="font-weight-600 mb-1" style="font-size: 13px;">End Date</label>
                        <input type="date" name="end_date" class="form-control form-control-sm" value="{{ request('end_date') }}">
                    </div>
                    <div class="col-md-3 d-flex">
                        <button type="submit" class="btn btn-sm btn-primary flex-grow-1 mr-2">Filter</button>
                        <a href="{{ route('accounting.journal-entries') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Journal Entries List -->
        <div class="card neo-card shadow-sm border-0">
            <div class="table-responsive">
                <table class="table neo-table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Entry #</th>
                            <th>Date</th>
                            <th>Reference</th>
                            <th>Description</th>
                            <th class="text-right">Total Debit</th>
                            <th class="text-right">Total Credit</th>
                            <th>Status</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($entries as $entry)
                            <tr>
                                <td class="font-weight-bold font-monospace text-primary">{{ $entry->entry_number }}</td>
                                <td>{{ $entry->entry_date->format('Y-m-d') }}</td>
                                <td>
                                    <span class="badge badge-light border text-uppercase font-weight-600" style="font-size: 11px;">
                                        {{ $entry->reference_type }}
                                    </span>
                                    @if($entry->reference_no)
                                        <small class="text-muted d-block">{{ $entry->reference_no }}</small>
                                    @endif
                                </td>
                                <td>{{ Str::limit($entry->description, 50) }}</td>
                                <td class="text-right font-weight-bold text-dark">{{ number_format((float)$entry->total_debit, 2) }}</td>
                                <td class="text-right font-weight-bold text-dark">{{ number_format((float)$entry->total_credit, 2) }}</td>
                                <td>
                                    <span class="badge badge-success-soft">Posted</span>
                                </td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-sm btn-outline-primary view-entry-btn" data-id="{{ $entry->id }}" title="View Lines">
                                        <i class="dripicons-preview"></i>
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted">No journal entries found for this period.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($entries->hasPages())
                <div class="card-footer bg-white border-0 py-3">
                    {{ $entries->links() }}
                </div>
            @endif
        </div>
    </div>
</section>

<!-- Create Manual Voucher Modal -->
<div id="createVoucherModal" tabindex="-1" role="dialog" class="modal fade neo-modal" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title font-weight-bold">New Journal Entry Voucher</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <form action="{{ route('accounting.journal-entries.store') }}" method="POST" id="voucherForm">
                @csrf
                <div class="modal-body">
                    <div class="form-row mb-3">
                        <div class="col-md-4">
                            <label class="font-weight-600">Date <span class="text-danger">*</span></label>
                            <input type="date" name="entry_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                        </div>
                        <div class="col-md-8">
                            <label class="font-weight-600">Description / Memo <span class="text-danger">*</span></label>
                            <input type="text" name="description" class="form-control" placeholder="e.g. Month-end adjustment, Depreciation" required>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mb-2 mt-4">
                        <h6 class="font-weight-bold mb-0">Journal Line Items</h6>
                        <button type="button" class="btn btn-sm btn-outline-success" id="addLineBtn">
                            <i class="dripicons-plus mr-1"></i> Add Line
                        </button>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered table-sm" id="voucherLinesTable">
                            <thead class="bg-light">
                                <tr>
                                    <th style="width: 40%;">Account</th>
                                    <th>Memo</th>
                                    <th style="width: 20%;" class="text-right">Debit</th>
                                    <th style="width: 20%;" class="text-right">Credit</th>
                                    <th style="width: 5%;"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr class="line-row">
                                    <td>
                                        <select name="items[0][chart_of_account_id]" class="form-control form-control-sm" required>
                                            <option value="">-- Select Account --</option>
                                            @foreach($accounts as $acc)
                                                <option value="{{ $acc->id }}">{{ $acc->code }} - {{ $acc->name }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td><input type="text" name="items[0][memo]" class="form-control form-control-sm" placeholder="Memo"></td>
                                    <td><input type="number" step="0.01" name="items[0][debit]" class="form-control form-control-sm text-right debit-input" value="0.00"></td>
                                    <td><input type="number" step="0.01" name="items[0][credit]" class="form-control form-control-sm text-right credit-input" value="0.00"></td>
                                    <td class="text-center"><button type="button" class="btn btn-sm btn-link text-danger remove-line-btn">&times;</button></td>
                                </tr>
                                <tr class="line-row">
                                    <td>
                                        <select name="items[1][chart_of_account_id]" class="form-control form-control-sm" required>
                                            <option value="">-- Select Account --</option>
                                            @foreach($accounts as $acc)
                                                <option value="{{ $acc->id }}">{{ $acc->code }} - {{ $acc->name }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td><input type="text" name="items[1][memo]" class="form-control form-control-sm" placeholder="Memo"></td>
                                    <td><input type="number" step="0.01" name="items[1][debit]" class="form-control form-control-sm text-right debit-input" value="0.00"></td>
                                    <td><input type="number" step="0.01" name="items[1][credit]" class="form-control form-control-sm text-right credit-input" value="0.00"></td>
                                    <td class="text-center"><button type="button" class="btn btn-sm btn-link text-danger remove-line-btn">&times;</button></td>
                                </tr>
                            </tbody>
                            <tfoot class="bg-light font-weight-bold">
                                <tr>
                                    <td colspan="2" class="text-right">Total:</td>
                                    <td class="text-right" id="totalDebitCell">0.00</td>
                                    <td class="text-right" id="totalCreditCell">0.00</td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td colspan="2" class="text-right">Balance Check:</td>
                                    <td colspan="2" class="text-center" id="balanceStatusCell">
                                        <span class="badge badge-success">Balanced (0.00)</span>
                                    </td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary neo-btn-glow" id="submitVoucherBtn">Post Journal Entry</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Entry Modal -->
<div id="viewEntryModal" tabindex="-1" role="dialog" class="modal fade neo-modal" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title font-weight-bold" id="viewModalTitle">Journal Entry</h5>
                    <small class="text-muted" id="viewModalSubtitle"></small>
                </div>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <table class="table table-bordered table-sm mb-0" id="entryDetailTable">
                    <thead class="bg-light">
                        <tr>
                            <th>Account</th>
                            <th>Memo</th>
                            <th class="text-right">Debit</th>
                            <th class="text-right">Credit</th>
                        </tr>
                    </thead>
                    <tbody id="entryDetailBody"></tbody>
                    <tfoot class="bg-light font-weight-bold">
                        <tr>
                            <td colspan="2" class="text-right">Total:</td>
                            <td class="text-right" id="viewModalTotalDebit"></td>
                            <td class="text-right" id="viewModalTotalCredit"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script type="text/javascript">
    let lineIndex = 2;

    function calculateTotals() {
        let totalDebit = 0;
        let totalCredit = 0;

        $('.debit-input').each(function() {
            totalDebit += parseFloat($(this).val()) || 0;
        });
        $('.credit-input').each(function() {
            totalCredit += parseFloat($(this).val()) || 0;
        });

        $('#totalDebitCell').text(totalDebit.toFixed(2));
        $('#totalCreditCell').text(totalCredit.toFixed(2));

        let diff = Math.abs(totalDebit - totalCredit);
        if (diff < 0.01 && (totalDebit > 0 || totalCredit > 0)) {
            $('#balanceStatusCell').html('<span class="badge badge-success">Balanced (0.00)</span>');
            $('#submitVoucherBtn').prop('disabled', false);
        } else {
            $('#balanceStatusCell').html('<span class="badge badge-danger">Out of Balance (Diff: ' + diff.toFixed(2) + ')</span>');
            $('#submitVoucherBtn').prop('disabled', true);
        }
    }

    $(document).on('input', '.debit-input, .credit-input', function() {
        calculateTotals();
    });

    $('#addLineBtn').click(function() {
        let accountsOptions = `
            <option value="">-- Select Account --</option>
            @foreach($accounts as $acc)
                <option value="{{ $acc->id }}">{{ $acc->code }} - {{ $acc->name }}</option>
            @endforeach
        `;

        let rowHtml = `
            <tr class="line-row">
                <td>
                    <select name="items[${lineIndex}][chart_of_account_id]" class="form-control form-control-sm" required>
                        ${accountsOptions}
                    </select>
                </td>
                <td><input type="text" name="items[${lineIndex}][memo]" class="form-control form-control-sm" placeholder="Memo"></td>
                <td><input type="number" step="0.01" name="items[${lineIndex}][debit]" class="form-control form-control-sm text-right debit-input" value="0.00"></td>
                <td><input type="number" step="0.01" name="items[${lineIndex}][credit]" class="form-control form-control-sm text-right credit-input" value="0.00"></td>
                <td class="text-center"><button type="button" class="btn btn-sm btn-link text-danger remove-line-btn">&times;</button></td>
            </tr>
        `;

        $('#voucherLinesTable tbody').append(rowHtml);
        lineIndex++;
    });

    $(document).on('click', '.remove-line-btn', function() {
        if ($('#voucherLinesTable tbody tr').length > 2) {
            $(this).closest('tr').remove();
            calculateTotals();
        } else {
            alert('A journal voucher requires at least two lines.');
        }
    });

    // View Entry details via AJAX
    $(document).on('click', '.view-entry-btn', function() {
        let entryId = $(this).data('id');
        $.get("{{ url('accounting/journal-entries') }}/" + entryId, function(entry) {
            $('#viewModalTitle').text(entry.entry_number + ' - ' + (entry.description || 'Journal Entry'));
            $('#viewModalSubtitle').text('Date: ' + entry.entry_date + ' | Ref: ' + (entry.reference_type || 'Manual') + ' ' + (entry.reference_no || ''));

            let bodyHtml = '';
            entry.items.forEach(function(item) {
                bodyHtml += `
                    <tr>
                        <td class="font-weight-600">${item.account ? item.account.code + ' - ' + item.account.name : 'N/A'}</td>
                        <td>${item.memo || ''}</td>
                        <td class="text-right">${parseFloat(item.debit) > 0 ? parseFloat(item.debit).toFixed(2) : '-'}</td>
                        <td class="text-right">${parseFloat(item.credit) > 0 ? parseFloat(item.credit).toFixed(2) : '-'}</td>
                    </tr>
                `;
            });

            $('#entryDetailBody').html(bodyHtml);
            $('#viewModalTotalDebit').text(parseFloat(entry.total_debit).toFixed(2));
            $('#viewModalTotalCredit').text(parseFloat(entry.total_credit).toFixed(2));

            $('#viewEntryModal').modal('show');
        });
    });
</script>
@endpush
