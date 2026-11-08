@extends('backend.layout.main')
@section('content')

@push('css')
<link rel="stylesheet" href="{{ versioned_asset('css/pages/due-hub.css') }}">
@endpush

<div class="due-hub-workspace">
    <!-- Top Command Bar -->
    <div class="due-hub-header">
        <div>
            <h1 class="due-hub-title">
                <i class="dripicons-wallet text-primary"></i>
                Payments &amp; Due Management Command Center
            </h1>
            <div class="small text-muted mt-1">
                Fast operational settlement hub for customer receipts, supplier payments, and operational dues with audit history.
            </div>
        </div>

        <!-- 3 Primary Unified Tabs -->
        <div class="due-hub-nav">
            <a href="{{ route('dues.index', ['tab' => 'sales']) }}" class="tab-sales {{ $tab === 'sales' ? 'active' : '' }}" title="Shortcut: Alt+1">
                <i class="dripicons-cart"></i>
                <span>Customer Dues (Receivables)</span>
            </a>
            <a href="{{ route('dues.index', ['tab' => 'purchases']) }}" class="tab-purchases {{ $tab === 'purchases' ? 'active' : '' }}" title="Shortcut: Alt+2">
                <i class="dripicons-card"></i>
                <span>Supplier Dues (Payables)</span>
            </a>
            <a href="{{ route('dues.index', ['tab' => 'expenses']) }}" class="tab-expenses {{ $tab === 'expenses' ? 'active' : '' }}" title="Shortcut: Alt+3">
                <i class="dripicons-briefcase"></i>
                <span>Other Dues (Expenses & Services)</span>
            </a>
        </div>

        <div class="d-flex align-items-center">
            <a href="{{ route('report.outstandingDues', ['type' => $tab === 'purchases' ? 'payables' : ($tab === 'expenses' ? 'expenses' : 'receivables')]) }}" class="btn-report-link" title="Open Printable Audit Report">
                <i class="dripicons-document"></i>
                <span>Outstanding Dues Report</span>
            </a>
        </div>
    </div>

    <!-- Executive KPI Bar -->
    <div class="due-hub-kpi-bar">
        <div class="due-kpi-card highlight-due">
            <div>
                <div class="due-kpi-title">Active Outstanding Due</div>
                <div class="due-kpi-num text-purple" id="kpi-total-due">
                    {{ $generalSetting->currency ?? '₹' }} {{ number_format($totalDue, 2) }}
                </div>
            </div>
            <div class="text-primary font-weight-bold u-fs-20px">
                <i class="dripicons-wallet"></i>
            </div>
        </div>

        <div class="due-kpi-card highlight-overdue">
            <div>
                <div class="due-kpi-title">Overdue Amount</div>
                <div class="due-kpi-num text-danger" id="kpi-total-overdue">
                    {{ $generalSetting->currency ?? '₹' }} {{ number_format($totalOverdue, 2) }}
                </div>
            </div>
            <div class="text-danger font-weight-bold u-fs-20px">
                <i class="dripicons-warning"></i>
            </div>
        </div>

        <div class="due-kpi-card">
            <div>
                <div class="due-kpi-title">Active Documents</div>
                <div class="due-kpi-num" id="kpi-bill-count">
                    {{ $rows->count() }} <span class="small font-weight-normal text-muted">bills</span>
                </div>
            </div>
            <div class="text-secondary font-weight-bold u-fs-20px">
                <i class="dripicons-stack"></i>
            </div>
        </div>

        <div class="due-kpi-card">
            <div>
                <div class="due-kpi-title">Active Parties</div>
                <div class="due-kpi-num" id="kpi-party-count">
                    {{ $uniqueParties->count() }} <span class="small font-weight-normal text-muted">parties</span>
                </div>
            </div>
            <div class="text-info font-weight-bold u-fs-20px">
                <i class="dripicons-user"></i>
            </div>
        </div>
    </div>

    <!-- Master-Detail Split Workspace -->
    <div class="due-hub-grid">
        <!-- Left Panel: Due Documents Table (65%) -->
        <div class="due-list-panel">
            <!-- Toolbar & Fast Search: Inline Toolbar -->
            <div class="due-panel-toolbar">
                <form method="GET" action="{{ route('dues.index') }}" class="due-toolbar-form">
                    <input type="hidden" name="tab" value="{{ $tab }}">
                    <input type="hidden" name="view" value="{{ $viewMode }}">

                    <!-- Search Input: Flex-enclosed so icon never overlaps text -->
                    <div class="due-search-box">
                        <span class="due-search-icon-prefix"><i class="fa fa-search"></i></span>
                        <input type="text" name="search" id="quick-search-input" class="due-search-input-field" placeholder="Search reference, party, phone... (Alt+F)" value="{{ $search }}" autocomplete="off">
                        <span class="due-search-badge">Alt+F</span>
                    </div>

                    <!-- Party Filter -->
                    @if($tab === 'sales')
                        <div class="due-filter-wrap due-party-filter-wrap">
                            <select name="party_id" class="due-filter-select" onchange="this.form.submit()">
                                <option value="">— All Customers —</option>
                                @foreach($customers as $c)
                                    <option value="{{ $c->id }}" @selected($partyId == $c->id)>{{ $c->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @elseif($tab === 'purchases')
                        <div class="due-filter-wrap due-party-filter-wrap">
                            <select name="party_id" class="due-filter-select" onchange="this.form.submit()">
                                <option value="">— All Suppliers —</option>
                                @foreach($suppliers as $s)
                                    <option value="{{ $s->id }}" @selected($partyId == $s->id)>{{ $s->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    <!-- Due Status Filter -->
                    <div class="due-filter-wrap due-status-filter-wrap">
                        <select name="due_filter" class="due-filter-select" onchange="this.form.submit()">
                            <option value="all" @selected($dueFilter === 'all')>All Open Dues</option>
                            <option value="overdue" @selected($dueFilter === 'overdue')>Overdue Only</option>
                            <option value="unpaid" @selected($dueFilter === 'unpaid')>100% Unpaid</option>
                            <option value="partial" @selected($dueFilter === 'partial')>Partially Paid</option>
                        </select>
                    </div>

                    <!-- Bill-wise vs Party Grouped View Toggle: Color Coded with 8px Gap -->
                    <div class="due-toggle-wrap">
                        <a href="{{ route('dues.index', ['tab' => $tab, 'view' => 'bill_wise', 'party_id' => $partyId, 'due_filter' => $dueFilter, 'search' => $search]) }}" class="due-view-btn due-view-mode-btn due-btn-billwise {{ $viewMode === 'bill_wise' ? 'active' : '' }}" data-view-mode="bill_wise" title="Switch to Bill-wise view">
                            <i class="fa fa-list-alt"></i>
                            <span>Bill-wise</span>
                        </a>
                        <a href="{{ route('dues.index', ['tab' => $tab, 'view' => 'party_wise', 'party_id' => $partyId, 'due_filter' => $dueFilter, 'search' => $search]) }}" class="due-view-btn due-view-mode-btn due-btn-partywise {{ $viewMode === 'party_wise' ? 'active' : '' }}" data-view-mode="party_wise" title="Switch to Party Grouped view">
                            <i class="fa fa-users"></i>
                            <span>Party Grouped</span>
                        </a>
                    </div>
                </form>
            </div>

            <!-- Table Container -->
            <div class="table-responsive">
                @if($viewMode === 'bill_wise')
                    <table class="table table-hover table-sm align-middle mb-0" id="due-documents-table">
                        <thead class="bg-light">
                            <tr>
                                <th class="u-w-32px text-center">
                                    <input type="checkbox" id="check-all-bills" title="Select all bills for batch settlement">
                                </th>
                                <th>Date</th>
                                <th>Reference</th>
                                <th>Party Details</th>
                                <th>Due Date</th>
                                <th class="text-right">Billed</th>
                                <th class="text-right">Paid</th>
                                <th class="text-right font-weight-bold">Due Amount</th>
                                <th class="u-w-80px text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                                $partyColorPalette = [];
                                $nextColorIdx = 0;
                            @endphp
                            @forelse($rows as $r)
                                @php
                                    $pid = (string) $r->party_id;
                                    if (!isset($partyColorPalette[$pid])) {
                                        $partyColorPalette[$pid] = $nextColorIdx % 5;
                                        $nextColorIdx++;
                                    }
                                    $cIdx = $partyColorPalette[$pid];
                                @endphp
                                <tr class="due-row party-tint-{{ $cIdx }} cursor-pointer" data-bill-id="{{ $r->id }}" data-kind="{{ $tab === 'sales' ? 'sale' : ($tab === 'purchases' ? 'purchase' : 'expense') }}" data-party-id="{{ $r->party_id }}" data-party-name="{{ $r->party_name }}" data-ref="{{ $r->reference_no }}" data-due="{{ $r->due_amt }}">
                                    <td class="text-center" onclick="event.stopPropagation()">
                                        <input type="checkbox" class="bill-select-checkbox" value="{{ $r->id }}" data-party-id="{{ $r->party_id }}" data-party-name="{{ $r->party_name }}" data-due="{{ $r->due_amt }}" data-ref="{{ $r->reference_no }}">
                                    </td>
                                    <td>{{ date('d-m-Y', strtotime($r->created_at)) }}</td>
                                    <td>
                                        <span class="font-weight-bold font-monospace text-primary">{{ $r->reference_no }}</span>
                                    </td>
                                    <td>
                                        <div class="font-weight-600 text-dark">{{ $r->party_name }}</div>
                                        @if($r->party_phone)
                                            <small class="text-muted"><i class="fa fa-phone mr-1"></i>{{ $r->party_phone }}</small>
                                        @endif
                                    </td>
                                    <td>
                                        <div>{{ $r->due_date_formatted }}</div>
                                        @if($r->is_overdue)
                                            <span class="badge-due-overdue"><i class="fa fa-exclamation-circle"></i> {{ $r->days_overdue }}d Overdue</span>
                                        @elseif($r->due_amt > 0.009)
                                            <span class="badge-due-left">Due in {{ $r->days_left }}d</span>
                                        @endif
                                    </td>
                                    <td class="text-right">{{ number_format($r->billed_amt, 2) }}</td>
                                    <td class="text-right text-muted">{{ number_format($r->paid_amt, 2) }}</td>
                                    <td class="text-right font-weight-bold text-danger">
                                        {{ number_format($r->due_amt, 2) }}
                                    </td>
                                    <td class="text-center" onclick="event.stopPropagation()">
                                        <button type="button" class="btn-trigger-settle" data-bill-id="{{ $r->id }}" data-kind="{{ $tab === 'sales' ? 'sale' : ($tab === 'purchases' ? 'purchase' : 'expense') }}" data-party-id="{{ $r->party_id }}" data-party-name="{{ $r->party_name }}" data-ref="{{ $r->reference_no }}" data-due="{{ $r->due_amt }}">
                                            <i class="fa fa-bolt"></i>
                                            <span>Settle</span>
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="text-center py-5 text-muted">
                                        <div class="mb-2"><i class="dripicons-checkmark text-success u-fs-28px"></i></div>
                                        <div class="font-weight-bold">Zero Outstanding Dues!</div>
                                        <div class="small">All vouchers are fully settled in this category.</div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                @else
                    <!-- Party-wise Grouped View -->
                    <table class="table table-hover table-sm align-middle mb-0" id="party-groups-table">
                        <thead class="bg-light">
                            <tr>
                                <th>Party Name</th>
                                <th>Contact / City</th>
                                <th class="text-center">Open Bills</th>
                                <th class="text-right">Total Billed</th>
                                <th class="text-right">Total Paid</th>
                                <th class="text-right font-weight-bold">Total Due</th>
                                <th class="u-w-100px text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($partyGroups as $p)
                                <tr class="party-group-row cursor-pointer" data-party-id="{{ $p->party_id }}" data-party-name="{{ $p->party_name }}" data-total-due="{{ $p->total_due }}" data-bills="{{ json_encode($p->bills) }}" data-kind="{{ $tab === 'sales' ? 'sale' : 'purchase' }}">
                                    <td>
                                        <span class="font-weight-bold text-dark">{{ $p->party_name }}</span>
                                        @if($p->has_overdue)
                                            <span class="badge badge-danger ml-1">Overdue Bills</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div>{{ $p->party_phone ?: '—' }}</div>
                                        <small class="text-muted">{{ $p->party_city }}</small>
                                    </td>
                                    <td class="text-center font-weight-bold party-bill-count">{{ $p->bill_count }}</td>
                                    <td class="text-right">{{ number_format($p->total_billed, 2) }}</td>
                                    <td class="text-right text-muted">{{ number_format($p->total_paid, 2) }}</td>
                                    <td class="text-right font-weight-bold text-danger party-total-due">
                                        {{ number_format($p->total_due, 2) }}
                                    </td>
                                    <td class="text-center" onclick="event.stopPropagation()">
                                        <button type="button" class="btn-party-settle-all" data-party-id="{{ $p->party_id }}" data-party-name="{{ $p->party_name }}" data-total-due="{{ $p->total_due }}" data-bills="{{ json_encode($p->bills) }}" data-kind="{{ $tab === 'sales' ? 'sale' : 'purchase' }}">
                                            <i class="fa fa-bolt"></i>
                                            <span>Clear All</span>
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center py-5 text-muted">
                                        No party dues found.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                @endif
            </div>
        </div>

        <!-- Right Panel: Fast Settlement Panel (35%) -->
        <div class="due-fast-drawer" id="fast-settlement-drawer">
            <div class="due-drawer-header">
                <h3 class="due-drawer-title">
                    <i class="dripicons-bolt text-primary"></i>
                    Fast Due Settlement
                </h3>
                <span class="badge badge-light border text-muted small" id="drawer-mode-badge">Single Bill</span>
            </div>

            <div class="due-drawer-body">
                <!-- Target Information Card -->
                <div class="due-target-card" id="drawer-target-card">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="font-weight-bold text-dark" id="drawer-party-name">— Choose a Bill —</span>
                        <span class="font-weight-bold font-monospace text-primary" id="drawer-ref-no">—</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-muted small">Outstanding Balance:</span>
                        <span class="font-weight-800 text-danger u-fs-16px" id="drawer-due-amount">₹ 0.00</span>
                    </div>
                    <div class="small text-muted mt-1 d-none" id="drawer-batch-info">
                        <i class="fa fa-layer-group text-primary mr-1"></i> Multi-bill batch settlement mode (<span id="drawer-batch-count">0</span> bills selected).
                    </div>
                </div>

                <!-- Settlement Form -->
                <form id="fast-settlement-form" onsubmit="return false;">
                    @csrf
                    <input type="hidden" name="kind" id="form-kind" value="{{ $tab === 'sales' ? 'sale' : ($tab === 'purchases' ? 'purchase' : 'expense') }}">
                    <input type="hidden" name="bill_id" id="form-bill-id" value="">
                    <input type="hidden" name="party_id" id="form-party-id" value="">
                    <input type="hidden" name="bill_ids" id="form-bill-ids" value="">

                    <!-- Amount & Presets -->
                    <div class="form-group mb-2">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label class="due-form-label mb-0">Paying Amount (₹) *</label>
                            <div class="btn-group btn-group-sm">
                                <button type="button" class="btn-preset-pill" id="btn-preset-full">Full</button>
                                <button type="button" class="btn-preset-pill ml-1" id="btn-preset-half">50%</button>
                            </div>
                        </div>
                        <input type="number" step="0.01" min="0.01" name="amount" id="form-amount" class="due-form-input font-weight-bold u-fs-16px text-dark" placeholder="0.00" required>
                    </div>

                    <!-- Payment Date & Method -->
                    <div class="form-row mb-2">
                        <div class="col-6">
                            <label class="due-form-label">Date *</label>
                            <input type="date" name="payment_at" id="form-date" class="due-form-input" value="{{ date('Y-m-d') }}" required>
                        </div>
                        <div class="col-6">
                            <label class="due-form-label">Method *</label>
                            <select name="paying_method" id="form-method" class="due-form-input" required>
                                <option value="Cash">Cash</option>
                                <option value="Bank">Bank Transfer / UPI</option>
                                <option value="Cheque">Cheque</option>
                                <option value="Credit Card">Card</option>
                            </select>
                        </div>
                    </div>

                    <!-- Settlement Account -->
                    <div class="form-group mb-2">
                        <label class="due-form-label">Deposit / Payment Account *</label>
                        <select name="account_id" id="form-account" class="due-form-input" required>
                            @foreach($accounts as $acc)
                                <option value="{{ $acc['id'] }}" data-type="{{ $acc['type'] ?? 'all' }}">{{ $acc['name'] }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Optional Deductions Toggle -->
                    <div class="mb-2">
                        <button type="button" class="btn btn-link btn-sm p-0 small font-weight-bold" data-toggle="collapse" data-target="#discount-drawer">
                            + Add Cash Discount / TDS (Optional)
                        </button>
                        <div class="collapse mt-2" id="discount-drawer">
                            <div class="form-row">
                                <div class="col-6">
                                    <label class="due-form-label">Discount (₹)</label>
                                    <input type="number" step="0.01" min="0" name="discount" id="form-discount" class="due-form-input" placeholder="0.00">
                                </div>
                                <div class="col-6">
                                    <label class="due-form-label">TDS Amount (₹)</label>
                                    <input type="number" step="0.01" min="0" name="tds_amount" id="form-tds" class="due-form-input" placeholder="0.00">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Reference & Remarks -->
                    <div class="form-group mb-3">
                        <label class="due-form-label">Reference / Note</label>
                        <input type="text" name="payment_note" id="form-note" class="due-form-input" placeholder="e.g. Chq #09812, UTR, remarks">
                    </div>

                    <!-- Action Submit -->
                    <button type="button" id="btn-submit-settlement" class="btn shadow-sm">
                        <i class="fa fa-check"></i>
                        <span>Record Settlement (Ctrl+Enter)</span>
                    </button>
                </form>

                <!-- Feedback Toast -->
                <div class="alert alert-success mt-3 py-2 px-3 d-none" id="settlement-success-alert">
                    <div class="font-weight-bold small d-flex align-items-center justify-content-between">
                        <span class="d-inline-flex align-items-center gap-1"><i class="fa fa-check-circle"></i> Settlement Recorded!</span>
                        <a href="#" target="_blank" id="btn-print-voucher" class="btn btn-sm btn-light border py-0 px-2 font-weight-bold">
                            <i class="dripicons-print mr-1"></i> Print Voucher
                        </a>
                    </div>
                </div>

                <!-- FIFO Pending Bills Breakdown (Party Grouped Mode) -->
                <div class="fifo-breakdown-card d-none" id="fifo-breakdown-panel">
                    <div class="fifo-header">
                        <div class="fifo-title">
                            <i class="fa fa-layer-group text-primary mr-1"></i>
                            <span>Pending Bills (Oldest First · FIFO)</span>
                        </div>
                        <div class="fifo-summary-chips" id="fifo-summary-counts"></div>
                    </div>
                    <div class="fifo-bills-scroll" id="fifo-bills-list">
                        <!-- Dynamically populated by renderFifoBreakdown() -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Historical Payments & Receipts Register (Full Width Audit Trail) -->
    <div class="due-history-card">
        <div class="due-history-header">
            <div class="d-flex align-items-center gap-2">
                <div class="due-history-icon">
                    <i class="fa fa-history"></i>
                </div>
                <div>
                    <h4 class="due-history-title">
                        @if($tab === 'sales')
                            Recent Customer Receipts &amp; Settlement History
                        @elseif($tab === 'purchases')
                            Recent Supplier Payment Vouchers &amp; Settlement History
                        @else
                            Recent Operational Expense Payments &amp; Settlement History
                        @endif
                    </h4>
                    <div class="due-history-subtitle">
                        Audit register of settled vouchers and allocations with 1-click printable receipts.
                    </div>
                </div>
            </div>
            <div class="badge badge-light border text-muted px-2 py-1 font-weight-bold">
                {{ $historicalPayments->count() }} Recent Records
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover table-sm align-middle mb-0" id="due-historical-payments-table">
                <thead class="bg-light">
                    <tr>
                        <th class="u-w-40px text-center">#</th>
                        <th>Date &amp; Time</th>
                        <th>Voucher Ref</th>
                        <th>Linked Document</th>
                        <th>Party Details</th>
                        <th>Payment Method &amp; Account</th>
                        <th>Reference / Note</th>
                        <th class="text-right">Amount Settled</th>
                        <th class="u-w-110px text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($historicalPayments as $idx => $hp)
                        @php
                            $hpPartyName = '—';
                            $hpPartyPhone = '';
                            $hpDocRef = '—';
                            if ($hp->sale) {
                                $hpPartyName = $hp->sale->customer?->name ?? 'Walk-in Customer';
                                $hpPartyPhone = $hp->sale->customer?->phone_number ?? '';
                                $hpDocRef = $hp->sale->reference_no;
                            } elseif ($hp->purchase) {
                                $hpPartyName = $hp->purchase->supplier?->name ?? 'Unknown Supplier';
                                $hpPartyPhone = $hp->purchase->supplier?->phone_number ?? '';
                                $hpDocRef = $hp->purchase->reference_no;
                            }
                            $pDate = $hp->payment_at ? date('d-m-Y', strtotime($hp->payment_at)) : date('d-m-Y', strtotime($hp->created_at));
                            $pTime = date('h:i A', strtotime($hp->payment_at ?? $hp->created_at));
                        @endphp
                        <tr>
                            <td class="text-center text-muted small">{{ $idx + 1 }}</td>
                            <td>
                                <div class="font-weight-600">{{ $pDate }}</div>
                                <small class="text-muted">{{ $pTime }}</small>
                            </td>
                            <td>
                                <span class="font-weight-bold font-monospace text-primary">{{ $hp->payment_reference ?: ('PAY-'.$hp->id) }}</span>
                            </td>
                            <td>
                                <span class="badge badge-light border font-monospace">{{ $hpDocRef }}</span>
                            </td>
                            <td>
                                <div class="font-weight-600 text-dark">{{ $hpPartyName }}</div>
                                @if($hpPartyPhone)
                                    <small class="text-muted"><i class="fa fa-phone mr-1"></i>{{ $hpPartyPhone }}</small>
                                @endif
                            </td>
                            <td>
                                <div>{{ $hp->account?->name ?? 'General Account' }}</div>
                                <span class="badge badge-secondary small font-weight-normal">{{ $hp->paying_method }}</span>
                            </td>
                            <td class="small text-muted">
                                {{ $hp->payment_note ?: '—' }}
                            </td>
                            <td class="text-right font-weight-bold text-success u-fs-14px">
                                {{ $generalSetting->currency ?? '₹' }} {{ number_format($hp->amount, 2) }}
                            </td>
                            <td class="text-center">
                                <a href="{{ route('bill-payments.show', $hp->id) }}" target="_blank" class="btn-voucher-print" title="View &amp; Print Voucher">
                                    <i class="dripicons-print mr-1"></i>
                                    <span>Voucher</span>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center py-4 text-muted">
                                <div class="mb-1"><i class="dripicons-document text-muted u-fs-24px"></i></div>
                                <div class="font-weight-bold">Zero Historical Settlements</div>
                                <div class="small">No settled vouchers recorded yet in this category.</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@push('scripts')
<script type="text/javascript">
$(document).ready(function() {
    const STORAGE_KEY = 'zolo_dues_view_mode';
    const currentViewMode = '{{ $viewMode }}';

    // View mode persistence via localStorage: redirect if URL does not specify view and stored preference differs
    const urlParams = new URLSearchParams(window.location.search);
    if (!urlParams.has('view')) {
        const stored = localStorage.getItem(STORAGE_KEY);
        if (stored && (stored === 'bill_wise' || stored === 'party_wise') && stored !== currentViewMode) {
            urlParams.set('view', stored);
            window.location.search = urlParams.toString();
            return;
        }
    }

    // Save view mode preference on toggle click
    $('.due-view-mode-btn').on('click', function() {
        const mode = $(this).data('view-mode');
        if (mode) {
            localStorage.setItem(STORAGE_KEY, mode);
        }
    });

    let activeDue = 0;
    let isBatchMode = false;
    let isPartyMode = currentViewMode === 'party_wise';
    let currentPartyBills = [];

    // Helper: format currency
    function formatMoney(amount) {
        return '₹ ' + parseFloat(amount).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    }

    // Filter accounts by payment method
    function filterAccounts() {
        const method = $('#form-method').val();
        $('#form-account option').each(function() {
            const accType = $(this).data('type') || '';
            if (method === 'Cash') {
                $(this).toggle(accType === 'cash' || accType === '' || accType === 'all');
            } else {
                $(this).toggle(accType === 'bank' || accType === '' || accType === 'all');
            }
        });
        const visibleFirst = $('#form-account option:visible').first().val();
        if (visibleFirst) $('#form-account').val(visibleFirst);
    }
    $('#form-method').on('change', filterAccounts);
    filterAccounts();

    // Render FIFO Breakdown (Date-wise Pending Bills & Clearance Simulation)
    function renderFifoBreakdown(bills, currentPayAmount) {
        if (!bills || !bills.length) {
            $('#fifo-breakdown-panel').addClass('d-none');
            return;
        }

        let remainingPool = Math.max(0, parseFloat(currentPayAmount) || 0);
        let countCleared = 0;
        let countPartial = 0;
        let countUnpaid = 0;

        let html = '';
        bills.forEach(function(b) {
            const billDue = parseFloat(b.due) || 0;
            let alloc = 0;
            let statusClass = 'fifo-item-unpaid';
            let statusBadge = '';
            let remainingBillDue = billDue;

            if (remainingPool > 0.009) {
                if (remainingPool >= (billDue - 0.009)) {
                    alloc = billDue;
                    remainingPool -= billDue;
                    remainingBillDue = 0;
                    statusClass = 'fifo-item-cleared';
                    statusBadge = '<span class="badge badge-success px-2 py-1"><i class="fa fa-check mr-1"></i>Cleared</span>';
                    countCleared++;
                } else {
                    alloc = remainingPool;
                    remainingBillDue = billDue - alloc;
                    remainingPool = 0;
                    statusClass = 'fifo-item-partial';
                    statusBadge = '<span class="badge badge-warning text-dark px-2 py-1"><i class="fa fa-bolt mr-1"></i>Partial</span>';
                    countPartial++;
                }
            } else {
                statusClass = 'fifo-item-unpaid';
                statusBadge = '<span class="badge badge-secondary px-2 py-1"><i class="fa fa-clock-o mr-1"></i>Due</span>';
                countUnpaid++;
            }

            html += `
            <div class="fifo-bill-row ${statusClass}">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <div>
                        <span class="font-weight-bold font-monospace text-primary">${b.ref}</span>
                        <span class="small text-muted ml-2"><i class="fa fa-calendar-alt mr-1"></i>${b.date}</span>
                    </div>
                    ${statusBadge}
                </div>
                <div class="d-flex justify-content-between align-items-center small">
                    <div>
                        <span class="text-muted">Due: </span><span class="font-weight-600">₹ ${billDue.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>
                        ${alloc > 0.009 ? `<span class="text-success font-weight-bold ml-2">(+₹ ${alloc.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2})} paid)</span>` : ''}
                    </div>
                    <div>
                        <span class="text-muted">Balance: </span>
                        <span class="font-weight-bold ${remainingBillDue <= 0.009 ? 'text-success' : 'text-danger'}">
                            ₹ ${remainingBillDue.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2})}
                        </span>
                    </div>
                </div>
            </div>
            `;
        });

        $('#fifo-bills-list').html(html);

        let summaryHtml = '';
        if (countCleared > 0) summaryHtml += `<span class="badge badge-success mr-1">${countCleared} Cleared</span>`;
        if (countPartial > 0) summaryHtml += `<span class="badge badge-warning text-dark mr-1">${countPartial} Partial</span>`;
        if (countUnpaid > 0) summaryHtml += `<span class="badge badge-secondary">${countUnpaid} Due</span>`;
        $('#fifo-summary-counts').html(summaryHtml);
        $('#fifo-breakdown-panel').removeClass('d-none');
    }

    // Dynamic simulation on paying amount changes
    $('#form-amount').on('input keyup change', function() {
        if (isPartyMode && currentPartyBills && currentPartyBills.length) {
            renderFifoBreakdown(currentPartyBills, $(this).val());
        }
    });

    // Preset Buttons
    $('#btn-preset-full').on('click', function() {
        if (activeDue > 0) {
            $('#form-amount').val(activeDue.toFixed(2)).trigger('input');
        }
    });
    $('#btn-preset-half').on('click', function() {
        if (activeDue > 0) {
            $('#form-amount').val((activeDue / 2).toFixed(2)).trigger('input');
        }
    });

    // Party Group Selection Function
    function selectPartyGroup(el) {
        $('.party-group-row').removeClass('row-selected');
        const row = $(el).closest('.party-group-row');
        row.addClass('row-selected');

        isBatchMode = true;
        isPartyMode = true;

        const partyId = row.data('party-id');
        const partyName = row.data('party-name');
        const totalDue = parseFloat(row.data('total-due')) || 0;
        const kind = row.data('kind');
        let bills = row.data('bills') || [];
        if (typeof bills === 'string') {
            try { bills = JSON.parse(bills); } catch(e) { bills = []; }
        }

        currentPartyBills = bills;
        activeDue = totalDue;

        const billIds = bills.map(b => b.id);

        $('#drawer-mode-badge').text('Party Batch (' + bills.length + ' Bills)').removeClass('badge-light').addClass('badge-primary');
        $('#drawer-batch-info').removeClass('d-none');
        $('#drawer-batch-count').text(bills.length);

        $('#form-kind').val(kind);
        $('#form-party-id').val(partyId);
        $('#form-bill-ids').val(billIds.join(','));
        $('#form-bill-id').val(billIds.length ? billIds[0] : '');

        $('#drawer-party-name').text(partyName);
        $('#drawer-ref-no').text('Pending Bills (' + bills.length + ')');
        $('#drawer-due-amount').text(formatMoney(activeDue));
        $('#form-amount').val(activeDue.toFixed(2));

        $('#settlement-success-alert').addClass('d-none');

        renderFifoBreakdown(currentPartyBills, activeDue);
    }

    $('.party-group-row').on('click', function(e) {
        selectPartyGroup(this);
    });

    $('.btn-party-settle-all').on('click', function(e) {
        e.stopPropagation();
        selectPartyGroup(this);
        $('#form-amount').focus().select();
    });

    // Select Single Row (Bill-wise)
    $('.due-row, .btn-trigger-settle').on('click', function(e) {
        $('.due-row').removeClass('row-selected');
        const row = $(this).closest('.due-row');
        row.addClass('row-selected');

        isBatchMode = false;
        isPartyMode = false;
        currentPartyBills = [];
        $('#fifo-breakdown-panel').addClass('d-none');

        $('#drawer-mode-badge').text('Single Bill').removeClass('badge-primary').addClass('badge-light');
        $('#drawer-batch-info').addClass('d-none');

        const billId = row.data('bill-id');
        const kind = row.data('kind');
        const partyId = row.data('party-id');
        const partyName = row.data('party-name');
        const refNo = row.data('ref');
        activeDue = parseFloat(row.data('due')) || 0;

        $('#form-kind').val(kind);
        $('#form-bill-id').val(billId);
        $('#form-party-id').val(partyId);
        $('#form-bill-ids').val('');

        $('#drawer-party-name').text(partyName);
        $('#drawer-ref-no').text(refNo);
        $('#drawer-due-amount').text(formatMoney(activeDue));

        $('#form-amount').val(activeDue.toFixed(2)).focus();
        $('#settlement-success-alert').addClass('d-none');
    });

    // Check All Checkbox
    $('#check-all-bills').on('change', function() {
        const checked = $(this).is(':checked');
        $('.bill-select-checkbox').prop('checked', checked).trigger('change');
    });

    // Multi-Select Checkboxes (Batch Mode in Bill-wise)
    $('.bill-select-checkbox').on('change', function() {
        const selected = $('.bill-select-checkbox:checked');
        if (selected.length > 1) {
            isBatchMode = true;
            isPartyMode = false;
            currentPartyBills = [];
            $('#fifo-breakdown-panel').addClass('d-none');

            let sumDue = 0;
            let billIds = [];
            let partyName = selected.first().data('party-name');

            selected.each(function() {
                sumDue += parseFloat($(this).data('due')) || 0;
                billIds.push($(this).val());
            });

            activeDue = sumDue;
            $('#drawer-mode-badge').text('Batch (' + selected.length + ' Bills)').removeClass('badge-light').addClass('badge-primary');
            $('#drawer-batch-info').removeClass('d-none');
            $('#drawer-batch-count').text(selected.length);

            $('#form-bill-ids').val(billIds.join(','));
            $('#form-bill-id').val(selected.first().val());
            $('#form-party-id').val(selected.first().data('party-id'));

            $('#drawer-party-name').text(partyName);
            $('#drawer-ref-no').text(selected.length + ' Bills Selected');
            $('#drawer-due-amount').text(formatMoney(activeDue));
            $('#form-amount').val(activeDue.toFixed(2));
        } else if (selected.length === 1) {
            selected.closest('.due-row').trigger('click');
        }
    });

    // Submit Settlement Handler
    function submitSettlement() {
        const amount = parseFloat($('#form-amount').val()) || 0;
        if (amount <= 0) {
            alert('Please enter a valid payment amount greater than zero.');
            $('#form-amount').focus();
            return;
        }

        const btn = $('#btn-submit-settlement');
        btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin mr-1"></i> Recording...');

        const endpoint = isBatchMode ? "{{ route('dues.batchSettle') }}" : "{{ route('dues.settle') }}";
        const payload = {
            _token: $('meta[name="csrf-token"]').attr('content'),
            kind: $('#form-kind').val(),
            bill_id: $('#form-bill-id').val(),
            party_id: $('#form-party-id').val(),
            bill_ids: $('#form-bill-ids').val() ? $('#form-bill-ids').val().split(',') : [$('#form-bill-id').val()],
            amount: amount,
            total_amount: amount,
            payment_at: $('#form-date').val(),
            paying_method: $('#form-method').val(),
            account_id: $('#form-account').val(),
            payment_note: $('#form-note').val(),
            discount: $('#form-discount').val(),
            tds_amount: $('#form-tds').val()
        };

        $.ajax({
            url: endpoint,
            method: 'POST',
            data: payload,
            dataType: 'json',
            success: function(res) {
                btn.prop('disabled', false).html('<i class="fa fa-check mr-1"></i> Record Settlement (Ctrl+Enter)');
                if (res.success) {
                    $('#settlement-success-alert').removeClass('d-none');
                    if (res.voucher_url) {
                        $('#btn-print-voucher').attr('href', res.voucher_url).show();
                    } else {
                        $('#btn-print-voucher').hide();
                    }

                    if (isPartyMode) {
                        const pRow = $('.party-group-row[data-party-id="' + res.party_id + '"]');
                        if (res.new_party_due <= 0.009) {
                            pRow.fadeOut(400, function() { $(this).remove(); });
                            $('#drawer-due-amount').text('₹ 0.00');
                            $('#form-amount').val('0.00');
                            $('#fifo-breakdown-panel').addClass('d-none');
                        } else {
                            pRow.find('.party-total-due').text(res.new_party_due.toFixed(2));
                            pRow.find('.party-bill-count').text(res.open_bills_count);
                            pRow.data('total-due', res.new_party_due);
                            activeDue = res.new_party_due;
                            $('#drawer-due-amount').text(formatMoney(activeDue));
                            $('#form-amount').val(activeDue.toFixed(2));
                            setTimeout(function() { location.reload(); }, 900);
                        }
                    } else if (!isBatchMode) {
                        const row = $('.due-row[data-bill-id="' + payload.bill_id + '"]');
                        if (res.is_fully_paid) {
                            row.fadeOut(300, function() { $(this).remove(); });
                        } else {
                            row.find('.due-amt').text(res.remaining_due.toFixed(2));
                            row.data('due', res.remaining_due);
                        }
                    } else {
                        setTimeout(function() { location.reload(); }, 900);
                    }
                }
            },
            error: function(err) {
                btn.prop('disabled', false).html('<i class="fa fa-check mr-1"></i> Record Settlement (Ctrl+Enter)');
                const msg = err.responseJSON && err.responseJSON.message ? err.responseJSON.message : 'Error recording settlement.';
                alert(msg);
            }
        });
    }

    $('#btn-submit-settlement').on('click', submitSettlement);

    // Keyboard Shortcuts: Ctrl+Enter to submit, Alt+F to search
    $(document).on('keydown', function(e) {
        if (e.ctrlKey && e.key === 'Enter') {
            e.preventDefault();
            submitSettlement();
        } else if (e.altKey && e.key.toLowerCase() === 'f') {
            e.preventDefault();
            $('#quick-search-input').focus().select();
        }
    });

    // Auto-select on page load
    if (currentViewMode === 'party_wise') {
        const firstPartyRow = $('.party-group-row').first();
        if (firstPartyRow.length) {
            selectPartyGroup(firstPartyRow);
        }
    } else {
        const firstDueRow = $('.due-row').first();
        if (firstDueRow.length) {
            firstDueRow.trigger('click');
        }
    }
});
</script>
@endpush

@endsection

