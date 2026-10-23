@extends('backend.layout.main')
@section('content')

<link rel="stylesheet" href="<?php echo asset('css/commercial-entry.css') . '?v=' . (file_exists(public_path('css/commercial-entry.css')) ? filemtime(public_path('css/commercial-entry.css')) : time()) ?>" type="text/css">
<link rel="stylesheet" href="<?php echo asset('css/commercial-workspace.css') . '?v=' . (file_exists(public_path('css/commercial-workspace.css')) ? filemtime(public_path('css/commercial-workspace.css')) : time()) ?>" type="text/css">
<link rel="stylesheet" href="<?php echo asset('css/zolo-erp-neo.css') . '?v=' . (file_exists(public_path('css/zolo-erp-neo.css')) ? filemtime(public_path('css/zolo-erp-neo.css')) : time()) ?>" type="text/css">

<style>
    .voucher-hub-workspace-view {
        padding: 12px 18px 24px;
        background: #f8fafc;
        min-height: calc(100vh - 40px);
    }
    body.dark-mode .voucher-hub-workspace-view {
        background: #0b0f19;
    }
    .desk-card-wrapper {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
        padding: 18px 20px;
        margin-bottom: 16px;
    }
    body.dark-mode .desk-card-wrapper {
        background: #141c2e;
        border-color: #334155;
    }
    .vh-table {
        border-collapse: collapse !important;
        width: 100% !important;
    }
    .vh-table thead th {
        background: #0f172a !important;
        color: #ffffff !important;
        font-size: 10.5px !important;
        font-weight: 700 !important;
        text-transform: uppercase !important;
        letter-spacing: 0.04em !important;
        padding: 9px 12px !important;
        border: none !important;
    }
    body.dark-mode .vh-table thead th {
        background: #090d16 !important;
        color: #f8fafc !important;
    }
    .vh-table tbody tr:nth-child(even) {
        background: #f8fafc;
    }
    .vh-table tbody tr:nth-child(odd) {
        background: #ffffff;
    }
    body.dark-mode .vh-table tbody tr:nth-child(even) {
        background: #141c2c;
    }
    body.dark-mode .vh-table tbody tr:nth-child(odd) {
        background: #1e293b;
    }
    .vh-table tbody tr:hover {
        background: #e2e8f0 !important;
    }
    body.dark-mode .vh-table tbody tr:hover {
        background: #27354f !important;
    }
    .vh-table td {
        padding: 8px 12px !important;
        border-bottom: 1px solid #e2e8f0 !important;
        vertical-align: middle !important;
        font-size: 12.5px;
    }
    body.dark-mode .vh-table td {
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

@include('backend.partials.document-entry-layout')
<section class="voucher-hub-workspace-view">
    <!-- Top Command Bar -->
    <div class="comm-command-bar">
        <div class="comm-title-group">
            <h1><i class="dripicons-pencil text-primary"></i> Voucher Hub Command Center <small class="text-light opacity-75">(F9)</small></h1>
            <span class="comm-title-badge"><i class="dripicons-tag"></i> {{ $year->name }} &bull; {{ $year->start_date->format('Y-m-d') }} to {{ $year->end_date->format('Y-m-d') }} &bull; {{ ucfirst($year->status) }}</span>
        </div>
        <ul class="comm-nav-pills">
            <li><a class="nav-link active" href="{{ route('accounting.vouchers') }}"><i class="dripicons-home"></i> Voucher Hub</a></li>
            <li><a class="nav-link" href="{{ route('accounting.day-book') }}"><i class="dripicons-calendar"></i> Day Book</a></li>
            <li><a class="nav-link" href="{{ route('accounting.cash-book') }}"><i class="dripicons-wallet"></i> Cash Book</a></li>
            <li><a class="nav-link" href="{{ route('accounting.ageing') }}"><i class="dripicons-clock"></i> Receivables / Payables</a></li>
            <li><a class="nav-link" href="{{ route('accounting.general-ledger') }}"><i class="dripicons-view-thumb"></i> Ledger</a></li>
            <li><a class="nav-link" href="{{ route('accounting.semantic-mappings') }}"><i class="dripicons-gear"></i> Account Mappings</a></li>
        </ul>
        <div class="comm-actions">
            @if($year->lock_date)
                <span class="badge badge-warning" style="font-size: 11px; padding: 5px 8px;">
                    <i class="dripicons-lock"></i> Locked thru {{ $year->lock_date->format('Y-m-d') }}
                </span>
            @endif
        </div>
    </div>

    @if(session('message'))
        <div class="alert alert-success alert-dismissible text-center mb-3">
            <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            {{ session('message') }}
        </div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger mb-3" role="alert">
            <ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    @if($canPost)
    <!-- Voucher Entry Form Container -->
    <div class="desk-card-wrapper">
        <form method="POST" action="{{ route('accounting.vouchers.store') }}" id="accounting-voucher-form" class="m-0">
            @csrf
            <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', (string) Illuminate\Support\Str::uuid()) }}">
            
            <div class="row mb-3" style="margin: 0 -6px;">
                <div class="col-md-4" style="padding: 0 6px;">
                    <label for="voucher-type" class="font-weight-600 mb-1" style="font-size: 11px; text-transform: uppercase; color: #64748b;">Voucher Type</label>
                    <select id="voucher-type" name="voucher_type" class="form-control form-control-sm" style="height:32px; font-size:12px;">
                        @foreach(['contra' => 'Contra (F4)', 'payment' => 'Payment (F5)', 'receipt' => 'Receipt (F6)', 'journal' => 'Journal (F7)'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('voucher_type', 'journal') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4" style="padding: 0 6px;">
                    <label for="voucher-date" class="font-weight-600 mb-1" style="font-size: 11px; text-transform: uppercase; color: #64748b;">Posting Date</label>
                    <input id="voucher-date" name="entry_date" type="date" class="form-control form-control-sm" min="{{ $year->start_date->format('Y-m-d') }}" max="{{ $year->end_date->format('Y-m-d') }}" value="{{ old('entry_date', max($year->start_date->format('Y-m-d'), min(now()->toDateString(), $year->end_date->format('Y-m-d')))) }}" required style="height:32px; font-size:12px;">
                </div>
                <div class="col-md-4" style="padding: 0 6px;">
                    <label for="reference-mode" class="font-weight-600 mb-1" style="font-size: 11px; text-transform: uppercase; color: #64748b;">Allocation Mode</label>
                    <select id="reference-mode" name="reference_mode" class="form-control form-control-sm" style="height:32px; font-size:12px;">
                        @foreach(['new_reference' => 'New Reference', 'against_reference' => 'Against Reference', 'advance' => 'Advance', 'on_account' => 'On Account'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('reference_mode') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="row mb-3" style="margin: 0 -6px;">
                <div class="col-md-8" style="padding: 0 6px;">
                    <label for="voucher-description" class="font-weight-600 mb-1" style="font-size: 11px; text-transform: uppercase; color: #64748b;">Narration</label>
                    <textarea id="voucher-description" name="description" class="form-control form-control-sm" maxlength="2000" rows="2" required placeholder="Enter narration or transaction details..." style="font-size:12.5px;">{{ old('description') }}</textarea>
                </div>
                <div class="col-md-4" style="padding: 0 6px;">
                    <label for="narration-template" class="font-weight-600 mb-1" style="font-size: 11px; text-transform: uppercase; color: #64748b;">Narration Template</label>
                    <select id="narration-template" class="form-control form-control-sm" style="height:32px; font-size:12px;">
                        <option value="">Choose a template</option>
                        <option>Cash deposited in bank</option>
                        <option>Supplier bill payment</option>
                        <option>Customer bill receipt</option>
                        <option>Account adjustment</option>
                    </select>
                </div>
            </div>

            <!-- Table of Lines -->
            <div class="table-responsive mb-3">
                <table class="table vh-table mb-0">
                    <caption class="sr-only">Voucher debit and credit lines</caption>
                    <thead>
                        <tr>
                            <th scope="col" style="width: 30%;">Account</th>
                            <th scope="col" style="width: 25%;">Party (AR/AP)</th>
                            <th scope="col" style="width: 15%;" class="text-right">Debit</th>
                            <th scope="col" style="width: 15%;" class="text-right">Credit</th>
                            <th scope="col">Memo</th>
                            <th scope="col" style="width: 5%;" class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody id="voucher-lines"></tbody>
                </table>
            </div>

            <div class="d-flex justify-content-between align-items-center mb-3">
                <button class="btn btn-outline-primary btn-sm font-weight-bold" type="button" id="add-voucher-line" style="font-size: 11px; height: 28px;">
                    <i class="dripicons-plus mr-1"></i> Add Line
                </button>
                <div id="voucher-balance" role="status" aria-live="polite" class="font-weight-bold text-muted" style="font-size: 12px;">
                    Enter balanced debit and credit lines.
                </div>
            </div>

            <div class="row mb-3" style="margin: 0 -6px; background:#f8fafc; padding: 10px 4px; border-radius: 6px; border: 1px solid #e2e8f0;">
                <div class="col-md-4" style="padding: 0 6px;">
                    <label for="voucher-due-date" class="font-weight-600 mb-1" style="font-size: 11px; text-transform: uppercase; color: #64748b;">Due Date</label>
                    <input type="date" id="voucher-due-date" name="due_date" class="form-control form-control-sm" value="{{ old('due_date') }}" style="height:30px; font-size:12px;">
                </div>
                <div class="col-md-4" style="padding: 0 6px;">
                    <label for="cheque-no" class="font-weight-600 mb-1" style="font-size: 11px; text-transform: uppercase; color: #64748b;">Cheque Number</label>
                    <input id="cheque-no" name="cheque_no" class="form-control form-control-sm" maxlength="100" value="{{ old('cheque_no') }}" placeholder="Cheque / Ref No" style="height:30px; font-size:12px;">
                </div>
                <div class="col-md-4" style="padding: 0 6px;">
                    <label for="cheque-date" class="font-weight-600 mb-1" style="font-size: 11px; text-transform: uppercase; color: #64748b;">Cheque Date</label>
                    <input type="date" id="cheque-date" name="cheque_date" class="form-control form-control-sm" value="{{ old('cheque_date') }}" style="height:30px; font-size:12px;">
                </div>
            </div>

            <div class="d-flex justify-content-between align-items-center pt-2">
                <div>
                    <button type="button" id="choose-bills" class="btn btn-outline-secondary btn-sm" hidden data-toggle="modal" data-target="#allocation-modal">
                        <i class="dripicons-view-list mr-1"></i> Choose Bills for Allocation
                    </button>
                    <div id="voucher-allocations"></div>
                    <span id="allocation-summary" class="text-muted small"></span>
                </div>
                <button type="submit" class="btn btn-primary btn-sm px-4 font-weight-bold" id="post-voucher" disabled style="background:#7c3aed; border-color:#7c3aed; height:34px;">
                    Post Voucher (Ctrl+Enter)
                </button>
            </div>
        </form>
    </div>

    <!-- Template for dynamic lines -->
    <template id="voucher-line-template">
        <tr>
            <td>
                <select class="form-control form-control-sm" data-field="chart_of_account_id" required style="height:30px; font-size:12px;">
                    <option value="">Choose account</option>
                    @foreach($accounts as $account)
                        <option value="{{ $account->id }}" data-control="{{ $account->control_type }}">{{ $account->code }} — {{ $account->name }}</option>
                    @endforeach
                </select>
            </td>
            <td>
                <select class="form-control form-control-sm" data-party style="height:30px; font-size:12px;">
                    <option value="">No party</option>
                    @foreach($customers as $party)<option value="customer:{{ $party->id }}">Customer: {{ $party->name }}</option>@endforeach
                    @foreach($suppliers as $party)<option value="supplier:{{ $party->id }}">Supplier: {{ $party->name }}</option>@endforeach
                </select>
                <input type="hidden" data-field="partner_type">
                <input type="hidden" data-field="partner_id">
            </td>
            <td><input class="form-control form-control-sm text-right font-monospace" type="number" step="0.0001" min="0" data-field="debit" value="0" style="min-width: 7rem; height:30px; font-size:12px;"></td>
            <td><input class="form-control form-control-sm text-right font-monospace" type="number" step="0.0001" min="0" data-field="credit" value="0" style="min-width: 7rem; height:30px; font-size:12px;"></td>
            <td><input class="form-control form-control-sm" data-field="memo" maxlength="255" placeholder="Line memo" style="height:30px; font-size:12px;"></td>
            <td class="text-center align-middle"><button type="button" class="btn btn-sm btn-link text-danger p-0 font-weight-bold" data-remove style="font-size:16px;">&times;</button></td>
        </tr>
    </template>

    <!-- Allocation Modal -->
    <div class="modal fade" id="allocation-modal" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="allocation-title">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content" style="border-radius: 8px; overflow: hidden; border: 1px solid #cbd5e1;">
                <div class="modal-header" style="background:#0f172a; color:#fff; padding: 12px 18px;">
                    <h5 class="modal-title font-weight-bold" id="allocation-title" style="font-size: 15px; color:#fff;">Allocate Against Outstanding Bills</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="color:#fff; opacity:0.8;"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body" style="padding: 16px 20px;">
                    <p class="text-muted small mb-3">Select a matching voucher line and amount for each bill. Account and party must agree.</p>
                    <div class="table-responsive">
                        <table class="table table-bordered table-sm mb-0">
                            <thead style="background:#f1f5f9; font-size:11px; text-transform:uppercase;">
                                <tr>
                                    <th scope="col">Bill</th>
                                    <th scope="col">Party</th>
                                    <th scope="col" class="text-right">Open Amount</th>
                                    <th scope="col">Voucher Line</th>
                                    <th scope="col" class="text-right">Allocate Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($openItems as $item)
                                    <tr data-bill-id="{{ $item->id }}" data-account-id="{{ $item->account_id }}" data-party="{{ $item->party_type }}:{{ $item->party_id }}" data-open="{{ $item->open_amount }}">
                                        <td class="font-weight-bold font-monospace text-primary">{{ $item->document_no }}</td>
                                        <td>{{ $item->party_type }} #{{ $item->party_id }}</td>
                                        <td class="text-right font-monospace font-weight-600">₹ {{ number_format((float)$item->open_amount, 2) }}</td>
                                        <td><select class="form-control form-control-sm" data-bill-line aria-label="Voucher line for {{ $item->document_no }}" style="height:28px; font-size:11px;"><option value="">Skip bill</option></select></td>
                                        <td><input type="number" class="form-control form-control-sm text-right font-monospace" step="0.0001" min="0" max="{{ abs((float) $item->open_amount) }}" data-bill-amount aria-label="Amount for {{ $item->document_no }}" value="0" style="height:28px; font-size:11px;"></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="text-center text-muted py-3">No open bills in this branch.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer border-0 p-3" style="background:#f8fafc;">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary btn-sm font-weight-bold" id="apply-allocations" style="background:#7c3aed; border-color:#7c3aed;">Apply Allocations</button>
                </div>
            </div>
        </div>
    </div>
    @else
        <div class="alert alert-info">Ask a company administrator for voucher posting permission.</div>
    @endif

    <!-- Posted Journal History -->
    <details class="desk-card-wrapper voucher-history">
        <summary class="font-weight-bold" style="cursor:pointer; font-size:13px;">
            <i class="dripicons-history text-primary mr-1"></i> Posted Journal History
        </summary>
        <div class="table-responsive">
            <table class="table vh-table mb-0">
                <thead>
                    <tr>
                        <th scope="col">Number</th>
                        <th scope="col">Date</th>
                        <th scope="col">Narration</th>
                        <th scope="col" class="text-right">Debit / Credit</th>
                        <th scope="col">History &amp; Details</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($entries as $entry)
                        <tr>
                            <td class="font-weight-bold font-monospace text-primary">{{ $entry->entry_number }}</td>
                            <td>{{ $entry->entry_date->format('Y-m-d') }}</td>
                            <td>{{ $entry->description }}</td>
                            <td class="text-right font-monospace font-weight-600">
                                ₹ {{ number_format((float)$entry->total_debit, 2) }} / ₹ {{ number_format((float)$entry->total_credit, 2) }}
                            </td>
                            <td>
                                <details>
                                    <summary class="font-weight-600 text-primary" style="cursor:pointer; font-size: 11.5px;">Details &amp; Lines</summary>
                                    <ul class="mt-2 mb-2 pl-3 small text-muted">
                                        @foreach($entry->items as $line)
                                            <li>{{ $line->account->name }}: Dr ₹ {{ number_format((float)$line->debit, 2) }} / Cr ₹ {{ number_format((float)$line->credit, 2) }}</li>
                                        @endforeach
                                    </ul>
                                    @if($entry->reversal_of_id)
                                        <p class="text-muted small mb-0 font-weight-bold">Reversal of journal #{{ $entry->reversal_of_id }}</p>
                                    @elseif($canManage)
                                        <form method="POST" action="{{ route('accounting.journal.reverse', $entry->id) }}" class="mt-2 p-2 rounded" style="background:#fee2e2; border:1px solid #fca5a5;">
                                            @csrf
                                            <div class="form-row align-items-center">
                                                <div class="col-md-4">
                                                    <label for="reverse-date-{{ $entry->id }}" class="small font-weight-600 mb-0">Reversal Date</label>
                                                    <input class="form-control form-control-sm" id="reverse-date-{{ $entry->id }}" type="date" name="entry_date" required style="height:26px; font-size:11px;">
                                                </div>
                                                <div class="col-md-5">
                                                    <label for="reverse-reason-{{ $entry->id }}" class="small font-weight-600 mb-0">Reason</label>
                                                    <input class="form-control form-control-sm" id="reverse-reason-{{ $entry->id }}" name="reason" maxlength="500" required placeholder="Reason for reversal" style="height:26px; font-size:11px;">
                                                </div>
                                                <div class="col-md-3 mt-3">
                                                    <button class="btn btn-outline-danger btn-sm w-100 font-weight-bold" type="submit" style="height:26px; font-size:10.5px; padding:0;">
                                                        Post Reversal
                                                    </button>
                                                </div>
                                            </div>
                                        </form>
                                    @endif
                                </details>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">No posted journals in this financial year.</td>
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
    </details>

    @if($canManage)
    <!-- Period Controls & Settlement Account Links -->
    <div class="desk-card-wrapper mt-3">
        <details>
            <summary class="font-weight-bold" style="cursor:pointer; color:#0f172a; font-size:14px;">
                <i class="dripicons-lock mr-1"></i> Period controls and settlement account links
            </summary>
            <div class="mt-3 pt-3 border-top">
                <form method="POST" action="{{ route('accounting.period') }}" class="mb-4">
                    @csrf
                    <div class="row" style="margin: 0 -6px;">
                        <div class="col-md-3 form-group" style="padding: 0 6px;">
                            <label for="period-action" class="font-weight-600 mb-1" style="font-size: 11px; text-transform: uppercase; color: #64748b;">Action</label>
                            <select name="action" id="period-action" class="form-control form-control-sm" style="height:32px; font-size:12px;">
                                <option value="lock">Lock through date</option>
                                <option value="unlock" title="Reopening a closed year reverses its transfer to Retained Earnings">Unlock / reopen year</option>
                                <option value="close" title="Moves the year's profit or loss into Retained Earnings and locks the year">Close financial year (profit to Retained Earnings)</option>
                            </select>
                        </div>
                        <div class="col-md-3 form-group" style="padding: 0 6px;">
                            <label for="period-date" class="font-weight-600 mb-1" style="font-size: 11px; text-transform: uppercase; color: #64748b;">Lock Date</label>
                            <input type="date" name="lock_date" id="period-date" class="form-control form-control-sm" style="height:32px; font-size:12px;">
                        </div>
                        <div class="col-md-4 form-group" style="padding: 0 6px;">
                            <label for="period-reason" class="font-weight-600 mb-1" style="font-size: 11px; text-transform: uppercase; color: #64748b;">Reason</label>
                            <input name="reason" id="period-reason" class="form-control form-control-sm" maxlength="500" required placeholder="Reason for action" style="height:32px; font-size:12px;">
                        </div>
                        <div class="col-md-2 form-group align-self-end" style="padding: 0 6px;">
                            <button class="btn btn-outline-primary btn-sm w-100 font-weight-bold" type="submit" style="height:32px; font-size:12px;">Update Period</button>
                        </div>
                    </div>
                </form>

                <h6 class="font-weight-bold mb-1" style="font-size: 13px; color: #0f172a;">Settlement Account Links</h6>
                <p class="text-muted small mb-3">Unlinked accounts use the default company cash or bank mapping.</p>
                @foreach($legacyAccounts as $legacy)
                    <form method="POST" action="{{ route('accounting.settlement-accounts.link', $legacy->id) }}" class="form-group mb-2">
                        @csrf
                        <div class="d-flex align-items-center" style="gap: 10px;">
                            <label for="account-link-{{ $legacy->id }}" class="mb-0 font-weight-600" style="width: 220px; font-size: 12px;">{{ $legacy->name }}</label>
                            <select name="chart_of_account_id" id="account-link-{{ $legacy->id }}" class="form-control form-control-sm" required style="height:30px; font-size:12px; max-width: 380px;">
                                <option value="">Select cash or bank account</option>
                                @foreach($accounts->whereIn('control_type', ['cash', 'bank']) as $account)
                                    <option value="{{ $account->id }}" @selected($legacy->chart_of_account_id == $account->id)>{{ $account->name }}</option>
                                @endforeach
                            </select>
                            <button type="submit" class="btn btn-outline-primary btn-sm font-weight-bold" style="height:30px; font-size:11px; padding: 0 14px;">Link</button>
                        </div>
                    </form>
                @endforeach
            </div>
        </details>
    </div>
    @endif
</section>

@endsection

@push('scripts')
@if($canPost)
    <script type="application/json" id="previous-voucher-lines">@json(old('items', []))</script>
    <script src="{{ asset('js/accounting-vouchers.js') }}?v={{ filemtime(public_path('js/accounting-vouchers.js')) }}"></script>
@endif
@endpush
