@extends('backend.layout.main')

@section('content')
<style>
    .optech-voucher-wrapper {
        background-color: #e8e4dc;
        min-height: calc(100vh - 80px);
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        color: #2c3e50;
    }
    .optech-shortcut-banner {
        background: #1e3932;
        color: #d1fae5;
        font-size: 11px;
        font-weight: 600;
        letter-spacing: 0.3px;
        padding: 5px 12px;
        border-radius: 4px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.15);
    }
    .optech-shortcut-banner kbd {
        background: #064e3b;
        color: #a7f3d0;
        border: 1px solid #047857;
        padding: 1px 5px;
        border-radius: 3px;
        font-family: inherit;
        font-weight: 700;
    }
    .optech-header-card {
        background: #fdfaf4;
        border: 1px solid #d4cebe;
        border-radius: 6px;
        box-shadow: inset 0 1px 1px rgba(255,255,255,0.8), 0 2px 4px rgba(0,0,0,0.06);
    }
    .optech-vch-tabs .btn {
        font-weight: 700;
        font-size: 12px;
        padding: 6px 14px;
        border-radius: 4px;
        border: 1px solid #cbd5e1;
        transition: all 0.15s ease;
    }
    .optech-vch-tabs .btn.active {
        background: #0f766e;
        color: #ffffff;
        border-color: #0f766e;
        box-shadow: 0 2px 6px rgba(15,118,110,0.4);
    }
    .optech-vch-tabs .btn:not(.active) {
        background: #ffffff;
        color: #334155;
    }
    .optech-grid-table {
        background: #ffffff;
        border: 1px solid #cbd5e1;
        border-collapse: separate;
        border-spacing: 0;
    }
    .optech-grid-table thead th {
        background: #0f766e;
        color: #ffffff;
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.4px;
        padding: 7px 10px;
        border: 1px solid #0d6b63;
    }
    .optech-grid-table tbody td {
        padding: 5px 8px;
        vertical-align: middle;
        border: 1px solid #e2e8f0;
    }
    .optech-grid-table tbody tr:hover {
        background-color: #f8fafc;
    }
    .optech-dr-cr-select {
        font-weight: 800;
        color: #1e293b;
        background-color: #e2e8f0;
        border: 1px solid #94a3b8;
        border-radius: 4px;
        font-size: 13px;
        width: 65px;
        text-align: center;
    }
    .optech-balance-badge {
        font-size: 11px;
        font-weight: 600;
        padding: 2px 6px;
        border-radius: 3px;
        display: inline-block;
        margin-top: 2px;
    }
    .optech-balance-dr {
        background: #fee2e2;
        color: #991b1b;
        border: 1px solid #fca5a5;
    }
    .optech-balance-cr {
        background: #e0e7ff;
        color: #3730a3;
        border: 1px solid #a5b4fc;
    }
    .optech-footer-card {
        background: #fdfaf4;
        border: 1px solid #d4cebe;
        border-radius: 6px;
    }
    .optech-total-box {
        background: #f8fafc;
        border: 2px solid #cbd5e1;
        border-radius: 6px;
        padding: 10px 16px;
    }
    .optech-total-box.balanced {
        border-color: #10b981;
        background-color: #ecfdf5;
    }
    .optech-total-box.unbalanced {
        border-color: #ef4444;
        background-color: #fef2f2;
    }
    .inline-add-btn {
        padding: 4px 8px;
        font-size: 12px;
        font-weight: bold;
        line-height: 1;
        background: #0f766e;
        color: white;
        border: none;
        border-radius: 3px;
    }
    .inline-add-btn:hover {
        background: #115e59;
        color: white;
    }
</style>

<div class="optech-voucher-wrapper p-3">
    <!-- Top Bar with Shortcuts matching Optech screen_05.jpg / screen_06.jpg -->
    <div class="d-flex justify-content-between align-items-center mb-2">
        <div>
            <h4 class="font-weight-bold text-dark mb-0 d-inline-block">
                <i class="dripicons-article text-teal mr-1"></i> Express Voucher Entry
            </h4>
            <span class="badge badge-secondary ml-2" id="vchModeBadge">JOURNAL</span>
        </div>
        <div class="optech-shortcut-banner d-none d-md-flex align-items-center">
            <span class="mr-3"><kbd>Alt+C</kbd> Create Ledger</span>
            <span class="mr-3"><kbd>Alt+S</kbd> Save Entry</span>
            <span class="mr-3"><kbd>Esc</kbd> Close</span>
            <span class="mr-3"><kbd>F4</kbd> Contra</span>
            <span class="mr-3"><kbd>F5</kbd> Payment</span>
            <span class="mr-3"><kbd>F6</kbd> Receipt</span>
            <span class="mr-3"><kbd>F7</kbd> Journal</span>
            <span><kbd>Ctl+F9</kbd> Series</span>
        </div>
    </div>

    <!-- Alert Messages -->
    <div id="voucherAlertArea"></div>

    <form id="voucherEntryForm" method="POST" action="{{ route('accounting.journal-entries.store') }}">
        @csrf
        <input type="hidden" name="idempotency_key" id="idempotencyKey" value="{{ (string) Illuminate\Support\Str::uuid() }}">
        <input type="hidden" name="reference_type" value="manual">
        <input type="hidden" name="voucher_type" id="hiddenVoucherType" value="journal">

        <!-- Header Card -->
        <div class="optech-header-card p-3 mb-3">
            <div class="row align-items-center mb-2">
                <!-- Voucher Type Tabs -->
                <div class="col-lg-6 col-md-12 mb-2 mb-lg-0">
                    <label class="font-weight-bold text-muted small text-uppercase mb-1">Voucher Type (F4 - F10)</label>
                    <div class="optech-vch-tabs d-flex flex-wrap" role="group">
                        <button type="button" class="btn btn-sm mr-1 mb-1 vch-type-btn" data-type="contra" data-key="F4">
                            F4 · Contra
                        </button>
                        <button type="button" class="btn btn-sm mr-1 mb-1 vch-type-btn" data-type="payment" data-key="F5">
                            F5 · Payment
                        </button>
                        <button type="button" class="btn btn-sm mr-1 mb-1 vch-type-btn" data-type="receipt" data-key="F6">
                            F6 · Receipt
                        </button>
                        <button type="button" class="btn btn-sm mr-1 mb-1 vch-type-btn active" data-type="journal" data-key="F7">
                            F7 · Journal
                        </button>
                        <button type="button" class="btn btn-sm mb-1 vch-type-btn" data-type="memo" data-key="F10">
                            F10 · Memo
                        </button>
                    </div>
                </div>

                <!-- Series & Voucher Number -->
                <div class="col-lg-3 col-md-6 mb-2 mb-lg-0">
                    <label class="font-weight-bold text-muted small text-uppercase mb-1 d-flex justify-content-between align-items-center">
                        <span>Voucher Series *</span>
                        <button type="button" class="inline-add-btn" id="btnInlineSeries" title="Add New Series">[+]</button>
                    </label>
                    <select name="series_code" id="seriesSelect" class="form-control form-control-sm font-weight-bold">
                        <option value="">-- Default Series --</option>
                        @foreach($series as $ser)
                            <option value="{{ $ser->code }}" data-type="{{ $ser->document_type }}" {{ $ser->is_default ? 'selected' : '' }}>
                                {{ $ser->code }} ({{ $ser->prefix }}...{{ $ser->suffix }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Voucher No & Date -->
                <div class="col-lg-3 col-md-6">
                    <div class="row">
                        <div class="col-6 pr-1">
                            <label class="font-weight-bold text-muted small text-uppercase mb-1">Vch No</label>
                            <input type="text" id="voucherNoDisplay" class="form-control form-control-sm font-weight-bold text-center bg-light" value="AUTO" readonly>
                        </div>
                        <div class="col-6 pl-1">
                            <label class="font-weight-bold text-muted small text-uppercase mb-1">
                                Date <span class="badge badge-light" id="dayOfWeekBadge"></span>
                            </label>
                            <input type="date" name="entry_date" id="entryDate" class="form-control form-control-sm font-weight-bold" value="{{ $businessDate }}" required>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Second Row: GST Nature and Cheque details -->
            <div class="row">
                <div class="col-md-3">
                    <label class="font-weight-bold text-muted small text-uppercase mb-1">GST Nature</label>
                    <select name="gst_nature" id="gstNature" class="form-control form-control-sm">
                        <option value="">-- Not Applicable --</option>
                        <option value="Taxable">Taxable</option>
                        <option value="Exempted">Exempted</option>
                        <option value="Nil Rated">Nil Rated</option>
                        <option value="Non-GST">Non-GST</option>
                        <option value="Reverse Charge">Reverse Charge (RCM)</option>
                    </select>
                </div>
                <div class="col-md-3" id="chequeNoCol">
                    <label class="font-weight-bold text-muted small text-uppercase mb-1">Cheque / Ref No</label>
                    <input type="text" name="cheque_no" id="chequeNo" class="form-control form-control-sm" placeholder="e.g. CHQ-990812">
                </div>
                <div class="col-md-3" id="chequeDateCol">
                    <label class="font-weight-bold text-muted small text-uppercase mb-1">Cheque Date</label>
                    <input type="date" name="cheque_date" id="chequeDate" class="form-control form-control-sm">
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <div class="form-check form-check-inline mb-2">
                        <input class="form-check-input" type="checkbox" id="carryVoucher" value="1">
                        <label class="form-check-label small font-weight-bold" for="carryVoucher">Carry Voucher</label>
                    </div>
                    <div class="form-check form-check-inline mb-2">
                        <input class="form-check-input" type="checkbox" id="multiEntry" value="1" checked>
                        <label class="form-check-label small font-weight-bold" for="multiEntry">Multi Entry</label>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Voucher Lines Grid -->
        <div class="card border-0 shadow-sm mb-3">
            <div class="table-responsive">
                <table class="table optech-grid-table mb-0" id="voucherTable">
                    <thead>
                        <tr>
                            <th style="width: 75px;" class="text-center">Type</th>
                            <th style="width: 38%;">
                                Particulars (Ledger Account) *
                                <button type="button" class="inline-add-btn ml-1" id="btnInlineAccount" title="Add Ledger (Alt+C)">[+]</button>
                            </th>
                            <th style="width: 22%;">
                                Partner / Sub-Ledger
                                <span class="small text-light font-weight-normal">(if AR/AP)</span>
                            </th>
                            <th style="width: 14%;" class="text-right">Debit (Rs.)</th>
                            <th style="width: 14%;" class="text-right">Credit (Rs.)</th>
                            <th style="width: 50px;" class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody id="voucherTableBody">
                        <!-- Row 0: By default Dr -->
                        <tr class="voucher-row" data-index="0">
                            <td class="text-center">
                                <select class="optech-dr-cr-select row-type" data-index="0">
                                    <option value="Dr" selected>Dr</option>
                                    <option value="Cr">Cr</option>
                                </select>
                            </td>
                            <td>
                                <select name="items[0][chart_of_account_id]" class="form-control form-control-sm font-weight-bold account-select" data-index="0" required>
                                    <option value="">-- Select Ledger Account --</option>
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
                                    <select name="items[0][partner_id]" class="form-control form-control-sm partner-select" data-index="0" disabled>
                                        <option value="">-- No Sub-Ledger Required --</option>
                                    </select>
                                    <input type="hidden" name="items[0][partner_type]" class="partner-type" id="partnerType_0" value="">
                                </div>
                            </td>
                            <td>
                                <input type="number" step="0.01" name="items[0][debit]" class="form-control form-control-sm text-right font-weight-bold debit-input" data-index="0" value="0.00">
                            </td>
                            <td>
                                <input type="number" step="0.01" name="items[0][credit]" class="form-control form-control-sm text-right font-weight-bold credit-input" data-index="0" value="0.00" readonly>
                            </td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-link text-danger p-0 remove-row-btn" title="Remove row" disabled>&times;</button>
                            </td>
                        </tr>

                        <!-- Row 1: By default Cr -->
                        <tr class="voucher-row" data-index="1">
                            <td class="text-center">
                                <select class="optech-dr-cr-select row-type" data-index="1">
                                    <option value="Dr">Dr</option>
                                    <option value="Cr" selected>Cr</option>
                                </select>
                            </td>
                            <td>
                                <select name="items[1][chart_of_account_id]" class="form-control form-control-sm font-weight-bold account-select" data-index="1" required>
                                    <option value="">-- Select Ledger Account --</option>
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
                                    <select name="items[1][partner_id]" class="form-control form-control-sm partner-select" data-index="1" disabled>
                                        <option value="">-- No Sub-Ledger Required --</option>
                                    </select>
                                    <input type="hidden" name="items[1][partner_type]" class="partner-type" id="partnerType_1" value="">
                                </div>
                            </td>
                            <td>
                                <input type="number" step="0.01" name="items[1][debit]" class="form-control form-control-sm text-right font-weight-bold debit-input" data-index="1" value="0.00" readonly>
                            </td>
                            <td>
                                <input type="number" step="0.01" name="items[1][credit]" class="form-control form-control-sm text-right font-weight-bold credit-input" data-index="1" value="0.00">
                            </td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-link text-danger p-0 remove-row-btn" title="Remove row" disabled>&times;</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="p-2 bg-light border-top d-flex justify-content-between align-items-center">
                <button type="button" class="btn btn-sm btn-outline-primary font-weight-bold" id="btnAddRow">
                    <i class="dripicons-plus mr-1"></i> Add Line (Ctrl+Enter)
                </button>
                <small class="text-muted">Tip: Tab through fields to auto-calculate the balancing amount.</small>
            </div>
        </div>

        <!-- Footer Section: Narration, Remarks, Totals & Save -->
        <div class="optech-footer-card p-3">
            <div class="row align-items-center">
                <!-- Narration & Standard Remarks -->
                <div class="col-lg-6 col-md-12 mb-3 mb-lg-0">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label class="font-weight-bold text-muted small text-uppercase mb-0">General Narration / Remarks *</label>
                        <div class="d-flex align-items-center">
                            <span class="small text-muted mr-1">Quick Remark:</span>
                            <select id="standardRemarkSelect" class="form-control form-control-sm py-0" style="width: 170px; height: 26px;">
                                <option value="">-- Insert Remark --</option>
                                @foreach($remarks as $rem)
                                    <option value="{{ $rem->remark }}">{{ $rem->title }}</option>
                                @endforeach
                            </select>
                            <button type="button" class="inline-add-btn ml-1" id="btnInlineRemark" title="Add Remark">[+]</button>
                        </div>
                    </div>
                    <textarea name="description" id="voucherDescription" rows="3" class="form-control form-control-sm" placeholder="Narration for this voucher entry (e.g. Being payment made for freight charges...)" required></textarea>
                </div>

                <!-- Balance Summary & Post Button -->
                <div class="col-lg-6 col-md-12">
                    <div class="row">
                        <div class="col-sm-7 mb-2 mb-sm-0">
                            <div class="optech-total-box unbalanced" id="balanceStatusBox">
                                <div class="d-flex justify-content-between font-weight-bold small mb-1">
                                    <span>Total Debit:</span>
                                    <span id="totalDebitDisplay">Rs. 0.00</span>
                                </div>
                                <div class="d-flex justify-content-between font-weight-bold small mb-1">
                                    <span>Total Credit:</span>
                                    <span id="totalCreditDisplay">Rs. 0.00</span>
                                </div>
                                <div class="d-flex justify-content-between font-weight-bold border-top pt-1 text-danger" id="diffRow">
                                    <span>Difference:</span>
                                    <span id="diffDisplay">Rs. 0.00</span>
                                </div>
                                <div class="text-center mt-1">
                                    <span class="badge badge-danger font-weight-bold" id="balancedBadge">Unbalanced</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-5 d-flex flex-column justify-content-center">
                            <button type="submit" class="btn btn-success btn-block font-weight-bold py-2 mb-2" id="btnSaveVoucher" disabled>
                                <i class="dripicons-checkmark mr-1"></i> Save Voucher (Alt+S)
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-block btn-sm" id="btnResetForm">
                                <i class="dripicons-clockwise mr-1"></i> Clear Form
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- ================= INLINE MODAL 1: CREATE LEDGER ACCOUNT (Alt+C) ================= -->
<div class="modal fade" id="modalInlineAccount" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header bg-teal text-white py-2">
                <h5 class="modal-title font-weight-bold" style="font-size: 15px;">Create Ledger Account (Alt+C)</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
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
                            <select name="type" id="inlineAccountType" class="form-control form-control-sm" required>
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
                        <select name="control_type" class="form-control form-control-sm">
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
                <div class="modal-footer py-2 bg-light">
                    <button type="button" class="btn btn-sm btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-success font-weight-bold">Save Ledger</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ================= INLINE MODAL 2: CREATE VOUCHER SERIES ================= -->
<div class="modal fade" id="modalInlineSeries" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header bg-teal text-white py-2">
                <h5 class="modal-title font-weight-bold" style="font-size: 15px;">Create Voucher Series</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
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
                <div class="modal-footer py-2 bg-light">
                    <button type="button" class="btn btn-sm btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-success font-weight-bold">Save Series</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ================= INLINE MODAL 3: CREATE STANDARD REMARK ================= -->
<div class="modal fade" id="modalInlineRemark" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header bg-teal text-white py-2">
                <h5 class="modal-title font-weight-bold" style="font-size: 15px;">Create Standard Remark</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
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
                <div class="modal-footer py-2 bg-light">
                    <button type="button" class="btn btn-sm btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-success font-weight-bold">Save Remark</button>
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
            document.querySelectorAll('.vch-type-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            const type = this.dataset.type;
            document.getElementById('hiddenVoucherType').value = type;
            document.getElementById('vchModeBadge').textContent = type.toUpperCase();

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
                partnerSelect.innerHTML = '<option value="">-- No Sub-Ledger Required --</option>';
                partnerSelect.setAttribute('disabled', 'disabled');
                partnerTypeHidden.value = '';
                return;
            }

            const bal = opt.dataset.bal || '';
            const control = opt.dataset.control || '';
            const isDr = bal.includes('Dr');
            balPreview.innerHTML = `<span class="optech-balance-badge ${isDr ? 'optech-balance-dr' : 'optech-balance-cr'}">Bal: ${bal}</span>`;

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
                partnerSelect.innerHTML = '<option value="">-- No Sub-Ledger Required --</option>';
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
        const nextRow = document.querySelector(`#voucherTableBody tr[data-index="${nextIndex}"]`);

        if (nextRow && Math.abs(diff) > 0.001) {
            const nextTypeSelect = nextRow.querySelector('.row-type');
            const nextDebit = nextRow.querySelector('.debit-input');
            const nextCredit = nextRow.querySelector('.credit-input');

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

        document.getElementById('totalDebitDisplay').textContent = 'Rs. ' + totalDr.toFixed(2);
        document.getElementById('totalCreditDisplay').textContent = 'Rs. ' + totalCr.toFixed(2);
        document.getElementById('diffDisplay').textContent = 'Rs. ' + diff.toFixed(2);

        const statusBox = document.getElementById('balanceStatusBox');
        const badge = document.getElementById('balancedBadge');
        const diffRow = document.getElementById('diffRow');
        const btnSave = document.getElementById('btnSaveVoucher');

        if (diff === 0 && totalDr > 0) {
            statusBox.className = 'optech-total-box balanced';
            badge.className = 'badge badge-success font-weight-bold';
            badge.textContent = '✓ Balanced (0.00)';
            diffRow.className = 'd-flex justify-content-between font-weight-bold border-top pt-1 text-success';
            btnSave.removeAttribute('disabled');
        } else {
            statusBox.className = 'optech-total-box unbalanced';
            badge.className = 'badge badge-danger font-weight-bold';
            badge.textContent = '⚠ Unbalanced';
            diffRow.className = 'd-flex justify-content-between font-weight-bold border-top pt-1 text-danger';
            btnSave.setAttribute('disabled', 'disabled');
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
                <select class="optech-dr-cr-select row-type" data-index="${newIndex}">
                    <option value="Dr">Dr</option>
                    <option value="Cr" selected>Cr</option>
                </select>
            </td>
            <td>
                <select name="items[${newIndex}][chart_of_account_id]" class="form-control form-control-sm font-weight-bold account-select" data-index="${newIndex}" required>
                    ${optionsHtml}
                </select>
                <div class="account-balance-preview mt-1 small" id="balPreview_${newIndex}"></div>
            </td>
            <td>
                <div class="partner-selector-wrap" id="partnerWrap_${newIndex}">
                    <select name="items[${newIndex}][partner_id]" class="form-control form-control-sm partner-select" data-index="${newIndex}" disabled>
                        <option value="">-- No Sub-Ledger Required --</option>
                    </select>
                    <input type="hidden" name="items[${newIndex}][partner_type]" class="partner-type" id="partnerType_${newIndex}" value="">
                </div>
            </td>
            <td>
                <input type="number" step="0.01" name="items[${newIndex}][debit]" class="form-control form-control-sm text-right font-weight-bold debit-input" data-index="${newIndex}" value="0.00" readonly>
            </td>
            <td>
                <input type="number" step="0.01" name="items[${newIndex}][credit]" class="form-control form-control-sm text-right font-weight-bold credit-input" data-index="${newIndex}" value="0.00">
            </td>
            <td class="text-center">
                <button type="button" class="btn btn-sm btn-link text-danger p-0 remove-row-btn" title="Remove row">&times;</button>
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
            btnSave.innerHTML = '<i class="dripicons-checkmark mr-1"></i> Save Voucher (Alt+S)';
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
