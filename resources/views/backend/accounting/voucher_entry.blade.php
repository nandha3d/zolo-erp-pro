@extends('backend.layout.main')

@section('content')
<section class="voucher-entry-workspace">
    <x-accounting-command-bar active="voucher-entry" title="Express Voucher Entry" purpose="Enter debit and credit lines. Use Voucher Hub to allocate payments to bills or start a guided transfer.">
        @if((new \App\Services\Platform\NavigationAccess($role_has_permissions_list ?? null))->allows('accounting.reports.view'))
            <a class="btn btn-outline-light" href="{{ route('accounting.vouchers') }}">Voucher Hub</a>
            <a class="btn btn-outline-light" href="{{ route('accounting.vouchers', ['voucher_type' => 'contra']) }}">Money Transfer</a>
        @endif
    </x-accounting-command-bar>

    <!-- Alert Messages -->
    <div id="voucherAlertArea"></div>

    <form id="voucherEntryForm" method="POST" action="{{ route('accounting.journal-entries.store') }}">
        @csrf
        <input type="hidden" name="idempotency_key" id="idempotencyKey" value="{{ (string) Illuminate\Support\Str::uuid() }}">
        <input type="hidden" name="reference_type" value="manual">
        <input type="hidden" name="voucher_type" id="hiddenVoucherType" value="journal">

        <div class="desk-card-wrapper">
            <details class="voucher-shortcuts">
                <summary>Keyboard shortcuts</summary>
                <ul class="list-unstyled mb-0">
                    <li><kbd>F4</kbd> Contra</li>
                    <li><kbd>F5</kbd> Payment</li>
                    <li><kbd>F6</kbd> Receipt</li>
                    <li><kbd>F7</kbd> Journal</li>
                    <li><kbd>F10</kbd> Memo</li>
                    <li><kbd>Alt+C</kbd> New ledger</li>
                    <li><kbd>Ctrl+F9</kbd> New series</li>
                    <li><kbd>Ctrl+Enter</kbd> Add line</li>
                    <li><kbd>Alt+S</kbd> Save voucher</li>
                </ul>
            </details>
            <h2 class="voucher-section-title">Voucher details</h2>
            <div class="voucher-type-options" role="group" aria-label="Voucher type">
                <button type="button" class="btn btn-sm btn-outline-primary btn-accounting-text vch-type-btn" data-type="contra" data-key="F4" aria-pressed="false">F4 · Contra</button>
                <button type="button" class="btn btn-sm btn-outline-primary btn-accounting-text vch-type-btn" data-type="payment" data-key="F5" aria-pressed="false">F5 · Payment</button>
                <button type="button" class="btn btn-sm btn-outline-primary btn-accounting-text vch-type-btn" data-type="receipt" data-key="F6" aria-pressed="false">F6 · Receipt</button>
                <button type="button" class="btn btn-sm btn-outline-primary btn-accounting-text vch-type-btn active" data-type="journal" data-key="F7" aria-pressed="true">F7 · Journal</button>
                <button type="button" class="btn btn-sm btn-outline-primary btn-accounting-text vch-type-btn" data-type="memo" data-key="F10" aria-pressed="false">F10 · Memo</button>
            </div>

            <div class="voucher-detail-fields">
                <div>
                    <label for="seriesSelect">Voucher series</label>
                    <div class="voucher-inline-field">
                        <select name="series_code" id="seriesSelect" class="custom-select-native form-control form-control-sm">
                            <option value="">Default series</option>
                            @foreach($series as $ser)
                                <option value="{{ $ser->code }}" @selected($ser->is_default)>
                                    {{ $ser->code }} ({{ $ser->prefix }}...{{ $ser->suffix }})
                                </option>
                            @endforeach
                        </select>
                        <button type="button" class="btn btn-sm btn-outline-primary btn-accounting-text" id="btnInlineSeries" title="New series (Ctrl+F9)" aria-label="New voucher series">+</button>
                    </div>
                </div>

                <div>
                    <label for="voucherNoDisplay">Voucher number</label>
                    <input type="text" id="voucherNoDisplay" class="form-control form-control-sm" value="AUTO" readonly>
                </div>

                <div>
                    <label for="entryDate">Date <span class="text-muted font-weight-normal" id="dayOfWeekBadge"></span></label>
                    <input type="date" name="entry_date" id="entryDate" class="form-control form-control-sm" value="{{ $businessDate }}" required>
                </div>

                <div>
                    <label for="gstNature">GST nature</label>
                    <select name="gst_nature" id="gstNature" class="custom-select-native form-control form-control-sm">
                        <option value="">Not applicable</option>
                        <option value="Taxable">Taxable</option>
                        <option value="Exempted">Exempted</option>
                        <option value="Nil Rated">Nil Rated</option>
                        <option value="Non-GST">Non-GST</option>
                        <option value="Reverse Charge">Reverse Charge (RCM)</option>
                    </select>
                </div>
                <div id="chequeNoCol">
                    <label for="chequeNo">Cheque / reference no.</label>
                    <input type="text" name="cheque_no" id="chequeNo" class="form-control form-control-sm" placeholder="e.g. CHQ-990812">
                </div>
                <div id="chequeDateCol">
                    <label for="chequeDate">Cheque date</label>
                    <input type="date" name="cheque_date" id="chequeDate" class="form-control form-control-sm">
                </div>
            </div>
            <div class="voucher-entry-options">
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="checkbox" id="carryVoucher" value="1">
                    <label class="form-check-label small font-weight-bold" for="carryVoucher">Carry Voucher</label>
                </div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="checkbox" id="multiEntry" value="1" checked>
                    <label class="form-check-label small font-weight-bold" for="multiEntry">Multi Entry</label>
                </div>
            </div>
        </div>

        <div class="desk-card-wrapper">
            <div class="voucher-section-bar">
                <h2 class="voucher-section-title" id="voucherLinesTitle">Voucher lines</h2>
                <button type="button" class="btn btn-sm btn-outline-primary btn-accounting-text" id="btnInlineAccount" title="New ledger (Alt+C)">+ New ledger</button>
            </div>
            <div class="table-responsive voucher-entry-lines" tabindex="0" role="region" aria-labelledby="voucherLinesTitle">
                <table class="table mb-0" id="voucherTable">
                    <thead>
                        <tr>
                            <th scope="col" class="text-center">Type</th>
                            <th scope="col">Ledger account</th>
                            <th scope="col">Customer / supplier</th>
                            <th scope="col" class="text-right">Debit (₹)</th>
                            <th scope="col" class="text-right">Credit (₹)</th>
                            <th scope="col" class="text-center"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody id="voucherTableBody">
                        <!-- Row 0: By default Dr -->
                        <tr class="voucher-row" data-index="0">
                            <td class="text-center">
                                <select class="custom-select-native form-control form-control-sm row-type" aria-label="Debit or credit" data-index="0">
                                    <option value="Dr" selected>Dr</option>
                                    <option value="Cr">Cr</option>
                                </select>
                            </td>
                            <td>
                                <select name="items[0][chart_of_account_id]" class="custom-select-native form-control form-control-sm font-weight-bold account-select" aria-label="Ledger account" data-index="0" required>
                                    <option value="">Select a ledger</option>
                                    @foreach($accounts as $acc)
                                        <option value="{{ $acc->id }}" data-type="{{ $acc->type }}" data-control="{{ $acc->control_type }}" data-bal="{{ $acc->formatted_balance }}">
                                            {{ $acc->code }} - {{ $acc->name }} ({{ $acc->formatted_balance }})
                                        </option>
                                    @endforeach
                                </select>
                                <div class="account-balance-preview mt-1 small" id="balPreview_0"></div>
                            </td>
                            <td>
                                <div class="partner-selector-wrap" id="partnerWrap_0">
                                    <select name="items[0][partner_id]" class="custom-select-native form-control form-control-sm partner-select" aria-label="Customer or supplier" data-index="0" disabled>
                                        <option value="">No customer or supplier required</option>
                                    </select>
                                    <input type="hidden" name="items[0][partner_type]" class="partner-type" id="partnerType_0" value="">
                                </div>
                            </td>
                            <td>
                                <input type="number" step="0.01" name="items[0][debit]" class="form-control form-control-sm text-right font-weight-bold debit-input" aria-label="Debit amount" data-index="0" value="0.00">
                            </td>
                            <td>
                                <input type="number" step="0.01" name="items[0][credit]" class="form-control form-control-sm text-right font-weight-bold credit-input" aria-label="Credit amount" data-index="0" value="0.00" readonly>
                            </td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-link text-danger btn-accounting-text remove-row-btn" aria-label="Remove line" title="Remove line" disabled>&times;</button>
                            </td>
                        </tr>

                        <!-- Row 1: By default Cr -->
                        <tr class="voucher-row" data-index="1">
                            <td class="text-center">
                                <select class="custom-select-native form-control form-control-sm row-type" aria-label="Debit or credit" data-index="1">
                                    <option value="Dr">Dr</option>
                                    <option value="Cr" selected>Cr</option>
                                </select>
                            </td>
                            <td>
                                <select name="items[1][chart_of_account_id]" class="custom-select-native form-control form-control-sm font-weight-bold account-select" aria-label="Ledger account" data-index="1" required>
                                    <option value="">Select a ledger</option>
                                    @foreach($accounts as $acc)
                                        <option value="{{ $acc->id }}" data-type="{{ $acc->type }}" data-control="{{ $acc->control_type }}" data-bal="{{ $acc->formatted_balance }}">
                                            {{ $acc->code }} - {{ $acc->name }} ({{ $acc->formatted_balance }})
                                        </option>
                                    @endforeach
                                </select>
                                <div class="account-balance-preview mt-1 small" id="balPreview_1"></div>
                            </td>
                            <td>
                                <div class="partner-selector-wrap" id="partnerWrap_1">
                                    <select name="items[1][partner_id]" class="custom-select-native form-control form-control-sm partner-select" aria-label="Customer or supplier" data-index="1" disabled>
                                        <option value="">No customer or supplier required</option>
                                    </select>
                                    <input type="hidden" name="items[1][partner_type]" class="partner-type" id="partnerType_1" value="">
                                </div>
                            </td>
                            <td>
                                <input type="number" step="0.01" name="items[1][debit]" class="form-control form-control-sm text-right font-weight-bold debit-input" aria-label="Debit amount" data-index="1" value="0.00" readonly>
                            </td>
                            <td>
                                <input type="number" step="0.01" name="items[1][credit]" class="form-control form-control-sm text-right font-weight-bold credit-input" aria-label="Credit amount" data-index="1" value="0.00">
                            </td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-link text-danger btn-accounting-text remove-row-btn" aria-label="Remove line" title="Remove line" disabled>&times;</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="voucher-lines-footer">
                <button type="button" class="btn btn-sm btn-outline-primary btn-accounting-text" id="btnAddRow" title="Ctrl+Enter">
                    + Add line
                </button>
                <small class="text-muted">Tab out of an amount to suggest a balancing amount on the next line.</small>
            </div>
        </div>

        <div class="desk-card-wrapper">
            <div class="voucher-review">
                <div>
                    <label for="voucherDescription" class="voucher-section-title">Narration <span aria-hidden="true">*</span></label>
                    <textarea name="description" id="voucherDescription" rows="2" class="form-control form-control-sm" placeholder="e.g. Payment for freight charges" required></textarea>
                    <div class="voucher-remark-picker">
                        <label for="standardRemarkSelect">Quick remark</label>
                        <div class="voucher-inline-field">
                            <select id="standardRemarkSelect" class="custom-select-native form-control form-control-sm">
                                <option value="">Insert a saved remark</option>
                                @foreach($remarks as $rem)
                                    <option value="{{ $rem->remark }}">{{ $rem->title }}</option>
                                @endforeach
                            </select>
                            <button type="button" class="btn btn-sm btn-outline-primary btn-accounting-text" id="btnInlineRemark" title="New remark" aria-label="New remark">+</button>
                        </div>
                    </div>
                </div>

                <div class="voucher-balance">
                    <div class="voucher-section-bar">
                        <h2 class="voucher-section-title">Balance summary</h2>
                        <span class="badge badge-neutral" id="balancedBadge" role="status">Awaiting amounts</span>
                    </div>
                    <dl class="voucher-balance-list mb-0">
                        <div><dt>Total debit</dt><dd id="totalDebitDisplay">₹0.00</dd></div>
                        <div><dt>Total credit</dt><dd id="totalCreditDisplay">₹0.00</dd></div>
                        <div id="diffRow"><dt>Difference</dt><dd id="diffDisplay">₹0.00</dd></div>
                    </dl>
                </div>
            </div>
            <div class="voucher-entry-actions d-flex align-items-center justify-content-end">
                <span id="saveHintText" class="small text-muted mr-3" role="status" aria-live="polite">Awaiting amounts</span>
                <button type="button" class="btn btn-outline-secondary btn-accounting-text mr-2" id="btnResetForm">Clear form</button>
                <button type="submit" class="btn btn-primary btn-accounting-text" id="btnSaveVoucher" title="Enter debit and credit amounts to balance the voucher" disabled>Save voucher</button>
            </div>
        </div>
    </form>
</section>

<!-- ================= INLINE MODAL 1: CREATE LEDGER ACCOUNT (Alt+C) ================= -->
<div class="modal fade" id="modalInlineAccount" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create Ledger Account (Alt+C)</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">&times;</button>
            </div>
            <form id="formInlineAccount">
                @csrf
                <div class="modal-body p-3">
                    <div class="form-group mb-2">
                        <label class="small font-weight-bold mb-1">Account Code *</label>
                        <input type="text" name="code" class="form-control form-control-sm" placeholder="e.g. 5020 or PETTY-CASH" required>
                    </div>
                    <div class="form-group mb-2">
                        <label class="small font-weight-bold mb-1">Account Name *</label>
                        <input type="text" name="name" class="form-control form-control-sm" placeholder="e.g. Office Stationery Expense" required>
                    </div>
                    <div class="form-row mb-2">
                        <div class="col-6">
                            <label class="small font-weight-bold mb-1">Account Type *</label>
                            <select name="type" id="inlineAccountType" class="custom-select-native form-control form-control-sm" required>
                                <option value="expense">Expense</option>
                                <option value="revenue">Revenue / Income</option>
                                <option value="asset">Asset</option>
                                <option value="liability">Liability</option>
                                <option value="equity">Equity</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="small font-weight-bold mb-1">Sub-Type *</label>
                            <input type="text" name="sub_type" class="form-control form-control-sm" value="operating_expense" required>
                        </div>
                    </div>
                    <div class="form-group mb-2">
                        <label class="small font-weight-bold mb-1">Control / Role</label>
                        <select name="control_type" class="custom-select-native form-control form-control-sm">
                            <option value="none">General Account</option>
                            <option value="cash">Cash Account</option>
                            <option value="bank">Bank Account</option>
                            <option value="ar">Accounts Receivable (Customer)</option>
                            <option value="ap">Accounts Payable (Supplier)</option>
                        </select>
                    </div>
                    <div class="form-group mb-0">
                        <label class="small font-weight-bold mb-1">Opening Balance (Rs.)</label>
                        <input type="number" step="0.01" name="opening_balance" class="form-control form-control-sm" value="0.00">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary">Save Ledger</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ================= INLINE MODAL 2: CREATE VOUCHER SERIES ================= -->
<div class="modal fade" id="modalInlineSeries" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create Voucher Series</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">&times;</button>
            </div>
            <form id="formInlineSeries">
                @csrf
                <div class="modal-body p-3">
                    <div class="form-group mb-2">
                        <label class="small font-weight-bold mb-1">Series Code / Name *</label>
                        <input type="text" name="code" class="form-control form-control-sm" placeholder="e.g. BANK-PAYMENT" required>
                    </div>
                    <div class="form-row mb-2">
                        <div class="col-6">
                            <label class="small font-weight-bold mb-1">Prefix</label>
                            <input type="text" name="prefix" class="form-control form-control-sm" placeholder="e.g. BP-">
                        </div>
                        <div class="col-6">
                            <label class="small font-weight-bold mb-1">Suffix</label>
                            <input type="text" name="suffix" class="form-control form-control-sm" placeholder="e.g. /26-27">
                        </div>
                    </div>
                    <div class="form-row mb-0">
                        <div class="col-6">
                            <label class="small font-weight-bold mb-1">Next Number *</label>
                            <input type="number" name="next_number" class="form-control form-control-sm" value="1" min="1" required>
                        </div>
                        <div class="col-6">
                            <label class="small font-weight-bold mb-1">Padding (Digits)</label>
                            <input type="number" name="padding" class="form-control form-control-sm" value="5" min="1" max="18">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary">Save Series</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ================= INLINE MODAL 3: CREATE STANDARD REMARK ================= -->
<div class="modal fade" id="modalInlineRemark" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create Standard Remark</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">&times;</button>
            </div>
            <form id="formInlineRemark">
                @csrf
                <div class="modal-body p-3">
                    <div class="form-group mb-2">
                        <label class="small font-weight-bold mb-1">Title / Short Name *</label>
                        <input type="text" name="title" class="form-control form-control-sm" placeholder="e.g. Cash Expense" required>
                    </div>
                    <div class="form-group mb-0">
                        <label class="small font-weight-bold mb-1">Remark Text *</label>
                        <textarea name="remark" rows="2" class="form-control form-control-sm" placeholder="e.g. Being cash paid towards office expenses" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary">Save Remark</button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
(function() {
    'use strict';

    // In-memory customer and supplier data strictly from DB
    const customers = @json($customers);
    const suppliers = @json($suppliers);
    const formatAmount = new Intl.NumberFormat('en-IN', {style: 'currency', currency: 'INR'});

    let rowCount = 2;

    // Day of week calculator
    function updateDayOfWeek() {
        const dateVal = document.getElementById('entryDate').value;
        if (!dateVal) return;
        const d = new Date(dateVal + 'T00:00:00');
        const days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        document.getElementById('dayOfWeekBadge').textContent = days[d.getDay()];
    }
    document.getElementById('entryDate').addEventListener('change', updateDayOfWeek);
    updateDayOfWeek();

    // Voucher Type Tab Switching
    document.querySelectorAll('.vch-type-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.vch-type-btn').forEach(button => {
                button.classList.toggle('active', button === this);
                button.setAttribute('aria-pressed', button === this);
            });
            const type = this.dataset.type;
            document.getElementById('hiddenVoucherType').value = type;

            // Set suggested narration template
            const desc = document.getElementById('voucherDescription');
            if (!desc.value.trim() || desc.value.startsWith('Being ')) {
                if (type === 'payment') desc.value = 'Being payment made towards ';
                else if (type === 'receipt') desc.value = 'Being receipt received against ';
                else if (type === 'contra') desc.value = 'Being cash deposited/withdrawn from bank';
                else if (type === 'journal') desc.value = 'Being adjustment entry passed';
            }
            refreshNextVoucherNumber();
        });
    });

    // Refresh next voucher number preview
    function refreshNextVoucherNumber() {
        const seriesCode = document.getElementById('seriesSelect').value;
        fetch('{{ route("accounting.voucher.next-number") }}?series_code=' + encodeURIComponent(seriesCode))
            .then(res => res.json())
            .then(data => {
                document.getElementById('voucherNoDisplay').value = data.next_number || 'AUTO';
            })
            .catch(() => {
                document.getElementById('voucherNoDisplay').value = 'AUTO';
            });
    }
    document.getElementById('seriesSelect').addEventListener('change', refreshNextVoucherNumber);
    refreshNextVoucherNumber();

    // Row Dr/Cr toggle and field toggling
    function attachRowListeners(row) {
        const index = row.dataset.index;
        const typeSelect = row.querySelector('.row-type');
        const debitInput = row.querySelector('.debit-input');
        const creditInput = row.querySelector('.credit-input');
        const accountSelect = row.querySelector('.account-select');
        const balPreview = document.getElementById('balPreview_' + index);
        const partnerSelect = row.querySelector('.partner-select');
        const partnerTypeHidden = row.querySelector('.partner-type');

        typeSelect.addEventListener('change', function() {
            if (this.value === 'Dr') {
                debitInput.removeAttribute('readonly');
                creditInput.setAttribute('readonly', 'readonly');
                creditInput.value = '0.00';
            } else {
                creditInput.removeAttribute('readonly');
                debitInput.setAttribute('readonly', 'readonly');
                debitInput.value = '0.00';
            }
            recalculateTotals();
        });

        accountSelect.addEventListener('change', function() {
            const opt = this.options[this.selectedIndex];
            if (!opt || !opt.value) {
                balPreview.innerHTML = '';
                partnerSelect.innerHTML = '<option value="">No customer or supplier required</option>';
                partnerSelect.setAttribute('disabled', 'disabled');
                partnerTypeHidden.value = '';
                return;
            }

            const bal = opt.dataset.bal || '';
            const control = opt.dataset.control || '';
            balPreview.innerHTML = `<span class="badge badge-neutral">Bal: ${bal}</span>`;

            // Control account: Accounts Receivable (Customer) or Accounts Payable (Supplier)
            if (control === 'ar') {
                partnerSelect.removeAttribute('disabled');
                partnerSelect.setAttribute('required', 'required');
                partnerTypeHidden.value = 'customer';
                let html = '<option value="">-- Select Customer (Required) --</option>';
                customers.forEach(c => {
                    html += `<option value="${c.id}">${c.name} ${c.city ? '('+c.city+')' : ''}</option>`;
                });
                partnerSelect.innerHTML = html;
            } else if (control === 'ap') {
                partnerSelect.removeAttribute('disabled');
                partnerSelect.setAttribute('required', 'required');
                partnerTypeHidden.value = 'supplier';
                let html = '<option value="">-- Select Supplier (Required) --</option>';
                suppliers.forEach(s => {
                    html += `<option value="${s.id}">${s.name} ${s.city ? '('+s.city+')' : ''}</option>`;
                });
                partnerSelect.innerHTML = html;
            } else {
                partnerSelect.innerHTML = '<option value="">No customer or supplier required</option>';
                partnerSelect.setAttribute('disabled', 'disabled');
                partnerSelect.removeAttribute('required');
                partnerTypeHidden.value = '';
            }
        });

        debitInput.addEventListener('input', recalculateTotals);
        creditInput.addEventListener('input', recalculateTotals);

        // Auto-balance assist on blur
        debitInput.addEventListener('blur', function() {
            autoBalanceNextRow(index, 'debit');
        });
        creditInput.addEventListener('blur', function() {
            autoBalanceNextRow(index, 'credit');
        });

        const removeBtn = row.querySelector('.remove-row-btn');
        if (removeBtn) {
            removeBtn.addEventListener('click', function() {
                if (document.querySelectorAll('#voucherTableBody tr').length > 2) {
                    row.remove();
                    recalculateTotals();
                    updateRemoveButtons();
                }
            });
        }
    }

    function autoBalanceNextRow(currentIndex, side) {
        const rows = document.querySelectorAll('#voucherTableBody tr');
        let totalDr = 0, totalCr = 0;
        rows.forEach(r => {
            totalDr += parseFloat(r.querySelector('.debit-input').value) || 0;
            totalCr += parseFloat(r.querySelector('.credit-input').value) || 0;
        });

        const diff = Math.round((totalDr - totalCr) * 100) / 100;
        const nextIndex = parseInt(currentIndex) + 1;
        let targetRow = document.querySelector(`#voucherTableBody tr[data-index="${nextIndex}"]`);

        if (!targetRow && Math.abs(diff) > 0.001) {
            rows.forEach(r => {
                if (r.dataset.index !== String(currentIndex) && !targetRow) {
                    const dr = parseFloat(r.querySelector('.debit-input').value) || 0;
                    const cr = parseFloat(r.querySelector('.credit-input').value) || 0;
                    if (dr === 0 && cr === 0) {
                        targetRow = r;
                    }
                }
            });
        }

        if (targetRow && Math.abs(diff) > 0.001) {
            const nextTypeSelect = targetRow.querySelector('.row-type');
            const nextDebit = targetRow.querySelector('.debit-input');
            const nextCredit = targetRow.querySelector('.credit-input');

            if (diff > 0) { // More debit than credit, suggest Credit on next row
                nextTypeSelect.value = 'Cr';
                nextCredit.removeAttribute('readonly');
                nextDebit.setAttribute('readonly', 'readonly');
                nextDebit.value = '0.00';
                if ((parseFloat(nextCredit.value) || 0) === 0) {
                    nextCredit.value = diff.toFixed(2);
                }
            } else { // More credit than debit, suggest Debit on next row
                nextTypeSelect.value = 'Dr';
                nextDebit.removeAttribute('readonly');
                nextCredit.setAttribute('readonly', 'readonly');
                nextCredit.value = '0.00';
                if ((parseFloat(nextDebit.value) || 0) === 0) {
                    nextDebit.value = Math.abs(diff).toFixed(2);
                }
            }
            recalculateTotals();
        }
    }

    function recalculateTotals() {
        let totalDr = 0, totalCr = 0;
        document.querySelectorAll('#voucherTableBody tr').forEach(r => {
            totalDr += parseFloat(r.querySelector('.debit-input').value) || 0;
            totalCr += parseFloat(r.querySelector('.credit-input').value) || 0;
        });

        totalDr = Math.round(totalDr * 100) / 100;
        totalCr = Math.round(totalCr * 100) / 100;
        const diff = Math.round(Math.abs(totalDr - totalCr) * 100) / 100;

        document.getElementById('totalDebitDisplay').textContent = formatAmount.format(totalDr);
        document.getElementById('totalCreditDisplay').textContent = formatAmount.format(totalCr);
        document.getElementById('diffDisplay').textContent = formatAmount.format(diff);

        const badge = document.getElementById('balancedBadge');
        const diffRow = document.getElementById('diffRow');
        const btnSave = document.getElementById('btnSaveVoucher');
        const balanced = diff === 0 && totalDr > 0;
        const status = balanced ? 'Balanced' : diff > 0 ? 'Unbalanced' : 'Awaiting amounts';
        if (badge.textContent !== status) {
            badge.className = 'badge ' + (balanced ? 'badge-success' : diff > 0 ? 'badge-danger' : 'badge-neutral');
            badge.textContent = status;
        }
        diffRow.classList.toggle('text-danger', diff > 0);
        btnSave.disabled = !balanced;
        const saveHint = document.getElementById('saveHintText');
        if (saveHint) {
            if (balanced) {
                saveHint.textContent = 'Balanced — ready to save';
                saveHint.className = 'small text-success font-weight-600 mr-3';
                btnSave.title = 'Save voucher (Alt+S or Ctrl+Enter)';
            } else if (totalDr === 0 && totalCr === 0) {
                saveHint.textContent = 'Awaiting amounts';
                saveHint.className = 'small text-muted mr-3';
                btnSave.title = 'Enter debit and credit amounts to balance the voucher';
            } else {
                saveHint.textContent = 'Unbalanced (Diff: ' + formatAmount.format(diff) + ')';
                saveHint.className = 'small text-danger font-weight-600 mr-3';
                btnSave.title = 'Voucher is unbalanced. Total Debit (' + formatAmount.format(totalDr) + ') must equal Total Credit (' + formatAmount.format(totalCr) + '). Difference: ' + formatAmount.format(diff);
            }
        }
    }

    function updateRemoveButtons() {
        const rows = document.querySelectorAll('#voucherTableBody tr');
        rows.forEach(r => {
            const btn = r.querySelector('.remove-row-btn');
            if (rows.length > 2) {
                btn.removeAttribute('disabled');
            } else {
                btn.setAttribute('disabled', 'disabled');
            }
        });
    }

    // Attach to initial rows
    document.querySelectorAll('#voucherTableBody tr').forEach(attachRowListeners);
    updateRemoveButtons();

    // Add Row functionality
    document.getElementById('btnAddRow').addEventListener('click', function() {
        const tbody = document.getElementById('voucherTableBody');
        const newIndex = rowCount++;
        const tr = document.createElement('tr');
        tr.className = 'voucher-row';
        tr.dataset.index = newIndex;

        // Clone account options
        const firstSelect = document.querySelector('.account-select');
        const optionsHtml = firstSelect ? firstSelect.innerHTML : '';

        tr.innerHTML = `
            <td class="text-center">
                <select class="custom-select-native form-control form-control-sm row-type" aria-label="Debit or credit" data-index="${newIndex}">
                    <option value="Dr">Dr</option>
                    <option value="Cr" selected>Cr</option>
                </select>
            </td>
            <td>
                <select name="items[${newIndex}][chart_of_account_id]" class="custom-select-native form-control form-control-sm font-weight-bold account-select" aria-label="Ledger account" data-index="${newIndex}" required>
                    ${optionsHtml}
                </select>
                <div class="account-balance-preview mt-1 small" id="balPreview_${newIndex}"></div>
            </td>
            <td>
                <div class="partner-selector-wrap" id="partnerWrap_${newIndex}">
                    <select name="items[${newIndex}][partner_id]" class="custom-select-native form-control form-control-sm partner-select" aria-label="Customer or supplier" data-index="${newIndex}" disabled>
                        <option value="">No customer or supplier required</option>
                    </select>
                    <input type="hidden" name="items[${newIndex}][partner_type]" class="partner-type" id="partnerType_${newIndex}" value="">
                </div>
            </td>
            <td>
                <input type="number" step="0.01" name="items[${newIndex}][debit]" class="form-control form-control-sm text-right font-weight-bold debit-input" aria-label="Debit amount" data-index="${newIndex}" value="0.00" readonly>
            </td>
            <td>
                <input type="number" step="0.01" name="items[${newIndex}][credit]" class="form-control form-control-sm text-right font-weight-bold credit-input" aria-label="Credit amount" data-index="${newIndex}" value="0.00">
            </td>
            <td class="text-center">
                <button type="button" class="btn btn-sm btn-link text-danger btn-accounting-text remove-row-btn" aria-label="Remove line" title="Remove line">&times;</button>
            </td>
        `;
        tbody.appendChild(tr);
        attachRowListeners(tr);
        updateRemoveButtons();

        // Focus new account select
        tr.querySelector('.account-select').focus();
    });

    // Standard Remarks Quick-Fill
    document.getElementById('standardRemarkSelect').addEventListener('change', function() {
        if (this.value) {
            const desc = document.getElementById('voucherDescription');
            desc.value = (desc.value.trim() ? desc.value.trim() + ' - ' : '') + this.value;
            this.value = '';
        }
    });

    // Reset Form
    document.getElementById('btnResetForm').addEventListener('click', function() {
        if (confirm('Clear voucher entry form?')) {
            window.location.reload();
        }
    });

    // ================= INLINE MODAL HANDLERS =================

    // 1. Inline Ledger Account (Alt+C)
    document.getElementById('btnInlineAccount').addEventListener('click', () => $('#modalInlineAccount').modal('show'));
    document.getElementById('formInlineAccount').addEventListener('submit', function(e) {
        e.preventDefault();
        const data = Object.fromEntries(new FormData(this).entries());
        fetch('{{ route("accounting.voucher.inline-account") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
            },
            body: JSON.stringify(data),
        })
        .then(res => res.json())
        .then(json => {
            if (json.success) {
                const acc = json.data;
                const newOptHtml = `<option value="${acc.id}" data-type="${acc.type}" data-control="${acc.control_type || ''}" data-bal="${acc.formatted_balance}" selected>
                    ${acc.code} - ${acc.name} (${acc.formatted_balance})
                </option>`;
                document.querySelectorAll('.account-select').forEach(sel => {
                    sel.insertAdjacentHTML('beforeend', newOptHtml);
                });
                $('#modalInlineAccount').modal('hide');
                document.getElementById('formInlineAccount').reset();
                showAlert('success', 'Ledger [' + acc.name + '] created successfully.');
            } else {
                showAlert('danger', json.message || 'Error creating account.');
            }
        })
        .catch(err => showAlert('danger', 'Failed to create ledger: ' + err.message));
    });

    // 2. Inline Series
    document.getElementById('btnInlineSeries').addEventListener('click', () => $('#modalInlineSeries').modal('show'));
    document.getElementById('formInlineSeries').addEventListener('submit', function(e) {
        e.preventDefault();
        const data = Object.fromEntries(new FormData(this).entries());
        data.document_type = 'journal';
        fetch('{{ route("document-series.store") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
            },
            body: JSON.stringify(data),
        })
        .then(res => res.json())
        .then(json => {
            if (json.success || json.data) {
                const ser = json.data;
                const sel = document.getElementById('seriesSelect');
                const opt = document.createElement('option');
                opt.value = ser.code;
                opt.textContent = `${ser.code} (${ser.prefix || ''}...${ser.suffix || ''})`;
                opt.selected = true;
                sel.appendChild(opt);
                $('#modalInlineSeries').modal('hide');
                document.getElementById('formInlineSeries').reset();
                refreshNextVoucherNumber();
                showAlert('success', 'Series [' + ser.code + '] created successfully.');
            } else {
                showAlert('danger', json.message || 'Error creating series.');
            }
        })
        .catch(err => showAlert('danger', 'Failed to create series: ' + err.message));
    });

    // 3. Inline Remark
    document.getElementById('btnInlineRemark').addEventListener('click', () => $('#modalInlineRemark').modal('show'));
    document.getElementById('formInlineRemark').addEventListener('submit', function(e) {
        e.preventDefault();
        const data = Object.fromEntries(new FormData(this).entries());
        data.type = 'all';
        fetch('{{ route("standard-remark.store") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
            },
            body: JSON.stringify(data),
        })
        .then(res => res.json())
        .then(json => {
            if (json.success || json.data) {
                const rem = json.data;
                const sel = document.getElementById('standardRemarkSelect');
                const opt = document.createElement('option');
                opt.value = rem.remark;
                opt.textContent = rem.title;
                sel.appendChild(opt);
                $('#modalInlineRemark').modal('hide');
                document.getElementById('formInlineRemark').reset();
                showAlert('success', 'Remark [' + rem.title + '] created.');
            } else {
                showAlert('danger', json.message || 'Error creating remark.');
            }
        })
        .catch(err => showAlert('danger', 'Failed to create remark: ' + err.message));
    });

    // Submit Voucher Entry via AJAX
    document.getElementById('voucherEntryForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const btnSave = document.getElementById('btnSaveVoucher');
        btnSave.disabled = true;
        btnSave.innerHTML = '<span class="spinner-border spinner-border-sm mr-1"></span> Posting...';

        const formData = new FormData(this);

        fetch(this.action, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
            },
            body: formData,
        })
        .then(async res => {
            const data = await res.json();
            if (!res.ok) throw new Error(data.message || (data.errors ? Object.values(data.errors).flat().join(', ') : 'Error posting voucher.'));
            return data;
        })
        .then(data => {
            showAlert('success', data.message || 'Voucher posted successfully!');
            setTimeout(() => {
                window.location.reload();
            }, 1200);
        })
        .catch(err => {
            showAlert('danger', 'Posting Failed: ' + err.message);
            btnSave.disabled = false;
            btnSave.textContent = 'Save voucher';
        });
    });

    function showAlert(type, msg) {
        const area = document.getElementById('voucherAlertArea');
        area.innerHTML = `<div class="alert alert-${type} alert-dismissible fade show" role="alert">
            <strong>${type === 'success' ? 'Success!' : 'Notice:'}</strong> ${msg}
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>`;
        area.scrollIntoView({ behavior: 'smooth' });
    }

    // Keyboard Shortcuts
    document.addEventListener('keydown', function(e) {
        if (e.defaultPrevented || document.querySelector('dialog[open], .modal.show')) return;
        if (e.ctrlKey && e.key === 'Enter') {
            e.preventDefault();
            document.getElementById('btnAddRow').click();
            return;
        }
        if (e.ctrlKey && e.key === 'F9') {
            e.preventDefault();
            document.getElementById('btnInlineSeries').click();
            return;
        }
        // Alt+C: Create Ledger
        if (e.altKey && (e.key === 'c' || e.key === 'C')) {
            e.preventDefault();
            $('#modalInlineAccount').modal('show');
        }
        // Alt+S: Save Voucher
        if (e.altKey && (e.key === 's' || e.key === 'S')) {
            e.preventDefault();
            const btn = document.getElementById('btnSaveVoucher');
            if (!btn.disabled) btn.click();
        }
        // Function keys F4-F10
        if (e.key === 'F4') {
            e.preventDefault();
            document.querySelector('.vch-type-btn[data-key="F4"]').click();
        } else if (e.key === 'F5') {
            e.preventDefault();
            document.querySelector('.vch-type-btn[data-key="F5"]').click();
        } else if (e.key === 'F6') {
            e.preventDefault();
            document.querySelector('.vch-type-btn[data-key="F6"]').click();
        } else if (e.key === 'F7') {
            e.preventDefault();
            document.querySelector('.vch-type-btn[data-key="F7"]').click();
        } else if (e.key === 'F10') {
            e.preventDefault();
            document.querySelector('.vch-type-btn[data-key="F10"]').click();
        }
    });

})();
</script>
@endpush
@endsection

@push('css')
    <link rel="stylesheet" href="{{ versioned_asset('css/pages/accounting-voucher-entry.css') }}">
@endpush
