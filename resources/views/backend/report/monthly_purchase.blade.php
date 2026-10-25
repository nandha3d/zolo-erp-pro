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
    .calendar-year-bar {
        background: var(--z-ink, #022051);
        color: #ffffff;
        padding: 10px 16px;
        border-radius: 6px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 14px;
    }
    body.dark-mode .calendar-year-bar {
        background: #090d16;
    }
    .months-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
        gap: 12px;
    }
    .month-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 14px;
        box-shadow: 0 1px 2px rgba(15, 23, 42, 0.03);
        transition: all 0.2s ease;
    }
    body.dark-mode .month-card {
        background: #182235;
        border-color: #334155;
    }
    .month-card:hover {
        border-color: #cbd5e1;
        box-shadow: 0 4px 10px rgba(15, 23, 42, 0.06);
    }
    body.dark-mode .month-card:hover {
        border-color: #475569;
    }
    .month-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding-bottom: 8px;
        margin-bottom: 8px;
        border-bottom: 1px solid #f1f5f9;
    }
    body.dark-mode .month-header {
        border-bottom-color: #26334a;
    }
    .month-title {
        font-size: 13px;
        font-weight: 700;
        color: #0f172a;
    }
    body.dark-mode .month-title {
        color: #f8fafc;
    }
    .month-total-pill {
        background: #ecfdf5;
        color: #047857;
        font-weight: 700;
        font-size: 13px;
        padding: 4px 8px;
        border-radius: 6px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 8px;
        border: 1px solid #a7f3d0;
    }
    body.dark-mode .month-total-pill {
        background: #064e3b;
        color: #6ee7b7;
        border-color: #047857;
    }
    .month-metric-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 11px;
        padding: 2px 0;
        color: #64748b;
    }
    body.dark-mode .month-metric-row {
        color: #94a3b8;
    }
    .month-metric-row strong {
        color: #334155;
    }
    body.dark-mode .month-metric-row strong {
        color: #cbd5e1;
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

    $annualTotalPurchase = 0;
    $annualTotalTax = 0;
    $annualTotalDiscount = 0;
    $activeMonths = 0;

    $monthNames = [
        'January', 'February', 'March', 'April', 'May', 'June',
        'July', 'August', 'September', 'October', 'November', 'December'
    ];

    foreach($monthNames as $idx => $mName) {
        $t = isset($total[$idx]) ? floatval(str_replace(',', '', $total[$idx])) : 0;
        $tTax = isset($total_tax[$idx]) ? floatval(str_replace(',', '', $total_tax[$idx])) : 0;
        $oTax = isset($order_tax[$idx]) ? floatval(str_replace(',', '', $order_tax[$idx])) : 0;
        $tDisc = isset($total_discount[$idx]) ? floatval(str_replace(',', '', $total_discount[$idx])) : 0;
        $oDisc = isset($order_discount[$idx]) ? floatval(str_replace(',', '', $order_discount[$idx])) : 0;

        $annualTotalPurchase += $t;
        $annualTotalTax += ($tTax + $oTax);
        $annualTotalDiscount += ($tDisc + $oDisc);
        if($t > 0) {
            $activeMonths++;
        }
    }
@endphp

<section class="report-workspace-view">
    <!-- Top Command Bar -->
    <div class="comm-command-bar">
        <div class="comm-title-group">
            <h1><i class="dripicons-calendar text-primary"></i> {{ __('db.Monthly Purchase Report') }} Command Center</h1>
            <span class="comm-title-badge"><i class="dripicons-tag"></i> Annual Procurement Turnover & Monthly Inward Matrix</span>
        </div>
        <ul class="comm-nav-pills">
            <li><a class="nav-link active" href="{{ url('report/monthly_purchase/'.$year) }}"><i class="dripicons-calendar"></i> Monthly Purchase</a></li>
            <li><a class="nav-link" href="{{ url('report/daily_purchase/'.$year.'/'.date('m')) }}"><i class="dripicons-calendar"></i> Daily Purchase</a></li>
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
                <div class="kpi-label">ANNUAL WINDOW</div>
                <div class="kpi-number" style="font-size:15px; font-weight:700; color:#475569;">
                    Year {{ $year }}
                </div>
            </div>
            <div style="width:36px;height:36px;border-radius:6px;background:var(--z-primary-soft, #fbf4fb);display:flex;align-items:center;justify-content:center;color:var(--z-primary, #7d287e);">
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
                <div class="kpi-label">ANNUAL INWARD EXPENDITURE</div>
                <div class="kpi-number" style="color:#059669;">₹ {{ number_format($annualTotalPurchase, 2) }}</div>
            </div>
            <div style="width:36px;height:36px;border-radius:6px;background:#dcfce7;display:flex;align-items:center;justify-content:center;color:#059669;">
                <i class="dripicons-box" style="font-size:18px;"></i>
            </div>
        </div>
        <div class="kpi-card">
            <div>
                <div class="kpi-label">TOTAL ANNUAL TAX</div>
                <div class="kpi-number">₹ {{ number_format($annualTotalTax, 2) }}</div>
            </div>
            <div style="width:36px;height:36px;border-radius:6px;background:#fef3c7;display:flex;align-items:center;justify-content:center;color:#d97706;">
                <i class="dripicons-ticket" style="font-size:18px;"></i>
            </div>
        </div>
        <div class="kpi-card">
            <div>
                <div class="kpi-label">ACTIVE INWARD MONTHS</div>
                <div class="kpi-number">{{ $activeMonths }} / 12 Months</div>
            </div>
            <div style="width:36px;height:36px;border-radius:6px;background:#f1f5f9;display:flex;align-items:center;justify-content:center;color:#475569;">
                <i class="dripicons-pulse" style="font-size:18px;"></i>
            </div>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="desk-filter-card">
        {{ Form::open(['route' => ['report.monthlyPurchaseByWarehouse', $year], 'method' => 'post', 'id' => 'report-form', 'class' => 'form-inline d-flex align-items-center flex-wrap gap-2']) }}
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

    <!-- Annual Months Container Wrapper -->
    <div class="desk-card-wrapper">
        <div class="calendar-year-bar">
            <a href="{{ url('report/monthly_purchase/'.($year-1)) }}" class="btn btn-sm btn-outline-light btn-action-pill" style="border-color:rgba(255,255,255,0.3); color:#fff;">
                <i class="fa fa-chevron-left"></i> {{ __('db.Previous') }} ({{ $year - 1 }})
            </a>
            <div class="h5 font-weight-bold text-white mb-0 text-center">
                <i class="dripicons-calendar mr-2"></i> Purchase Expenditure Calendar &mdash; Year {{ $year }}
            </div>
            <a href="{{ url('report/monthly_purchase/'.($year+1)) }}" class="btn btn-sm btn-outline-light btn-action-pill" style="border-color:rgba(255,255,255,0.3); color:#fff;">
                ({{ $year + 1 }}) {{ __('db.Next') }} <i class="fa fa-chevron-right"></i>
            </a>
        </div>

        <div class="months-grid">
            @foreach($monthNames as $idx => $mName)
                @php
                    $mNumber = str_pad($idx + 1, 2, '0', STR_PAD_LEFT);
                    $mPurchases = isset($total[$idx]) ? $total[$idx] : '0';
                    $hasPurchases = floatval(str_replace(',', '', $mPurchases)) > 0;
                @endphp
                <div class="month-card">
                    <div class="month-header">
                        <span class="month-title">{{ $mName }} {{ $year }}</span>
                        <a href="{{ url('report/daily_purchase/'.$year.'/'.$mNumber) }}" class="btn btn-sm btn-link p-0 text-primary small" title="View Daily Breakdown" style="font-size:11px;">
                            Daily <i class="fa fa-arrow-right"></i>
                        </a>
                    </div>

                    <div class="month-total-pill">
                        <span style="font-size:10.5px; text-transform:uppercase; font-weight:600;">Total</span>
                        <span>₹ {{ $mPurchases }}</span>
                    </div>

                    @if(isset($total_tax[$idx]) && floatval(str_replace(',', '', $total_tax[$idx])) > 0)
                        <div class="month-metric-row">
                            <span>Product Tax:</span>
                            <strong>₹ {{ $total_tax[$idx] }}</strong>
                        </div>
                    @endif
                    @if(isset($order_tax[$idx]) && floatval(str_replace(',', '', $order_tax[$idx])) > 0)
                        <div class="month-metric-row">
                            <span>Order Tax:</span>
                            <strong>₹ {{ $order_tax[$idx] }}</strong>
                        </div>
                    @endif
                    @if(isset($total_discount[$idx]) && floatval(str_replace(',', '', $total_discount[$idx])) > 0)
                        <div class="month-metric-row">
                            <span>Product Disc:</span>
                            <strong>₹ {{ $total_discount[$idx] }}</strong>
                        </div>
                    @endif
                    @if(isset($order_discount[$idx]) && floatval(str_replace(',', '', $order_discount[$idx])) > 0)
                        <div class="month-metric-row">
                            <span>Order Disc:</span>
                            <strong>₹ {{ $order_discount[$idx] }}</strong>
                        </div>
                    @endif
                    @if(isset($shipping_cost[$idx]) && floatval(str_replace(',', '', $shipping_cost[$idx])) > 0)
                        <div class="month-metric-row">
                            <span>Shipping Cost:</span>
                            <strong>₹ {{ $shipping_cost[$idx] }}</strong>
                        </div>
                    @endif
                    @if(!$hasPurchases)
                        <div class="text-center text-muted small py-2" style="font-size:11px;">
                            No purchases recorded
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</section>

@endsection

@push('scripts')
<script type="text/javascript">
	$("ul#report").siblings('a').attr('aria-expanded','true');
    $("ul#report").addClass("show");
    $("ul#report #monthly-purchase-report-menu").addClass("active");

	$('#warehouse_id').val($('input[name="warehouse_id_hidden"]').val());
	$('.selectpicker').selectpicker('refresh');

	$('#warehouse_id').on("change", function(){
        if ($(this).val() == '0') {
            window.location.href = "{{ url('report/monthly_purchase/'.$year) }}";
        } else {
            $('#report-form').submit();
        }
	});
</script>
@endpush
