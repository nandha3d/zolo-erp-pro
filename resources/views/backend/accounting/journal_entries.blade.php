@extends('backend.layout.main')

@section('content')

<link rel="stylesheet" href="<?php echo asset('css/commercial-entry.css') . '?v=' . (file_exists(public_path('css/commercial-entry.css')) ? filemtime(public_path('css/commercial-entry.css')) : time()) ?>" type="text/css">
<link rel="stylesheet" href="<?php echo asset('css/commercial-workspace.css') . '?v=' . (file_exists(public_path('css/commercial-workspace.css')) ? filemtime(public_path('css/commercial-workspace.css')) : time()) ?>" type="text/css">
<link rel="stylesheet" href="<?php echo asset('css/zolo-erp-neo.css') . '?v=' . (file_exists(public_path('css/zolo-erp-neo.css')) ? filemtime(public_path('css/zolo-erp-neo.css')) : time()) ?>" type="text/css">

<style>
    .journal-workspace-view {
        padding: 12px 18px 24px;
        background: #f8fafc;
        min-height: calc(100vh - 40px);
    }
    body.dark-mode .journal-workspace-view {
        background: #0b0f19;
    }
    .kpi-summary-strip {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 12px;
        margin-bottom: 14px;
    }
    .kpi-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 12px 16px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03);
    }
    body.dark-mode .kpi-card {
        background: #141c2e;
        border-color: #334155;
    }
    .kpi-card .kpi-label {
        font-size: 10px;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        font-weight: 700;
        color: #64748b;
        margin-bottom: 2px;
    }
    .kpi-card .kpi-number {
        font-size: 19px;
        font-weight: 700;
        color: #0f172a;
    }
    body.dark-mode .kpi-card .kpi-number {
        color: #f8fafc;
    }
    .desk-filter-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 10px 14px;
        margin-bottom: 14px;
        box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03);
    }
    body.dark-mode .desk-filter-card {
        background: #141c2e;
        border-color: #334155;
    }
    .desk-card-wrapper {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
        padding: 14px 16px;
    }
    body.dark-mode .desk-card-wrapper {
        background: #141c2e;
        border-color: #334155;
    }
    .journal-grid-table {
        border-collapse: collapse !important;
        width: 100% !important;
    }
    .journal-grid-table thead th {
        background: #0f172a !important;
        color: #ffffff !important;
        font-size: 10.5px !important;
        font-weight: 700 !important;
        text-transform: uppercase !important;
        letter-spacing: 0.04em !important;
        padding: 9px 12px !important;
        border: none !important;
        white-space: nowrap;
    }
    body.dark-mode .journal-grid-table thead th {
        background: #090d16 !important;
        color: #f8fafc !important;
    }
    .journal-grid-table tbody tr:nth-child(even) {
        background: #f8fafc;
    }
    .journal-grid-table tbody tr:nth-child(odd) {
        background: #ffffff;
    }
    body.dark-mode .journal-grid-table tbody tr:nth-child(even) {
        background: #141c2c;
    }
    body.dark-mode .journal-grid-table tbody tr:nth-child(odd) {
        background: #1e293b;
    }
    .journal-grid-table tbody tr:hover {
        background: #e2e8f0 !important;
    }
    body.dark-mode .journal-grid-table tbody tr:hover {
        background: #27354f !important;
    }
    .journal-grid-table td {
        padding: 9px 12px !important;
        border-bottom: 1px solid #e2e8f0 !important;
        vertical-align: middle !important;
        font-size: 12.5px;
    }
    body.dark-mode .journal-grid-table td {
        border-color: #334155 !important;
        color: #e2e8f0;
    }
    .btn-action-pill {
        height: 28px !important;
        font-size: 11px !important;
        padding: 0 12px !important;
        border-radius: 4px !important;
        font-weight: 600 !important;
        display: inline-flex !important;
        align-items: center !important;
        gap: 5px !important;
    }
</style>

<x-success-message key="message" />
<x-error-message key="not_permitted" />
<x-validation-error fieldName="accounting" />
<x-validation-error fieldName="reason" />
<x-validation-error fieldName="entry_date" />

@php
    $totalEntriesCount = $entries->total();
    $totalDebitSum = $entries->sum('total_debit');
    $totalCreditSum = $entries->sum('total_credit');
@endphp

<section class="journal-workspace-view">
    <!-- Top Command Bar -->
    <div class="comm-command-bar">
        <div class="comm-title-group">
            <h1><i class="dripicons-briefcase text-primary"></i> General Journal Entries Command Center</h1>
            <span class="comm-title-badge"><i class="dripicons-tag"></i> Double-Entry Financial Audit Trail</span>
        </div>
        <ul class="comm-nav-pills">
            <li><a class="nav-link" href="{{ route('accounting.voucher.entry') }}"><i class="dripicons-pencil"></i> Voucher Entry · F9</a></li>
            <li><a class="nav-link active" href="{{ route('accounting.journal-entries') }}"><i class="dripicons-align-justify"></i> Journal Entries</a></li>
            <li><a class="nav-link" href="{{ route('accounting.coa') }}"><i class="dripicons-network"></i> Chart of Accounts</a></li>
            <li><a class="nav-link" href="{{ route('accounting.general-ledger') }}"><i class="dripicons-view-thumb"></i> General Ledger</a></li>
            <li><a class="nav-link" href="{{ route('accounting.trial-balance') }}"><i class="dripicons-scale"></i> Trial Balance</a></li>
            <li><a class="nav-link" href="{{ route('accounting.profit-loss') }}"><i class="dripicons-graph-line"></i> P&amp;L</a></li>
            <li><a class="nav-link" href="{{ route('accounting.balance-sheet') }}"><i class="dripicons-document"></i> Balance Sheet</a></li>
        </ul>
        <div class="comm-actions">
            <button type="button" class="btn btn-primary btn-action-pill" data-toggle="modal" data-target="#createVoucherModal" style="background:#7c3aed; border-color:#7c3aed; color:#fff;">
                <i class="dripicons-plus"></i> New Journal Voucher
            </button>
        </div>
    </div>

    <!-- KPI Summary Strip -->
    <div class="kpi-summary-strip">
        <div class="kpi-card">
            <div>
                <div class="kpi-label">TOTAL JOURNAL ENTRIES</div>
                <div class="kpi-number">{{ number_format($totalEntriesCount) }}</div>
            </div>
            <div style="width:36px;height:36px;border-radius:6px;background:#ede9fe;display:flex;align-items:center;justify-content:center;color:#7c3aed;">
                <i class="dripicons-align-justify" style="font-size:18px;"></i>
            </div>
        </div>
        <div class="kpi-card">
            <div>
                <div class="kpi-label">PAGE DEBITS POSTED</div>
                <div class="kpi-number" style="color:#0284c7;">₹ {{ number_format($totalDebitSum, 2) }}</div>
            </div>
            <div style="width:36px;height:36px;border-radius:6px;background:#e0f2fe;display:flex;align-items:center;justify-content:center;color:#0284c7;">
                <i class="dripicons-arrow-up" style="font-size:18px;"></i>
            </div>
        </div>
        <div class="kpi-card">
            <div>
                <div class="kpi-label">PAGE CREDITS POSTED</div>
                <div class="kpi-number" style="color:#059669;">₹ {{ number_format($totalCreditSum, 2) }}</div>
            </div>
            <div style="width:36px;height:36px;border-radius:6px;background:#d1fae5;display:flex;align-items:center;justify-content:center;color:#059669;">
                <i class="dripicons-arrow-down" style="font-size:18px;"></i>
            </div>
        </div>
        <div class="kpi-card">
            <div>
                <div class="kpi-label">STATUS &amp; INTEGRITY</div>
                <div class="kpi-number" style="font-size:14px; font-weight:600; color:#059669;">
                    <i class="dripicons-checkmark text-success"></i> 100% Balanced
                </div>
            </div>
            <div style="width:36px;height:36px;border-radius:6px;background:#fef3c7;display:flex;align-items:center;justify-content:center;color:#d97706;">
                <i class="dripicons-scale" style="font-size:18px;"></i>
            </div>
        </div>
    </div>

    <!-- Filter Card -->
    <div class="desk-filter-card">
        <form action="{{ route('accounting.journal-entries') }}" method="GET" class="m-0">
            <div class="row align-items-end" style="margin: 0 -6px;">
                <div class="col-md-3" style="padding: 0 6px;">
                    <label class="font-weight-600 mb-1" style="font-size: 11px; text-transform: uppercase; color: #64748b;">Reference Type</label>
                    <select name="reference_type" class="form-control form-control-sm" style="height:32px; font-size:12px;">
                        <option value="">-- All Types --</option>
                        <option value="sale" {{ request('reference_type') == 'sale' ? 'selected' : '' }}>Sale / Invoices</option>
                        <option value="purchase" {{ request('reference_type') == 'purchase' ? 'selected' : '' }}>Purchase Orders</option>
                        <option value="payment" {{ request('reference_type') == 'payment' ? 'selected' : '' }}>Payments</option>
                        <option value="expense" {{ request('reference_type') == 'expense' ? 'selected' : '' }}>Expenses</option>
                        <option value="manual" {{ request('reference_type') == 'manual' ? 'selected' : '' }}>Manual Vouchers</option>
                    </select>
                </div>
                <div class="col-md-3" style="padding: 0 6px;">
                    <label class="font-weight-600 mb-1" style="font-size: 11px; text-transform: uppercase; color: #64748b;">Start Date</label>
                    <input type="date" name="start_date" class="form-control form-control-sm" value="{{ request('start_date') }}" style="height:32px; font-size:12px;">
                </div>
                <div class="col-md-3" style="padding: 0 6px;">
                    <label class="font-weight-600 mb-1" style="font-size: 11px; text-transform: uppercase; color: #64748b;">End Date</label>
                    <input type="date" name="end_date" class="form-control form-control-sm" value="{{ request('end_date') }}" style="height:32px; font-size:12px;">
                </div>
                <div class="col-md-3 d-flex" style="padding: 0 6px;">
                    <button type="submit" class="btn btn-sm btn-primary flex-grow-1 mr-2" style="height:32px; font-size:12px; font-weight:600;">
                        <i class="dripicons-search"></i> Filter
                    </button>
                    <a href="{{ route('accounting.journal-entries') }}" class="btn btn-sm btn-outline-secondary" style="height:32px; font-size:12px; display:inline-flex; align-items:center;">Reset</a>
                </div>
            </div>
        </form>
    </div>

    <!-- Journal Entries Table -->
    <div class="desk-card-wrapper">
        <div class="table-responsive">
            <table class="table journal-grid-table mb-0">
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
                                <span class="badge badge-light border text-uppercase font-weight-600" style="font-size: 11px; padding: 3px 6px; border-radius: 4px;">
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
                                    <span class="badge badge-secondary" style="font-size: 10.5px; padding: 4px 8px; border-radius: 4px;">Reversed</span>
                                @else
                                    <span class="badge badge-success" style="font-size: 10.5px; padding: 4px 8px; border-radius: 4px; background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0;">
                                        Posted
                                    </span>
                                @endif
                            </td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-outline-primary view-entry-btn" data-id="{{ $entry->id }}" title="View Lines" style="height:26px; width:26px; padding:0; display:inline-flex; align-items:center; justify-content:center; border-radius:4px;">
                                    <i class="dripicons-preview"></i>
                                </button>
                                {{-- Posted journals are never edited or deleted; a manual one is cancelled by a reversing entry. Bill postings are corrected from the bill. --}}
                                @if($entry->reference_type === 'manual' && !$entry->reversal_of_id && !$isReversed)
                                    <button type="button" class="btn btn-sm btn-outline-danger reverse-entry-btn" data-url="{{ route('accounting.journal.reverse', $entry->id) }}" data-number="{{ $entry->entry_number }}" data-date="{{ $entry->entry_date->format('Y-m-d') }}" title="Reverse" style="height:26px; width:26px; padding:0; display:inline-flex; align-items:center; justify-content:center; border-radius:4px;">
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
<div id="createVoucherModal" tabindex="-1" role="dialog" class="modal fade" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content" style="border-radius: 8px; overflow: hidden; border: 1px solid #cbd5e1;">
            <div class="modal-header" style="background:#0f172a; color:#fff; padding: 12px 18px;">
                <h5 class="modal-title font-weight-bold" style="font-size: 15px; color:#fff;">
                    <i class="dripicons-pencil mr-1"></i> New Journal Entry Voucher
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="color:#fff; opacity:0.8;"><span aria-hidden="true">&times;</span></button>
            </div>
            <form action="{{ route('accounting.journal-entries.store') }}" method="POST" id="voucherForm">
                @csrf
                <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', (string) Illuminate\Support\Str::uuid()) }}">
                <div class="modal-body" style="padding: 16px 20px;">
                    <div class="form-row mb-3">
                        <div class="col-md-4">
                            <label class="font-weight-600 mb-1" style="font-size: 11.5px; color: #475569;">Date <span class="text-danger">*</span></label>
                            <input type="date" name="entry_date" class="form-control form-control-sm" value="{{ date('Y-m-d') }}" required style="height:32px; font-size:12.5px;">
                        </div>
                        <div class="col-md-8">
                            <label class="font-weight-600 mb-1" style="font-size: 11.5px; color: #475569;">Description / Memo <span class="text-danger">*</span></label>
                            <input type="text" name="description" class="form-control form-control-sm" placeholder="e.g. Month-end adjustment, Depreciation" required style="height:32px; font-size:12.5px;">
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mb-2 mt-3">
                        <h6 class="font-weight-bold mb-0" style="font-size: 13px; color: #0f172a;">Journal Line Items</h6>
                        <button type="button" class="btn btn-sm btn-outline-success" id="addLineBtn" style="font-size: 11px; height: 26px; padding: 0 10px;">
                            <i class="dripicons-plus mr-1"></i> Add Line
                        </button>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered table-sm mb-0" id="voucherLinesTable" style="border-collapse: collapse;">
                            <thead style="background: #f1f5f9;">
                                <tr>
                                    <th style="width: 40%; font-size: 11px; text-transform: uppercase;">Account</th>
                                    <th style="font-size: 11px; text-transform: uppercase;">Memo</th>
                                    <th style="width: 20%; font-size: 11px; text-transform: uppercase;" class="text-right">Debit</th>
                                    <th style="width: 20%; font-size: 11px; text-transform: uppercase;" class="text-right">Credit</th>
                                    <th style="width: 5%;"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr class="line-row">
                                    <td>
                                        <select name="items[0][chart_of_account_id]" class="form-control form-control-sm" required style="height:30px; font-size:12px;">
                                            <option value="">-- Select Account --</option>
                                            @foreach($accounts as $acc)
                                                <option value="{{ $acc->id }}">{{ $acc->code }} - {{ $acc->name }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td><input type="text" name="items[0][memo]" class="form-control form-control-sm" placeholder="Memo" style="height:30px; font-size:12px;"></td>
                                    <td><input type="number" step="0.01" name="items[0][debit]" class="form-control form-control-sm text-right debit-input" value="0.00" style="height:30px; font-size:12px;"></td>
                                    <td><input type="number" step="0.01" name="items[0][credit]" class="form-control form-control-sm text-right credit-input" value="0.00" style="height:30px; font-size:12px;"></td>
                                    <td class="text-center align-middle"><button type="button" class="btn btn-sm btn-link text-danger remove-line-btn p-0 font-weight-bold" style="font-size:16px;">&times;</button></td>
                                </tr>
                                <tr class="line-row">
                                    <td>
                                        <select name="items[1][chart_of_account_id]" class="form-control form-control-sm" required style="height:30px; font-size:12px;">
                                            <option value="">-- Select Account --</option>
                                            @foreach($accounts as $acc)
                                                <option value="{{ $acc->id }}">{{ $acc->code }} - {{ $acc->name }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td><input type="text" name="items[1][memo]" class="form-control form-control-sm" placeholder="Memo" style="height:30px; font-size:12px;"></td>
                                    <td><input type="number" step="0.01" name="items[1][debit]" class="form-control form-control-sm text-right debit-input" value="0.00" style="height:30px; font-size:12px;"></td>
                                    <td><input type="number" step="0.01" name="items[1][credit]" class="form-control form-control-sm text-right credit-input" value="0.00" style="height:30px; font-size:12px;"></td>
                                    <td class="text-center align-middle"><button type="button" class="btn btn-sm btn-link text-danger remove-line-btn p-0 font-weight-bold" style="font-size:16px;">&times;</button></td>
                                </tr>
                            </tbody>
                            <tfoot style="background: #f8fafc;" class="font-weight-bold">
                                <tr>
                                    <td colspan="2" class="text-right" style="font-size:11.5px;">Total:</td>
                                    <td class="text-right font-monospace" id="totalDebitCell" style="font-size:12.5px;">0.00</td>
                                    <td class="text-right font-monospace" id="totalCreditCell" style="font-size:12.5px;">0.00</td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td colspan="2" class="text-right" style="font-size:11.5px;">Balance Check:</td>
                                    <td colspan="2" class="text-center" id="balanceStatusCell">
                                        <span class="badge badge-success" style="background:#dcfce7; color:#15803d; border:1px solid #bbf7d0; padding:4px 8px;">Balanced (0.00)</span>
                                    </td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
                <div class="modal-footer border-0 p-3" style="background: #f8fafc;">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm font-weight-bold" id="submitVoucherBtn" style="background:#7c3aed; border-color:#7c3aed;">Post Journal Entry</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Entry Modal -->
<div id="viewEntryModal" tabindex="-1" role="dialog" class="modal fade" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content" style="border-radius: 8px; overflow: hidden; border: 1px solid #cbd5e1;">
            <div class="modal-header" style="background:#0f172a; color:#fff; padding: 12px 18px;">
                <div>
                    <h5 class="modal-title font-weight-bold mb-0" id="viewModalTitle" style="font-size: 15px; color:#fff;">Journal Entry</h5>
                    <small class="text-light opacity-75" id="viewModalSubtitle" style="font-size: 11px;"></small>
                </div>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="color:#fff; opacity:0.8;"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body" style="padding: 16px 20px;">
                <table class="table table-bordered table-sm mb-0" id="entryDetailTable">
                    <thead style="background: #0f172a; color: #fff;">
                        <tr>
                            <th style="font-size:11px; text-transform:uppercase;">Account</th>
                            <th style="font-size:11px; text-transform:uppercase;">Memo</th>
                            <th class="text-right" style="font-size:11px; text-transform:uppercase;">Debit</th>
                            <th class="text-right" style="font-size:11px; text-transform:uppercase;">Credit</th>
                        </tr>
                    </thead>
                    <tbody id="entryDetailBody"></tbody>
                    <tfoot style="background: #f8fafc;" class="font-weight-bold">
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
<div id="reverseEntryModal" tabindex="-1" role="dialog" class="modal fade" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 8px; overflow: hidden; border: 1px solid #cbd5e1;">
            <div class="modal-header" style="background:#0f172a; color:#fff; padding: 12px 18px;">
                <h5 class="modal-title font-weight-bold" style="font-size: 15px; color:#fff;">
                    <i class="dripicons-return mr-1"></i> Reverse Journal <span id="reverse-entry-number" class="font-monospace"></span>
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="color:#fff; opacity:0.8;"><span aria-hidden="true">&times;</span></button>
            </div>
            <form id="reverseEntryForm" method="POST">
                @csrf
                <div class="modal-body" style="padding: 16px 20px;">
                    <p class="text-muted mb-2" style="font-size: 12px;">Posts an equal and opposite entry; the original stays in the books for audit.</p>
                    <div class="form-group mb-2">
                        <label class="font-weight-600 mb-1" style="font-size: 11.5px; color: #475569;">Reversal Date <span class="text-danger">*</span></label>
                        <input type="date" name="entry_date" class="form-control form-control-sm" required style="height:32px; font-size:12.5px;">
                    </div>
                    <div class="form-group mb-0">
                        <label class="font-weight-600 mb-1" style="font-size: 11.5px; color: #475569;">Reason <span class="text-danger">*</span></label>
                        <textarea name="reason" class="form-control form-control-sm" rows="2" maxlength="500" required style="font-size:12.5px;"></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 p-3" style="background:#f8fafc;">
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
            $('#balanceStatusCell').html('<span class="badge badge-success" style="background:#dcfce7; color:#15803d; border:1px solid #bbf7d0; padding:4px 8px;">Balanced (0.00)</span>');
            $('#submitVoucherBtn').prop('disabled', false);
        } else {
            $('#balanceStatusCell').html('<span class="badge badge-danger" style="background:#fee2e2; color:#b91c1c; border:1px solid #fca5a5; padding:4px 8px;">Out of Balance (Diff: ' + diff.toFixed(2) + ')</span>');
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
                    <select name="items[${lineIndex}][chart_of_account_id]" class="form-control form-control-sm" required style="height:30px; font-size:12px;">
                        ${accountsOptions}
                    </select>
                </td>
                <td><input type="text" name="items[${lineIndex}][memo]" class="form-control form-control-sm" placeholder="Memo" style="height:30px; font-size:12px;"></td>
                <td><input type="number" step="0.01" name="items[${lineIndex}][debit]" class="form-control form-control-sm text-right debit-input" value="0.00" style="height:30px; font-size:12px;"></td>
                <td><input type="number" step="0.01" name="items[${lineIndex}][credit]" class="form-control form-control-sm text-right credit-input" value="0.00" style="height:30px; font-size:12px;"></td>
                <td class="text-center align-middle"><button type="button" class="btn btn-sm btn-link text-danger remove-line-btn p-0 font-weight-bold" style="font-size:16px;">&times;</button></td>
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
                        <td class="text-right font-monospace">${parseFloat(item.debit) > 0 ? parseFloat(item.debit).toFixed(2) : '-'}</td>
                        <td class="text-right font-monospace">${parseFloat(item.credit) > 0 ? parseFloat(item.credit).toFixed(2) : '-'}</td>
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
