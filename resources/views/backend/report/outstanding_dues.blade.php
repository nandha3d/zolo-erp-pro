@extends('backend.layout.main')
@section('content')

<style>
.outstanding-dues-workspace {
    padding: 12px 18px 24px;
}
.dues-kpi-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
    gap: 12px;
    margin-bottom: 14px;
}
.dues-kpi-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 12px 16px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
}
.dues-kpi-card.kpi-highlight-due {
    background: linear-gradient(135deg, #fdf4ff 0%, #fae8ff 100%);
    border-color: #f0abfc;
}
.dues-kpi-card.kpi-highlight-overdue {
    background: linear-gradient(135deg, #fff1f2 0%, #ffe4e6 100%);
    border-color: #fecdd3;
}
.dues-kpi-label {
    font-size: 10.5px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #64748b;
    margin-bottom: 4px;
}
.dues-kpi-val {
    font-size: 19px;
    font-weight: 800;
    color: #0f172a;
    line-height: 1.2;
}
.dues-kpi-val.text-purple {
    color: #86198f;
}
.dues-kpi-val.text-rose {
    color: #e11d48;
}
.dues-kpi-icon {
    width: 38px;
    height: 38px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
}
.dues-filter-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 14px 16px;
    margin-bottom: 16px;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
}
.dues-table-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
}
.badge-due-status {
    font-size: 11px;
    font-weight: 700;
    padding: 3px 8px;
    border-radius: 4px;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.badge-status-overdue {
    background: #ffe4e6;
    color: #be123c;
    border: 1px solid #fecdd3;
}
.badge-status-unpaid {
    background: #fef3c7;
    color: #b45309;
    border: 1px solid #fde68a;
}
.badge-status-partial {
    background: #e0f2fe;
    color: #0369a1;
    border: 1px solid #bae6fd;
}
.badge-status-settled {
    background: #dcfce7;
    color: #15803d;
    border: 1px solid #bbf7d0;
}
body.dark-mode .dues-kpi-card {
    background: #1e293b;
    border-color: #334155;
}
body.dark-mode .dues-kpi-card.kpi-highlight-due {
    background: #2a1538;
    border-color: #581c87;
}
body.dark-mode .dues-kpi-card.kpi-highlight-overdue {
    background: #3a151b;
    border-color: #881337;
}
body.dark-mode .dues-kpi-label {
    color: #94a3b8;
}
body.dark-mode .dues-kpi-val {
    color: #f8fafc;
}
body.dark-mode .dues-filter-card,
body.dark-mode .dues-table-card {
    background: #1e293b;
    border-color: #334155;
}
@media print {
    body {
        background: #ffffff !important;
        color: #000000 !important;
    }
    .comm-command-bar,
    .dues-filter-card,
    .not-printable,
    .dt-buttons,
    .dataTables_filter,
    .dataTables_length,
    .dataTables_paginate,
    .dataTables_info,
    #sidebar,
    header.header {
        display: none !important;
    }
    .outstanding-dues-workspace {
        padding: 0 !important;
    }
    .dues-table-card {
        border: none !important;
        box-shadow: none !important;
    }
    .dues-print-header {
        display: block !important;
        margin-bottom: 20px;
        border-bottom: 2px solid #0f172a;
        padding-bottom: 10px;
    }
    table {
        font-size: 10px !important;
    }
    table th, table td {
        padding: 4px 6px !important;
        border: 1px solid #cbd5e1 !important;
    }
}
/* Navigation Toolbar & Theme Buttons */
.dues-report-nav-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 12px 16px;
    margin-bottom: 14px;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
}
.dues-report-title-box {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}
.dues-report-title {
    margin: 0;
    font-size: 16px;
    font-weight: 800;
    color: var(--neo-text-primary, #0f172a);
    display: flex;
    align-items: center;
    gap: 8px;
}
.dues-nav-btn-group {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    margin: 0;
    padding: 0;
    list-style: none;
}
.dues-nav-pill {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    height: 34px;
    padding: 0 14px;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 700;
    text-decoration: none !important;
    transition: all 0.15s ease;
    cursor: pointer;
    border: 1px solid transparent;
}
/* Receivables Pill */
.dues-nav-pill.pill-receivables {
    background: #fdf4ff;
    color: var(--z-primary, #7d287e);
    border-color: #f0abfc;
}
.dues-nav-pill.pill-receivables:hover {
    background: #fae8ff;
    color: var(--z-primary-hover, #6b21a8);
}
.dues-nav-pill.pill-receivables.is-active {
    background: linear-gradient(135deg, var(--z-primary, #7d287e) 0%, var(--z-primary-hover, #6b21a8) 100%);
    color: #ffffff;
    border-color: transparent;
    box-shadow: 0 2px 4px rgba(125, 40, 126, 0.25);
}
/* Payables Pill */
.dues-nav-pill.pill-payables {
    background: #f0f9ff;
    color: #0284c7;
    border-color: #7dd3fc;
}
.dues-nav-pill.pill-payables:hover {
    background: #e0f2fe;
    color: #0369a1;
}
.dues-nav-pill.pill-payables.is-active {
    background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%);
    color: #ffffff;
    border-color: transparent;
    box-shadow: 0 2px 4px rgba(2, 132, 199, 0.25);
}
/* Ageing Pill */
.dues-nav-pill.pill-ageing {
    background: #f8fafc;
    color: #475569;
    border-color: #cbd5e1;
}
.dues-nav-pill.pill-ageing:hover {
    background: #f1f5f9;
    color: #0f172a;
    border-color: #94a3b8;
}
/* Due Clearance Hub Pill */
.dues-nav-pill.pill-hub {
    background: #ecfdf5;
    color: #059669;
    border-color: #a7f3d0;
}
.dues-nav-pill.pill-hub:hover {
    background: #d1fae5;
    color: #047857;
}
/* Print Button */
.dues-print-btn {
    height: 34px;
    padding: 0 14px;
    font-size: 12px;
    font-weight: 700;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
body.dark-mode .dues-report-nav-toolbar {
    background: #1e293b;
    border-color: #334155;
}
body.dark-mode .dues-report-title {
    color: #f8fafc;
}
body.dark-mode .dues-nav-pill.pill-receivables {
    background: #2e106533;
    border-color: #7e22ce;
    color: #d8b4fe;
}
body.dark-mode .dues-nav-pill.pill-payables {
    background: #0c4a6e33;
    border-color: #0284c7;
    color: #7dd3fc;
}
body.dark-mode .dues-nav-pill.pill-ageing {
    background: #334155;
    border-color: #475569;
    color: #cbd5e1;
}
body.dark-mode .dues-nav-pill.pill-hub {
    background: #064e3b33;
    border-color: #059669;
    color: #34d399;
}
.dues-print-header {
    display: none;
}
</style>

<div class="outstanding-dues-workspace">
    <!-- Top Navigation Toolbar with Theme Buttons -->
    <div class="dues-report-nav-toolbar">
        <div class="dues-report-title-box">
            <h1 class="dues-report-title">
                <i class="dripicons-wallet text-primary"></i>
                Outstanding Payments (Dues) Command Center
            </h1>
            <span class="badge badge-light border text-muted px-2 py-1">
                {{ $type === 'receivables' ? 'Customer Receivables' : 'Supplier Payables' }}
                @if($customerId)
                    · Filtered: Customer #{{ $customerId }}
                @elseif($supplierId)
                    · Filtered: Supplier #{{ $supplierId }}
                @endif
            </span>
        </div>

        <div class="d-flex align-items-center gap-2 flex-wrap">
            <div class="dues-nav-btn-group">
                <a class="dues-nav-pill pill-receivables {{ $type === 'receivables' ? 'is-active' : '' }}"
                   href="{{ route('report.outstandingDues', ['type' => 'receivables'] + ($customerId ? ['customer_id' => $customerId] : [])) }}">
                    <i class="dripicons-user"></i> Receivables (Customers)
                </a>
                <a class="dues-nav-pill pill-payables {{ $type === 'payables' ? 'is-active' : '' }}"
                   href="{{ route('report.outstandingDues', ['type' => 'payables'] + ($supplierId ? ['supplier_id' => $supplierId] : [])) }}">
                    <i class="dripicons-user-id"></i> Payables (Suppliers)
                </a>
                <a class="dues-nav-pill pill-ageing" href="{{ route('accounting.ageing') }}">
                    <i class="dripicons-clock"></i> Ageing Ledger
                </a>
                <a class="dues-nav-pill pill-hub font-weight-bold" href="{{ route('dues.index', ['tab' => $type === 'payables' ? 'purchases' : 'sales']) }}">
                    <i class="dripicons-wallet"></i> Due Clearance Hub
                </a>
            </div>

            <button type="button" class="btn btn-outline-secondary dues-print-btn" onclick="window.print()">
                <i class="dripicons-print"></i> Print
            </button>
        </div>
    </div>

    <!-- Printable Letterhead (Visible only on print) -->
    <div class="dues-print-header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h3 class="font-weight-bold mb-1">{{ $company->trade_name ?: $company->legal_name }}</h3>
                <div class="small text-muted">{{ $company->address ?: '' }} {{ $company->city ? '· '.$company->city : '' }}</div>
                @if($company->gstin)
                    <div class="small font-weight-bold">GSTIN: {{ $company->gstin }}</div>
                @endif
            </div>
            <div class="text-right">
                <h4 class="font-weight-bold text-uppercase mb-0 text-primary">
                    {{ $type === 'receivables' ? 'Outstanding Customer Receivables' : 'Outstanding Supplier Payables' }}
                </h4>
                <div class="small text-muted">As on: {{ date('d M Y') }}</div>
                @if($startDate || $endDate)
                    <div class="small text-muted">Period: {{ $startDate ? date('d-m-Y', strtotime($startDate)) : 'Beginning' }} to {{ date('d-m-Y', strtotime($endDate)) }}</div>
                @endif
            </div>
        </div>
    </div>

    <!-- Executive KPI Summary Cards -->
    <div class="dues-kpi-grid">
        <div class="dues-kpi-card kpi-highlight-due">
            <div>
                <div class="dues-kpi-label">Total Outstanding Due</div>
                <div class="dues-kpi-val text-purple">
                    {{ $generalSetting->currency ?? '₹' }} {{ number_format($kpiDue, 2) }}
                </div>
            </div>
            <div class="dues-kpi-icon bg-light text-primary">
                <i class="dripicons-wallet"></i>
            </div>
        </div>

        <div class="dues-kpi-card kpi-highlight-overdue">
            <div>
                <div class="dues-kpi-label">Overdue Amount</div>
                <div class="dues-kpi-val text-rose">
                    {{ $generalSetting->currency ?? '₹' }} {{ number_format($kpiOverdue, 2) }}
                </div>
            </div>
            <div class="dues-kpi-icon bg-light text-danger">
                <i class="dripicons-warning"></i>
            </div>
        </div>

        <div class="dues-kpi-card">
            <div>
                <div class="dues-kpi-label">Total Amount Settled</div>
                <div class="dues-kpi-val text-success">
                    {{ $generalSetting->currency ?? '₹' }} {{ number_format($kpiPaid, 2) }}
                </div>
            </div>
            <div class="dues-kpi-icon bg-light text-success">
                <i class="dripicons-checkmark"></i>
            </div>
        </div>

        <div class="dues-kpi-card">
            <div>
                <div class="dues-kpi-label">Total Billed Volume</div>
                <div class="dues-kpi-val">
                    {{ $generalSetting->currency ?? '₹' }} {{ number_format($kpiBilled, 2) }}
                </div>
            </div>
            <div class="dues-kpi-icon bg-light text-secondary">
                <i class="dripicons-document"></i>
            </div>
        </div>

        <div class="dues-kpi-card">
            <div>
                <div class="dues-kpi-label">Open Vouchers</div>
                <div class="dues-kpi-val">
                    {{ $rows->count() }} <span class="small font-weight-normal text-muted">record(s)</span>
                </div>
            </div>
            <div class="dues-kpi-icon bg-light text-info">
                <i class="dripicons-stack"></i>
            </div>
        </div>
    </div>

    <!-- Audit & Operational Clearance Link Banner -->
    <div class="alert alert-light border d-flex align-items-center justify-content-between mb-3 py-2 px-3 not-printable" style="border-radius: 8px;">
        <span class="small text-muted">
            <i class="fa fa-info-circle text-primary mr-1"></i>
            This is an audit and reconciliation report. To record payments, settlements, and clear dues, open the
            <a href="{{ route('dues.index', ['tab' => $type === 'payables' ? 'purchases' : 'sales']) }}" class="font-weight-bold text-primary">
                Due Management Command Center
            </a>.
        </span>
        <a href="{{ route('dues.index', ['tab' => $type === 'payables' ? 'purchases' : 'sales']) }}" class="btn btn-outline-primary btn-sm py-1 font-weight-bold">
            <i class="dripicons-wallet mr-1"></i> Open Due Clearance
        </a>
    </div>
    <!-- Filter Card -->
    <div class="dues-filter-card not-printable">
        <form method="GET" action="{{ route('report.outstandingDues') }}" id="dues-filter-form" class="row align-items-end g-2">
            <input type="hidden" name="type" value="{{ $type }}">

            <div class="col-md-2 col-sm-6">
                <label class="small font-weight-bold text-muted mb-1">From Date</label>
                <input type="date" name="start_date" class="form-control form-control-sm" value="{{ $startDate }}">
            </div>

            <div class="col-md-2 col-sm-6">
                <label class="small font-weight-bold text-muted mb-1">To Date</label>
                <input type="date" name="end_date" class="form-control form-control-sm" value="{{ $endDate }}">
            </div>

            @if($type === 'receivables')
                <div class="col-md-2 col-sm-6">
                    <label class="small font-weight-bold text-muted mb-1">Customer</label>
                    <select name="customer_id" class="form-control form-control-sm selectpicker" data-live-search="true">
                        <option value="">— All Customers —</option>
                        @foreach($customers as $c)
                            <option value="{{ $c->id }}" @selected($customerId == $c->id)>{{ $c->name }} ({{ $c->phone_number ?: 'No phone' }})</option>
                        @endforeach
                    </select>
                </div>
            @else
                <div class="col-md-2 col-sm-6">
                    <label class="small font-weight-bold text-muted mb-1">Supplier</label>
                    <select name="supplier_id" class="form-control form-control-sm selectpicker" data-live-search="true">
                        <option value="">— All Suppliers —</option>
                        @foreach($suppliers as $s)
                            <option value="{{ $s->id }}" @selected($supplierId == $s->id)>{{ $s->name }} ({{ $s->phone_number ?: 'No phone' }})</option>
                        @endforeach
                    </select>
                </div>
            @endif

            <div class="col-md-2 col-sm-6">
                <label class="small font-weight-bold text-muted mb-1">Warehouse</label>
                <select name="warehouse_id" class="form-control form-control-sm">
                    <option value="">— All Warehouses —</option>
                    @foreach($warehouses as $w)
                        <option value="{{ $w->id }}" @selected($warehouseId == $w->id)>{{ $w->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-2 col-sm-6">
                <label class="small font-weight-bold text-muted mb-1">Due Status</label>
                <select name="due_status" class="form-control form-control-sm">
                    <option value="all_dues" @selected($dueStatus === 'all_dues')>All Dues (Balance > 0)</option>
                    <option value="unpaid" @selected($dueStatus === 'unpaid')>Unpaid Only (0% Paid)</option>
                    <option value="partial" @selected($dueStatus === 'partial')>Partially Paid</option>
                    <option value="overdue" @selected($dueStatus === 'overdue')>Overdue Only</option>
                    <option value="all" @selected($dueStatus === 'all')>All Records (incl. Settled)</option>
                </select>
            </div>

            <div class="col-md-2 col-sm-12 d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm flex-grow-1">
                    <i class="fa fa-filter mr-1"></i> Apply
                </button>
                <a href="{{ route('report.outstandingDues', ['type' => $type]) }}" class="btn btn-outline-secondary btn-sm" title="Reset Filters">
                    <i class="dripicons-clockwise"></i>
                </a>
            </div>
        </form>
    </div>

    <!-- Data Table Card -->
    <div class="dues-table-card">
        <div class="table-responsive p-3">
            <table class="table table-hover table-sm align-middle mb-0" id="outstanding-dues-table">
                <thead class="bg-light">
                    <tr>
                        <th class="text-center" style="width: 35px;">#</th>
                        <th>Date</th>
                        <th>Reference</th>
                        @if($type === 'payables')
                            <th>Supplier Inv</th>
                        @endif
                        <th>Party</th>
                        <th>Warehouse</th>
                        <th>Due Date / Terms</th>
                        <th class="text-right">Billed ({{ $generalSetting->currency ?? '₹' }})</th>
                        <th class="text-right">Returned</th>
                        <th class="text-right">Paid</th>
                        <th class="text-right font-weight-bold">Due Amount</th>
                        <th class="text-center">Status</th>
                        
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $idx => $r)
                        <tr>
                            <td class="text-center font-weight-600 text-muted">{{ $idx + 1 }}</td>
                            <td>{{ date('d-m-Y', strtotime($r->created_at)) }}</td>
                            <td>
                                @if($type === 'receivables')
                                    <a href="{{ route('sales.index') }}" class="font-weight-bold text-primary font-monospace" title="View Sale">
                                        {{ $r->reference_no }}
                                    </a>
                                @else
                                    <a href="{{ route('purchases.index') }}" class="font-weight-bold text-primary font-monospace" title="View Purchase">
                                        {{ $r->reference_no }}
                                    </a>
                                @endif
                            </td>
                            @if($type === 'payables')
                                <td>{{ $r->supplier_invoice_no ?: '—' }}</td>
                            @endif
                            <td>
                                <div class="font-weight-600 text-dark">{{ $r->party_name ?: 'Unknown' }}</div>
                                @if(!empty($r->party_phone))
                                    <div class="small text-muted"><i class="fa fa-phone mr-1"></i>{{ $r->party_phone }}</div>
                                @endif
                            </td>
                            <td>{{ $r->warehouse_name ?: '—' }}</td>
                            <td>
                                <div>{{ date('d-m-Y', strtotime($r->due_date)) }}</div>
                                @if($r->is_overdue)
                                    <span class="badge badge-status-overdue">
                                        <i class="fa fa-exclamation-circle"></i> Overdue {{ $r->days_overdue }}d
                                    </span>
                                @elseif($r->due > 0.009)
                                    <span class="badge badge-light text-muted border">
                                        Due in {{ $r->days_left }}d
                                    </span>
                                @else
                                    <span class="badge badge-status-settled">
                                        <i class="fa fa-check"></i> Paid
                                    </span>
                                @endif
                            </td>
                            <td class="text-right">{{ number_format($r->billed, 2) }}</td>
                            <td class="text-right text-muted">{{ number_format($r->returned, 2) }}</td>
                            <td class="text-right text-success">{{ number_format($r->paid, 2) }}</td>
                            <td class="text-right font-weight-bold {{ $r->due > 0.009 ? 'text-danger' : 'text-success' }}">
                                {{ number_format($r->due, 2) }}
                            </td>
                            <td class="text-center">
                                @if($r->is_overdue)
                                    <span class="badge-due-status badge-status-overdue">Overdue</span>
                                @elseif($r->due <= 0.009)
                                    <span class="badge-due-status badge-status-settled">Settled</span>
                                @elseif($r->paid > 0.009)
                                    <span class="badge-due-status badge-status-partial">Partial</span>
                                @else
                                    <span class="badge-due-status badge-status-unpaid">Unpaid</span>
                                @endif
                            </td>
                            
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $type === 'payables' ? 12 : 11 }}" class="text-center py-4 text-muted">
                                <div class="mb-1"><i class="dripicons-checkmark text-success u-fs-24px"></i></div>
                                <div>No outstanding dues found matching the active filters.</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if($rows->count() > 0)
                    <tfoot class="bg-light font-weight-bold">
                        <tr>
                            <th colspan="{{ $type === 'payables' ? 7 : 6 }}" class="text-right">Grand Totals:</th>
                            <th class="text-right">{{ number_format($kpiBilled, 2) }}</th>
                            <th class="text-right">{{ number_format($kpiReturned, 2) }}</th>
                            <th class="text-right text-success">{{ number_format($kpiPaid, 2) }}</th>
                            <th class="text-right text-danger">{{ number_format($kpiDue, 2) }}</th>
                            <th></th>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>
</div>

@push('scripts')
<script type="text/javascript">
$(document).ready(function() {
    $('#outstanding-dues-table').DataTable({
        "order": [[1, "desc"]],
        "pageLength": 25,
        "lengthMenu": [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
        "dom": '<"row align-items-center mb-3"<"col-md-6 d-flex align-items-center gap-2"lB><"col-md-6 d-flex justify-content-end"f>r>t<"row align-items-center mt-3"<"col-md-6"i><"col-md-6 d-flex justify-content-end"p>>',
        "buttons": [
            {
                extend: 'copy',
                text: '<i class="fa fa-copy mr-1"></i> Copy',
                className: 'btn btn-sm btn-outline-secondary',
                exportOptions: { columns: ':visible:not(.not-exported)' }
            },
            {
                extend: 'csv',
                text: '<i class="fa fa-file-text-o mr-1"></i> CSV',
                className: 'btn btn-sm btn-outline-secondary',
                exportOptions: { columns: ':visible:not(.not-exported)' }
            },
            {
                extend: 'print',
                text: '<i class="fa fa-print mr-1"></i> Print',
                className: 'btn btn-sm btn-outline-secondary',
                exportOptions: { columns: ':visible:not(.not-exported)' }
            }
        ],
        "language": {
            "search": "_INPUT_",
            "searchPlaceholder": "Search table...",
            "paginate": {
                "previous": "<i class='dripicons-chevron-left'></i>",
                "next": "<i class='dripicons-chevron-right'></i>"
            }
        }
    });
});
</script>
@endpush

@endsection

