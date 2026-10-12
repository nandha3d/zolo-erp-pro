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
        padding: 16px;
    }
    body.dark-mode .desk-card-wrapper {
        background: #141c2e;
        border-color: #334155;
    }
    .calendar-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 4px;
        table-layout: fixed;
    }
    .calendar-month-bar {
        background: #0f172a;
        color: #ffffff;
        padding: 10px 16px;
        border-radius: 6px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 10px;
    }
    body.dark-mode .calendar-month-bar {
        background: #090d16;
    }
    .calendar-table thead th {
        background: #1e293b;
        color: #f8fafc;
        text-align: center;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.05em;
        text-transform: uppercase;
        padding: 8px 4px;
        border-radius: 4px;
    }
    body.dark-mode .calendar-table thead th {
        background: #141c2c;
    }
    .calendar-table td {
        vertical-align: top;
        padding: 8px;
        min-height: 105px;
        height: 105px;
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        transition: all 0.15s ease;
    }
    body.dark-mode .calendar-table td {
        background: #182235;
        border-color: #334155;
        color: #cbd5e1;
    }
    .calendar-table td.empty-day {
        background: #f8fafc;
        border-color: #f1f5f9;
        opacity: 0.45;
    }
    body.dark-mode .calendar-table td.empty-day {
        background: #0f172a;
        border-color: #1e293b;
    }
    .day-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 6px;
    }
    .day-number {
        font-size: 13px;
        font-weight: 700;
        color: #334155;
    }
    body.dark-mode .day-number {
        color: #94a3b8;
    }
    .today-pill {
        background: #ede9fe;
        color: #7c3aed;
        font-weight: 800;
        border: 1px solid #c4b5fd;
        padding: 1px 7px;
        border-radius: 12px;
        font-size: 11px;
    }
    body.dark-mode .today-pill {
        background: #3b0764;
        color: #c4b5fd;
        border-color: #6b21a8;
    }
    .metric-pill-total {
        background: #ecfdf5;
        color: #047857;
        font-weight: 700;
        font-size: 11.5px;
        padding: 3px 6px;
        border-radius: 4px;
        display: block;
        margin-bottom: 4px;
        border: 1px solid #a7f3d0;
    }
    body.dark-mode .metric-pill-total {
        background: #064e3b;
        color: #6ee7b7;
        border-color: #047857;
    }
    .metric-sub-line {
        font-size: 10px;
        color: #64748b;
        line-height: 1.35;
        display: block;
    }
    body.dark-mode .metric-sub-line {
        color: #94a3b8;
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

    $monthTotalPurchase = 0;
    $monthTotalTax = 0;
    $monthTotalDiscount = 0;
    $activeDays = 0;

    for ($d = 1; $d <= $number_of_day; $d++) {
        $gTot = isset($grand_total[$d]) ? floatval(str_replace(',', '', $grand_total[$d])) : 0;
        $tTax = isset($total_tax[$d]) ? floatval(str_replace(',', '', $total_tax[$d])) : 0;
        $oTax = isset($order_tax[$d]) ? floatval(str_replace(',', '', $order_tax[$d])) : 0;
        $tDisc = isset($total_discount[$d]) ? floatval(str_replace(',', '', $total_discount[$d])) : 0;
        $oDisc = isset($order_discount[$d]) ? floatval(str_replace(',', '', $order_discount[$d])) : 0;

        $monthTotalPurchase += $gTot;
        $monthTotalTax += ($tTax + $oTax);
        $monthTotalDiscount += ($tDisc + $oDisc);
        if ($gTot > 0) {
            $activeDays++;
        }
    }
@endphp

<section class="report-workspace-view">
    <!-- Top Command Bar -->
    <div class="comm-command-bar">
        <div class="comm-title-group">
            <h1><i class="dripicons-calendar text-primary"></i> {{ __('db.Daily Purchase Report') }} Command Center</h1>
            <span class="comm-title-badge"><i class="dripicons-tag"></i> Daily Inward Procurement & Tax Breakdown Matrix</span>
        </div>
        <ul class="comm-nav-pills">
            <li><a class="nav-link active" href="{{ url('report/daily_purchase/'.$year.'/'.$month) }}"><i class="dripicons-calendar"></i> Daily Purchase</a></li>
            <li><a class="nav-link" href="{{ url('report/monthly_purchase/'.$year) }}"><i class="dripicons-calendar"></i> Monthly Purchase</a></li>
            <li><a class="nav-link" href="{{ url('report/purchase_report') }}"><i class="dripicons-box"></i> Procurement Report</a></li>
            <li><a class="nav-link" href="{{ url('report/supplier_report') }}"><i class="dripicons-user"></i> Supplier Report</a></li>
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
                <div class="kpi-label">MONTH WINDOW</div>
                <div class="kpi-number" style="font-size:14px; font-weight:700; color:#475569;">
                    {{ date("F Y", strtotime($year.'-'.$month.'-01')) }}
                </div>
            </div>
            <div style="width:36px;height:36px;border-radius:6px;background:#ede9fe;display:flex;align-items:center;justify-content:center;color:#7c3aed;">
                <i class="dripicons-calendar" style="font-size:18px;"></i>
            </div>
        </div>
        <div class="kpi-card">
            <div>
                <div class="kpi-label">WAREHOUSE SCOPE</div>
                <div class="kpi-number" style="font-size:14px; font-weight:700;">{{ $warehouseName }}</div>
            </div>
            <div style="width:36px;height:36px;border-radius:6px;background:#e0f2fe;display:flex;align-items:center;justify-content:center;color:#0284c7;">
                <i class="dripicons-store" style="font-size:18px;"></i>
            </div>
        </div>
        <div class="kpi-card">
            <div>
                <div class="kpi-label">TOTAL MONTHLY INWARD</div>
                <div class="kpi-number" style="color:#059669;">₹ {{ number_format($monthTotalPurchase, 2) }}</div>
            </div>
            <div style="width:36px;height:36px;border-radius:6px;background:#dcfce7;display:flex;align-items:center;justify-content:center;color:#059669;">
                <i class="dripicons-box" style="font-size:18px;"></i>
            </div>
        </div>
        <div class="kpi-card">
            <div>
                <div class="kpi-label">TOTAL PURCHASE TAX</div>
                <div class="kpi-number">₹ {{ number_format($monthTotalTax, 2) }}</div>
            </div>
            <div style="width:36px;height:36px;border-radius:6px;background:#fef3c7;display:flex;align-items:center;justify-content:center;color:#d97706;">
                <i class="dripicons-ticket" style="font-size:18px;"></i>
            </div>
        </div>
        <div class="kpi-card">
            <div>
                <div class="kpi-label">ACTIVE INWARD DAYS</div>
                <div class="kpi-number">{{ $activeDays }} / {{ $number_of_day }} Days</div>
            </div>
            <div style="width:36px;height:36px;border-radius:6px;background:#f1f5f9;display:flex;align-items:center;justify-content:center;color:#475569;">
                <i class="dripicons-pulse" style="font-size:18px;"></i>
            </div>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="desk-filter-card">
        {{ Form::open(['route' => ['report.dailyPurchaseByWarehouse', $year, $month], 'method' => 'post', 'id' => 'report-form', 'class' => 'form-inline d-flex align-items-center flex-wrap gap-2']) }}
            <input type="hidden" name="warehouse_id_hidden" value="{{ $warehouse_id }}">
            <div class="form-group mr-2 mb-1">
                <label class="mr-2 font-weight-bold text-muted small"><i class="dripicons-store"></i> {{ __('db.Choose Warehouse') }}</label>
                <select class="form-control form-control-sm selectpicker" id="warehouse_id" name="warehouse_id" style="height:30px; font-size:12px; min-width:200px;">
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

    <!-- Calendar Wrapper -->
    <div class="desk-card-wrapper">
        <div class="calendar-month-bar">
            <a href="{{ url('report/daily_purchase/'.$prev_year.'/'.$prev_month) }}" class="btn btn-sm btn-outline-light btn-action-pill" style="border-color:rgba(255,255,255,0.3); color:#fff;">
                <i class="fa fa-chevron-left"></i> {{ __('db.Previous') }}
            </a>
            <div class="h5 font-weight-bold text-white mb-0 text-center">
                <i class="dripicons-calendar mr-2"></i> {{ date("F", strtotime($year.'-'.$month.'-01')).' ' .$year }}
            </div>
            <a href="{{ url('report/daily_purchase/'.$next_year.'/'.$next_month) }}" class="btn btn-sm btn-outline-light btn-action-pill" style="border-color:rgba(255,255,255,0.3); color:#fff;">
                {{ __('db.Next') }} <i class="fa fa-chevron-right"></i>
            </a>
        </div>

        <div class="table-responsive">
            <table class="calendar-table">
                <thead>
                    <tr>
                        <th>SUN</th>
                        <th>MON</th>
                        <th>TUE</th>
                        <th>WED</th>
                        <th>THU</th>
                        <th>FRI</th>
                        <th>SAT</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $i = 1;
                        $flag = 0;
                    @endphp
                    @while ($i <= $number_of_day)
                        <tr>
                            @for ($j = 1; $j <= 7; $j++)
                                @if ($i > $number_of_day)
                                    @php break; @endphp
                                @endif

                                @if ($flag)
                                    @php
                                        $isToday = ($year.'-'.$month.'-'.$i == date('Y').'-'.date('m').'-'.(int)date('d'));
                                        $hasPurchase = (isset($grand_total[$i]) && floatval(str_replace(',', '', $grand_total[$i])) > 0);
                                    @endphp
                                    <td>
                                        <div class="day-header">
                                            <span class="day-number">{{ $i }}</span>
                                            @if($isToday)
                                                <span class="today-pill">Today</span>
                                            @endif
                                        </div>

                                        @if($hasPurchase)
                                            <span class="metric-pill-total">₹ {{ $grand_total[$i] }}</span>
                                        @endif

                                        @if(isset($total_tax[$i]) && floatval(str_replace(',', '', $total_tax[$i])) > 0)
                                            <span class="metric-sub-line"><strong>Tax:</strong> ₹ {{ $total_tax[$i] }}</span>
                                        @endif
                                        @if(isset($order_tax[$i]) && floatval(str_replace(',', '', $order_tax[$i])) > 0)
                                            <span class="metric-sub-line"><strong>Ord.Tax:</strong> ₹ {{ $order_tax[$i] }}</span>
                                        @endif
                                        @if(isset($total_discount[$i]) && floatval(str_replace(',', '', $total_discount[$i])) > 0)
                                            <span class="metric-sub-line"><strong>Disc:</strong> ₹ {{ $total_discount[$i] }}</span>
                                        @endif
                                        @if(isset($order_discount[$i]) && floatval(str_replace(',', '', $order_discount[$i])) > 0)
                                            <span class="metric-sub-line"><strong>Ord.Disc:</strong> ₹ {{ $order_discount[$i] }}</span>
                                        @endif
                                        @if(isset($shipping_cost[$i]) && floatval(str_replace(',', '', $shipping_cost[$i])) > 0)
                                            <span class="metric-sub-line"><strong>Ship:</strong> ₹ {{ $shipping_cost[$i] }}</span>
                                        @endif
                                    </td>
                                    @php $i++; @endphp
                                @elseif ($j == $start_day)
                                    @php
                                        $isToday = ($year.'-'.$month.'-'.$i == date('Y').'-'.date('m').'-'.(int)date('d'));
                                        $hasPurchase = (isset($grand_total[$i]) && floatval(str_replace(',', '', $grand_total[$i])) > 0);
                                    @endphp
                                    <td>
                                        <div class="day-header">
                                            <span class="day-number">{{ $i }}</span>
                                            @if($isToday)
                                                <span class="today-pill">Today</span>
                                            @endif
                                        </div>

                                        @if($hasPurchase)
                                            <span class="metric-pill-total">₹ {{ $grand_total[$i] }}</span>
                                        @endif

                                        @if(isset($total_tax[$i]) && floatval(str_replace(',', '', $total_tax[$i])) > 0)
                                            <span class="metric-sub-line"><strong>Tax:</strong> ₹ {{ $total_tax[$i] }}</span>
                                        @endif
                                        @if(isset($order_tax[$i]) && floatval(str_replace(',', '', $order_tax[$i])) > 0)
                                            <span class="metric-sub-line"><strong>Ord.Tax:</strong> ₹ {{ $order_tax[$i] }}</span>
                                        @endif
                                        @if(isset($total_discount[$i]) && floatval(str_replace(',', '', $total_discount[$i])) > 0)
                                            <span class="metric-sub-line"><strong>Disc:</strong> ₹ {{ $total_discount[$i] }}</span>
                                        @endif
                                        @if(isset($order_discount[$i]) && floatval(str_replace(',', '', $order_discount[$i])) > 0)
                                            <span class="metric-sub-line"><strong>Ord.Disc:</strong> ₹ {{ $order_discount[$i] }}</span>
                                        @endif
                                        @if(isset($shipping_cost[$i]) && floatval(str_replace(',', '', $shipping_cost[$i])) > 0)
                                            <span class="metric-sub-line"><strong>Ship:</strong> ₹ {{ $shipping_cost[$i] }}</span>
                                        @endif
                                    </td>
                                    @php
                                        $flag = 1;
                                        $i++;
                                    @endphp
                                @else
                                    <td class="empty-day"></td>
                                @endif
                            @endfor
                        </tr>
                    @endwhile
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
    $("ul#report #daily-purchase-report-menu").addClass("active");

	$('#warehouse_id').val($('input[name="warehouse_id_hidden"]').val());
	$('.selectpicker').selectpicker('refresh');

	$('#warehouse_id').on("change", function(){
        if ($(this).val() == '0') {
            window.location.href = "{{ url('report/daily_purchase/'.$year.'/'.$month) }}";
        } else {
            $('#report-form').submit();
        }
	});
</script>
@endpush
