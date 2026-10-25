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
    .desk-fin-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
        padding: 16px 18px;
        margin-bottom: 14px;
        height: calc(100% - 14px);
    }
    body.dark-mode .desk-fin-card {
        background: #141c2e;
        border-color: #334155;
    }
    .desk-fin-card h3 {
        font-size: 14px;
        font-weight: 700;
        color: #0f172a;
        margin-bottom: 12px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    body.dark-mode .desk-fin-card h3 {
        color: #f8fafc;
    }
    .desk-fin-card hr {
        border-top: 1px solid #e2e8f0;
        margin: 10px 0 14px;
    }
    body.dark-mode .desk-fin-card hr {
        border-color: #334155;
    }
    .fin-line-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 6px 0;
        border-bottom: 1px dashed #f1f5f9;
        font-size: 12px;
        color: #475569;
    }
    body.dark-mode .fin-line-item {
        border-color: #1e293b;
        color: #94a3b8;
    }
    .fin-line-item:last-child {
        border-bottom: none;
    }
    .fin-line-item .fin-val {
        font-weight: 700;
        color: #0f172a;
        font-family: inherit;
    }
    body.dark-mode .fin-line-item .fin-val {
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
    .net-profit-badge {
        background: linear-gradient(135deg, #059669 0%, #10b981 100%);
        color: #ffffff;
        border-radius: 8px;
        padding: 16px;
        text-align: center;
        margin-bottom: 12px;
    }
    .net-profit-badge h4 {
        font-size: 24px;
        font-weight: 800;
        margin: 0;
        letter-spacing: -0.02em;
    }
</style>

@php
    $netProfit = (($sale[0]->grand_total - $sale[0]->shipping_cost - $sale[0]->tax) - ($product_cost - $product_tax) - ($return[0]->grand_total - $return[0]->tax) + ($purchase_return[0]->grand_total - $purchase_return[0]->tax) - $expense + $income);
    $cashInHand = ($payment_recieved - $payment_sent - $return[0]->grand_total + $purchase_return[0]->grand_total - $expense - $payroll);
@endphp

<section class="report-workspace-view">
    <!-- Top Command Bar -->
    <div class="comm-command-bar">
        <div class="comm-title-group">
            <h1><i class="dripicons-graph-line text-primary"></i> {{ __('db.Summary Report') }} &amp; Profit Loss</h1>
            <span class="comm-title-badge"><i class="dripicons-tag"></i> Real-Time Executive Profitability Statement</span>
        </div>
        <ul class="comm-nav-pills">
            <li><a class="nav-link active" href="{{ route('report.profitLoss') }}"><i class="dripicons-graph-line"></i> Profit &amp; Loss</a></li>
            <li><a class="nav-link" href="{{ url('report/sale_report') }}"><i class="dripicons-cart"></i> Sales</a></li>
            <li><a class="nav-link" href="{{ url('report/purchase') }}"><i class="dripicons-basket"></i> Purchases</a></li>
            <li><a class="nav-link" href="{{ route('report.paymentByDate') }}"><i class="dripicons-wallet"></i> Payments</a></li>
            <li><a class="nav-link" href="{{ url('report/warehouse_report') }}"><i class="dripicons-store"></i> Warehouses</a></li>
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
                <div class="kpi-label">REPORT TIMEFRAME</div>
                <div class="kpi-number" style="font-size:13px; font-weight:600; color:#475569;">
                    {{ date('d M Y', strtotime($start_date)) }} — {{ date('d M Y', strtotime($end_date)) }}
                </div>
            </div>
            <div style="width:36px;height:36px;border-radius:6px;background:var(--z-primary-soft, #fbf4fb);display:flex;align-items:center;justify-content:center;color:var(--z-primary, #7d287e);">
                <i class="dripicons-calendar" style="font-size:18px;"></i>
            </div>
        </div>
        <div class="kpi-card">
            <div>
                <div class="kpi-label">NET PROFIT / LOSS</div>
                <div class="kpi-number" style="font-size:17px; font-weight:700; color: {{ $netProfit >= 0 ? '#059669' : '#dc2626' }};">
                    ₹ {{ number_format((float)$netProfit, $general_setting->decimal, '.', '') }}
                </div>
            </div>
            <div style="width:36px;height:36px;border-radius:6px;background: {{ $netProfit >= 0 ? '#dcfce7' : '#fee2e2' }};display:flex;align-items:center;justify-content:center;color: {{ $netProfit >= 0 ? '#059669' : '#dc2626' }};">
                <i class="dripicons-graph-bar" style="font-size:18px;"></i>
            </div>
        </div>
        <div class="kpi-card">
            <div>
                <div class="kpi-label">NET CASH IN HAND</div>
                <div class="kpi-number" style="font-size:17px; font-weight:700; color:#0284c7;">
                    ₹ {{ number_format((float)$cashInHand, $general_setting->decimal, '.', '') }}
                </div>
            </div>
            <div style="width:36px;height:36px;border-radius:6px;background:#e0f2fe;display:flex;align-items:center;justify-content:center;color:#0284c7;">
                <i class="dripicons-wallet" style="font-size:18px;"></i>
            </div>
        </div>
        <div class="kpi-card">
            <div>
                <div class="kpi-label">STATEMENT AUDIT</div>
                <div class="kpi-number" style="font-size:14px; font-weight:600; color:#059669;">Verified Balanced</div>
            </div>
            <div style="width:36px;height:36px;border-radius:6px;background:#dcfce7;display:flex;align-items:center;justify-content:center;color:#059669;">
                <i class="dripicons-checkmark" style="font-size:18px;"></i>
            </div>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="desk-filter-card">
        {!! Form::open(['route' => 'report.profitLoss', 'method' => 'post', 'class' => 'form-inline d-flex align-items-center flex-wrap gap-2']) !!}
            <div class="form-group mr-2 mb-1">
                <label class="mr-2 font-weight-bold text-muted small"><i class="dripicons-calendar"></i> {{ __('db.Choose Your Date') }}</label>
                <div class="input-group">
                    <input type="text" class="daterangepicker-field form-control form-control-sm" style="height:30px; font-size:12px; width:220px;"
                        value="{{ $start_date }} To {{ $end_date }}" required />
                    <input type="hidden" name="start_date" value="{{ $start_date }}" />
                    <input type="hidden" name="end_date" value="{{ $end_date }}" />
                </div>
            </div>
            <div class="form-group mb-1">
                <button class="btn btn-primary btn-sm btn-action-pill" type="submit" style="background:#0f172a; border-color:#0f172a;">
                    <i class="fa fa-filter"></i> {{ __('db.submit') }}
                </button>
            </div>
        {!! Form::close() !!}
    </div>

    <!-- Row 1: Purchases, Sales, Sale Returns, Purchase Returns -->
    <div class="row">
        <div class="col-md-3">
            <div class="desk-fin-card">
                <h3><i class="fa fa-shopping-basket text-primary"></i> {{ __('db.Purchase') }}</h3>
                <hr>
                <div class="fin-line-item">
                    <span>{{ __('db.grand total') }}</span>
                    <span class="fin-val">{{ number_format((float)$purchase[0]->grand_total, $general_setting->decimal, '.', '') }}</span>
                </div>
                <div class="fin-line-item">
                    <span>{{ __('db.Purchase') }} Count</span>
                    <span class="fin-val">{{ $total_purchase }}</span>
                </div>
                <div class="fin-line-item">
                    <span>{{ __('db.Paid') }}</span>
                    <span class="fin-val">{{ number_format((float)$purchase[0]->paid_amount, $general_setting->decimal, '.', '') }}</span>
                </div>
                <div class="fin-line-item">
                    <span>{{ __('db.Tax') }}</span>
                    <span class="fin-val">{{ number_format((float)$purchase[0]->tax, $general_setting->decimal, '.', '') }}</span>
                </div>
                <div class="fin-line-item">
                    <span>{{ __('db.Discount') }}</span>
                    <span class="fin-val">{{ number_format((float)$purchase[0]->discount, $general_setting->decimal, '.', '') }}</span>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="desk-fin-card">
                <h3><i class="fa fa-shopping-cart text-success"></i> {{ __('db.Sale') }}</h3>
                <hr>
                <div class="fin-line-item">
                    <span>{{ __('db.grand total') }}</span>
                    <span class="fin-val">{{ number_format((float)$sale[0]->grand_total, $general_setting->decimal, '.', '') }}</span>
                </div>
                <div class="fin-line-item">
                    <span>{{ __('db.Shipping Cost') }}</span>
                    <span class="fin-val">{{ number_format((float)$sale[0]->shipping_cost, $general_setting->decimal, '.', '') }}</span>
                </div>
                <div class="fin-line-item">
                    <span>{{ __('db.Sale') }} Count</span>
                    <span class="fin-val">{{ $total_sale }}</span>
                </div>
                <div class="fin-line-item">
                    <span>{{ __('db.Paid') }}</span>
                    <span class="fin-val">{{ number_format((float)$sale[0]->paid_amount, $general_setting->decimal, '.', '') }}</span>
                </div>
                <div class="fin-line-item">
                    <span>{{ __('db.Tax') }}</span>
                    <span class="fin-val">{{ number_format((float)$sale[0]->tax, $general_setting->decimal, '.', '') }}</span>
                </div>
                <div class="fin-line-item">
                    <span>{{ __('db.Discount') }}</span>
                    <span class="fin-val">{{ number_format((float)$sale[0]->discount, $general_setting->decimal, '.', '') }}</span>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="desk-fin-card">
                <h3><i class="fa fa-undo text-warning"></i> {{ __('db.Sale Return') }}</h3>
                <hr>
                <div class="fin-line-item">
                    <span>{{ __('db.grand total') }}</span>
                    <span class="fin-val">{{ number_format((float)$return[0]->grand_total, $general_setting->decimal, '.', '') }}</span>
                </div>
                <div class="fin-line-item">
                    <span>{{ __('db.return') }} Count</span>
                    <span class="fin-val">{{ $total_return }}</span>
                </div>
                <div class="fin-line-item">
                    <span>{{ __('db.Tax') }}</span>
                    <span class="fin-val">{{ number_format((float)$return[0]->tax, $general_setting->decimal, '.', '') }}</span>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="desk-fin-card">
                <h3><i class="fa fa-reply text-info"></i> {{ __('db.Purchase Return') }}</h3>
                <hr>
                <div class="fin-line-item">
                    <span>{{ __('db.grand total') }}</span>
                    <span class="fin-val">{{ number_format((float)$purchase_return[0]->grand_total, $general_setting->decimal, '.', '') }}</span>
                </div>
                <div class="fin-line-item">
                    <span>{{ __('db.return') }} Count</span>
                    <span class="fin-val">{{ $total_purchase_return }}</span>
                </div>
                <div class="fin-line-item">
                    <span>{{ __('db.Tax') }}</span>
                    <span class="fin-val">{{ number_format((float)$purchase_return[0]->tax, $general_setting->decimal, '.', '') }}</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Row 2: Profit/Loss Analyses & Net Profit Summary -->
    <div class="row mt-2">
        <div class="col-md-4">
            <div class="desk-fin-card">
                <h3><i class="fa fa-line-chart text-primary"></i> Gross Trading Margin</h3>
                <hr>
                <div class="fin-line-item">
                    <span>{{ __('db.Sale') }}</span>
                    <span class="fin-val">{{ number_format((float)$sale[0]->grand_total, $general_setting->decimal, '.', '') }}</span>
                </div>
                <div class="fin-line-item">
                    <span>{{ __('db.Product Cost') }}</span>
                    <span class="fin-val text-danger">- {{ number_format((float)$product_cost, $general_setting->decimal, '.', '') }}</span>
                </div>
                <div class="fin-line-item" style="border-top:1px solid #cbd5e1; margin-top:8px; padding-top:8px;">
                    <span class="font-weight-bold">{{ __('db.profit') }}</span>
                    <span class="fin-val text-success" style="font-size:14px;">{{ number_format((float)($sale[0]->grand_total - $product_cost), $general_setting->decimal, '.', '') }}</span>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="desk-fin-card">
                <h3><i class="fa fa-calculator text-warning"></i> Return-Adjusted Margin</h3>
                <hr>
                <div class="fin-line-item">
                    <span>{{ __('db.Sale') }}</span>
                    <span class="fin-val">{{ number_format((float)$sale[0]->grand_total, $general_setting->decimal, '.', '') }}</span>
                </div>
                <div class="fin-line-item">
                    <span>{{ __('db.Product Cost') }}</span>
                    <span class="fin-val text-danger">- {{ number_format((float)$product_cost, $general_setting->decimal, '.', '') }}</span>
                </div>
                <div class="fin-line-item">
                    <span>{{ __('db.Sale Return') }}</span>
                    <span class="fin-val text-danger">- {{ number_format((float)$return[0]->grand_total, $general_setting->decimal, '.', '') }}</span>
                </div>
                <div class="fin-line-item">
                    <span>{{ __('db.Purchase Return') }}</span>
                    <span class="fin-val text-success">+ {{ number_format((float)$purchase_return[0]->grand_total, $general_setting->decimal, '.', '') }}</span>
                </div>
                <div class="fin-line-item" style="border-top:1px solid #cbd5e1; margin-top:8px; padding-top:8px;">
                    <span class="font-weight-bold">{{ __('db.profit') }}</span>
                    <span class="fin-val text-success" style="font-size:14px;">{{ number_format((float)($sale[0]->grand_total - $product_cost - $return[0]->grand_total + $purchase_return[0]->grand_total), $general_setting->decimal, '.', '') }}</span>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="desk-fin-card" style="border-color:#10b981;">
                <h3><i class="fa fa-shield text-success"></i> {{ __('db.Net Profit') }} / {{ __('db.Net Loss') }}</h3>
                <hr>
                <div class="net-profit-badge" style="{{ $netProfit < 0 ? 'background: linear-gradient(135deg, #dc2626 0%, #ef4444 100%);' : '' }}">
                    <div style="font-size:11px; text-transform:uppercase; letter-spacing:0.05em; opacity:0.9;">BOTTOM-LINE NET RESULT</div>
                    <h4>₹ {{ number_format((float)$netProfit, $general_setting->decimal, '.', '') }}</h4>
                </div>
                <p class="text-muted" style="font-size:10.5px; line-height:1.4; margin-bottom:0;">
                    Formula: (Sale - Ship - Tax) - (Cost - Tax) - (Sale Ret - Tax) + (Purch Ret - Tax) - Expense + Income
                </p>
            </div>
        </div>
    </div>

    <!-- Row 3: Payments In/Out & Incomes/Expenses -->
    <div class="row mt-2">
        <div class="col-md-3">
            <div class="desk-fin-card">
                <h3><i class="fa fa-arrow-circle-down text-success"></i> {{ __('db.Payment Recieved') }}</h3>
                <hr>
                <div class="fin-line-item">
                    <span>{{ __('db.Amount') }}</span>
                    <span class="fin-val text-success">{{ number_format((float)$payment_recieved, $general_setting->decimal, '.', '') }}</span>
                </div>
                <div class="fin-line-item">
                    <span>{{ __('db.Recieved') }} Count</span>
                    <span class="fin-val">{{ $payment_recieved_number }}</span>
                </div>
                <div class="fin-line-item"><span>Cash</span><span class="fin-val">{{ number_format((float)$cash_payment_sale, $general_setting->decimal, '.', '') }}</span></div>
                <div class="fin-line-item"><span>Cheque</span><span class="fin-val">{{ number_format((float)$cheque_payment_sale, $general_setting->decimal, '.', '') }}</span></div>
                <div class="fin-line-item"><span>Credit Card</span><span class="fin-val">{{ number_format((float)$credit_card_payment_sale, $general_setting->decimal, '.', '') }}</span></div>
                <div class="fin-line-item"><span>Gift Card</span><span class="fin-val">{{ number_format((float)$gift_card_payment_sale, $general_setting->decimal, '.', '') }}</span></div>
                <div class="fin-line-item"><span>Paypal</span><span class="fin-val">{{ number_format((float)$paypal_payment_sale, $general_setting->decimal, '.', '') }}</span></div>
                <div class="fin-line-item"><span>Deposit</span><span class="fin-val">{{ number_format((float)$deposit_payment_sale, $general_setting->decimal, '.', '') }}</span></div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="desk-fin-card">
                <h3><i class="fa fa-arrow-circle-up text-danger"></i> {{ __('db.Payment Sent') }}</h3>
                <hr>
                <div class="fin-line-item">
                    <span>{{ __('db.Amount') }}</span>
                    <span class="fin-val text-danger">{{ number_format((float)$payment_sent, $general_setting->decimal, '.', '') }}</span>
                </div>
                <div class="fin-line-item">
                    <span>Disbursements</span>
                    <span class="fin-val">{{ $payment_sent_number }}</span>
                </div>
                <div class="fin-line-item"><span>Cash</span><span class="fin-val">{{ number_format((float)$cash_payment_purchase, $general_setting->decimal, '.', '') }}</span></div>
                <div class="fin-line-item"><span>Cheque</span><span class="fin-val">{{ number_format((float)$cheque_payment_purchase, $general_setting->decimal, '.', '') }}</span></div>
                <div class="fin-line-item"><span>Credit Card</span><span class="fin-val">{{ number_format((float)$credit_card_payment_purchase, $general_setting->decimal, '.', '') }}</span></div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="desk-fin-card">
                <h3><i class="fa fa-money text-warning"></i> Operating Expenses</h3>
                <hr>
                <div class="fin-line-item">
                    <span>{{ __('db.Expense') }}</span>
                    <span class="fin-val text-danger">{{ number_format((float)$expense, $general_setting->decimal, '.', '') }}</span>
                </div>
                <div class="fin-line-item">
                    <span>{{ __('db.Expense') }} Entries</span>
                    <span class="fin-val">{{ $total_expense }}</span>
                </div>
                <div class="fin-line-item">
                    <span>{{ __('db.Income') }}</span>
                    <span class="fin-val text-success">{{ number_format((float)$income, $general_setting->decimal, '.', '') }}</span>
                </div>
                <div class="fin-line-item">
                    <span>{{ __('db.Income') }} Entries</span>
                    <span class="fin-val">{{ $total_income }}</span>
                </div>
                <div class="fin-line-item">
                    <span>{{ __('db.Payroll') }}</span>
                    <span class="fin-val text-danger">{{ number_format((float)$payroll, $general_setting->decimal, '.', '') }}</span>
                </div>
                <div class="fin-line-item">
                    <span>{{ __('db.Payroll') }} Entries</span>
                    <span class="fin-val">{{ $total_payroll }}</span>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="desk-fin-card">
                <h3><i class="fa fa-briefcase text-info"></i> {{ __('db.Cash in Hand') }}</h3>
                <hr>
                <div class="fin-line-item">
                    <span>{{ __('db.Recieved') }}</span>
                    <span class="fin-val text-success">{{ number_format((float)($payment_recieved), $general_setting->decimal, '.', '') }}</span>
                </div>
                <div class="fin-line-item">
                    <span>{{ __('db.Sent') }}</span>
                    <span class="fin-val text-danger">- {{ number_format((float)($payment_sent), $general_setting->decimal, '.', '') }}</span>
                </div>
                <div class="fin-line-item">
                    <span>{{ __('db.Sale Return') }}</span>
                    <span class="fin-val text-danger">- {{ number_format((float)$return[0]->grand_total, $general_setting->decimal, '.', '') }}</span>
                </div>
                <div class="fin-line-item">
                    <span>{{ __('db.Purchase Return') }}</span>
                    <span class="fin-val text-success">+ {{ number_format((float)$purchase_return[0]->grand_total, $general_setting->decimal, '.', '') }}</span>
                </div>
                <div class="fin-line-item">
                    <span>{{ __('db.Expense') }}</span>
                    <span class="fin-val text-danger">- {{ number_format((float)$expense, $general_setting->decimal, '.', '') }}</span>
                </div>
                <div class="fin-line-item">
                    <span>{{ __('db.Payroll') }}</span>
                    <span class="fin-val text-danger">- {{ number_format((float)$payroll, $general_setting->decimal, '.', '') }}</span>
                </div>
                <div class="fin-line-item" style="border-top:1px solid #cbd5e1; margin-top:8px; padding-top:8px;">
                    <span class="font-weight-bold">{{ __('db.In Hand') }}</span>
                    <span class="fin-val text-primary" style="font-size:14px;">{{ number_format((float)$cashInHand, $general_setting->decimal, '.', '') }}</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Row 4: Warehouse Breakdown -->
    <div class="row mt-2">
        <div class="col-12 mb-2">
            <h5 class="font-weight-bold text-muted small text-uppercase" style="letter-spacing:0.05em;"><i class="dripicons-store"></i> Warehouse Profitability Breakdown</h5>
        </div>
        @foreach($warehouse_name as $key => $name)
            <div class="col-md-4">
                <div class="desk-fin-card">
                    <h3><i class="fa fa-building-o text-primary"></i> {{ $name }}</h3>
                    <hr>
                    <div class="text-center p-2 mb-2 rounded" style="background:#f1f5f9;">
                        <span class="small text-muted font-weight-bold">Gross Margin: </span>
                        <span class="font-weight-bold text-dark">₹ {{ number_format((float)($warehouse_sale[$key][0]->grand_total - $warehouse_purchase[$key][0]->grand_total - $warehouse_return[$key][0]->grand_total + $warehouse_purchase_return[$key][0]->grand_total), $general_setting->decimal, '.', '') }}</span>
                    </div>
                    <div class="text-center p-2 mb-2 rounded" style="background:#f8fafc;">
                        <span class="small text-muted font-weight-bold">Net (Excl Tax): </span>
                        <span class="font-weight-bold text-success">₹ {{ number_format((float)(($warehouse_sale[$key][0]->grand_total - $warehouse_sale[$key][0]->tax) - ($warehouse_purchase[$key][0]->grand_total - $warehouse_purchase[$key][0]->tax) - ($warehouse_return[$key][0]->grand_total - $warehouse_return[$key][0]->tax) + ($warehouse_purchase_return[$key][0]->grand_total - $warehouse_purchase_return[$key][0]->tax) ), $general_setting->decimal, '.', '') }}</span>
                    </div>
                    <div class="fin-line-item">
                        <span>Allocated {{ __('db.Expense') }}</span>
                        <span class="fin-val text-danger">₹ {{ number_format((float)$warehouse_expense[$key], $general_setting->decimal, '.', '') }}</span>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</section>

@endsection

@push('scripts')
<script type="text/javascript">

    $("ul#report").siblings('a').attr('aria-expanded','true');
    $("ul#report").addClass("show");
    $("ul#report #profit-loss-report-menu").addClass("active");

    $(".daterangepicker-field").daterangepicker({
      callback: function(startDate, endDate, period){
        var start_date = startDate.format('YYYY-MM-DD');
        var end_date = endDate.format('YYYY-MM-DD');
        var title = start_date + ' To ' + end_date;
        $(this).val(title);
        $('input[name="start_date"]').val(start_date);
        $('input[name="end_date"]').val(end_date);
      }
    });
</script>
@endpush
