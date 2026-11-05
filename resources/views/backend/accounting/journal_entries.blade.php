@extends('backend.layout.main')

@section('content')


<x-success-message key="message" />
<x-error-message key="not_permitted" />
<x-validation-error fieldName="accounting" />
<x-validation-error fieldName="reason" />
<x-validation-error fieldName="entry_date" />

@php
    $totalEntriesCount = $entries->total();
    $totalDebitSum = $entries->sum('total_debit');
    $totalCreditSum = $entries->sum('total_credit');
    $journalContext = app(\App\Services\Platform\CompanyContextResolver::class)->forActor();
    $canCreateJournal = $canReverseJournal = true;
    try { \App\Services\Accounting\AccountingAccess::assert($journalContext); }
    catch (\Illuminate\Auth\Access\AuthorizationException $e) { $canCreateJournal = false; }
    try { \App\Services\Accounting\AccountingAccess::assert($journalContext, 'accounting.journal.reverse'); }
    catch (\Illuminate\Auth\Access\AuthorizationException $e) { $canReverseJournal = false; }
@endphp

<section class="journal-workspace-view">
    <!-- Top Command Bar -->
    <x-accounting-command-bar active="journals" title="Journal entries" purpose="Review posted transactions and their debit and credit lines. Use the source document to correct a bill.">
            @if($canCreateJournal)
            <button type="button" class="btn btn-primary btn-action-pill u-bg-primary u-bc-primary u-c-xffffff" data-toggle="modal" data-target="#createVoucherModal">
                <i class="dripicons-plus"></i> New Journal Voucher
            </button>
            @endif
            <button type="button" class="btn btn-outline-secondary" data-erp-print><i class="dripicons-print"></i> Print / PDF</button>
    </x-accounting-command-bar>

    <!-- KPI Summary Strip -->
    <div class="kpi-summary-strip">
        <div class="kpi-card">
            <div>
                <div class="kpi-label">TOTAL JOURNAL ENTRIES</div>
                <div class="kpi-number">{{ number_format($totalEntriesCount) }}</div>
            </div>
            <div class="z-icon-tile z-tone-primary">
                <i class="dripicons-align-justify u-fs-18px"></i>
            </div>
        </div>
        <div class="kpi-card">
            <div>
                <div class="kpi-label">PAGE DEBITS POSTED</div>
                <div class="kpi-number u-c-info">₹ {{ number_format($totalDebitSum, 2) }}</div>
            </div>
            <div class="z-icon-tile z-tone-info">
                <i class="dripicons-arrow-up u-fs-18px"></i>
            </div>
        </div>
        <div class="kpi-card">
            <div>
                <div class="kpi-label">PAGE CREDITS POSTED</div>
                <div class="kpi-number u-c-success">₹ {{ number_format($totalCreditSum, 2) }}</div>
            </div>
            <div class="z-icon-tile z-tone-success">
                <i class="dripicons-arrow-down u-fs-18px"></i>
            </div>
        </div>
        <div class="kpi-card">
            <div>
                <div class="kpi-label">STATUS &amp; INTEGRITY</div>
                <div class="kpi-number u-fs-14px u-fw-600 u-c-success">
                    <i class="dripicons-checkmark text-success"></i> 100% Balanced
                </div>
            </div>
            <div class="z-icon-tile z-tone-warning">
                <i class="dripicons-scale u-fs-18px"></i>
            </div>
        </div>
    </div>

    <!-- Filter Card -->
    <div class="desk-filter-card">
        <form action="{{ route('accounting.journal-entries') }}" method="GET" class="m-0">
            <div class="row align-items-end u-m-0-6px">
                <div class="col-md-3 u-p-0-6px">
                    <label for="journal-entries-field-1" class="font-weight-600 mb-1 u-fs-11px u-tt-uppercase u-c-text-muted">Reference Type</label>
                    <select id="journal-entries-field-1" name="reference_type" class="form-control form-control-sm u-h-32px u-fs-12px">
                        <option value="">-- All Types --</option>
                        <option value="sale" {{ request('reference_type') == 'sale' ? 'selected' : '' }}>Sale / Invoices</option>
                        <option value="purchase" {{ request('reference_type') == 'purchase' ? 'selected' : '' }}>Purchase bills</option>
                        <option value="payment" {{ request('reference_type') == 'payment' ? 'selected' : '' }}>Payments</option>
                        <option value="expense" {{ request('reference_type') == 'expense' ? 'selected' : '' }}>Expenses</option>
                        <option value="manual" {{ request('reference_type') == 'manual' ? 'selected' : '' }}>Manual Vouchers</option>
                        @foreach(['voucher' => 'Vouchers / Transfers', 'reversal' => 'Reversals', 'damage' => 'Damaged stock', 'stock_loss' => 'Stock losses', 'job_work_receipt' => 'Job work receipts', 'production' => 'Production', 'brokerage' => 'Brokerage', 'migration_opening' => 'Opening balances', 'year_close' => 'Year close'] as $source => $label)
                            <option value="{{ $source }}" @selected(request('reference_type') === $source)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 u-p-0-6px">
                    <label for="journal-entries-field-2" class="font-weight-600 mb-1 u-fs-11px u-tt-uppercase u-c-text-muted">Start Date</label>
                    <input id="journal-entries-field-2" type="date" name="start_date" class="form-control form-control-sm u-h-32px u-fs-12px" value="{{ request('start_date') }}">
                </div>
                <div class="col-md-3 u-p-0-6px">
                    <label for="journal-entries-field-3" class="font-weight-600 mb-1 u-fs-11px u-tt-uppercase u-c-text-muted">End Date</label>
                    <input id="journal-entries-field-3" type="date" name="end_date" class="form-control form-control-sm u-h-32px u-fs-12px" value="{{ request('end_date') }}">
                </div>
                <div class="col-md-3 d-flex u-p-0-6px">
                    <button type="submit" class="btn btn-sm btn-primary flex-grow-1 mr-2 u-h-32px u-fs-12px u-fw-600">
                        <i class="dripicons-search"></i> Filter
                    </button>
                    <a href="{{ route('accounting.journal-entries') }}" class="btn btn-sm btn-outline-secondary u-h-32px u-fs-12px u-d-inline-flex u-items-center">Reset</a>
                </div>
            </div>
        </form>
    </div>

    <!-- Journal Entries Table -->
    <div class="desk-card-wrapper">
        <div class="table-responsive">
            <table class="table journal-grid-table mb-0">
                <caption class="sr-only">Journal entries</caption>
                <thead>
                    <tr>
                        <th scope="col">Entry #</th>
                        <th scope="col">Date</th>
                        <th scope="col">Reference</th>
                        <th scope="col">Description</th>
                        <th scope="col" class="text-right">Total Debit</th>
                        <th scope="col" class="text-right">Total Credit</th>
                        <th scope="col">Status</th>
                        <th scope="col" class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($entries as $entry)
                        <tr>
                            <td class="font-weight-bold font-monospace text-primary">{{ $entry->entry_number }}</td>
                            <td>{{ $entry->entry_date->format('Y-m-d') }}</td>
                            <td>
                                <span class="badge badge-light border text-uppercase font-weight-600 u-fs-11px u-p-3px-6px u-r-4px">
                                    {{ $entry->reference_type }}
                                </span>
                                @if($entry->reference_no)
                                    <small class="text-muted d-block">{{ $entry->reference_no }}</small>
                                @endif
                            </td>
                            <td>{{ Str::limit($entry->description, 50) }}</td>
                            <td class="text-right font-weight-bold text-dark font-monospace">₹ {{ number_format((float)$entry->total_debit, 2) }}</td>
                            <td class="text-right font-weight-bold text-dark font-monospace">₹ {{ number_format((float)$entry->total_credit, 2) }}</td>
                            @php $isReversed = in_array($entry->id, $reversedIds); @endphp
                            <td>
                                @if($isReversed)
                                    <span class="badge badge-secondary u-fs-10_5px u-p-4px-8px u-r-4px">Reversed</span>
                                @else
                                    <span class="badge badge-success u-fs-10_5px u-p-4px-8px u-r-4px u-bg-success-soft u-c-success u-b-1px-solid-success-border">
                                        Posted
                                    </span>
                                @endif
                            </td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-outline-primary view-entry-btn u-h-26px u-w-26px u-p-0 u-d-inline-flex u-items-center u-justify-center u-r-4px" data-url="{{ route('accounting.journal-entries.show', ['id' => $entry->id, 'branch_scope' => request('branch_scope')]) }}" title="View Lines" aria-label="View lines for {{ $entry->entry_number }}">
                                    <i class="dripicons-preview"></i>
                                </button>
                                {{-- Posted journals are never edited or deleted; a manual one is cancelled by a reversing entry. Bill postings are corrected from the bill. --}}
                                @if($canReverseJournal && (int) $entry->branch_id === $journalContext->branchId && $entry->reference_type === 'manual' && !$entry->reversal_of_id && !$isReversed)
                                    <button type="button" class="btn btn-sm btn-outline-danger reverse-entry-btn u-h-26px u-w-26px u-p-0 u-d-inline-flex u-items-center u-justify-center u-r-4px" data-url="{{ route('accounting.journal.reverse', $entry->id) }}" data-number="{{ $entry->entry_number }}" data-date="{{ $entry->entry_date->format('Y-m-d') }}" title="Reverse" aria-label="Reverse {{ $entry->entry_number }}">
                                        <i class="dripicons-return"></i>
                                    </button>
                                @endif
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
            <div class="mt-3 d-flex justify-content-end">
                {{ $entries->links() }}
            </div>
        @endif
    </div>
</section>

<!-- Create Manual Voucher Modal -->
<div id="createVoucherModal" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="create-voucher-title" class="modal fade" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered" style="max-width: min(1080px, 96vw);">
        <div class="modal-content u-r-8px u-ov-hidden u-b-1px-solid-border-strong">
            <div class="modal-header u-bg-ink u-c-xffffff u-p-12px-18px">
                <h5 id="create-voucher-title" class="modal-title font-weight-bold u-fs-15px u-c-xffffff">
                    <i class="dripicons-pencil mr-1"></i> New Journal Entry Voucher
                </h5>
                <button type="button" class="close u-c-xffffff u-op-0_8" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <form action="{{ route('accounting.journal-entries.store') }}" method="POST" id="voucherForm">
                @csrf
                <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', (string) Illuminate\Support\Str::uuid()) }}">
                <div class="modal-body u-p-16px-20px">
                    <div class="form-row mb-3">
                        <div class="col-md-4">
                            <label for="journal-entries-field-4" class="font-weight-600 mb-1 u-fs-11_5px u-c-text-2">Date <span class="text-danger">*</span></label>
                            <input id="journal-entries-field-4" type="date" name="entry_date" class="form-control form-control-sm u-h-32px u-fs-12_5px" value="{{ date('Y-m-d') }}" required>
                        </div>
                        <div class="col-md-8">
                            <label for="journal-entries-field-5" class="font-weight-600 mb-1 u-fs-11_5px u-c-text-2">Description / Memo <span class="text-danger">*</span></label>
                            <input id="journal-entries-field-5" type="text" name="description" class="form-control form-control-sm u-h-32px u-fs-12_5px" placeholder="e.g. Month-end adjustment, Depreciation" required>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mb-2 mt-3">
                        <h6 class="font-weight-bold mb-0 u-fs-13px u-c-text">Journal Line Items</h6>
                        <button type="button" class="btn btn-sm btn-outline-success u-fs-11px u-h-26px u-p-0-10px" id="addLineBtn">
                            <i class="dripicons-plus mr-1"></i> Add Line
                        </button>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered table-sm mb-0 u-bcol-collapse" id="voucherLinesTable">
                <caption class="sr-only">Journal entries</caption>
                            <thead class="u-bg-surface-3">
                                <tr>
                                    <th scope="col" style="width: 36%; min-width: 220px;" class="u-fs-11px u-tt-uppercase">Account <span class="text-danger">*</span></th>
                                    <th scope="col" style="width: 28%; min-width: 160px;" class="u-fs-11px u-tt-uppercase">Line Memo</th>
                                    <th scope="col" style="width: 16%; min-width: 110px;" class="text-right u-fs-11px u-tt-uppercase">Debit (₹)</th>
                                    <th scope="col" style="width: 16%; min-width: 110px;" class="text-right u-fs-11px u-tt-uppercase">Credit (₹)</th>
                                    <th scope="col" style="width: 4%; min-width: 38px;" class="text-center"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr class="line-row">
                                    <td>
                                        <select aria-label="Journal account" name="items[0][chart_of_account_id]" class="form-control form-control-sm u-h-34px u-fs-12_5px" required>
                                            <option value="">-- Select Account --</option>
                                            @foreach($accounts as $acc)
                                                <option value="{{ $acc->id }}">{{ $acc->code }} - {{ $acc->name }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td><input aria-label="Line memo" type="text" name="items[0][memo]" class="form-control form-control-sm u-h-34px u-fs-12_5px" style="padding: 4px 10px;" placeholder="Optional line memo"></td>
                                    <td><input aria-label="Debit amount" type="number" step="0.01" min="0" name="items[0][debit]" class="form-control form-control-sm text-right debit-input u-h-34px u-fs-12_5px font-monospace" style="padding: 4px 10px;" value="0.00"></td>
                                    <td><input aria-label="Credit amount" type="number" step="0.01" min="0" name="items[0][credit]" class="form-control form-control-sm text-right credit-input u-h-34px u-fs-12_5px font-monospace" style="padding: 4px 10px;" value="0.00"></td>
                                    <td class="text-center align-middle"><button type="button" aria-label="Remove journal line" class="btn btn-sm btn-link text-danger remove-line-btn p-0 font-weight-bold u-fs-18px" title="Remove line">&times;</button></td>
                                </tr>
                                <tr class="line-row">
                                    <td>
                                        <select aria-label="Journal account" name="items[1][chart_of_account_id]" class="form-control form-control-sm u-h-34px u-fs-12_5px" required>
                                            <option value="">-- Select Account --</option>
                                            @foreach($accounts as $acc)
                                                <option value="{{ $acc->id }}">{{ $acc->code }} - {{ $acc->name }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td><input aria-label="Line memo" type="text" name="items[1][memo]" class="form-control form-control-sm u-h-34px u-fs-12_5px" style="padding: 4px 10px;" placeholder="Optional line memo"></td>
                                    <td><input aria-label="Debit amount" type="number" step="0.01" min="0" name="items[1][debit]" class="form-control form-control-sm text-right debit-input u-h-34px u-fs-12_5px font-monospace" style="padding: 4px 10px;" value="0.00"></td>
                                    <td><input aria-label="Credit amount" type="number" step="0.01" min="0" name="items[1][credit]" class="form-control form-control-sm text-right credit-input u-h-34px u-fs-12_5px font-monospace" style="padding: 4px 10px;" value="0.00"></td>
                                    <td class="text-center align-middle"><button type="button" aria-label="Remove journal line" class="btn btn-sm btn-link text-danger remove-line-btn p-0 font-weight-bold u-fs-18px" title="Remove line">&times;</button></td>
                                </tr>
                            </tbody>
                            <tfoot class="font-weight-bold u-bg-surface-2">
                                <tr>
                                    <td colspan="2" class="text-right u-fs-11_5px">Total:</td>
                                    <td class="text-right font-monospace u-fs-12_5px" id="totalDebitCell">0.00</td>
                                    <td class="text-right font-monospace u-fs-12_5px" id="totalCreditCell">0.00</td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td colspan="2" class="text-right u-fs-11_5px">Balance Check:</td>
                                    <td colspan="2" class="text-center" id="balanceStatusCell">
                                        <span class="badge badge-secondary u-p-4px-8px">Awaiting amounts</span>
                                    </td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
                <div class="modal-footer border-0 p-3 u-bg-surface-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm font-weight-bold u-bg-primary u-bc-primary" id="submitVoucherBtn" disabled>Post Journal Entry</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Entry Modal -->
<div id="viewEntryModal" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="viewModalTitle" class="modal fade" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered" style="max-width: min(1080px, 96vw);">
        <div class="modal-content u-r-8px u-ov-hidden u-b-1px-solid-border-strong">
            <div class="modal-header u-bg-ink u-c-xffffff u-p-12px-18px">
                <div>
                    <h5 class="modal-title font-weight-bold mb-0 u-fs-15px u-c-xffffff" id="viewModalTitle">Journal Entry</h5>
                    <small class="text-light opacity-75 u-fs-11px" id="viewModalSubtitle"></small>
                </div>
                <button type="button" class="close u-c-xffffff u-op-0_8" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body u-p-16px-20px">
                <table class="table table-bordered table-sm mb-0" id="entryDetailTable">
                <caption class="sr-only">Journal entries</caption>
                    <thead class="u-bg-ink u-c-xffffff">
                        <tr>
                            <th scope="col" class="u-fs-11px u-tt-uppercase">Account</th>
                            <th scope="col" class="u-fs-11px u-tt-uppercase">Memo</th>
                            <th scope="col" class="text-right u-fs-11px u-tt-uppercase">Debit</th>
                            <th scope="col" class="text-right u-fs-11px u-tt-uppercase">Credit</th>
                        </tr>
                    </thead>
                    <tbody id="entryDetailBody"></tbody>
                    <tfoot class="font-weight-bold u-bg-surface-2">
                        <tr>
                            <td colspan="2" class="text-right">Total:</td>
                            <td class="text-right font-monospace" id="viewModalTotalDebit"></td>
                            <td class="text-right font-monospace" id="viewModalTotalCredit"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Reverse Journal Modal -->
<div id="reverseEntryModal" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="reverse-voucher-title" class="modal fade" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content u-r-8px u-ov-hidden u-b-1px-solid-border-strong">
            <div class="modal-header u-bg-ink u-c-xffffff u-p-12px-18px">
                <h5 id="reverse-voucher-title" class="modal-title font-weight-bold u-fs-15px u-c-xffffff">
                    <i class="dripicons-return mr-1"></i> Reverse Journal <span id="reverse-entry-number" class="font-monospace"></span>
                </h5>
                <button type="button" class="close u-c-xffffff u-op-0_8" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <form id="reverseEntryForm" method="POST">
                @csrf
                <div class="modal-body u-p-16px-20px">
                    <p class="text-muted mb-2 u-fs-12px">Posts an equal and opposite entry; the original stays in the books for audit.</p>
                    <div class="form-group mb-2">
                        <label for="journal-entries-field-6" class="font-weight-600 mb-1 u-fs-11_5px u-c-text-2">Reversal Date <span class="text-danger">*</span></label>
                        <input id="journal-entries-field-6" type="date" name="entry_date" class="form-control form-control-sm u-h-32px u-fs-12_5px" required>
                    </div>
                    <div class="form-group mb-0">
                        <label for="journal-entries-field-7" class="font-weight-600 mb-1 u-fs-11_5px u-c-text-2">Reason <span class="text-danger">*</span></label>
                        <textarea id="journal-entries-field-7" name="reason" class="form-control form-control-sm u-fs-12_5px" rows="2" maxlength="500" required></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 p-3 u-bg-surface-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger btn-sm font-weight-bold">Post Reversal</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script type="text/javascript">
    $(document).on('click', '.reverse-entry-btn', function() {
        var $btn = $(this), $form = $('#reverseEntryForm'), today = new Date().toISOString().slice(0, 10);
        $form.attr('action', $btn.attr('data-url'));
        $form.find('[name=entry_date]').attr('min', $btn.attr('data-date')).val(today < $btn.attr('data-date') ? $btn.attr('data-date') : today);
        $form.find('[name=reason]').val('');
        $('#reverse-entry-number').text($btn.attr('data-number'));
        $('#reverseEntryModal').modal('show');
    });

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
            $('#balanceStatusCell').html('<span class="badge badge-success u-bg-success-soft u-c-success u-b-1px-solid-success-border u-p-4px-8px">Balanced (0.00)</span>');
            $('#submitVoucherBtn').prop('disabled', false);
        } else if (totalDebit === 0 && totalCredit === 0) {
            $('#balanceStatusCell').html('<span class="badge badge-secondary u-p-4px-8px">Awaiting amounts</span>');
            $('#submitVoucherBtn').prop('disabled', true);
        } else {
            $('#balanceStatusCell').html('<span class="badge badge-danger u-bg-danger-soft u-c-danger u-b-1px-solid-danger-border u-p-4px-8px">Out of Balance (Diff: ' + diff.toFixed(2) + ')</span>');
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
                    <select aria-label="Journal account" name="items[${lineIndex}][chart_of_account_id]" class="form-control form-control-sm u-h-34px u-fs-12_5px" required>
                        ${accountsOptions}
                    </select>
                </td>
                <td><input aria-label="Line memo" type="text" name="items[${lineIndex}][memo]" class="form-control form-control-sm u-h-34px u-fs-12_5px" style="padding: 4px 10px;" placeholder="Optional line memo"></td>
                <td><input aria-label="Debit amount" type="number" step="0.01" min="0" name="items[${lineIndex}][debit]" class="form-control form-control-sm text-right debit-input u-h-34px u-fs-12_5px font-monospace" style="padding: 4px 10px;" value="0.00"></td>
                <td><input aria-label="Credit amount" type="number" step="0.01" min="0" name="items[${lineIndex}][credit]" class="form-control form-control-sm text-right credit-input u-h-34px u-fs-12_5px font-monospace" style="padding: 4px 10px;" value="0.00"></td>
                <td class="text-center align-middle"><button type="button" aria-label="Remove journal line" class="btn btn-sm btn-link text-danger remove-line-btn p-0 font-weight-bold u-fs-18px" title="Remove line">&times;</button></td>
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
        const trigger = $(this).prop('disabled', true);
        $.get(trigger.data('url'), function(entry) {
            $('#viewModalTitle').text(entry.entry_number + ' - ' + (entry.description || 'Journal Entry'));
            $('#viewModalSubtitle').text('Date: ' + entry.entry_date + ' | Ref: ' + (entry.reference_type || 'Manual') + ' ' + (entry.reference_no || ''));

            const body = $('#entryDetailBody').empty();
            entry.items.forEach(function(item) {
                $('<tr>').append(
                    $('<td class="font-weight-600">').text(item.account ? item.account.code + ' - ' + item.account.name : 'N/A'),
                    $('<td>').text(item.memo || ''),
                    $('<td class="text-right font-monospace">').text(Number(item.debit) > 0 ? Number(item.debit).toFixed(2) : '-'),
                    $('<td class="text-right font-monospace">').text(Number(item.credit) > 0 ? Number(item.credit).toFixed(2) : '-')
                ).appendTo(body);
            });

            $('#viewModalTotalDebit').text(parseFloat(entry.total_debit).toFixed(2));
            $('#viewModalTotalCredit').text(parseFloat(entry.total_credit).toFixed(2));

            $('#viewEntryModal').modal('show');
        }).fail(function() {
            $('#viewModalTitle').text('Journal lines unavailable');
            $('#viewModalSubtitle').text('Refresh the journal list and try again.');
            $('#entryDetailBody, #viewModalTotalDebit, #viewModalTotalCredit').empty();
            $('#viewEntryModal').modal('show');
        }).always(function() {
            trigger.prop('disabled', false);
        });
    });
</script>
@endpush

@push('css')
    <link rel="stylesheet" href="<?php echo asset('css/commercial-entry.css') . '?v=' . (file_exists(public_path('css/commercial-entry.css')) ? filemtime(public_path('css/commercial-entry.css')) : time()) ?>" type="text/css">
    <link rel="stylesheet" href="<?php echo asset('css/commercial-workspace.css') . '?v=' . (file_exists(public_path('css/commercial-workspace.css')) ? filemtime(public_path('css/commercial-workspace.css')) : time()) ?>" type="text/css">
    <link rel="stylesheet" href="<?php echo asset('css/zolo-erp-neo.css') . '?v=' . (file_exists(public_path('css/zolo-erp-neo.css')) ? filemtime(public_path('css/zolo-erp-neo.css')) : time()) ?>" type="text/css">
    <link rel="stylesheet" href="{{ versioned_asset('css/pages/accounting-journal-entries.css') }}">
@endpush
