@extends('backend.layout.main')
@section('content')

<link rel="stylesheet" href="<?php echo asset('css/commercial-entry.css') . '?v=' . (file_exists(public_path('css/commercial-entry.css')) ? filemtime(public_path('css/commercial-entry.css')) : time()) ?>" type="text/css">
<link rel="stylesheet" href="<?php echo asset('css/commercial-workspace.css') . '?v=' . (file_exists(public_path('css/commercial-workspace.css')) ? filemtime(public_path('css/commercial-workspace.css')) : time()) ?>" type="text/css">
<link rel="stylesheet" href="<?php echo asset('css/zolo-erp-neo.css') . '?v=' . (file_exists(public_path('css/zolo-erp-neo.css')) ? filemtime(public_path('css/zolo-erp-neo.css')) : time()) ?>" type="text/css">

<style>
    .report-workspace-view {
        padding: 12px 18px 24px;
        background: #f8fafc;
        min-height: calc(100vh - 40px);
    }
    body.dark-mode .report-workspace-view {
        background: #0b0f19;
    }
    .kpi-summary-strip {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
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
        font-size: 18px;
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
    #challan-table {
        border-collapse: collapse !important;
        width: 100% !important;
    }
    #challan-table thead th {
        background: var(--z-table-head-bg, #022051) !important;
        color: var(--z-table-head-text, #ffffff) !important;
        font-size: 10.5px !important;
        font-weight: 700 !important;
        text-transform: uppercase !important;
        letter-spacing: 0.04em !important;
        padding: 9px 12px !important;
        border: none !important;
        white-space: nowrap;
    }
    body.dark-mode #challan-table thead th {
        background: var(--z-table-head-bg, #022051) !important;
        color: var(--z-table-head-text, #ffffff) !important;
    }
    #challan-table tbody tr:nth-child(even) {
        background: var(--z-table-stripe, #f8fafc);
    }
    #challan-table tbody tr:nth-child(odd) {
        background: var(--z-surface, #ffffff);
    }
    body.dark-mode #challan-table tbody tr:nth-child(even) {
        background: #141c2c;
    }
    body.dark-mode #challan-table tbody tr:nth-child(odd) {
        background: #1e293b;
    }
    #challan-table tbody tr:hover {
        background: #e2e8f0 !important;
    }
    body.dark-mode #challan-table tbody tr:hover {
        background: #27354f !important;
    }
    #challan-table td {
        padding: 9px 12px !important;
        border-bottom: 1px solid #e2e8f0 !important;
        vertical-align: middle !important;
        font-size: 12.5px;
    }
    body.dark-mode #challan-table td {
        border-color: #334155 !important;
        color: #e2e8f0;
    }
    #challan-table tfoot th {
        background: #f1f5f9 !important;
        color: #0f172a !important;
        font-weight: 700 !important;
        padding: 8px 12px !important;
        border-top: 2px solid #cbd5e1 !important;
    }
    body.dark-mode #challan-table tfoot th {
        background: #1e293b !important;
        color: #f8fafc !important;
        border-top-color: #475569 !important;
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

@php
    $challanCount = count($challan_data);
@endphp

<section class="report-workspace-view">
    <!-- Top Command Bar -->
    <div class="comm-command-bar">
        <div class="comm-title-group">
            <h1><i class="dripicons-clipboard text-primary"></i> Challan Report Command Center</h1>
            <span class="comm-title-badge"><i class="dripicons-tag"></i> Dispatch Log &amp; Delivery Realization</span>
        </div>
        <ul class="comm-nav-pills">
            <li><a class="nav-link" href="{{ route('delivery-challans.index') }}"><i class="dripicons-list"></i> Challan Register</a></li>
            <li><a class="nav-link active" href="{{ route('report.challan') }}"><i class="dripicons-clipboard"></i> Challan Report</a></li>
            <li><a class="nav-link" href="{{ url('report/sale_report') }}"><i class="dripicons-cart"></i> Sales Report</a></li>
            <li><a class="nav-link" href="{{ route('report.paymentByDate') }}"><i class="dripicons-wallet"></i> Payments</a></li>
        </ul>
        <div class="comm-actions">
            <button class="btn btn-secondary btn-action-pill" onclick="window.print()">
                <i class="dripicons-print"></i> Print
            </button>
        </div>
    </div>

    <!-- KPI Summary Strip -->
    <div class="kpi-summary-strip">
        <div class="kpi-card">
            <div>
                <div class="kpi-label">TOTAL CHALLANS</div>
                <div class="kpi-number">{{ number_format($challanCount) }}</div>
            </div>
            <div style="width:36px;height:36px;border-radius:6px;background:var(--z-primary-soft, #fbf4fb);display:flex;align-items:center;justify-content:center;color:var(--z-primary, #7d287e);">
                <i class="dripicons-clipboard" style="font-size:18px;"></i>
            </div>
        </div>
        <div class="kpi-card">
            <div>
                <div class="kpi-label">BASED ON</div>
                <div class="kpi-number" style="font-size:14px; font-weight:700;">
                    {{ $based_on == 'created_at' ? 'Created Date' : 'Closing Date' }}
                </div>
            </div>
            <div style="width:36px;height:36px;border-radius:6px;background:#e0f2fe;display:flex;align-items:center;justify-content:center;color:#0284c7;">
                <i class="dripicons-clock" style="font-size:18px;"></i>
            </div>
        </div>
        <div class="kpi-card">
            <div>
                <div class="kpi-label">WINDOW</div>
                <div class="kpi-number" style="font-size:13px; font-weight:600; color:#475569;">
                    {{ date('d M Y', strtotime($starting_date)) }} — {{ date('d M Y', strtotime($ending_date)) }}
                </div>
            </div>
            <div style="width:36px;height:36px;border-radius:6px;background:#fef3c7;display:flex;align-items:center;justify-content:center;color:#d97706;">
                <i class="dripicons-calendar" style="font-size:18px;"></i>
            </div>
        </div>
        <div class="kpi-card">
            <div>
                <div class="kpi-label">STATUS</div>
                <div class="kpi-number" style="font-size:14px; font-weight:600; color:#059669;">Dispatched</div>
            </div>
            <div style="width:36px;height:36px;border-radius:6px;background:#dcfce7;display:flex;align-items:center;justify-content:center;color:#059669;">
                <i class="dripicons-checkmark" style="font-size:18px;"></i>
            </div>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="desk-filter-card">
        <form action="{{ route('report.challan') }}" method="get" class="form-inline d-flex align-items-center flex-wrap gap-2">
            <div class="form-group mr-2 mb-1">
                <label class="mr-2 font-weight-bold text-muted small"><i class="dripicons-tag"></i> Based On</label>
                <select name="based_on" class="form-control form-control-sm" style="height:30px; font-size:12px; min-width:140px;">
                    <option value="created_at" @selected($based_on != 'closing_date')>Created Date</option>
                    <option value="closing_date" @selected($based_on == 'closing_date')>Closing Date</option>
                </select>
            </div>
            <div class="form-group mr-2 mb-1">
                <label class="mr-2 font-weight-bold text-muted small"><i class="dripicons-calendar"></i> Starting Date</label>
                <input type="text" class="date form-control form-control-sm" name="starting_date" value="{{ $starting_date }}" required style="height:30px; font-size:12px; width:130px;" />
            </div>
            <div class="form-group mr-2 mb-1">
                <label class="mr-2 font-weight-bold text-muted small"><i class="dripicons-calendar"></i> Ending Date</label>
                <input type="text" class="date form-control form-control-sm" name="ending_date" value="{{ $ending_date }}" required style="height:30px; font-size:12px; width:130px;" />
            </div>
            <div class="form-group mb-1">
                <button class="btn btn-primary btn-sm btn-action-pill" type="submit" style="background:#0f172a; border-color:#0f172a;">
                    <i class="fa fa-filter"></i> {{ __('db.submit') }}
                </button>
            </div>
        </form>
    </div>

    <!-- Data Table Container -->
    <div class="desk-card-wrapper">
        <div class="table-responsive">
            <table id="challan-table" class="table table-hover" style="width:100%;">
                <thead>
                    <tr>
                        <th class="not-exported"></th>
                        <th>Challan No</th>
                        <th>Order No</th>
                        <th>Order Date</th>
                        <th>Code</th>
                        <th>Delivery Date</th>
                        <th>Sales Amount</th>
                        <th>Cash Payment</th>
                        <th>Online Payment</th>
                        <th>Cheque Payment</th>
                        <th>Shipping Income</th>
                        <th>Delivery Charge</th>
                        <th>Net</th>
                        <th>Net Cash</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($challan_data as $challan)
                        <?php
                        $packingSlipList = explode(",", $challan->packing_slip_list);
                        $status_list = explode(",", $challan->status_list);
                        $cash_list = explode(",", $challan->cash_list);
                        $cheque_list = explode(",", $challan->cheque_list);
                        $online_payment_list = explode(",", $challan->online_payment_list);
                        $delivery_charge_list = explode(",", $challan->delivery_charge_list);
                        ?>
                        @foreach ($packingSlipList as $key => $packingSlipId)
                            <?php
                            $packingSlip = \App\Models\PackingSlip::with('sale.products')->find($packingSlipId);
                            if(!$cash_list[$key]) $cash_list[$key] = 0;
                            if(!$online_payment_list[$key]) $online_payment_list[$key] = 0;
                            if(!$cheque_list[$key]) $cheque_list[$key] = 0;
                            if(!$delivery_charge_list[$key]) $delivery_charge_list[$key] = 0;
                            ?>
                            @if($packingSlip && $packingSlip->sale)
                                <tr>
                                    <td>{{ $index }}</td>
                                    <td><span class="font-weight-bold font-monospace text-primary">DC-{{ $challan->reference_no }}</span></td>
                                    <td>{{ $packingSlip->sale->reference_no }}</td>
                                    <td>{{ date(config('date_format'), strtotime($packingSlip->sale->created_at)) }}</td>
                                    <td>
                                        @foreach ($packingSlip->sale->products as $i => $product)
                                            @if($i),@endif
                                            {{ $product->code }}
                                        @endforeach
                                    </td>
                                    <td>
                                        @if ($packingSlip->sale->sale_status == 1)
                                            {{ date(config('date_format'), strtotime($packingSlip->sale->updated_at)) }}
                                        @else
                                            <span class="text-muted">N/A</span>
                                        @endif
                                    </td>
                                    <td class="font-weight-600">{{ number_format($packingSlip->sale->grand_total, 2) }}</td>
                                    <td>{{ number_format((float)$cash_list[$key], 2) }}</td>
                                    <td>{{ number_format((float)$online_payment_list[$key], 2) }}</td>
                                    <td>{{ number_format((float)$cheque_list[$key], 2) }}</td>
                                    <td>{{ number_format($packingSlip->sale->shipping_cost, 2) }}</td>
                                    <td>{{ number_format((float)$delivery_charge_list[$key], 2) }}</td>
                                    <td class="font-weight-bold">{{ number_format($cash_list[$key] + $online_payment_list[$key] + $cheque_list[$key] - $delivery_charge_list[$key], 2) }}</td>
                                    <td class="font-weight-bold text-success">{{ number_format($cash_list[$key] - $delivery_charge_list[$key], 2) }}</td>
                                </tr>
                                <?php $index++; ?>
                            @endif
                        @endforeach
                    @endforeach
                </tbody>
                <tfoot class="tfoot active">
                    <th></th>
                    <th>Total:</th>
                    <th></th>
                    <th></th>
                    <th></th>
                    <th></th>
                    <th></th>
                    <th></th>
                    <th></th>
                    <th></th>
                    <th></th>
                    <th></th>
                    <th></th>
                    <th></th>
                </tfoot>
            </table>
        </div>
    </div>
</section>

@endsection

@push('scripts')
<script type="text/javascript">
    $("ul#report").siblings('a').attr('aria-expanded','true');
    $("ul#report").addClass("show");
    $("ul#report #challan-report-menu").addClass("active");

    $('#challan-table').DataTable({
        "order": [],
        'columnDefs': [
            {
                "orderable": false,
                'targets': [0]
            },
            {
                'checkboxes': {
                    'selectRow': true
                },
                'targets': 0
            }
        ],
        'select': { style: 'multi', selector: 'td:first-child' },
        'lengthMenu': [[10, 25, 50, -1], [10, 25, 50, "All"]],
        dom: '<"row align-items-center mb-2"<"col-md-6 d-flex align-items-center gap-2"lB><"col-md-6 d-flex justify-content-end"f>r>t<"row align-items-center mt-2"<"col-md-6"i><"col-md-6 d-flex justify-content-end"p>>',
        buttons: [
            {
                extend: 'pdf',
                text: '<i title="export to pdf" class="fa fa-file-pdf-o text-danger"></i>',
                exportOptions: {
                    columns: ':visible:Not(.not-exported)',
                    rows: ':visible'
                },
                action: function(e, dt, button, config) {
                    datatable_sum(dt, true);
                    $.fn.dataTable.ext.buttons.pdfHtml5.action.call(this, e, dt, button, config);
                    datatable_sum(dt, false);
                },
                footer: true
            },
            {
                extend: 'excel',
                text: '<i title="export to excel" class="dripicons-document-new text-success"></i>',
                exportOptions: {
                    columns: ':visible:not(.not-exported)',
                    rows: ':visible'
                },
                action: function(e, dt, button, config) {
                    datatable_sum(dt, true);
                    $.fn.dataTable.ext.buttons.excelHtml5.action.call(this, e, dt, button, config);
                    datatable_sum(dt, false);
                },
                footer: true
            },
            {
                extend: 'csv',
                text: '<i title="export to csv" class="fa fa-file-text-o text-info"></i>',
                exportOptions: {
                    columns: ':visible:Not(.not-exported)',
                    rows: ':visible'
                },
                action: function(e, dt, button, config) {
                    datatable_sum(dt, true);
                    $.fn.dataTable.ext.buttons.csvHtml5.action.call(this, e, dt, button, config);
                    datatable_sum(dt, false);
                },
                footer: true
            },
            {
                extend: 'print',
                text: '<i title="print" class="fa fa-print text-primary"></i>',
                exportOptions: {
                    columns: ':visible:not(.not-exported)',
                    rows: ':visible',
                },
                action: function(e, dt, button, config) {
                    datatable_sum(dt, true);
                    $.fn.dataTable.ext.buttons.print.action.call(this, e, dt, button, config);
                    datatable_sum(dt, false);
                },
                footer: true
            },
            {
                extend: 'colvis',
                text: '<i title="column visibility" class="fa fa-eye"></i>',
                columns: ':gt(0)'
            },
        ],
        drawCallback: function() {
            var api = this.api();
            datatable_sum(api, false);
        }
    });

    function datatable_sum(dt_selector, is_calling_first) {
        if (dt_selector.rows('.selected').any() && is_calling_first) {
            var rows = dt_selector.rows('.selected').indexes();
            $(dt_selector.column(6).footer()).html(dt_selector.cells(rows, 6, { page: 'current' }).data().sum().toFixed(2));
            $(dt_selector.column(7).footer()).html(dt_selector.cells(rows, 7, { page: 'current' }).data().sum().toFixed(2));
            $(dt_selector.column(8).footer()).html(dt_selector.cells(rows, 8, { page: 'current' }).data().sum().toFixed(2));
            $(dt_selector.column(9).footer()).html(dt_selector.cells(rows, 9, { page: 'current' }).data().sum().toFixed(2));
            $(dt_selector.column(10).footer()).html(dt_selector.cells(rows, 10, { page: 'current' }).data().sum().toFixed(2));
            $(dt_selector.column(11).footer()).html(dt_selector.cells(rows, 11, { page: 'current' }).data().sum().toFixed(2));
            $(dt_selector.column(12).footer()).html(dt_selector.cells(rows, 12, { page: 'current' }).data().sum().toFixed(2));
            $(dt_selector.column(13).footer()).html(dt_selector.cells(rows, 13, { page: 'current' }).data().sum().toFixed(2));
        } else {
            $(dt_selector.column(6).footer()).html(dt_selector.column(6, { page: 'current' }).data().sum().toFixed(2));
            $(dt_selector.column(7).footer()).html(dt_selector.column(7, { page: 'current' }).data().sum().toFixed(2));
            $(dt_selector.column(8).footer()).html(dt_selector.column(8, { page: 'current' }).data().sum().toFixed(2));
            $(dt_selector.column(9).footer()).html(dt_selector.column(9, { page: 'current' }).data().sum().toFixed(2));
            $(dt_selector.column(10).footer()).html(dt_selector.column(10, { page: 'current' }).data().sum().toFixed(2));
            $(dt_selector.column(11).footer()).html(dt_selector.column(11, { page: 'current' }).data().sum().toFixed(2));
            $(dt_selector.column(12).footer()).html(dt_selector.column(12, { page: 'current' }).data().sum().toFixed(2));
            $(dt_selector.column(13).footer()).html(dt_selector.column(13, { page: 'current' }).data().sum().toFixed(2));
        }
    }
</script>
@endpush
