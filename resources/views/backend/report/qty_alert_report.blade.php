@extends('backend.layout.main') @section('content')

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
    #report-table {
        border-collapse: collapse !important;
        width: 100% !important;
    }
    #report-table thead th {
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
    body.dark-mode #report-table thead th {
        background: #090d16 !important;
        color: #f8fafc !important;
    }
    #report-table tbody tr:nth-child(even) {
        background: #f8fafc;
    }
    #report-table tbody tr:nth-child(odd) {
        background: #ffffff;
    }
    body.dark-mode #report-table tbody tr:nth-child(even) {
        background: #141c2c;
    }
    body.dark-mode #report-table tbody tr:nth-child(odd) {
        background: #1e293b;
    }
    #report-table tbody tr:hover {
        background: #e2e8f0 !important;
    }
    body.dark-mode #report-table tbody tr:hover {
        background: #27354f !important;
    }
    #report-table td {
        padding: 9px 12px !important;
        border-bottom: 1px solid #e2e8f0 !important;
        vertical-align: middle !important;
        font-size: 12.5px;
    }
    body.dark-mode #report-table td {
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

@php
    $alertCount = count($lims_product_data);
@endphp

<section class="report-workspace-view">
    <!-- Top Command Bar -->
    <div class="comm-command-bar">
        <div class="comm-title-group">
            <h1><i class="dripicons-warning text-danger"></i> {{ __('db.Product Quantity Alert') }} Command Center</h1>
            <span class="comm-title-badge" style="background:#fee2e2; color:#b91c1c; border-color:#fca5a5;"><i class="dripicons-tag"></i> Critical Low Stock Levels</span>
        </div>
        <ul class="comm-nav-pills">
            <li><a class="nav-link" href="{{ url('report/product_report') }}"><i class="dripicons-box"></i> Product Report</a></li>
            <li><a class="nav-link active" href="{{ route('report.qtyAlert') }}"><i class="dripicons-warning"></i> Qty Alerts</a></li>
            <li><a class="nav-link" href="{{ route('report.productExpiry') }}"><i class="dripicons-hourglass"></i> Expiry Report</a></li>
            <li><a class="nav-link" href="{{ url('report/purchase') }}"><i class="dripicons-basket"></i> Purchase Report</a></li>
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
                <div class="kpi-label">CRITICAL ITEMS ALERT</div>
                <div class="kpi-number" style="color:#b91c1c;">{{ number_format($alertCount) }}</div>
            </div>
            <div style="width:36px;height:36px;border-radius:6px;background:#fee2e2;display:flex;align-items:center;justify-content:center;color:#b91c1c;">
                <i class="dripicons-warning" style="font-size:18px;"></i>
            </div>
        </div>
        <div class="kpi-card">
            <div>
                <div class="kpi-label">ACTION RECOMMENDED</div>
                <div class="kpi-number" style="font-size:14px; font-weight:700; color:#d97706;">Raise Purchase Orders</div>
            </div>
            <div style="width:36px;height:36px;border-radius:6px;background:#fef3c7;display:flex;align-items:center;justify-content:center;color:#d97706;">
                <i class="dripicons-basket" style="font-size:18px;"></i>
            </div>
        </div>
        <div class="kpi-card">
            <div>
                <div class="kpi-label">MONITORING</div>
                <div class="kpi-number" style="font-size:14px; font-weight:600; color:#0284c7;">Safety Stock Thresholds</div>
            </div>
            <div style="width:36px;height:36px;border-radius:6px;background:#e0f2fe;display:flex;align-items:center;justify-content:center;color:#0284c7;">
                <i class="dripicons-pulse" style="font-size:18px;"></i>
            </div>
        </div>
        <div class="kpi-card">
            <div>
                <div class="kpi-label">HEALTH</div>
                <div class="kpi-number" style="font-size:14px; font-weight:600; color: {{ $alertCount > 0 ? '#b91c1c' : '#059669' }};">
                    {{ $alertCount > 0 ? 'Requires Reorder' : 'Optimal Inventory' }}
                </div>
            </div>
            <div style="width:36px;height:36px;border-radius:6px;background:#dcfce7;display:flex;align-items:center;justify-content:center;color:#059669;">
                <i class="dripicons-checkmark" style="font-size:18px;"></i>
            </div>
        </div>
    </div>

    <!-- Data Table Container -->
    <div class="desk-card-wrapper">
        <div class="table-responsive">
            <table id="report-table" class="table table-hover" style="width:100%;">
                <thead>
                    <tr>
                        <th class="not-exported"></th>
                        <th>{{ __('db.Image') }}</th>
                        <th>{{ __('db.Product Name') }}</th>
                        <th>{{ __('db.Product Code') }}</th>
                        <th>{{ __('db.Quantity') }}</th>
                        <th>{{ __('db.Alert Quantity') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($lims_product_data as $key => $product)
                        <tr>
                            <td>{{ $key }}</td>
                            <td>
                                <?php
                                $images = explode(",", $product->image);
                                $product->base_image = $images[0];
                                ?>
                                @if($product->base_image)
                                    <img src="{{ url('images/product', $product->base_image) }}" height="48" width="48" style="object-fit: cover; border-radius: 4px; border: 1px solid #e2e8f0;">
                                @else
                                    <div style="width:48px; height:48px; background:#f1f5f9; border-radius:4px; display:flex; align-items:center; justify-content:center; color:#94a3b8;">
                                        <i class="dripicons-box" style="font-size:18px;"></i>
                                    </div>
                                @endif
                            </td>
                            <td><span class="font-weight-600">{{ $product->name }}</span></td>
                            <td><span class="font-weight-bold font-monospace text-primary">{{ $product->code }}</span></td>
                            <td>
                                <span class="badge badge-danger font-weight-bold" style="font-size: 12px; padding: 4px 8px;">
                                    {{ number_format((float)($product->qty), $general_setting->decimal, '.', '') }}
                                </span>
                            </td>
                            <td>
                                <span class="font-weight-bold text-muted">
                                    {{ number_format((float)($product->alert_quantity), $general_setting->decimal, '.', '') }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</section>

@endsection

@push('scripts')
<script type="text/javascript">
    $("ul#report").siblings('a').attr('aria-expanded','true');
    $("ul#report").addClass("show");
    $("ul#report #qtyAlert-report-menu").addClass("active");

    $('#report-table').DataTable({
        "order": [],
        'language': {
            'lengthMenu': '_MENU_ {{ __('db.records per page') }}',
            "info": '<small>{{ __('db.Showing') }} _START_ - _END_ (_TOTAL_)</small>',
            "search": '{{ __('db.Search') }}',
            'paginate': {
                'previous': '<i class="dripicons-chevron-left"></i>',
                'next': '<i class="dripicons-chevron-right"></i>'
            }
        },
        'columnDefs': [
            {
                "orderable": false,
                'targets': 0
            },
            {
                'render': function(data, type, row, meta) {
                    if (type === 'display') {
                        data = '<div class="checkbox"><input type="checkbox" class="dt-checkboxes"><label></label></div>';
                    }
                    return data;
                },
                'checkboxes': {
                    'selectRow': true,
                    'selectAllRender': '<div class="checkbox"><input type="checkbox" class="dt-checkboxes"><label></label></div>'
                },
                'targets': [0]
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
            },
            {
                extend: 'excel',
                text: '<i title="export to excel" class="dripicons-document-new text-success"></i>',
                exportOptions: {
                    columns: ':visible:Not(.not-exported)',
                    rows: ':visible'
                },
            },
            {
                extend: 'csv',
                text: '<i title="export to csv" class="fa fa-file-text-o text-info"></i>',
                exportOptions: {
                    columns: ':visible:Not(.not-exported)',
                    rows: ':visible'
                },
            },
            {
                extend: 'print',
                text: '<i title="print" class="fa fa-print text-primary"></i>',
                exportOptions: {
                    columns: ':visible:Not(.not-exported)',
                    rows: ':visible'
                },
            },
            {
                extend: 'colvis',
                text: '<i title="column visibility" class="fa fa-eye"></i>',
                columns: ':gt(0)'
            }
        ],
    });
</script>
@endpush
