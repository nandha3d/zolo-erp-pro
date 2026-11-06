@extends('backend.layout.main')
@section('content')

<x-error-message key="not_permitted" />

@php
    $general_setting = \Illuminate\Support\Facades\DB::table('general_settings')->latest()->first();
    $currency = \App\Models\Currency::find($general_setting->currency ?? 1);
    $currencySymbol = $currency->symbol ?? '₹';
    $customerName = 'All Customers';
    $customer = $ledgerData['party'] ?? null;
    if ($customer) {
        $customerName = $customer->name . ($customer->company_name ? ' (' . $customer->company_name . ')' : '');
    }
    $openingBal = (float)($ledgerData['opening_balance'] ?? 0);
    $totalDebits = (float)($ledgerData['total_debit'] ?? 0);
    $totalCredits = (float)($ledgerData['total_credit'] ?? 0);
    $closingBal = (float)($ledgerData['closing_balance'] ?? 0);
@endphp

<style>
.report-workspace-view {
    padding: 12px 18px 24px;
    background: #f8fafc;
    min-height: calc(100vh - 40px);
}
body.dark-mode .report-workspace-view {
    background: #0b0f19;
}

/* Global Command Bar & Nav Pills */
.comm-command-bar {
    display: flex !important;
    align-items: center !important;
    justify-content: space-between !important;
    background: var(--z-ink, #0b1329) !important;
    border: 1px solid #1e293b !important;
    border-radius: 6px !important;
    padding: 5px 12px !important;
    margin-bottom: 12px !important;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.25) !important;
    flex-shrink: 0 !important;
    gap: 8px !important;
    color: #f8fafc !important;
    flex-wrap: wrap !important;
}
.comm-title-group {
    display: flex !important;
    align-items: center !important;
    gap: 10px !important;
}
.comm-title-group h1 {
    font-size: 13.5px !important;
    font-weight: 700 !important;
    margin: 0 !important;
    color: #f8fafc !important;
    line-height: 1.2 !important;
    white-space: nowrap !important;
    display: flex !important;
    align-items: center !important;
    gap: 6px !important;
}
.comm-title-badge {
    font-size: 10px !important;
    font-weight: 600 !important;
    padding: 2px 8px !important;
    border-radius: 4px !important;
    background: #1e293b !important;
    color: #94a3b8 !important;
    border: 1px solid #334155 !important;
    white-space: nowrap !important;
}
.comm-nav-pills {
    display: flex !important;
    gap: 4px !important;
    margin: 0 !important;
    padding: 3px !important;
    list-style: none !important;
    background: #0f172a !important;
    border: 1px solid #334155 !important;
    border-radius: 6px !important;
}
.comm-nav-pills li {
    list-style: none !important;
    margin: 0 !important;
    padding: 0 !important;
}
.comm-nav-pills .nav-link {
    font-size: 11.5px !important;
    font-weight: 600 !important;
    padding: 4px 10px !important;
    border-radius: 4px !important;
    color: #cbd5e1 !important;
    border: none !important;
    transition: all 0.15s ease-in-out !important;
    display: flex !important;
    align-items: center !important;
    gap: 5px !important;
    cursor: pointer !important;
    text-decoration: none !important;
    white-space: nowrap !important;
}
.comm-nav-pills .nav-link:hover {
    background: #334155 !important;
    color: #ffffff !important;
}
.comm-nav-pills .nav-link.active {
    background: var(--z-primary, #7d287e) !important;
    color: #ffffff !important;
    box-shadow: 0 1px 3px rgba(125, 40, 126, 0.4) !important;
}
.comm-actions .btn-group .btn-secondary {
    background: #1e293b !important;
    border-color: #334155 !important;
    color: #f8fafc !important;
}
.comm-actions .btn-group .btn-secondary:hover {
    background: #334155 !important;
    color: #ffffff !important;
}

/* Global 5-KPI Strip */
.kpi-summary-strip {
    display: grid !important;
    grid-template-columns: repeat(5, minmax(0, 1fr)) !important;
    gap: 12px !important;
    margin-bottom: 14px !important;
}
@media (max-width: 1200px) {
    .kpi-summary-strip {
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)) !important;
    }
}
.kpi-card {
    background: #ffffff !important;
    border: 1px solid #e2e8f0 !important;
    border-radius: 8px !important;
    padding: 12px 16px !important;
    display: flex !important;
    align-items: center !important;
    justify-content: space-between !important;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03) !important;
}
body.dark-mode .kpi-card {
    background: #141c2e !important;
    border-color: #334155 !important;
}
.kpi-card .kpi-label {
    font-size: 10px !important;
    text-transform: uppercase !important;
    letter-spacing: 0.05em !important;
    font-weight: 700 !important;
    color: #64748b !important;
    margin-bottom: 2px !important;
}
.kpi-card .kpi-number {
    font-size: 16px !important;
    font-weight: 700 !important;
    color: #0f172a !important;
}
body.dark-mode .kpi-card .kpi-number {
    color: #f8fafc !important;
}

/* Filter Card & Desk Card Wrapper */
.desk-filter-card {
    background: #ffffff !important;
    border: 1px solid #e2e8f0 !important;
    border-radius: 8px !important;
    padding: 10px 14px !important;
    margin-bottom: 14px !important;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03) !important;
}
body.dark-mode .desk-filter-card {
    background: #141c2e !important;
    border-color: #334155 !important;
}
.desk-card-wrapper {
    background: #ffffff !important;
    border: 1px solid #e2e8f0 !important;
    border-radius: 8px !important;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04) !important;
    padding: 14px 16px !important;
}
body.dark-mode .desk-card-wrapper {
    background: #141c2e !important;
    border-color: #334155 !important;
}

/* Global Table Thead - Dark Navy */
.ledger-table thead th {
    background: var(--z-table-head-bg, #022051) !important;
    color: var(--z-table-head-text, #ffffff) !important;
    font-size: 10.5px !important;
    font-weight: 700 !important;
    text-transform: uppercase !important;
    letter-spacing: 0.04em !important;
    padding: 9px 12px !important;
    border: none !important;
    white-space: nowrap !important;
}
body.dark-mode .ledger-table thead th {
    background: var(--z-table-head-bg, #022051) !important;
    color: var(--z-table-head-text, #ffffff) !important;
}
.ledger-table tbody tr:nth-child(even) {
    background: var(--z-table-stripe, #f8fafc);
}
.ledger-table tbody tr:nth-child(odd) {
    background: var(--z-surface, #ffffff);
}
body.dark-mode .ledger-table tbody tr:nth-child(even) {
    background: #141c2c;
}
body.dark-mode .ledger-table tbody tr:nth-child(odd) {
    background: #1e293b;
}
.ledger-table tbody tr:hover {
    background: #e2e8f0 !important;
}
body.dark-mode .ledger-table tbody tr:hover {
    background: #27354f !important;
}
.ledger-table td {
    font-size: 12.5px;
    padding: 8px 10px;
    vertical-align: middle;
    border-color: #f1f5f9;
}
.ledger-table tfoot th {
    font-size: 13px;
    font-weight: 800;
    background: #f8fafc;
    border-top: 2px solid #0f172a;
    border-bottom: 2px solid #0f172a;
    padding: 10px;
}
.ledger-opening-row {
    background-color: #f1f5f9 !important;
    font-weight: 600;
}
body.dark-mode .ledger-opening-row {
    background-color: #1e293b !important;
}
.badge-type-pill {
    font-size: 11px;
    font-weight: 700;
    padding: 3px 8px;
    border-radius: 9999px;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.badge-type-sale { background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; }
.badge-type-payment { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
.badge-type-return { background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }
.badge-type-deposit { background: #fef3c7; color: #b45309; border: 1px solid #fde68a; }
.badge-type-quotation { background: #f3e8ff; color: #7e22ce; border: 1px solid #e9d5ff; }
.badge-type-opening { background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; }

.quick-filter-pills {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    align-items: center;
}
.quick-filter-pills .btn-filter-pill {
    font-size: 11.5px;
    font-weight: 600;
    padding: 4px 10px;
    border-radius: 20px;
    border: 1px solid #e2e8f0;
    background: #ffffff;
    color: #475569;
    cursor: pointer;
    transition: all 0.15s ease;
}
.quick-filter-pills .btn-filter-pill:hover,
.quick-filter-pills .btn-filter-pill.active {
    background: #0f172a;
    border-color: #0f172a;
    color: #ffffff;
}

/* Dark mode compatibility */
body.dark-mode .ledger-table td {
    border-color: #334155;
    color: #e2e8f0;
}
body.dark-mode .ledger-table tfoot th {
    background: #1e293b;
    border-color: #64748b;
    color: #f8fafc;
}
body.dark-mode .quick-filter-pills .btn-filter-pill {
    background: #1e293b;
    border-color: #334155;
    color: #cbd5e1;
}
body.dark-mode .quick-filter-pills .btn-filter-pill.active {
    background: #38bdf8;
    border-color: #38bdf8;
    color: #0f172a;
}

/* Print specific styling */
@media print {
    body, body.dark-mode {
        background: #ffffff !important;
        color: #0f172a !important;
    }
    .comm-command-bar,
    .desk-filter-card,
    .quick-filter-bar,
    .not-exported-col,
    .side-navbar,
    header.header,
    footer.main-footer,
    .btn,
    .dataTables_length,
    .dataTables_filter,
    .dataTables_info,
    .dataTables_paginate {
        display: none !important;
    }
    .report-workspace-view {
        padding: 0 !important;
        margin: 0 !important;
    }
    .desk-card-wrapper {
        border: none !important;
        box-shadow: none !important;
        padding: 0 !important;
    }
    .print-only-header {
        display: block !important;
        margin-bottom: 15px;
    }
    .ledger-table {
        width: 100% !important;
        border-collapse: collapse !important;
    }
    .ledger-table th, .ledger-table td {
        border: 1px solid #cbd5e1 !important;
        padding: 5px 6px !important;
        font-size: 8.5pt !important;
    }
    .ledger-table tfoot th {
        background: #f1f5f9 !important;
        border-top: 2px solid #0f172a !important;
    }
}
@media screen {
    .print-only-header {
        display: none;
    }
}
</style>

<section class="report-workspace-view">
    <!-- Top Command Bar -->
    <div class="comm-command-bar d-print-none">
        <div class="comm-title-group">
            <h1><i class="dripicons-user-group text-primary"></i> {{ __('db.Customer Report') }} Command Center</h1>
            <span class="comm-title-badge"><i class="dripicons-tag"></i> 360° Account Receivable &amp; Single-View Ledger</span>
        </div>
        <ul class="comm-nav-pills">
            <li><a class="nav-link active" href="{{ url('report/customer_report') }}"><i class="dripicons-user-group"></i> Customer Ledger</a></li>
            <li><a class="nav-link" href="{{ route('report.customerDueByDate') }}"><i class="dripicons-warning"></i> Customer Due</a></li>
            <li><a class="nav-link" href="{{ url('report/customer_group') }}"><i class="dripicons-network"></i> Customer Group</a></li>
            <li><a class="nav-link" href="{{ route('customer.index') }}"><i class="dripicons-user"></i> Customer Master</a></li>
            <li><a class="nav-link" href="{{ route('report.outstandingDues') }}"><i class="dripicons-pulse"></i> Outstanding Dues</a></li>
        </ul>
        <div class="comm-actions">
            <div class="btn-group">
                <button type="button" class="btn btn-secondary btn-action-pill" onclick="printReport('all')">
                    <i class="dripicons-print"></i> Print Complete Ledger
                </button>
                <button type="button" class="btn btn-secondary btn-action-pill dropdown-toggle dropdown-toggle-split" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    <span class="sr-only">Toggle Dropdown</span>
                </button>
                <div class="dropdown-menu dropdown-menu-right">
                    <a class="dropdown-item" href="javascript:void(0)" onclick="printReport('all')"><i class="dripicons-document mr-1"></i> Print Complete Ledger</a>
                    <a class="dropdown-item" href="javascript:void(0)" onclick="printReport('current')"><i class="dripicons-view-list mr-1"></i> Print Current Filtered View</a>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item" href="javascript:void(0)" onclick="printReport('sale')"><i class="dripicons-cart mr-1"></i> Print Invoices Only</a>
                    <a class="dropdown-item" href="javascript:void(0)" onclick="printReport('payment')"><i class="dripicons-wallet mr-1"></i> Print Payments Only</a>
                    <a class="dropdown-item" href="javascript:void(0)" onclick="printReport('return')"><i class="dripicons-return mr-1"></i> Print Returns Only</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Print Letterhead & Party Header (Only Visible When Printing) -->
    <div class="print-only-header">
        <div style="border-bottom: 2px solid #0f172a; padding-bottom: 10px; margin-bottom: 12px; display: flex; justify-content: space-between; align-items: flex-start;">
            <div>
                <h2 style="margin: 0; font-size: 16pt; font-weight: 800; color: #0f172a;">{{ $general_setting->company_name ?? 'zoloERP Pro' }}</h2>
                <div style="font-size: 9pt; color: #475569; margin-top: 3px;">
                    GSTIN: <strong>{{ $general_setting->vat_registration_number ?? '—' }}</strong> | Phone: {{ $general_setting->phone ?? '—' }}
                </div>
                <div style="font-size: 8.5pt; color: #64748b;">
                    {{ $general_setting->address ?? '' }}
                </div>
            </div>
            <div style="text-align: right;">
                <h3 style="margin: 0; font-size: 13pt; font-weight: 800; text-transform: uppercase; color: #0f172a;" id="printDocTitle">Customer Account Statement / Ledger</h3>
                <div style="font-size: 8.5pt; color: #64748b; margin-top: 4px;">
                    Period: <strong>{{ date('d-m-Y', strtotime($start_date)) }}</strong> To <strong>{{ date('d-m-Y', strtotime($end_date)) }}</strong>
                </div>
                <div style="font-size: 8pt; color: #94a3b8;">Printed on: {{ date('d-m-Y H:i') }}</div>
            </div>
        </div>

        @if($customer)
        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 10px 14px; margin-bottom: 14px; display: flex; justify-content: space-between;">
            <div>
                <div style="font-size: 8pt; text-transform: uppercase; font-weight: 700; color: #64748b;">Customer / Debtor Details:</div>
                <div style="font-size: 11.5pt; font-weight: 800; color: #0f172a;">{{ $customer->name }}</div>
                <div style="font-size: 9pt; color: #334155;">{{ $customer->company_name ?? '' }}</div>
                <div style="font-size: 8.5pt; color: #64748b;">Phone: {{ $customer->phone_number }} | Email: {{ $customer->email ?? '—' }}</div>
                <div style="font-size: 8.5pt; color: #64748b;">GSTIN: <strong>{{ $customer->tax_no ?? 'Unregistered' }}</strong></div>
            </div>
            <div style="text-align: right; min-width: 180px;">
                <div style="font-size: 8pt; text-transform: uppercase; font-weight: 700; color: #64748b;">Opening Balance:</div>
                <div style="font-size: 10pt; font-weight: 700; color: #0f172a;">{{ $currencySymbol }} {{ number_format(abs($openingBal), 2) }} {{ $openingBal >= 0 ? 'Dr' : 'Cr' }}</div>
                <div style="font-size: 8pt; text-transform: uppercase; font-weight: 700; color: #64748b; margin-top: 4px;">Current Outstanding:</div>
                <div style="font-size: 12pt; font-weight: 800; color: {{ $closingBal > 0 ? '#b91c1c' : '#15803d' }};">
                    {{ $currencySymbol }} {{ number_format(abs($closingBal), 2) }} {{ $closingBal >= 0 ? 'Dr' : 'Cr' }}
                </div>
            </div>
        </div>
        @endif
    </div>

    <!-- KPI Summary Strip -->
    <div class="kpi-summary-strip d-print-none">
        <div class="kpi-card">
            <div>
                <div class="kpi-label">REPORT TIMEFRAME</div>
                <div class="kpi-number u-fs-13px u-fw-600 u-c-text-2">
                    {{ date('d M Y', strtotime($start_date)) }} — {{ date('d M Y', strtotime($end_date)) }}
                </div>
            </div>
            <div class="z-icon-tile z-tone-primary">
                <i class="dripicons-calendar u-fs-18px"></i>
            </div>
        </div>
        <div class="kpi-card">
            <div>
                <div class="kpi-label">OPENING BALANCE</div>
                <div class="kpi-number u-fs-15px u-fw-700">
                    {{ $currencySymbol }} {{ number_format(abs($openingBal), 2) }}
                    <span class="badge badge-sm {{ $openingBal >= 0 ? 'badge-secondary' : 'badge-success' }}">{{ $openingBal >= 0 ? 'Dr' : 'Cr' }}</span>
                </div>
            </div>
            <div class="z-icon-tile z-tone-info">
                <i class="dripicons-wallet u-fs-18px"></i>
            </div>
        </div>
        <div class="kpi-card">
            <div>
                <div class="kpi-label">TOTAL INVOICED (DEBIT)</div>
                <div class="kpi-number u-fs-15px u-fw-700 text-primary">
                    {{ $currencySymbol }} {{ number_format($totalDebits, 2) }}
                </div>
            </div>
            <div class="z-icon-tile z-tone-warning">
                <i class="dripicons-cart u-fs-18px"></i>
            </div>
        </div>
        <div class="kpi-card">
            <div>
                <div class="kpi-label">TOTAL CREDITS (PAID/RET)</div>
                <div class="kpi-number u-fs-15px u-fw-700 text-success">
                    {{ $currencySymbol }} {{ number_format($totalCredits, 2) }}
                </div>
            </div>
            <div class="z-icon-tile z-tone-success">
                <i class="dripicons-checkmark u-fs-18px"></i>
            </div>
        </div>
        <div class="kpi-card" style="border-left: 3px solid {{ $closingBal > 0 ? '#ef4444' : '#10b981' }};">
            <div>
                <div class="kpi-label">NET CLOSING BALANCE</div>
                <div class="kpi-number u-fs-16px u-fw-800" style="color: {{ $closingBal > 0 ? '#b91c1c' : '#15803d' }};">
                    {{ $currencySymbol }} {{ number_format(abs($closingBal), 2) }}
                    <span class="badge badge-sm {{ $closingBal >= 0 ? 'badge-danger' : 'badge-success' }}">{{ $closingBal >= 0 ? 'Dr' : 'Cr' }}</span>
                </div>
            </div>
            <div class="z-icon-tile {{ $closingBal > 0 ? 'z-tone-danger' : 'z-tone-success' }}">
                <i class="dripicons-scale u-fs-18px"></i>
            </div>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="desk-filter-card d-print-none">
        {!! Form::open(['route' => 'report.customer', 'method' => 'post', 'id' => 'ledgerFilterForm', 'class' => 'form-inline d-flex align-items-center flex-wrap gap-2']) !!}
            <div class="form-group mr-2 mb-1">
                <label class="mr-2 font-weight-bold text-muted small"><i class="dripicons-calendar"></i> {{ __('db.Choose Your Date') }}</label>
                <div class="input-group">
                    <input type="text" class="daterangepicker-field form-control form-control-sm u-h-30px u-fs-12px u-w-210px"
                        value="{{ $start_date }} To {{ $end_date }}" required />
                    <input type="hidden" name="start_date" value="{{ $start_date }}" />
                    <input type="hidden" name="end_date" value="{{ $end_date }}" />
                </div>
            </div>
            <div class="form-group mr-2 mb-1">
                <label class="mr-2 font-weight-bold text-muted small"><i class="dripicons-user"></i> {{ __('db.Choose Customer') }}</label>
                <select id="customer_id" name="customer_id" class="form-control form-control-sm u-h-30px u-fs-12px u-minw-220px selectpicker" data-live-search="true">
                    @foreach ($lims_customer_list as $c)
                        @if ($c->type != 'walkin')
                            <option value="{{ $c->id }}" @selected($c->id == $customer_id)>
                                {{ $c->name }} ({{ $c->phone_number }})
                            </option>
                        @endif
                    @endforeach
                </select>
            </div>
            <div class="form-group mr-2 mb-1">
                <label class="mr-2 font-weight-bold text-muted small"><i class="dripicons-filter"></i> Transaction Type</label>
                <select id="transaction_type" name="transaction_type" class="form-control form-control-sm u-h-30px u-fs-12px">
                    <option value="all" @selected(($transaction_type ?? 'all') == 'all')>All Transactions (Complete Ledger)</option>
                    <option value="sale" @selected(($transaction_type ?? '') == 'sale')>Sales Invoices Only</option>
                    <option value="payment" @selected(($transaction_type ?? '') == 'payment')>Payments Only</option>
                    <option value="return" @selected(($transaction_type ?? '') == 'return')>Sales Returns Only</option>
                    <option value="quotation" @selected(($transaction_type ?? '') == 'quotation')>Quotations Only</option>
                </select>
            </div>
            <div class="form-group mb-1">
                <button class="btn btn-primary btn-sm btn-action-pill u-bg-ink u-bc-ink mr-1" type="submit">
                    <i class="fa fa-filter"></i> {{ __('db.submit') }}
                </button>
                <a href="{{ route('report.customer', ['customer_id' => $customer_id]) }}" class="btn btn-outline-secondary btn-sm btn-action-pill">
                    <i class="dripicons-clockwise"></i> Reset
                </a>
            </div>
        {!! Form::close() !!}
    </div>

    <!-- Quick In-Table Filter Pills -->
    <div class="d-flex justify-content-between align-items-center mb-2 px-1 quick-filter-bar d-print-none">
        <div class="quick-filter-pills">
            <span class="small font-weight-bold text-muted mr-1">Quick View:</span>
            <button type="button" class="btn-filter-pill active" data-filter="all">
                All ({{ $ledgerData['items']->count() }})
            </button>
            <button type="button" class="btn-filter-pill" data-filter="sale">
                <i class="dripicons-cart mr-1"></i> Invoices ({{ $ledgerData['items']->where('type', 'sale')->count() }})
            </button>
            <button type="button" class="btn-filter-pill" data-filter="payment">
                <i class="dripicons-wallet mr-1"></i> Payments ({{ $ledgerData['items']->where('type', 'payment')->count() + $ledgerData['items']->where('type', 'deposit')->count() }})
            </button>
            <button type="button" class="btn-filter-pill" data-filter="return">
                <i class="dripicons-return mr-1"></i> Returns ({{ $ledgerData['items']->where('type', 'return')->count() }})
            </button>
            <button type="button" class="btn-filter-pill" data-filter="quotation">
                <i class="dripicons-document mr-1"></i> Quotations ({{ $ledgerData['items']->where('type', 'quotation')->count() }})
            </button>
        </div>
        <div class="text-muted small">
            <span>Showing statement for: <strong>{{ $customerName }}</strong></span>
        </div>
    </div>

    <!-- Unified Single-View Ledger Table -->
    <div class="desk-card-wrapper">
        <div class="table-responsive">
            <table id="customer-ledger-table" class="table ledger-table u-w-100pct">
                <thead>
                    <tr>
                        <th class="not-exported-col" style="width: 35px;">#</th>
                        <th style="width: 95px;">{{ __('db.date') }}</th>
                        <th style="width: 120px;">{{ __('db.Type') }}</th>
                        <th style="width: 130px;">{{ __('db.reference') }} No</th>
                        <th>{{ __('db.Warehouse') }}</th>
                        <th>{{ __('db.Description') }} / Particulars</th>
                        <th class="text-right" style="width: 110px;">Debit ({{ $currencySymbol }})</th>
                        <th class="text-right" style="width: 110px;">Credit ({{ $currencySymbol }})</th>
                        <th class="text-right" style="width: 130px;">Running Balance ({{ $currencySymbol }})</th>
                        <th class="not-exported-col" style="width: 85px;">{{ __('db.status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Opening Balance Row -->
                    <tr class="ledger-opening-row" data-type="opening">
                        <td class="text-muted font-italic not-exported-col">—</td>
                        <td>{{ date('d/m/Y', strtotime($start_date)) }}</td>
                        <td><span class="badge-type-pill badge-type-opening"><i class="dripicons-wallet"></i> Opening Balance</span></td>
                        <td class="font-italic text-muted">—</td>
                        <td class="text-muted">—</td>
                        <td><strong>Opening Balance Brought Forward</strong></td>
                        <td class="text-right font-weight-bold">{{ $openingBal > 0 ? number_format($openingBal, 2) : '0.00' }}</td>
                        <td class="text-right font-weight-bold">{{ $openingBal < 0 ? number_format(abs($openingBal), 2) : '0.00' }}</td>
                        <td class="text-right font-weight-bold">
                            {{ number_format(abs($openingBal), 2) }}
                            <span class="badge badge-sm {{ $openingBal >= 0 ? 'badge-secondary' : 'badge-success' }}">{{ $openingBal >= 0 ? 'Dr' : 'Cr' }}</span>
                        </td>
                        <td class="not-exported-col"><span class="badge badge-light border">B/F</span></td>
                    </tr>

                    @php
                        $calcRunning = $openingBal;
                        $calcTotalDebit = 0.0;
                        $calcTotalCredit = 0.0;
                    @endphp

                    @forelse($ledgerData['items'] as $index => $item)
                        @php
                            $calcTotalDebit += $item->debit;
                            $calcTotalCredit += $item->credit;
                            $calcRunning += ($item->debit - $item->credit);
                        @endphp
                        <tr data-type="{{ $item->type }}">
                            <td class="not-exported-col">{{ $index + 1 }}</td>
                            <td>{{ date('d/m/Y', strtotime($item->date)) }}</td>
                            <td>
                                @if($item->type === 'sale')
                                    <span class="badge-type-pill badge-type-sale"><i class="dripicons-cart"></i> Sale Invoice</span>
                                @elseif($item->type === 'payment')
                                    <span class="badge-type-pill badge-type-payment"><i class="dripicons-checkmark"></i> Payment Recv</span>
                                @elseif($item->type === 'return')
                                    <span class="badge-type-pill badge-type-return"><i class="dripicons-return"></i> Sales Return</span>
                                @elseif($item->type === 'deposit')
                                    <span class="badge-type-pill badge-type-deposit"><i class="dripicons-wallet"></i> Adv Deposit</span>
                                @elseif($item->type === 'quotation')
                                    <span class="badge-type-pill badge-type-quotation"><i class="dripicons-document"></i> Quotation</span>
                                @else
                                    <span class="badge-type-pill badge-type-opening">{{ $item->type_label }}</span>
                                @endif
                            </td>
                            <td class="font-weight-bold font-monospace text-primary">
                                @if($item->type === 'sale')
                                    <a href="{{ route('sales.index') }}?reference={{ $item->reference_no }}" target="_blank">{{ $item->reference_no }}</a>
                                @else
                                    {{ $item->reference_no }}
                                @endif
                            </td>
                            <td>{{ $item->warehouse }}</td>
                            <td>{{ $item->description }}</td>
                            <td class="text-right font-weight-bold {{ $item->debit > 0 ? 'text-dark' : 'text-muted' }}">
                                {{ $item->debit > 0 ? number_format($item->debit, 2) : '—' }}
                            </td>
                            <td class="text-right font-weight-bold {{ $item->credit > 0 ? 'text-success' : 'text-muted' }}">
                                {{ $item->credit > 0 ? number_format($item->credit, 2) : '—' }}
                            </td>
                            <td class="text-right font-weight-bold">
                                {{ number_format(abs($calcRunning), 2) }}
                                <span class="badge badge-sm {{ $calcRunning >= 0 ? 'badge-danger' : 'badge-success' }}">{{ $calcRunning >= 0 ? 'Dr' : 'Cr' }}</span>
                            </td>
                            <td class="not-exported-col">
                                <span class="badge badge-light border text-capitalize">{{ $item->status }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="text-center py-4 text-muted">
                                <i class="dripicons-information mr-1"></i> No transactions found for the selected timeframe.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr>
                        <th class="not-exported-col"></th>
                        <th colspan="5" class="text-right">Total Activity &amp; Closing Balance:</th>
                        <th class="text-right text-primary" id="footerTotalDebit">{{ number_format($totalDebits, 2) }}</th>
                        <th class="text-right text-success" id="footerTotalCredit">{{ number_format($totalCredits, 2) }}</th>
                        <th class="text-right" id="footerClosingBal">
                            {{ number_format(abs($closingBal), 2) }}
                            <span class="badge badge-sm {{ $closingBal >= 0 ? 'badge-danger' : 'badge-success' }}">{{ $closingBal >= 0 ? 'Dr' : 'Cr' }}</span>
                        </th>
                        <th class="not-exported-col"></th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <!-- Print Footer Signatures (Only Visible When Printing) -->
    <div class="print-only-header" style="margin-top: 40px;">
        <div style="display: flex; justify-content: space-between; align-items: flex-end; padding-top: 30px;">
            <div style="text-align: center; border-top: 1px dashed #94a3b8; width: 220px; padding-top: 6px; font-size: 8.5pt;">
                Customer Confirmation Signature
            </div>
            <div style="text-align: center; border-top: 1px dashed #94a3b8; width: 220px; padding-top: 6px; font-size: 8.5pt;">
                For {{ $general_setting->company_name ?? 'zoloERP Pro' }}<br>
                <strong>Authorized Signatory</strong>
            </div>
        </div>
    </div>
</section>

@endsection

@push('scripts')
<script type="text/javascript">
    $("ul#report").siblings('a').attr('aria-expanded','true');
    $("ul#report").addClass("show");
    $("ul#report #customer-report-menu").addClass("active");

    var table = null;

    $(document).ready(function() {
        $(".daterangepicker-field").daterangepicker({
            parentElement: '.daterangepicker-container',
            @if( Config::get('app.locale') == 'ar' || ($general_setting->is_rtl ?? false))
            orientation: 'left',
            @endif
            callback: function(startDate, endDate, period){
                var start_date = startDate.format('YYYY-MM-DD');
                var end_date = endDate.format('YYYY-MM-DD');
                var title = start_date + ' To ' + end_date;
                $(this).val(title);
                $('input[name="start_date"]').val(start_date);
                $('input[name="end_date"]').val(end_date);
            }
        });

        // Initialize DataTable on the unified ledger table
        table = $('#customer-ledger-table').DataTable({
            "order": [],
            "paging": true,
            "pageLength": 25,
            "lengthMenu": [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
            "language": {
                "lengthMenu": '_MENU_ {{__("db.records per page")}}',
                "info": '<small>{{__("db.Showing")}} _START_ - _END_ (_TOTAL_)</small>',
                "search": '{{__("db.Search")}}:',
                "paginate": {
                    "previous": '<i class="dripicons-chevron-left"></i>',
                    "next": '<i class="dripicons-chevron-right"></i>'
                }
            },
            "columnDefs": [
                { "orderable": false, "targets": [0, 9] }
            ]
        });

        // Quick filter pill handling
        $('.btn-filter-pill').on('click', function() {
            var filter = $(this).data('filter');
            $('.btn-filter-pill').removeClass('active');
            $(this).addClass('active');

            applyFilter(filter);
        });

        // Trigger transaction_type from backend if specified
        var initialType = "{{ $transaction_type ?? 'all' }}";
        if(initialType !== 'all') {
            $('.btn-filter-pill[data-filter="' + initialType + '"]').trigger('click');
        }
    });

    function applyFilter(filter) {
        if (!table) return;

        // Custom filter function for DataTables
        $.fn.dataTable.ext.search.pop(); // remove existing

        if (filter !== 'all') {
            $.fn.dataTable.ext.search.push(
                function(settings, data, dataIndex) {
                    if (settings.nTable.id !== 'customer-ledger-table') return true;
                    var row = table.row(dataIndex).node();
                    var type = $(row).data('type');
                    if (filter === 'payment') {
                        return type === 'payment' || type === 'deposit';
                    }
                    return type === filter;
                }
            );
        }

        table.draw();
    }

    function printReport(mode) {
        var prevFilter = $('.btn-filter-pill.active').data('filter');

        if (mode === 'all') {
            // Complete Ledger mode
            $('#printDocTitle').text('Complete Customer Account Statement / Ledger');
            $('.btn-filter-pill[data-filter="all"]').trigger('click');
        } else if (mode === 'current') {
            // Keep active view
            var filterName = prevFilter.charAt(0).toUpperCase() + prevFilter.slice(1);
            $('#printDocTitle').text(filterName === 'All' ? 'Complete Customer Account Statement / Ledger' : 'Customer ' + filterName + ' Statement');
        } else {
            // Specific filter mode
            $('.btn-filter-pill[data-filter="' + mode + '"]').trigger('click');
            var filterName = mode.charAt(0).toUpperCase() + mode.slice(1);
            $('#printDocTitle').text('Customer ' + filterName + ' Statement');
        }

        // Expand all records for full print
        if (table) {
            var origLen = table.page.len();
            table.page.len(-1).draw();

            setTimeout(function() {
                window.print();
                // Restore previous pagination
                setTimeout(function() {
                    table.page.len(origLen).draw();
                    if (mode === 'all' && prevFilter && prevFilter !== 'all') {
                        $('.btn-filter-pill[data-filter="' + prevFilter + '"]').trigger('click');
                    }
                }, 300);
            }, 300);
        } else {
            window.print();
        }
    }
</script>
@endpush

@push('css')
    <link rel="stylesheet" href="<?php echo asset('css/commercial-entry.css') . '?v=' . (file_exists(public_path('css/commercial-entry.css')) ? filemtime(public_path('css/commercial-entry.css')) : time()) ?>" type="text/css">
    <link rel="stylesheet" href="<?php echo asset('css/commercial-workspace.css') . '?v=' . (file_exists(public_path('css/commercial-workspace.css')) ? filemtime(public_path('css/commercial-workspace.css')) : time()) ?>" type="text/css">
    <link rel="stylesheet" href="<?php echo asset('css/zolo-erp-neo.css') . '?v=' . (file_exists(public_path('css/zolo-erp-neo.css')) ? filemtime(public_path('css/zolo-erp-neo.css')) : time()) ?>" type="text/css">
    <link rel="stylesheet" href="{{ versioned_asset('css/pages/shared-report-biller-report.css') }}">
@endpush
