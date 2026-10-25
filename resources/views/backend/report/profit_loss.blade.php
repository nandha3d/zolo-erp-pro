@extends('backend.layout.main')
@section('content')


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
                <div class="kpi-number u-fs-13px u-fw-600 u-c-text-2">
                    {{ date('d M Y', strtotime($start_date)) }} — {{ date('d M Y', strtotime($end_date)) }}
                </div>
            </div>
            <div class="z-icon-tile z-tone-primary">
                <i class="dripicons-calendar u-fs-18px"></i>
            </div>
        </div>
        <div class="kpi-card">
            <div>
                <div class="kpi-label">NET PROFIT / LOSS</div>
                <div class="kpi-number u-fs-17px u-fw-700 {{ $netProfit >= 0 ? 'u-c-success' : 'u-c-danger' }}">
                    ₹ {{ number_format((float)$netProfit, $general_setting->decimal, '.', '') }}
                </div>
            </div>
            <div class="z-icon-tile {{ $netProfit >= 0 ? 'z-tone-success' : 'z-tone-danger' }}">
                <i class="dripicons-graph-bar u-fs-18px"></i>
            </div>
        </div>
        <div class="kpi-card">
            <div>
                <div class="kpi-label">NET CASH IN HAND</div>
                <div class="kpi-number u-fs-17px u-fw-700 u-c-info">
                    ₹ {{ number_format((float)$cashInHand, $general_setting->decimal, '.', '') }}
                </div>
            </div>
            <div class="z-icon-tile z-tone-info">
                <i class="dripicons-wallet u-fs-18px"></i>
            </div>
        </div>
        <div class="kpi-card">
            <div>
                <div class="kpi-label">STATEMENT AUDIT</div>
                <div class="kpi-number u-fs-14px u-fw-600 u-c-success">Verified Balanced</div>
            </div>
            <div class="z-icon-tile z-tone-success">
                <i class="dripicons-checkmark u-fs-18px"></i>
            </div>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="desk-filter-card">
        {!! Form::open(['route' => 'report.profitLoss', 'method' => 'post', 'class' => 'form-inline d-flex align-items-center flex-wrap gap-2']) !!}
            <div class="form-group mr-2 mb-1">
                <label class="mr-2 font-weight-bold text-muted small"><i class="dripicons-calendar"></i> {{ __('db.Choose Your Date') }}</label>
                <div class="input-group">
                    <input type="text" class="daterangepicker-field form-control form-control-sm u-h-30px u-fs-12px u-w-220px"
                        value="{{ $start_date }} To {{ $end_date }}" required />
                    <input type="hidden" name="start_date" value="{{ $start_date }}" />
                    <input type="hidden" name="end_date" value="{{ $end_date }}" />
                </div>
            </div>
            <div class="form-group mb-1">
                <button class="btn btn-primary btn-sm btn-action-pill u-bg-ink u-bc-ink" type="submit">
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
                <div class="fin-line-item u-bt-1px-solid-border-strong u-mt-8px u-pt-8px">
                    <span class="font-weight-bold">{{ __('db.profit') }}</span>
                    <span class="fin-val text-success u-fs-14px">{{ number_format((float)($sale[0]->grand_total - $product_cost), $general_setting->decimal, '.', '') }}</span>
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
                <div class="fin-line-item u-bt-1px-solid-border-strong u-mt-8px u-pt-8px">
                    <span class="font-weight-bold">{{ __('db.profit') }}</span>
                    <span class="fin-val text-success u-fs-14px">{{ number_format((float)($sale[0]->grand_total - $product_cost - $return[0]->grand_total + $purchase_return[0]->grand_total), $general_setting->decimal, '.', '') }}</span>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="desk-fin-card u-bc-success-border">
                <h3><i class="fa fa-shield text-success"></i> {{ __('db.Net Profit') }} / {{ __('db.Net Loss') }}</h3>
                <hr>
                <div class="net-profit-badge {{ $netProfit < 0 ? 'u-bg-7abbf377' : '' }}">
                    <div class="u-fs-11px u-tt-uppercase u-ls-0_05em u-op-0_9">BOTTOM-LINE NET RESULT</div>
                    <h4>₹ {{ number_format((float)$netProfit, $general_setting->decimal, '.', '') }}</h4>
                </div>
                <p class="text-muted u-fs-10_5px u-lh-1_4 u-mb-0">
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
                <div class="fin-line-item u-bt-1px-solid-border-strong u-mt-8px u-pt-8px">
                    <span class="font-weight-bold">{{ __('db.In Hand') }}</span>
                    <span class="fin-val text-primary u-fs-14px">{{ number_format((float)$cashInHand, $general_setting->decimal, '.', '') }}</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Row 4: Warehouse Breakdown -->
    <div class="row mt-2">
        <div class="col-12 mb-2">
            <h5 class="font-weight-bold text-muted small text-uppercase u-ls-0_05em"><i class="dripicons-store"></i> Warehouse Profitability Breakdown</h5>
        </div>
        @foreach($warehouse_name as $key => $name)
            <div class="col-md-4">
                <div class="desk-fin-card">
                    <h3><i class="fa fa-building-o text-primary"></i> {{ $name }}</h3>
                    <hr>
                    <div class="text-center p-2 mb-2 rounded u-bg-surface-3">
                        <span class="small text-muted font-weight-bold">Gross Margin: </span>
                        <span class="font-weight-bold text-dark">₹ {{ number_format((float)($warehouse_sale[$key][0]->grand_total - $warehouse_purchase[$key][0]->grand_total - $warehouse_return[$key][0]->grand_total + $warehouse_purchase_return[$key][0]->grand_total), $general_setting->decimal, '.', '') }}</span>
                    </div>
                    <div class="text-center p-2 mb-2 rounded u-bg-surface-2">
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

@push('css')
    <link rel="stylesheet" href="<?php echo asset('css/commercial-entry.css') . '?v=' . (file_exists(public_path('css/commercial-entry.css')) ? filemtime(public_path('css/commercial-entry.css')) : time()) ?>" type="text/css">
    <link rel="stylesheet" href="<?php echo asset('css/commercial-workspace.css') . '?v=' . (file_exists(public_path('css/commercial-workspace.css')) ? filemtime(public_path('css/commercial-workspace.css')) : time()) ?>" type="text/css">
    <link rel="stylesheet" href="<?php echo asset('css/zolo-erp-neo.css') . '?v=' . (file_exists(public_path('css/zolo-erp-neo.css')) ? filemtime(public_path('css/zolo-erp-neo.css')) : time()) ?>" type="text/css">
    <link rel="stylesheet" href="{{ versioned_asset('css/pages/report-profit-loss.css') }}">
@endpush
