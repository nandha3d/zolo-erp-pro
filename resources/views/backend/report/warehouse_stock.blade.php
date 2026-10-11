@section('charts', '1')
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
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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
        font-size: 17px;
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
        padding: 20px 24px;
    }
    body.dark-mode .desk-card-wrapper {
        background: #141c2e;
        border-color: #334155;
    }
    .valuation-stat-box {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 14px 16px;
        margin-bottom: 12px;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    body.dark-mode .valuation-stat-box {
        background: #182235;
        border-color: #334155;
    }
    .valuation-stat-box .stat-label {
        font-size: 12px;
        font-weight: 600;
        color: #475569;
    }
    body.dark-mode .valuation-stat-box .stat-label {
        color: #94a3b8;
    }
    .valuation-stat-box .stat-val {
        font-size: 15px;
        font-weight: 700;
        color: #0f172a;
    }
    body.dark-mode .valuation-stat-box .stat-val {
        color: #f8fafc;
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

<x-error-message key="not_permitted" />

@php
    $warehouseName = 'All Warehouses';
    if($warehouse_id && $warehouse_id > 0) {
        $wh = \App\Models\Warehouse::find($warehouse_id);
        if($wh) $warehouseName = $wh->name;
    }

    $estimatedProfit = $total_price - $total_cost;
    $profitMarginPercent = ($total_cost > 0) ? (($estimatedProfit / $total_cost) * 100) : 0;
@endphp

<section class="report-workspace-view">
    <!-- Top Command Bar -->
    <div class="comm-command-bar">
        <div class="comm-title-group">
            <h1><i class="dripicons-store text-primary"></i> {{ __('db.Stock Chart') }} Command Center</h1>
            <span class="comm-title-badge"><i class="dripicons-tag"></i> Inventory Valuation & Margin Distribution Analytics</span>
        </div>
        <ul class="comm-nav-pills">
            <li><a class="nav-link active" href="{{ url('report/warehouse_stock') }}"><i class="dripicons-store"></i> Stock Chart</a></li>
            <li><a class="nav-link" href="{{ url('report/warehouse_report') }}"><i class="dripicons-archive"></i> Warehouse Report</a></li>
            <li><a class="nav-link" href="{{ url('report/product_report') }}"><i class="dripicons-box"></i> Product Report</a></li>
            <li><a class="nav-link" href="{{ url('report/qty_alert_report') }}"><i class="dripicons-warning"></i> Qty Alerts</a></li>
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
                <div class="kpi-label">TOTAL STOCK ITEMS</div>
                <div class="kpi-number">{{ number_format((float)$total_item, $general_setting->decimal, '.', '') }}</div>
            </div>
            <div style="width:36px;height:36px;border-radius:6px;background:#ede9fe;display:flex;align-items:center;justify-content:center;color:#7c3aed;">
                <i class="dripicons-box" style="font-size:18px;"></i>
            </div>
        </div>
        <div class="kpi-card">
            <div>
                <div class="kpi-label">TOTAL QUANTITY</div>
                <div class="kpi-number">{{ number_format((float)$total_qty, $general_setting->decimal, '.', '') }}</div>
            </div>
            <div style="width:36px;height:36px;border-radius:6px;background:#e0f2fe;display:flex;align-items:center;justify-content:center;color:#0284c7;">
                <i class="dripicons-stack" style="font-size:18px;"></i>
            </div>
        </div>
        <div class="kpi-card">
            <div>
                <div class="kpi-label">STOCK VALUE (BY COST)</div>
                <div class="kpi-number">₹ {{ number_format((float)$total_cost, $general_setting->decimal, '.', '') }}</div>
            </div>
            <div style="width:36px;height:36px;border-radius:6px;background:#fef3c7;display:flex;align-items:center;justify-content:center;color:#d97706;">
                <i class="dripicons-tag" style="font-size:18px;"></i>
            </div>
        </div>
        <div class="kpi-card">
            <div>
                <div class="kpi-label">STOCK VALUE (BY PRICE)</div>
                <div class="kpi-number" style="color:#2563eb;">₹ {{ number_format((float)$total_price, $general_setting->decimal, '.', '') }}</div>
            </div>
            <div style="width:36px;height:36px;border-radius:6px;background:#dbeafe;display:flex;align-items:center;justify-content:center;color:#2563eb;">
                <i class="dripicons-wallet" style="font-size:18px;"></i>
            </div>
        </div>
        <div class="kpi-card">
            <div>
                <div class="kpi-label">ESTIMATED PROFIT</div>
                <div class="kpi-number" style="color:#059669;">₹ {{ number_format((float)$estimatedProfit, $general_setting->decimal, '.', '') }}</div>
            </div>
            <div style="width:36px;height:36px;border-radius:6px;background:#dcfce7;display:flex;align-items:center;justify-content:center;color:#059669;">
                <i class="dripicons-graph-bar" style="font-size:18px;"></i>
            </div>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="desk-filter-card">
        {{ Form::open(['route' => 'report.warehouseStock', 'method' => 'get', 'id' => 'report-form', 'class' => 'form-inline d-flex align-items-center flex-wrap gap-2']) }}
            <div class="form-group mr-2 mb-1">
                <label class="mr-2 font-weight-bold text-muted small"><i class="dripicons-store"></i> {{ __('db.Choose Warehouse') }}</label>
                <select class="form-control form-control-sm selectpicker" id="warehouse_id" name="warehouse_id" style="height:30px; font-size:12px; min-width:220px;">
                    <option value="0">{{ __('db.All Warehouse') }}</option>
                    @foreach($lims_warehouse_list as $warehouse)
                        <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group mb-1">
                <button class="btn btn-primary btn-sm btn-action-pill" type="submit" style="background:#0f172a; border-color:#0f172a;">
                    <i class="fa fa-filter"></i> {{ __('db.submit') }}
                </button>
            </div>
        {{ Form::close() }}
    </div>

    <!-- Chart & Valuation Breakdown Grid -->
    <div class="desk-card-wrapper">
        <div class="row align-items-center">
            <!-- Left Column: Valuation Breakdown Details -->
            <div class="col-md-5">
                <div style="padding: 10px 16px 10px 0;">
                    <h5 class="font-weight-bold mb-3" style="color:#0f172a;">
                        <i class="dripicons-view-thumb text-primary mr-2"></i> Inventory Portfolio Valuation
                    </h5>
                    <p class="text-muted small mb-4">
                        Stock valuation breakdown comparing acquisition cost versus estimated retail realizable value for <strong>{{ $warehouseName }}</strong>.
                    </p>

                    <div class="valuation-stat-box">
                        <span class="stat-label"><i class="fa fa-circle text-warning mr-1"></i> Stock Value (By Cost)</span>
                        <span class="stat-val">₹ {{ number_format((float)$total_cost, $general_setting->decimal, '.', '') }}</span>
                    </div>

                    <div class="valuation-stat-box">
                        <span class="stat-label"><i class="fa fa-circle text-primary mr-1"></i> Stock Value (By Retail Price)</span>
                        <span class="stat-val" style="color:#2563eb;">₹ {{ number_format((float)$total_price, $general_setting->decimal, '.', '') }}</span>
                    </div>

                    <div class="valuation-stat-box" style="border-left: 3px solid #059669;">
                        <span class="stat-label"><i class="fa fa-circle text-success mr-1"></i> Expected Gross Profit</span>
                        <span class="stat-val" style="color:#059669;">₹ {{ number_format((float)$estimatedProfit, $general_setting->decimal, '.', '') }}</span>
                    </div>

                    <div class="valuation-stat-box">
                        <span class="stat-label"><i class="dripicons-pulse text-info mr-1"></i> Projected Markup / Margin</span>
                        <span class="stat-val" style="color:#7c3aed;">{{ number_format($profitMarginPercent, 1) }} %</span>
                    </div>
                </div>
            </div>

            <!-- Right Column: Pie Chart -->
            <div class="col-md-7">
                <div style="max-width:440px; margin:0 auto; padding:10px;">
                    @php
                        $color = '#7c3aed'; $color_rgba = 'rgba(124, 58, 237, 0.85)';
                        if($general_setting->theme == 'default.css'){
                            $color = '#7c3aed';
                            $color_rgba = 'rgba(124, 58, 237, 0.85)';
                        }
                        elseif($general_setting->theme == 'green.css'){
                            $color = '#10b981';
                            $color_rgba = 'rgba(16, 185, 129, 0.85)';
                        }
                        elseif($general_setting->theme == 'blue.css'){
                            $color = '#0284c7';
                            $color_rgba = 'rgba(2, 132, 199, 0.85)';
                        }
                        elseif($general_setting->theme == 'dark.css'){
                            $color = '#64748b';
                            $color_rgba = 'rgba(100, 116, 139, 0.85)';
                        }
                    @endphp
                    <canvas id="pieChart" data-color="{{$color}}" data-color_rgba="{{$color_rgba}}" data-price="{{$total_price}}" data-cost="{{$total_cost}}" width="360" height="360" data-label1="{{__('db.Stock Value by Price')}}" data-label2="{{__('db.Stock Value by Cost')}}" data-label3="{{__('db.Estimate Profit')}}"></canvas>
                </div>
            </div>
        </div>
    </div>
</section>

@endsection

@push('scripts')
<script type="text/javascript">
	$("ul#report").siblings('a').attr('aria-expanded','true');
    $("ul#report").addClass("show");
    $("ul#report #warehouse-stock-report-menu").addClass("active");

    var warehouse_id = <?php echo json_encode($warehouse_id) ?>;
	$('#warehouse_id').val(warehouse_id);
	$('.selectpicker').selectpicker('refresh');

	$('#warehouse_id').on("change", function(){
		$('#report-form').submit();
	});
</script>
@endpush
