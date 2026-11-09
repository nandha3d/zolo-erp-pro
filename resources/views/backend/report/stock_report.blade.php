@extends('backend.layout.main')
@section('content')

<x-error-message key="not_permitted" />

<style>
@media screen {
    .report-workspace-view {
        padding: 10px 18px 24px 18px !important;
        background: #f8fafc;
        min-height: calc(100vh - 70px);
    }
    body.dark-mode .report-workspace-view {
        background: #0b1120 !important;
    }

    /* Top Command Bar */
    .comm-command-bar {
        background: #ffffff !important;
        border: 1px solid #e2e8f0 !important;
        border-radius: 8px !important;
        padding: 10px 14px !important;
        margin-bottom: 12px !important;
        display: flex !important;
        flex-wrap: wrap !important;
        align-items: center !important;
        justify-content: space-between !important;
        gap: 10px !important;
        box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04) !important;
    }
    body.dark-mode .comm-command-bar {
        background: #141c2e !important;
        border-color: #334155 !important;
    }
    .comm-title-group h1 {
        font-size: 17px !important;
        font-weight: 700 !important;
        color: #0f172a !important;
        margin: 0 !important;
        display: flex !important;
        align-items: center !important;
        gap: 8px !important;
    }
    body.dark-mode .comm-title-group h1 {
        color: #f8fafc !important;
    }
    .comm-title-badge {
        font-size: 11px !important;
        color: #64748b !important;
        display: inline-flex !important;
        align-items: center !important;
        gap: 4px !important;
        margin-top: 2px !important;
    }

    /* Navigation Pills */
    .comm-nav-pills {
        display: flex !important;
        flex-wrap: wrap !important;
        gap: 6px !important;
        list-style: none !important;
        padding: 0 !important;
        margin: 0 !important;
    }
    .comm-nav-pills .nav-link {
        font-size: 11.5px !important;
        font-weight: 600 !important;
        padding: 5px 12px !important;
        border-radius: 20px !important;
        color: #475569 !important;
        background: #f1f5f9 !important;
        text-decoration: none !important;
        transition: all 0.15s ease !important;
        display: inline-flex !important;
        align-items: center !important;
        gap: 5px !important;
    }
    .comm-nav-pills .nav-link:hover {
        background: #e2e8f0 !important;
        color: #0f172a !important;
    }
    .comm-nav-pills .nav-link.active {
        background: #022051 !important;
        color: #ffffff !important;
    }
    body.dark-mode .comm-nav-pills .nav-link {
        background: #1e293b !important;
        color: #94a3b8 !important;
    }
    body.dark-mode .comm-nav-pills .nav-link:hover {
        background: #334155 !important;
        color: #f8fafc !important;
    }
    body.dark-mode .comm-nav-pills .nav-link.active {
        background: #3b82f6 !important;
        color: #ffffff !important;
    }

    /* Action buttons */
    .btn-action-pill {
        border-radius: 20px !important;
        font-size: 12px !important;
        font-weight: 600 !important;
        padding: 5px 14px !important;
        display: inline-flex !important;
        align-items: center !important;
        gap: 5px !important;
    }

    /* KPI Summary Strip */
    .kpi-summary-strip {
        display: grid !important;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)) !important;
        gap: 10px !important;
        margin-bottom: 12px !important;
    }
    .kpi-card {
        background: #ffffff !important;
        border: 1px solid #e2e8f0 !important;
        border-radius: 8px !important;
        padding: 10px 14px !important;
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
    .kpi-subtext {
        font-size: 11px !important;
        color: #64748b !important;
        margin-top: 2px !important;
    }

    /* Filter Card */
    .desk-filter-card {
        background: #ffffff !important;
        border: 1px solid #e2e8f0 !important;
        border-radius: 8px !important;
        padding: 10px 14px !important;
        margin-bottom: 12px !important;
        box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03) !important;
    }
    body.dark-mode .desk-filter-card {
        background: #141c2e !important;
        border-color: #334155 !important;
    }

    /* Quick Filter Bar */
    .quick-filter-pills {
        display: flex !important;
        flex-wrap: wrap !important;
        gap: 6px !important;
        align-items: center !important;
    }
    .btn-filter-pill {
        border: 1px solid #cbd5e1 !important;
        background: #ffffff !important;
        color: #334155 !important;
        font-size: 11px !important;
        font-weight: 600 !important;
        padding: 4px 10px !important;
        border-radius: 16px !important;
        cursor: pointer !important;
        transition: all 0.15s ease !important;
        display: inline-flex !important;
        align-items: center !important;
        gap: 4px !important;
    }
    .btn-filter-pill:hover {
        background: #f1f5f9 !important;
        border-color: #94a3b8 !important;
    }
    .btn-filter-pill.active {
        background: #022051 !important;
        border-color: #022051 !important;
        color: #ffffff !important;
    }
    body.dark-mode .btn-filter-pill {
        background: #1e293b !important;
        border-color: #475569 !important;
        color: #cbd5e1 !important;
    }
    body.dark-mode .btn-filter-pill.active {
        background: #3b82f6 !important;
        border-color: #3b82f6 !important;
        color: #ffffff !important;
    }

    /* Desk Card Wrapper */
    .desk-card-wrapper {
        background: #ffffff !important;
        border: 1px solid #e2e8f0 !important;
        border-radius: 8px !important;
        padding: 12px !important;
        box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04) !important;
    }
    body.dark-mode .desk-card-wrapper {
        background: #141c2e !important;
        border-color: #334155 !important;
    }

    /* Stock Table Styling */
    .stock-table {
        margin-bottom: 0 !important;
    }
    .stock-table thead th {
        background: #f1f5f9 !important;
        color: #0f172a !important;
        font-size: 11px !important;
        font-weight: 700 !important;
        text-transform: uppercase !important;
        letter-spacing: 0.04em !important;
        padding: 9px 10px !important;
        border-bottom: 2px solid #cbd5e1 !important;
        white-space: nowrap !important;
    }
    body.dark-mode .stock-table thead th {
        background: #1e293b !important;
        color: #f8fafc !important;
        border-color: #475569 !important;
    }
    .stock-table tbody tr:nth-child(even) {
        background: #f8fafc;
    }
    .stock-table tbody tr:nth-child(odd) {
        background: #ffffff;
    }
    body.dark-mode .stock-table tbody tr:nth-child(even) {
        background: #141c2c;
    }
    body.dark-mode .stock-table tbody tr:nth-child(odd) {
        background: #1e293b;
    }
    .stock-table tbody tr:hover {
        background: #e2e8f0 !important;
    }
    body.dark-mode .stock-table tbody tr:hover {
        background: #27354f !important;
    }
    .stock-table td {
        font-size: 12.5px;
        padding: 8px 10px;
        vertical-align: middle;
        border-color: #f1f5f9;
    }
    body.dark-mode .stock-table td {
        border-color: #334155;
        color: #e2e8f0;
    }
    .stock-table tfoot th {
        font-size: 13px;
        font-weight: 800;
        background: #f8fafc;
        border-top: 2px solid #0f172a;
        border-bottom: 2px solid #0f172a;
        padding: 10px;
    }
    body.dark-mode .stock-table tfoot th {
        background: #1e293b;
        border-color: #64748b;
        color: #f8fafc;
    }

    /* Product media cell */
    .product-thumb {
        width: 32px;
        height: 32px;
        border-radius: 4px;
        object-fit: cover;
        background: #f1f5f9;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border: 1px solid #e2e8f0;
    }
    body.dark-mode .product-thumb {
        background: #1e293b;
        border-color: #334155;
    }

    /* Status badge pills */
    .stock-status-pill {
        font-size: 11px;
        font-weight: 700;
        padding: 3px 8px;
        border-radius: 9999px;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    .stock-status-success { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
    .stock-status-warning { background: #fef3c7; color: #b45309; border: 1px solid #fde68a; }
    .stock-status-danger { background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }
}

/* Print Specific Rules */
@media print {
    .report-workspace-view {
        padding: 0 !important;
        background: transparent !important;
    }
    .comm-command-bar,
    .kpi-summary-strip,
    .desk-filter-card,
    .quick-filter-bar,
    .not-exported-col,
    .dataTables_filter,
    .dataTables_length,
    .dataTables_paginate,
    .dataTables_info,
    .dt-buttons {
        display: none !important;
    }
    .desk-card-wrapper {
        border: none !important;
        padding: 0 !important;
        box-shadow: none !important;
    }
    .stock-table {
        width: 100% !important;
        table-layout: fixed !important;
        border-collapse: collapse !important;
        font-size: 8pt !important;
    }
    .stock-table thead {
        display: table-header-group !important;
    }
    .stock-table tfoot {
        display: table-footer-group !important;
    }
    .stock-table tr {
        break-inside: avoid !important;
        page-break-inside: avoid !important;
    }
    .stock-table th,
    .stock-table td {
        padding: 4px 6px !important;
        border: 1px solid #cbd5e1 !important;
        font-size: 8pt !important;
    }
    .stock-table thead th {
        background: #f1f5f9 !important;
        color: #0f172a !important;
        font-weight: 700 !important;
    }
    /* Fixed 100% Geometry for A4 Print (8 visible columns) */
    .stock-table th:nth-child(2), .stock-table td:nth-child(2) { width: 26% !important; }
    .stock-table th:nth-child(3), .stock-table td:nth-child(3) { width: 14% !important; }
    .stock-table th:nth-child(4), .stock-table td:nth-child(4) { width: 12% !important; }
    .stock-table th:nth-child(5), .stock-table td:nth-child(5) { width: 10% !important; text-align: right !important; }
    .stock-table th:nth-child(6), .stock-table td:nth-child(6) { width: 9% !important; text-align: right !important; }
    .stock-table th:nth-child(7), .stock-table td:nth-child(7) { width: 10% !important; text-align: right !important; }
    .stock-table th:nth-child(8), .stock-table td:nth-child(8) { width: 9% !important; text-align: right !important; }
    .stock-table th:nth-child(9), .stock-table td:nth-child(9) { width: 10% !important; text-align: right !important; }
    .stock-table th:nth-child(10), .stock-table td:nth-child(10) { display: none !important; }
    .stock-table th:nth-child(11), .stock-table td:nth-child(11) { display: none !important; }
}
</style>

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
            <button class="btn btn-secondary btn-action-pill" onclick="printStockReport()">
                <i class="dripicons-print"></i> Print Statement
            </button>
            <button class="btn btn-primary btn-action-pill" onclick="exportStockCSV()">
                <i class="dripicons-download"></i> Export CSV
            </button>
        </div>
    </div>

    <!-- Letterhead for Official Printing -->
    <div class="erp-print-header">
        <div class="erp-print-letterhead">
            @php
                $siteLogo = $general_setting->site_logo ?? null;
            @endphp
            @if($siteLogo && file_exists(public_path('logo/'.$siteLogo)))
                <img src="{{ asset('logo/'.$siteLogo) }}" alt="Logo" class="erp-print-logo">
            @endif
            <div class="erp-print-company">
                <div class="erp-print-company-name">{{ $activeCompany->legal_name ?? ($activeCompany->name ?? config('company_name', 'zoloERP Pro')) }}</div>
                <div class="erp-print-company-line">{{ $activeCompany->address ?? '' }}</div>
                @if(!empty($activeCompany->gstin))
                    <div class="erp-print-company-line"><strong>GSTIN:</strong> {{ $activeCompany->gstin }}</div>
                @endif
                <div class="erp-print-company-line">
                    @if(!empty($activeCompany->phone))<span><strong>Phone:</strong> {{ $activeCompany->phone }}</span>@endif
                    @if(!empty($activeCompany->email))<span class="ml-2"><strong>Email:</strong> {{ $activeCompany->email }}</span>@endif
                </div>
            </div>
            <div class="erp-print-stamp">
                <div><strong>Statement:</strong> Stock Inventory Balance</div>
                <div><strong>Generated:</strong> {{ date('d M Y, h:i A') }}</div>
                <div><strong>Warehouse:</strong> {{ $warehouseName }}</div>
            </div>
        </div>
        <div class="erp-print-title">STOCK INVENTORY &amp; VALUATION REPORT</div>
        <div class="erp-print-meta">
            <span><b>Warehouse:</b> {{ $warehouseName }}</span>
            <span><b>Category:</b> {{ $categoryName }}</span>
            <span><b>Brand:</b> {{ $brandName }}</span>
            <span><b>Stock Filter:</b> {{ ucfirst(str_replace('_', ' ', $stock_status)) }}</span>
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
            <span>Location: <strong>{{ $warehouseName }}</strong> | Category: <strong>{{ $categoryName }}</strong></span>
        </div>
    </div>

    <!-- Tabular Stock Inventory Desk -->
    <div class="desk-card-wrapper">
        <div class="table-responsive">
            <table id="stock-inventory-table" class="table stock-table u-w-100pct">
                <thead>
                    <tr>
                        <th class="not-exported-col" style="width: 35px;">#</th>
                        <th style="width: 250px;">{{ __('db.product') }}</th>
                        <th style="width: 140px;">{{ __('db.category') }} / {{ __('db.Brand') }}</th>
                        <th style="width: 130px;">{{ __('db.Warehouse') }}</th>
                        <th class="text-right" style="width: 110px;">{{ __('db.Stock Quantity') }}</th>
                        <th class="text-right" style="width: 100px;">{{ __('db.Unit Cost') }} ({{ $currencySymbol }})</th>
                        <th class="text-right" style="width: 120px;">{{ __('db.Stock Value by Cost') }}</th>
                        <th class="text-right" style="width: 100px;">{{ __('db.Unit Price') }} ({{ $currencySymbol }})</th>
                        <th class="text-right" style="width: 120px;">{{ __('db.Stock Value by Price') }}</th>
                        <th class="text-right" style="width: 80px;">{{ __('db.Alert Quantity') }}</th>
                        <th class="text-center" style="width: 100px;">{{ __('db.Stock Status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($stockItems as $index => $item)
                        <tr data-status="{{ $item['status'] }}" data-qty="{{ $item['qty'] }}">
                            <td class="not-exported-col text-muted font-italic">{{ $index + 1 }}</td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="product-thumb mr-2 d-print-none">
                                        @if($item['image'] && file_exists(public_path('images/product/'.$item['image'])))
                                            <img src="{{ asset('images/product/'.$item['image']) }}" alt="Product" style="width: 30px; height: 30px; object-fit: cover; border-radius: 3px;">
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
                            <td>
                                <div><strong>{{ $item['category_name'] }}</strong></div>
                                <div class="small text-muted">{{ $item['brand_name'] }}</div>
                            </td>
                            <td>
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
                            <td class="text-right font-weight-bold">
                                <span class="{{ $item['qty'] <= 0 ? 'text-danger' : ($item['status'] == 'low_stock' ? 'text-warning font-weight-bold' : 'text-dark') }}">
                                    {{ number_format($item['qty'], 2) }}
                                </span>
                                <span class="badge badge-light border text-muted small ml-1">{{ $item['unit_code'] }}</span>
                            </td>
                            <td class="text-right">{{ number_format($item['cost'], 2) }}</td>
                            <td class="text-right font-weight-bold text-dark">{{ number_format($item['stock_value_cost'], 2) }}</td>
                            <td class="text-right">{{ number_format($item['price'], 2) }}</td>
                            <td class="text-right font-weight-bold text-primary">{{ number_format($item['stock_value_price'], 2) }}</td>
                            <td class="text-right text-muted">{{ $item['alert_quantity'] > 0 ? number_format($item['alert_quantity'], 2) : '—' }}</td>
                            <td class="text-center">
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
                        <th class="not-exported-col"></th>
                        <th colspan="3" class="text-right">Filtered Inventory Totals:</th>
                        <th class="text-right text-dark" id="foot-total-qty">{{ number_format($kpiTotalQty, 2) }}</th>
                        <th></th>
                        <th class="text-right text-dark" id="foot-total-cost">{{ number_format($kpiTotalCost, 2) }}</th>
                        <th></th>
                        <th class="text-right text-primary" id="foot-total-price">{{ number_format($kpiTotalPrice, 2) }}</th>
                        <th></th>
                        <th class="not-exported-col"></th>
                    </tr>
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
    $("ul#report #stock-report-menu").addClass("active");

    var stockTable = null;

    $(document).ready(function() {
        $('[data-toggle="tooltip"]').tooltip();

        stockTable = $('#stock-inventory-table').DataTable({
            "order": [[4, "desc"]],
            "paging": true,
            "pageLength": 25,
            "lengthMenu": [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
            "dom": '<"row align-items-center mb-2"<"col-md-6"l><"col-md-6 text-right"f>>rt<"row align-items-center mt-2"<"col-md-6"i><"col-md-6 text-right"p>>',
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

        stockTable.rows({ filter: 'applied' }).every(function(rowIdx, tableLoop, rowLoop) {
            var rowNode = this.node();
            var qty = parseFloat($(rowNode).attr('data-qty')) || 0;
            // Cost and Price column data: column 6 is cost valuation, column 8 is price valuation
            var data = this.data();
            var costVal = parseFloat(data[6].replace(/,/g, '')) || 0;
            var priceVal = parseFloat(data[8].replace(/,/g, '')) || 0;

            totalQty += qty;
            totalCost += costVal;
            totalPrice += priceVal;
        });

        $('#foot-total-qty').text(totalQty.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
        $('#foot-total-cost').text(totalCost.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
        $('#foot-total-price').text(totalPrice.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
    }

    function printStockReport() {
        if (!stockTable) {
            window.print();
            return;
        }

        var prevLen = stockTable.page.len();
        stockTable.page.len(-1).draw();

        setTimeout(function() {
            window.print();
            stockTable.page.len(prevLen).draw();
        }, 300);
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
