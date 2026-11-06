@extends('backend.layout.main')
@section('charts', '1')
@section('content')

@push('css')
<link rel="stylesheet" href="{{ versioned_asset('css/pages/index.css') }}">
@endpush

<x-success-message key="message" />
<x-error-message key="not_permitted" />

@php
    $general_setting = $general_setting ?? \App\Models\GeneralSetting::latest()->first();
    $navigation = new \App\Services\Platform\NavigationAccess($role_has_permissions_list ?? null);
    $menu = app(\App\Services\Platform\MenuSwitches::class);
    $stockUrl = app(\App\Services\Platform\InventoryNavigation::class)->sections($navigation)['stock']['url'] ?? null;
    $stockUrl = $stockUrl && $menu->allowsUrl($stockUrl) ? $stockUrl : null;
    $newSale = route('sales.index', ['new' => 1]);
    $newPurchase = route('purchases.index', ['new' => 1]);
    $newExpense = route('expenses.index', ['new' => 1]);
    $showMetrics = $navigation->allows('revenue_profit_summary');
    $showCashFlow = $navigation->allows('cash_flow');
    $showMonthly = $navigation->allows('monthly_summary');
    $showYearly = $navigation->allows('yearly_report');
    $breakdownTitle = $period === 'month' ? 'Monthly breakdown' : 'Period breakdown';
    $dateRange = $start === $end ? $reportDate->format('d M Y') : \Carbon\CarbonImmutable::parse($start)->format('d M Y') . ' – ' . $reportDate->format('d M Y');
    $warehouseQuery = $warehouseId ? '?' . http_build_query(['warehouse_id' => $warehouseId]) : '';
    $pnlUrl = $ledger && $navigation->allows('accounting.reports.view') && $menu->routeAllowed('accounting.profit-loss')
        ? route('accounting.profit-loss', ['start_date' => $start, 'end_date' => $end]) : null;
    $decimals = (int) $general_setting->decimal;
    $money = fn ($value) => $currency_symbol . number_format((float) $value, $decimals);
    $metrics = [
        ['Revenue', 'graph-bar', 'primary', $revenue, 'revenue', 'Sales returns', $return, 'return', $ledger ? 'Posted revenue for the selected branch and period.' : 'Sales less shipping and returns, plus other income.'],
        ['Profit', 'trophy', 'success', $profit, 'profit', 'Purchase returns', $purchase_return, 'purchase_return', $ledger ? 'Net income from the Profit and Loss report for the selected branch and period.' : 'Estimated profit after product costs and expenses.'],
        ['Sales', 'cart', 'info', $total_sale, 'total_sale', 'Active customers', $total_customers, null, 'Sales total excluding shipping.'],
        ['Purchases', 'archive', 'info', $purchase, 'total_purchase', 'Active suppliers', $total_suppliers, null, 'Purchase total after purchase returns.'],
        ['Expenses', 'wallet', 'warning', $expense, 'expense', 'Operating expenses', null, null, $ledger ? 'Posted operating expenses for the selected branch and period.' : 'Direct business expenses and overhead.'],
        ['Receivables', 'clock', 'warning', $invoice_due, 'invoice-due', 'Due from customers', null, null, 'Current outstanding customer balances.'],
        ['Payables', 'calendar', 'warning', $purchase_due, 'purchase_due', 'Due to suppliers', null, null, 'Current outstanding supplier balances.'],
        ['Cash & Bank', 'briefcase', 'success', $liquid_balance, 'liquid-balance', 'Inventory value', $stock_valuation, 'stock-value', 'Current cash and bank balance. Inventory value follows the warehouse filter.'],
    ];
    $hasBreakdown = min($revenue, $purchase, $expense) >= 0 && max($revenue, $purchase, $expense) > 0;
@endphp

<div class="erp-dashboard" data-dashboard data-currency="{{ $currency_symbol }}" data-decimals="{{ $decimals }}"
    data-date-format="{{ $general_setting->date_format }}"
    @if ($navigation->allows('sales-index') && $menu->routeAllowed('sales.index')) data-sale-url="{{ route('sales.index') }}" @endif
    @if ($navigation->allows('purchases-index') && $menu->routeAllowed('purchases.index')) data-purchase-url="{{ route('purchases.index') }}" @endif>
    @if (!config('database.connections.zoloerp_landlord') && Auth::user()->role_id <= 2 && !empty($versionUpgradeData['alert_version_upgrade_enable']))
        <div id="alertSection" class="zolo-banner-card alert alert-dismissible fade show" role="alert">
            <div class="zolo-banner-content">
                <span class="zolo-badge-pill">zoloERP System</span>
                <span class="zolo-banner-text">Version <strong>{{ $versionUpgradeData['demo_version'] }}</strong> is ready for your deployment.</span>
                <a href="{{ route('new-release') }}" class="zolo-banner-action">View Release Notes &rarr;</a>
            </div>
            <button type="button" id="closeButtonUpgrade" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    @endif

    <header class="dash-card dash-command">
        <div class="dash-heading">
            <span class="dash-icon"><i class="dripicons-meter" aria-hidden="true"></i></span>
            <div>
                <h1>Dashboard</h1>
                <p>Welcome back, {{ Auth::user()->name }}</p>
            </div>
        </div>
        <nav class="dash-actions" aria-label="Quick actions">
            @if ($navigation->has('sales.pos') && $menu->routeAllowed('sale.pos'))
                <a href="{{ route('sale.pos') }}" class="btn btn-outline-secondary"><i class="dripicons-shopping-bag" aria-hidden="true"></i> POS Terminal</a>
            @endif
            @if ($navigation->allows('sales-add') && $menu->allowsUrl($newSale))
                <a href="{{ $newSale }}" class="btn btn-primary"><i class="dripicons-plus" aria-hidden="true"></i> New Sale</a>
            @endif
            @if ($navigation->allows('purchases-add') && $menu->allowsUrl($newPurchase))
                <a href="{{ $newPurchase }}" class="btn btn-outline-secondary"><i class="dripicons-cart" aria-hidden="true"></i> New Purchase</a>
            @endif
            @if ($navigation->allows('expenses-add') && $menu->allowsUrl($newExpense))
                <a href="{{ $newExpense }}" class="btn btn-outline-secondary"><i class="dripicons-wallet" aria-hidden="true"></i> Add Expense</a>
            @endif
            @if (Route::has('accounting.coa') && $navigation->allows('accounting.reports.view') && $menu->routeAllowed('accounting.coa'))
                <a href="{{ route('accounting.coa') }}" class="btn btn-outline-secondary">Chart of Accounts</a>
            @endif
            @if (Route::has('accounting.general-ledger') && $navigation->allows('accounting.reports.view') && $menu->routeAllowed('accounting.general-ledger'))
                <a href="{{ route('accounting.general-ledger') }}" class="btn btn-outline-secondary">General Ledger</a>
            @endif
            @if ($stockUrl)
                <a href="{{ $stockUrl }}" class="btn btn-outline-secondary"><i class="dripicons-stack" aria-hidden="true"></i> Stock</a>
            @endif
        </nav>
    </header>

    @if (in_array('restaurant', explode(',', cache()->get('general_setting')->modules)))
        @if (Auth::user()->role_id > 2 && isset(Auth::user()->service_staff))
            @php
                $cooked = DB::table('sales')
                    ->where('waiter_id', Auth::user()->id)
                    ->where('sale_status', 5)
                    ->orWhere('sale_status', 6)
                    ->where('sales.created_at', '>=', now()->subDay())
                    ->count();
            @endphp
        @elseif (Auth::user()->role_id <= 2)
            @php
                $cooked = DB::table('sales')
                    ->where('sale_status', 6)
                    ->where('sales.created_at', '>=', now()->subDay())
                    ->count();
            @endphp
        @endif
        @isset($cooked)
            <a href="{{ route('kitchen.dashboard') }}" class="alert alert-warning mb-0">{{ $cooked }} {{ __('db.Orders to serve') }}</a>
        @endisset
    @endif

    @if ($showMetrics)
        <section class="dashboard-counts dash-overview" aria-labelledby="dash-overview-title">
            <div class="dash-overview-head">
                <div class="d-flex align-items-center flex-wrap gap-2">
                    <h2 id="dash-overview-title" class="mb-0">Financial overview</h2>
                    <a href="{{ url('/workspace#zw-companies') }}" class="dashboard-switch-company btn btn-sm btn-outline-primary ml-2 d-inline-flex align-items-center">
                        <i class="fa fa-cubes"></i> Master Group Insight
                    </a>
                    <p class="w-100 mb-0 mt-1">
                        Receivables, payables, cash, inventory and party counts show current balances or totals.
                        @if ($ledger)
                            Revenue, profit, expenses and cash reflect the selected branch across its warehouses.
                        @endif
                    </p>
                </div>
                <form action="{{ route('dashboard') }}" method="GET" class="dash-filters" aria-label="Dashboard filters">
                    @if (Auth::user()->role_id <= 2)
                        <div class="dash-filter">
                            <label for="warehouse_btn">Warehouse</label>
                            <select class="form-control custom-select-native" name="warehouse_id" id="warehouse_btn">
                                <option value="0">All warehouses</option>
                                @foreach ($warehouses as $warehouse)
                                    <option value="{{ $warehouse->id }}" @selected($warehouseId === $warehouse->id)>{{ $warehouse->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    <div class="dash-filter">
                        <label for="dash-period">Period</label>
                        <select class="form-control custom-select-native" id="dash-period" name="period">
                            <option value="today" @selected($period === 'today')>{{ __('db.Today') }}</option>
                            <option value="week" @selected($period === 'week')>{{ __('db.Last 7 Days') }}</option>
                            <option value="month" @selected($period === 'month')>{{ __('db.This Month') }}</option>
                            <option value="year" @selected($period === 'year')>Financial year</option>
                        </select>
                    </div>
                    <noscript><button type="submit" class="btn btn-outline-secondary">Apply</button></noscript>
                </form>
            </div>
            <div class="dash-metrics">
                @foreach ($metrics as [$label, $icon, $tone, $amount, $field, $note, $detail, $detailField, $help])
                    <article class="dash-card dash-metric">
                        <div class="dash-metric-head">
                            <span class="dash-icon dash-tone-{{ $tone }}"><i class="dripicons-{{ $icon }}" aria-hidden="true"></i></span>
                            <h3>
                                @if ($pnlUrl && in_array($field, ['revenue', 'profit', 'expense'], true))
                                    <a href="{{ $pnlUrl }}">{{ $label }}</a>
                                @else
                                    {{ $label }}
                                @endif
                            </h3>
                            <x-info :title="$help" type="info" />
                        </div>
                        <p class="dash-value" data-metric="{{ $field }}" data-amount="{{ $amount }}">{{ $money($amount) }}</p>
                        <p class="dash-note">
                            <span>{{ $note }}</span>
                            @if ($detailField)
                                <strong data-metric="{{ $detailField }}" data-amount="{{ $detail }}">{{ $money($detail) }}</strong>
                            @elseif ($detail !== null)
                                <strong>{{ number_format($detail) }}</strong>
                            @endif
                        </p>
                    </article>
                @endforeach
            </div>
        </section>
    @endif

    @if ($showCashFlow || $showMonthly)
        <div class="dash-pair">
            @if ($showCashFlow)
                <section class="dash-card" aria-labelledby="dash-cash-title">
                    <div class="dash-panel-head">
                        <div class="dash-heading">
                            <span class="dash-icon"><i class="dripicons-pulse" aria-hidden="true"></i></span>
                            <div>
                                <h2 id="dash-cash-title">{{ __('db.Cash Flow') }}</h2>
                                <p>{{ $ledger ? 'Posted cash movements by month' : 'Monthly payments received and sent' }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="dash-chart-body">
                        <div class="dash-chart">
                            <canvas id="cashFlow" role="img" aria-label="Monthly cash flow" aria-describedby="dash-cash-description"
                                data-recieved="{{ json_encode($payment_recieved) }}" data-sent="{{ json_encode($payment_sent) }}"
                                data-month="{{ json_encode($month) }}" data-label1="Payments received" data-label2="Payments sent"></canvas>
                        </div>
                        <p class="sr-only" id="dash-cash-description">
                            @foreach ($month as $index => $label)
                                {{ $label }}. Received {{ $money($payment_recieved[$index]) }}. Sent {{ $money($payment_sent[$index]) }}.
                            @endforeach
                        </p>
                    </div>
                </section>
            @endif

            @if ($showMonthly)
                <section class="dash-card" aria-labelledby="dash-monthly-title">
                    <div class="dash-panel-head">
                        <div class="dash-heading">
                            <span class="dash-icon dash-tone-success"><i class="dripicons-graph-pie" aria-hidden="true"></i></span>
                            <div>
                                <h2 id="dash-monthly-title">{{ $breakdownTitle }}</h2>
                                <p>{{ $dateRange }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="dash-breakdown">
                        @if ($hasBreakdown)
                            <div class="dash-donut" aria-hidden="true">
                                <canvas id="transactionChart" data-revenue="{{ $revenue }}" data-purchase="{{ $purchase }}"
                                    data-expense="{{ $expense }}" data-label1="Purchases" data-label2="Revenue" data-label3="Expenses"></canvas>
                            </div>
                        @endif
                        <dl class="dash-breakdown-values">
                            @foreach ([['Revenue', 'success', $revenue], ['Purchases', 'primary', $purchase], ['Expenses', 'warning', $expense]] as [$label, $tone, $amount])
                                <div>
                                    <dt><span class="dash-dot dash-tone-{{ $tone }}" aria-hidden="true"></span>{{ $label }}</dt>
                                    <dd>{{ $money($amount) }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    </div>
                </section>
            @endif
        </div>
    @endif

    <div class="dash-pair">
        <section class="dash-card" aria-labelledby="dash-activity-title">
            <div class="dash-panel-head">
                <div class="dash-heading">
                    <span class="dash-icon dash-tone-info"><i class="dripicons-swap" aria-hidden="true"></i></span>
                    <div>
                        <h2 id="dash-activity-title">Recent activity</h2>
                        <p>Latest 5 transactions</p>
                    </div>
                </div>
                <select id="dash-activity-kind" class="form-control custom-select-native" aria-label="Transaction type">
                    <option value="sale" data-url="{{ url('/recent-sale') . $warehouseQuery }}">Sales</option>
                    <option value="purchase" data-url="{{ url('/recent-purchase') . $warehouseQuery }}">Purchases</option>
                    <option value="quotation" data-url="{{ url('/recent-quotation') . $warehouseQuery }}">Quotations</option>
                    <option value="payment" data-url="{{ url('/recent-payment') . $warehouseQuery }}">Payments</option>
                </select>
            </div>
            <div class="table-responsive" id="dash-activity-results" role="region" aria-label="Recent transactions" tabindex="0">
                <table class="table table-sm dash-table" id="dash-activity-table" aria-labelledby="dash-activity-title">
                    <thead>
                        <tr>
                            <th scope="col">{{ __('db.reference') }}</th>
                            <th scope="col">{{ __('db.date') }}</th>
                            <th scope="col" data-activity-party>{{ __('db.customer') }}</th>
                            <th scope="col" data-activity-status>{{ __('db.status') }}</th>
                            <th scope="col" class="dash-number">{{ __('db.Amount') }}</th>
                        </tr>
                    </thead>
                    <tbody><tr><td colspan="5" class="dash-table-message" role="status">Loading transactions…</td></tr></tbody>
                </table>
            </div>
        </section>

        <section class="dash-card" aria-labelledby="dash-stock-title">
            <div class="dash-panel-head">
                <div class="dash-heading">
                    <span class="dash-icon dash-tone-{{ $low_stock_count ? 'warning' : 'success' }}"><i class="dripicons-stack" aria-hidden="true"></i></span>
                    <div>
                        <h2 id="dash-stock-title">Stock alerts</h2>
                        <p>{{ $low_stock_count ? $low_stock_count . ' stock entries at or below threshold' : 'No stock entries at or below threshold' }}</p>
                    </div>
                </div>
                @if ($stockUrl)
                    <a href="{{ $stockUrl }}" class="dash-link">View stock <i class="dripicons-arrow-thin-right" aria-hidden="true"></i></a>
                @endif
            </div>
            @if ($low_stock_products->isNotEmpty())
                <div class="table-responsive" role="region" aria-label="Low stock entries" tabindex="0">
                    <table class="table table-sm dash-table" aria-labelledby="dash-stock-title">
                        <thead>
                            <tr>
                                <th scope="col">Item</th>
                                <th scope="col">Warehouse</th>
                                <th scope="col" class="dash-number">On hand</th>
                                <th scope="col" class="dash-number">Threshold</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($low_stock_products as $item)
                                <tr>
                                    <th scope="row">{{ $item->name }} <small>{{ $item->code }}</small></th>
                                    <td>{{ $item->warehouse_name }}</td>
                                    <td class="dash-number">{{ $item->qty }}</td>
                                    <td class="dash-number">{{ $item->alert_quantity }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="dash-empty">Items reaching their reorder threshold will appear here.</p>
            @endif
        </section>
    </div>

    @if ($showYearly)
        <section class="dash-card" aria-labelledby="dash-yearly-title">
            <div class="dash-panel-head">
                <div class="dash-heading">
                    <span class="dash-icon"><i class="dripicons-graph-bar" aria-hidden="true"></i></span>
                    <div>
                        <h2 id="dash-yearly-title">Sales and purchases</h2>
                        <p>Monthly totals for {{ $year->name }}</p>
                    </div>
                </div>
            </div>
            <div class="dash-chart-body">
                <div class="dash-chart">
                    <canvas id="saleChart" role="img" aria-label="Monthly sales and purchase totals for {{ $year->name }}" aria-describedby="dash-yearly-description"
                        data-sale_chart_value="{{ json_encode($yearly_sale_amount) }}" data-purchase_chart_value="{{ json_encode($yearly_purchase_amount) }}"
                        data-label1="Purchases" data-label2="Sales" data-month="{{ json_encode($month) }}"></canvas>
                </div>
                <p class="sr-only" id="dash-yearly-description">
                    @foreach ($yearly_sale_amount as $index => $amount)
                        {{ $month[$index] }}. Sales {{ $money($amount) }}. Purchases {{ $money($yearly_purchase_amount[$index]) }}.
                    @endforeach
                </p>
            </div>
        </section>
    @endif

    <div class="dash-rankings">
        @foreach ([
            ['monthly-best-selling-qty', 'Best sellers this month', $reportDate->format('F Y'), 'Quantity'],
            ['yearly-best-selling-qty', 'Best sellers by quantity', $year->name, 'Quantity'],
            ['yearly-best-selling-price', 'Best sellers by value', $year->name, 'Sales value'],
        ] as [$key, $title, $period, $unit])
            <section class="dash-card" aria-labelledby="{{ $key }}-title">
                <div class="dash-panel-head">
                    <div class="dash-heading">
                        <span class="dash-icon dash-tone-warning"><i class="dripicons-star" aria-hidden="true"></i></span>
                        <div>
                            <h2 id="{{ $key }}-title">{{ $title }}</h2>
                            <p>{{ $period }} · Top 5</p>
                        </div>
                    </div>
                </div>
                <table class="table table-sm dash-table" aria-labelledby="{{ $key }}-title" data-ranking-url="{{ url('/' . $key) . $warehouseQuery }}" @if ($unit === 'Sales value') data-ranking-value @endif>
                    <thead><tr><th scope="col">Product</th><th scope="col" class="dash-number">{{ $unit }}</th></tr></thead>
                    <tbody><tr><td colspan="2" class="dash-table-message" role="status">Loading best sellers…</td></tr></tbody>
                </table>
            </section>
        @endforeach
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ versioned_asset('js/dashboard.js') }}"></script>
@endpush
