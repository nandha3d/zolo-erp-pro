@extends('backend.layout.main')
@section('content')

@push('css')
<link rel="stylesheet" href="{{ versioned_asset('css/pages/outstanding-dues.css') }}">
@endpush

<div class="outstanding-dues-workspace">
    <!-- Top Navigation Toolbar with Theme Buttons -->
    <div class="dues-report-nav-toolbar">
        <div class="dues-report-title-box">
            <h1 class="dues-report-title">
                <i class="dripicons-wallet text-primary"></i>
                Outstanding Payments (Dues) Command Center
            </h1>
            <span class="badge badge-light border text-muted px-2 py-1">
                {{ $type === 'receivables' ? 'Customer Receivables' : ($type === 'payables' ? 'Supplier Payables' : 'Operational Expenses') }}
                @if($customerId)
                    · Filtered: Customer #{{ $customerId }}
                @elseif($supplierId)
                    · Filtered: Supplier #{{ $supplierId }}
                @endif
            </span>
        </div>

        @php
            $ageingParams = ['party_type' => $type === 'payables' ? 'supplier' : 'customer'];
            if ($type === 'payables' && $supplierId) {
                $ageingParams['party_id'] = $supplierId;
            } elseif ($type === 'receivables' && $customerId) {
                $ageingParams['party_id'] = $customerId;
            }

            $hubTab = $type === 'payables' ? 'purchases' : ($type === 'expenses' ? 'expenses' : 'sales');
            $hubParams = ['tab' => $hubTab];
            if ($type === 'payables' && $supplierId) {
                $hubParams['party_id'] = $supplierId;
            } elseif ($type === 'receivables' && $customerId) {
                $hubParams['party_id'] = $customerId;
            }
        @endphp
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
                <a class="dues-nav-pill pill-expenses {{ $type === 'expenses' ? 'is-active' : '' }}"
                   href="{{ route('report.outstandingDues', ['type' => 'expenses']) }}">
                    <i class="dripicons-card"></i> Other Dues (Expenses)
                </a>
                <a class="dues-nav-pill pill-ageing" href="{{ route('accounting.ageing', $ageingParams) }}">
                    <i class="dripicons-clock"></i> Ageing Ledger
                </a>
                <a class="dues-nav-pill pill-hub font-weight-bold" href="{{ route('dues.index', $hubParams) }}">
                    <i class="dripicons-wallet"></i> Due Clearance Hub
                </a>
            </div>

            <button type="button" class="btn btn-outline-secondary dues-print-btn" data-erp-print="#outstanding-dues-table">
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
    <div class="u-r-8px alert alert-light border d-flex align-items-center justify-content-between mb-3 py-2 px-3 not-printable">
        <span class="small text-muted">
            <i class="fa fa-info-circle text-primary mr-1"></i>
            This is an audit and reconciliation report. To record payments, settlements, and clear dues, open the
            <a href="{{ route('dues.index', $hubParams) }}" class="font-weight-bold text-primary">
                Due Management Command Center
            </a>.
        </span>
        <a href="{{ route('dues.index', $hubParams) }}" class="btn btn-outline-primary btn-sm py-1 font-weight-bold">
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
            @elseif($type === 'payables')
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
                        <th class="dues-row-number text-center">#</th>
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
                    @foreach($rows as $idx => $r)
                        <tr>
                            <td class="text-center font-weight-600 text-muted">{{ $idx + 1 }}</td>
                            <td>{{ date('d-m-Y', strtotime($r->created_at)) }}</td>
                            <td>
                                @if($type === 'receivables')
                                    <a href="{{ route('sales.index', ['search' => $r->reference_no]) }}" class="font-weight-bold text-primary font-monospace" title="View Sale">
                                        {{ $r->reference_no }}
                                    </a>
                                @elseif($type === 'payables')
                                    <a href="{{ route('purchases.index', ['search' => $r->reference_no]) }}" class="font-weight-bold text-primary font-monospace" title="View Purchase">
                                        {{ $r->reference_no }}
                                    </a>
                                @else
                                    <span class="font-weight-bold text-primary font-monospace">
                                        {{ $r->reference_no }}
                                    </span>
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
                    @endforeach
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
                footer: true,
                exportOptions: { columns: ':visible:not(.not-exported)' }
            }
        ],
        "language": {
            "emptyTable": "No outstanding dues found matching the active filters.",
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

