@extends('backend.layout.main')

@push('css')
    <link rel="stylesheet" href="{{ versioned_asset('css/pages/stock-report.css') }}">
@endpush

@section('content')

@php
    $start_date = $start_date ?? request('start_date') ?? date('Y-m-01');
    $end_date = $end_date ?? request('end_date') ?? date('Y-m-d');
@endphp

<x-error-message key="not_permitted" />

<section class="report-workspace-view">
    <!-- Top Command Bar -->
    <div class="comm-command-bar d-print-none">
        <div class="comm-title-group">
            <h1><i class="dripicons-box text-primary"></i> {{ __('db.Stock Report') }} Command Center</h1>
            <span class="comm-title-badge"><i class="dripicons-tag"></i> Real-Time Inventory Balance, Stock Valuation &amp; Godown Distribution</span>
        </div>
        <ul class="comm-nav-pills">
            <li><a class="nav-link active" href="{{ route('report.stock') }}"><i class="dripicons-list"></i> Stock Report</a></li>
            <li><a class="nav-link" href="{{ route('report.warehouseStock') }}"><i class="dripicons-store"></i> Stock Chart</a></li>
            <li><a class="nav-link" href="{{ route('inventory-reports.stock-by-lot') }}"><i class="dripicons-stack"></i> Stock by Lot</a></li>
            <li><a class="nav-link" href="{{ route('report.warehouse') }}"><i class="dripicons-archive"></i> Warehouse Report</a></li>
            <li><a class="nav-link" href="{{ route('report.product') }}"><i class="dripicons-box"></i> Product Report</a></li>
            <li><a class="nav-link" href="{{ route('report.qtyAlert') }}"><i class="dripicons-warning"></i> Qty Alerts</a></li>
        </ul>
        <div class="comm-actions">
            <button class="btn btn-secondary btn-action-pill" data-erp-print>
                <i class="dripicons-print"></i> Print Statement
            </button>
            <button class="btn btn-primary btn-action-pill" onclick="exportStockCSV()">
                <i class="dripicons-download"></i> Export CSV
            </button>
        </div>
    </div>

    <!-- 5 KPI Summary Strip -->
    <div class="kpi-summary-strip d-print-none">
        <div class="kpi-card">
            <div>
                <div class="kpi-label">TOTAL CATALOG SKUS</div>
                <div class="kpi-number">{{ number_format($kpiTotalItems) }}</div>
                <div class="kpi-subtext text-success font-weight-bold">{{ number_format($kpiInStockItems) }} In Stock</div>
            </div>
            <div class="z-icon-tile z-tone-primary">
                <i class="dripicons-box u-fs-18px"></i>
            </div>
        </div>
        <div class="kpi-card">
            <div>
                <div class="kpi-label">TOTAL STOCK UNITS</div>
                <div class="kpi-number">{{ number_format($kpiTotalQty, 2) }}</div>
                <div class="kpi-subtext">Physical balance on hand</div>
            </div>
            <div class="z-icon-tile z-tone-info">
                <i class="dripicons-stack u-fs-18px"></i>
            </div>
        </div>
        <div class="kpi-card">
            <div>
                <div class="kpi-label">STOCK VALUE (BY COST)</div>
                <div class="kpi-number">{{ $currencySymbol }} {{ number_format($kpiTotalCost, 2) }}</div>
                <div class="kpi-subtext text-muted">Asset valuation @ cost</div>
            </div>
            <div class="z-icon-tile z-tone-warning">
                <i class="dripicons-tag u-fs-18px"></i>
            </div>
        </div>
        <div class="kpi-card">
            <div>
                <div class="kpi-label">STOCK VALUE (BY RETAIL)</div>
                <div class="kpi-number text-primary">{{ $currencySymbol }} {{ number_format($kpiTotalPrice, 2) }}</div>
                <div class="kpi-subtext text-muted">Potential sales revenue</div>
            </div>
            <div class="z-icon-tile z-tone-info">
                <i class="dripicons-wallet u-fs-18px"></i>
            </div>
        </div>
        <div class="kpi-card">
            <div>
                <div class="kpi-label">PROJECTED GROSS PROFIT</div>
                <div class="kpi-number text-success">{{ $currencySymbol }} {{ number_format($kpiEstimatedProfit, 2) }}</div>
                <div class="kpi-subtext text-success font-weight-bold">{{ number_format($kpiProfitMarginPercent, 1) }}% Margin</div>
            </div>
            <div class="z-icon-tile z-tone-success">
                <i class="dripicons-graph-bar u-fs-18px"></i>
            </div>
        </div>
    </div>

    <!-- Desk Filter Card -->
    <div class="desk-filter-card d-print-none">
        {!! Form::open(['route' => 'report.stock', 'method' => 'get', 'id' => 'stock-filter-form', 'class' => 'form-inline d-flex align-items-center flex-wrap gap-2']) !!}
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
                <label class="mr-2 font-weight-bold text-muted small"><i class="dripicons-store"></i> {{ __('db.Choose Warehouse') }}</label>
                <select name="warehouse_id" class="form-control form-control-sm u-h-30px u-fs-12px u-minw-160px">
                    <option value="0">{{ __('db.All Warehouse') }}</option>
                    @foreach($lims_warehouse_list as $warehouse)
                        <option value="{{ $warehouse->id }}" @selected($warehouse_id == $warehouse->id)>{{ $warehouse->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group mr-2 mb-1">
                <label class="mr-2 font-weight-bold text-muted small"><i class="dripicons-tags"></i> {{ __('db.category') }}</label>
                <select name="category_id" class="form-control form-control-sm u-h-30px u-fs-12px u-minw-160px">
                    <option value="0">All Categories</option>
                    @foreach($categories_list as $category)
                        <option value="{{ $category->id }}" @selected($category_id == $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group mr-2 mb-1">
                <label class="mr-2 font-weight-bold text-muted small"><i class="dripicons-bookmark"></i> {{ __('db.Brand') }}</label>
                <select name="brand_id" class="form-control form-control-sm u-h-30px u-fs-12px u-minw-150px">
                    <option value="0">All Brands</option>
                    @foreach($brands_list as $brand)
                        <option value="{{ $brand->id }}" @selected($brand_id == $brand->id)>{{ $brand->title }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group mr-2 mb-1">
                <label class="mr-2 font-weight-bold text-muted small"><i class="dripicons-checklist"></i> {{ __('db.Stock Status') }}</label>
                <select name="stock_status" class="form-control form-control-sm u-h-30px u-fs-12px u-minw-140px">
                    <option value="all" @selected($stock_status == 'all')>All Items</option>
                    <option value="in_stock" @selected($stock_status == 'in_stock')>In Stock (> 0)</option>
                    <option value="low_stock" @selected($stock_status == 'low_stock')>Low Stock Alert</option>
                    <option value="out_of_stock" @selected($stock_status == 'out_of_stock')>Out of Stock (<= 0)</option>
                </select>
            </div>
            <div class="form-group mb-1">
                <button class="btn btn-primary btn-sm btn-action-pill mr-1" type="submit">
                    <i class="fa fa-filter"></i> {{ __('db.submit') }}
                </button>
                <a href="{{ route('report.stock') }}" class="btn btn-outline-secondary btn-sm btn-action-pill">
                    <i class="dripicons-clockwise"></i> Reset
                </a>
            </div>
        {!! Form::close() !!}
    </div>

    <!-- Quick In-Table Filter Pills -->
    <div class="d-flex justify-content-between align-items-center mb-2 px-1 quick-filter-bar d-print-none">
        <div class="quick-filter-pills">
            <span class="small font-weight-bold text-muted mr-1">Quick Filter:</span>
            <button type="button" class="btn-filter-pill active" data-filter="all">
                All ({{ count($stockItems) }})
            </button>
            <button type="button" class="btn-filter-pill" data-filter="in_stock">
                <i class="dripicons-checkmark text-success mr-1"></i> In Stock ({{ $kpiInStockItems }})
            </button>
            <button type="button" class="btn-filter-pill" data-filter="low_stock">
                <i class="dripicons-warning text-warning mr-1"></i> Low Stock ({{ $kpiLowStockItems }})
            </button>
            <button type="button" class="btn-filter-pill" data-filter="out_of_stock">
                <i class="dripicons-cross text-danger mr-1"></i> Out of Stock ({{ $kpiOutOfStockItems }})
            </button>
        </div>
        <div class="text-muted small">
            <span>Period: <strong>{{ date('d M Y', strtotime($start_date)) }} — {{ date('d M Y', strtotime($end_date)) }}</strong> | Location: <strong>{{ $warehouseName }}</strong> | Category: <strong>{{ $categoryName }}</strong></span>
        </div>
    </div>

    <!-- Tabular Stock Inventory Desk -->
    <div class="desk-card-wrapper">
        <div class="table-responsive">
            <table id="stock-inventory-table" class="table stock-table u-w-100pct" data-no-fit="1" data-length-chosen="1">
                <thead>
                    <tr>
                        <th class="col-idx not-exported not-exported-col" data-width="35px">#</th>
                        <th class="col-product" data-width="240px">{{ __('db.product') }}</th>
                        <th class="col-category" data-width="140px">{{ __('db.category') }} / {{ __('db.Brand') }}</th>
                        <th class="col-warehouse" data-width="130px">{{ __('db.Warehouse') }}</th>
                        <th class="col-qty text-right" data-width="110px">{{ __('db.Stock Quantity') }}</th>
                        <th class="col-cost text-right" data-width="100px">{{ __('db.Unit Cost') }} ({{ $currencySymbol }})</th>
                        <th class="col-val-cost text-right" data-width="120px">Stock Value (Cost)</th>
                        <th class="col-price text-right" data-width="100px">{{ __('db.Unit Price') }} ({{ $currencySymbol }})</th>
                        <th class="col-val-price text-right" data-width="120px">Stock Value (Retail)</th>
                        <th class="col-alert text-right not-exported not-exported-col" data-width="80px">{{ __('db.Alert Quantity') }}</th>
                        <th class="col-status text-center not-exported not-exported-col" data-width="100px">{{ __('db.Stock Status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($stockItems as $index => $item)
                        <tr data-status="{{ $item['status'] }}" data-qty="{{ $item['qty'] }}" data-cost-val="{{ $item['stock_value_cost'] }}" data-price-val="{{ $item['stock_value_price'] }}">
                            <td class="col-idx not-exported not-exported-col text-muted font-italic">{{ $index + 1 }}</td>
                            <td class="col-product">
                                <div class="d-flex align-items-center">
                                    <div class="product-thumb mr-2 d-print-none">
                                        @if($item['image'] && file_exists(public_path('images/product/'.$item['image'])))
                                            <img src="{{ asset('images/product/'.$item['image']) }}" alt="Product">
                                        @else
                                            <i class="dripicons-box text-muted"></i>
                                        @endif
                                    </div>
                                    <div>
                                        <div class="font-weight-bold text-dark">{{ $item['name'] }}</div>
                                        <div class="small text-muted font-monospace">
                                            <span class="badge badge-light border">{{ $item['code'] }}</span>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="col-category">
                                <div><strong>{{ $item['category_name'] }}</strong></div>
                                <div class="small text-muted">{{ $item['brand_name'] }}</div>
                            </td>
                            <td class="col-warehouse">
                                @if(count($item['warehouse_distribution']) > 1 && $warehouse_id == 0)
                                    @php
                                        $distText = implode(' | ', array_map(function($w) {
                                            return $w['name'] . ': ' . number_format($w['qty'], 0);
                                        }, $item['warehouse_distribution']));
                                    @endphp
                                    <span class="badge badge-info cursor-pointer" data-toggle="tooltip" title="{{ $distText }}">
                                        {{ count($item['warehouse_distribution']) }} Warehouses <i class="dripicons-information"></i>
                                    </span>
                                @else
                                    <span>{{ $item['warehouse_name'] }}</span>
                                @endif
                            </td>
                            <td class="col-qty text-right font-weight-bold">
                                <span class="{{ $item['qty'] <= 0 ? 'text-danger' : ($item['status'] == 'low_stock' ? 'text-warning font-weight-bold' : 'text-dark') }}">
                                    {{ number_format($item['qty'], 2) }}
                                </span>
                                <span class="badge badge-light border text-muted small ml-1">{{ $item['unit_code'] }}</span>
                            </td>
                            <td class="col-cost text-right">{{ number_format($item['cost'], 2) }}</td>
                            <td class="col-val-cost text-right font-weight-bold text-dark">{{ number_format($item['stock_value_cost'], 2) }}</td>
                            <td class="col-price text-right">{{ number_format($item['price'], 2) }}</td>
                            <td class="col-val-price text-right font-weight-bold text-primary">{{ number_format($item['stock_value_price'], 2) }}</td>
                            <td class="col-alert text-right text-muted not-exported not-exported-col">{{ $item['alert_quantity'] > 0 ? number_format($item['alert_quantity'], 2) : '—' }}</td>
                            <td class="col-status text-center not-exported not-exported-col">
                                <span class="stock-status-pill stock-status-{{ $item['status_class'] }}">
                                    @if($item['status'] == 'in_stock')
                                        <i class="dripicons-checkmark"></i>
                                    @elseif($item['status'] == 'low_stock')
                                        <i class="dripicons-warning"></i>
                                    @else
                                        <i class="dripicons-cross"></i>
                                    @endif
                                    {{ $item['status_label'] }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11" class="text-center py-4 text-muted">
                                <i class="dripicons-box u-fs-24px d-block mb-2 text-muted"></i>
                                No active products found matching the specified filters.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr>
                        <th class="col-idx not-exported not-exported-col"></th>
                        <th colspan="3" class="text-right foot-label">Filtered Inventory Totals:</th>
                        <th class="col-qty text-right text-dark" id="foot-total-qty">{{ number_format($kpiTotalQty, 2) }}</th>
                        <th class="col-cost"></th>
                        <th class="col-val-cost text-right text-dark" id="foot-total-cost">{{ number_format($kpiTotalCost, 2) }}</th>
                        <th class="col-price"></th>
                        <th class="col-val-price text-right text-primary" id="foot-total-price">{{ number_format($kpiTotalPrice, 2) }}</th>
                        <th class="col-alert not-exported not-exported-col"></th>
                        <th class="col-status not-exported not-exported-col"></th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <!-- Signatures block for official print -->
    <div class="erp-print-signatures">
        <div class="erp-print-sig-box">
            <div>Store Keeper / Godown In-Charge</div>
        </div>
        <div class="erp-print-sig-box">
            <div>Authorized Signatory</div>
        </div>
    </div>
</section>

@endsection

@push('scripts')
<script type="text/javascript">
    $("ul#report").siblings('a').attr('aria-expanded','true');
    $("ul#report").addClass("show");
    $("ul#report #stock-report-menu").addClass("active");

    var stockTable = null;

    $(document).ready(function() {
        $('[data-toggle="tooltip"]').tooltip();

        stockTable = $('#stock-inventory-table').DataTable({
            "order": [[4, "desc"]],
            "paging": true,
            "pageLength": 25,
            "lengthMenu": [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
            "dom": '<"row align-items-center mb-2"<"col-md-6"l><"col-md-6 d-flex justify-content-end"Bf>>rt<"row align-items-center mt-2"<"col-md-6"i><"col-md-6 d-flex justify-content-end"p>>',
            "buttons": [
                {
                    extend: 'print',
                    text: '<i title="print" class="fa fa-print text-primary"></i>',
                    exportOptions: {
                        columns: ':visible:not(.not-exported)',
                        rows: ':visible'
                    },
                    action: function(e, dt, button, config) {
                        $.fn.dataTable.ext.buttons.print.action.call(this, e, dt, button, config);
                    },
                    footer: true
                },
                {
                    extend: 'csv',
                    text: '<i title="export to csv" class="fa fa-file-text-o text-info"></i>',
                    exportOptions: {
                        columns: ':visible:not(.not-exported)',
                        rows: ':visible'
                    },
                    action: function(e, dt, button, config) {
                        exportStockCSV();
                    },
                    footer: true
                },
                {
                    extend: 'colvis',
                    text: '<i title="column visibility" class="fa fa-eye"></i>',
                    columns: ':gt(0)'
                }
            ],
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
                { "orderable": false, "targets": [0, 10] }
            ],
            "drawCallback": function() {
                recalculateTotals();
            }
        });

        // Initialize Date Range Picker
        $(".daterangepicker-field").daterangepicker({
            callback: function(startDate, endDate, period) {
                var start_date = startDate.format('YYYY-MM-DD');
                var end_date = endDate.format('YYYY-MM-DD');
                var title = start_date + ' To ' + end_date;
                $(this).val(title);
                $('#stock-filter-form input[name="start_date"]').val(start_date);
                $('#stock-filter-form input[name="end_date"]').val(end_date);
            }
        });

        // Quick filter pill handling
        $('.btn-filter-pill').on('click', function() {
            var filter = $(this).data('filter');
            $('.btn-filter-pill').removeClass('active');
            $(this).addClass('active');

            applyStatusFilter(filter);
        });
    });

    function applyStatusFilter(status) {
        if (!stockTable) return;

        // Clear custom filters tagged for stock status
        $.fn.dataTable.ext.search = $.fn.dataTable.ext.search.filter(function(fn) {
            return !fn._isStockStatusFilter;
        });

        if (status !== 'all') {
            var filterFn = function(settings, data, dataIndex) {
                var rowNode = stockTable.row(dataIndex).node();
                if (!rowNode) return true;
                var rowStatus = $(rowNode).attr('data-status');
                return rowStatus === status;
            };
            filterFn._isStockStatusFilter = true;
            $.fn.dataTable.ext.search.push(filterFn);
        }

        stockTable.draw();
    }

    function recalculateTotals() {
        if (!stockTable) return;

        var totalQty = 0;
        var totalCost = 0;
        var totalPrice = 0;

        stockTable.rows({ filter: 'applied' }).nodes().each(function() {
            var $row = $(this);
            totalQty += parseFloat($row.attr('data-qty')) || 0;
            totalCost += parseFloat($row.attr('data-cost-val')) || 0;
            totalPrice += parseFloat($row.attr('data-price-val')) || 0;
        });

        $('#foot-total-qty').text(totalQty.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
        $('#foot-total-cost').text(totalCost.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
        $('#foot-total-price').text(totalPrice.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
    }

    function printStockReport(e) {
        if (e && e.preventDefault) e.preventDefault();
        $("[data-erp-print-title]").text("STOCK INVENTORY & VALUATION REPORT");

        if (stockTable && stockTable.buttons) {
            var btn = stockTable.buttons('.buttons-print');
            if (btn && btn.length) {
                btn.trigger();
                return;
            }
        }

        if (stockTable) {
            var prevLen = stockTable.page.len();
            stockTable.page.len(-1).draw();

            setTimeout(function() {
                window.print();
                setTimeout(function() {
                    stockTable.page.len(prevLen).draw();
                }, 600);
            }, 300);
        } else {
            window.print();
        }
    }

    function exportStockCSV() {
        var csvContent = "data:text/csv;charset=utf-8,";
        csvContent += ["#", "Product", "Code", "Category", "Brand", "Warehouse", "Current Qty", "Unit", "Unit Cost", "Stock Value (Cost)", "Unit Price", "Stock Value (Retail)", "Alert Qty", "Status"].join(",") + "\r\n";

        @foreach($stockItems as $idx => $item)
            csvContent += [
                "{{ $idx + 1 }}",
                "\"{{ addslashes($item['name']) }}\"",
                "\"{{ $item['code'] }}\"",
                "\"{{ addslashes($item['category_name']) }}\"",
                "\"{{ addslashes($item['brand_name']) }}\"",
                "\"{{ addslashes($item['warehouse_name']) }}\"",
                "{{ $item['qty'] }}",
                "\"{{ $item['unit_code'] }}\"",
                "{{ $item['cost'] }}",
                "{{ $item['stock_value_cost'] }}",
                "{{ $item['price'] }}",
                "{{ $item['stock_value_price'] }}",
                "{{ $item['alert_quantity'] }}",
                "\"{{ $item['status_label'] }}\""
            ].join(",") + "\r\n";
        @endforeach

        var encodedUri = encodeURI(csvContent);
        var link = document.createElement("a");
        link.setAttribute("href", encodedUri);
        link.setAttribute("download", "Stock_Report_" + "{{ date('Ymd_His') }}" + ".csv");
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
</script>
@endpush
