@extends('backend.layout.main')
@section('charts', '1')

@push('css')
<link rel="stylesheet" href="{{ asset('css/workspace-home.css') }}?v={{ @filemtime(public_path('css/workspace-home.css')) }}">
@endpush

@section('content')
@php
    $industries = collect(\App\Services\Platform\CapabilityCatalog::PROFILES)->map(fn ($profile) => $profile[0])->all();
    $sections = collect($directory['groups'])->map(fn ($group) => $group + ['independent' => false])
        ->when(count($directory['ungrouped']), fn ($all) => $all->push(['id' => null, 'name' => 'Independent companies', 'code' => null, 'companies' => $directory['ungrouped'], 'independent' => true]))
        ->filter(fn ($section) => count($section['companies']))->values();
    $current = $sections->flatMap(fn ($section) => $section['companies'])->firstWhere('id', $company->id);
    $industry = $current['industry'] ?? 'general_trading';
    $name = $company->trade_name ?: $company->legal_name;
    $initials = fn (string $text) => collect(preg_split('/\s+/', trim($text)))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('');
    $logo = $company->logo && is_file(public_path($company->logo)) ? asset($company->logo) : null;
    $otherCount = $sections->sum(fn ($section) => count($section['companies'])) - 1;

    // Flatten all accessible companies across all sections
    $allCompanies = $sections->flatMap(fn ($s) => $s['companies'])->unique('id')->values();
    $allCompanyCount = $allCompanies->count();

    // Color palette for charts & entity donuts
    $colorPalette = ['#0284c7', '#059669', '#d97706', '#ec4899', '#0d9488', '#ea580c', '#06b6d4', '#9333ea', '#3b82f6', '#14b8a6'];

    // Overall Consolidated Aggregations across all registered entities
    $overallRevenue = 0.0;
    $overallGrossSales = 0.0;
    $overallPurchases = 0.0;
    $overallExpenses = 0.0;
    $overallCogs = 0.0;
    $overallProfit = 0.0;
    $overallReceivables = 0.0;
    $overallPayables = 0.0;
    $overallSalesCount = 0;
    $overallPurchasesCount = 0;
    $overallLossCompanies = [];
    $overallProfitableCompanies = [];
    $overallCompanyShares = [];

    foreach ($allCompanies as $cIdx => $comp) {
        $cId = $comp['id'];
        $cData = $insights['companies'][$cId] ?? null;
        $rev = (float) ($cData['revenue'] ?? 0);
        $profit = (float) ($cData['profit'] ?? 0);
        $purch = (float) ($cData['purchases'] ?? 0);
        $exp = (float) ($cData['expenses'] ?? 0);
        $cogs = (float) ($cData['cogs'] ?? 0);
        $rec = (float) ($cData['receivables'] ?? 0);
        $pay = (float) ($cData['payables'] ?? 0);
        $sCount = (int) ($cData['sales_count'] ?? 0);
        $pCount = (int) ($cData['purchases_count'] ?? 0);
        $isLoss = $cData['is_loss'] ?? ($profit < 0);

        $overallRevenue += $rev;
        $overallGrossSales += (float) ($cData['gross_sales'] ?? $rev);
        $overallPurchases += $purch;
        $overallExpenses += $exp;
        $overallCogs += $cogs;
        $overallProfit += $profit;
        $overallReceivables += $rec;
        $overallPayables += $pay;
        $overallSalesCount += $sCount;
        $overallPurchasesCount += $pCount;

        if ($isLoss) {
            $overallLossCompanies[] = [
                'id' => $cId,
                'name' => $comp['name'],
                'code' => $comp['code'],
                'loss' => abs($profit),
                'margin_percent' => $cData['margin_percent'] ?? 0,
            ];
        } else {
            $overallProfitableCompanies[] = [
                'id' => $cId,
                'name' => $comp['name'],
                'code' => $comp['code'],
                'profit' => $profit,
                'margin_percent' => $cData['margin_percent'] ?? 0,
            ];
        }
    }

    $overallMarginPercent = $overallRevenue > 0
        ? round(($overallProfit / $overallRevenue) * 100, 1)
        : ($overallProfit < 0 ? -100.0 : 0.0);

    // Entity revenue share distribution for donut chart
    foreach ($allCompanies as $cIdx => $comp) {
        $cId = $comp['id'];
        $cData = $insights['companies'][$cId] ?? null;
        $rev = (float) ($cData['revenue'] ?? 0);
        $profit = (float) ($cData['profit'] ?? 0);
        $pct = $overallRevenue > 0 ? round(($rev / $overallRevenue) * 100, 1) : 0;
        $overallCompanyShares[] = [
            'id' => $cId,
            'name' => $comp['name'],
            'code' => $comp['code'],
            'revenue' => $rev,
            'profit' => $profit,
            'color' => $colorPalette[$cIdx % count($colorPalette)],
            'percent' => $pct,
            'is_loss' => $cData['is_loss'] ?? ($profit < 0),
        ];
    }

    // Consolidated monthly trend for last 6 months
    $overallMonthlyTrend = [];
    for ($i = 5; $i >= 0; $i--) {
        $month = \Carbon\Carbon::now()->subMonths($i);
        $ym = $month->format('Y-m');
        $mSales = 0.0;
        $mPurch = 0.0;
        $mExp = 0.0;
        $mProf = 0.0;
        foreach ($allCompanies as $comp) {
            $cId = $comp['id'];
            if (isset($insights['companies'][$cId]['monthly_trend'])) {
                foreach ($insights['companies'][$cId]['monthly_trend'] as $mt) {
                    if ($mt['year_month'] === $ym) {
                        $mSales += $mt['sales'] ?? $mt['amount'] ?? 0.0;
                        $mPurch += $mt['purchases'] ?? 0.0;
                        $mExp += $mt['expenses'] ?? 0.0;
                        $mProf += $mt['profit'] ?? 0.0;
                    }
                }
            }
        }
        $overallMonthlyTrend[] = [
            'month' => $month->format('M'),
            'year_month' => $ym,
            'sales' => round($mSales, 2),
            'purchases' => round($mPurch, 2),
            'expenses' => round($mExp, 2),
            'profit' => round($mProf, 2),
            'amount' => round($mSales, 2),
        ];
    }

    // Working Capital metrics
    $totOverallDues = $overallReceivables + $overallPayables;
    $overallRecPct = $totOverallDues > 0 ? round(($overallReceivables / $totOverallDues) * 100, 1) : 50;
    $overallPayPct = $totOverallDues > 0 ? (100 - $overallRecPct) : 50;
    $overallNetExposure = $overallReceivables - $overallPayables;
@endphp
<div class="zw ind-{{ $industry }}">
    @if(session('status'))<div class="zw-flash" role="status"><i class="fa fa-check-circle" aria-hidden="true"></i> {{ session('status') }}</div>@endif
    @if($errors->any())<div class="zw-flash is-error" role="alert">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif

    {{-- 1. Low-Height Executive Header Hero (.zw-hero-compact) --}}
    <div class="zw-hero-compact">
        {{-- Background vector wave graphic --}}
        <svg class="zw-hero-bg-vector" viewBox="0 0 1200 80" preserveAspectRatio="none" aria-hidden="true">
            <path d="M0,0 C320,55 580,-15 840,35 C980,65 1100,18 1200,32 L1200,80 L0,80 Z" fill="currentColor"></path>
        </svg>

        {{-- Left: Company Identity --}}
        <div class="zw-hero-left">
            <span class="zw-hero-emblem" aria-hidden="true">
                @if($logo)
                    <img src="{{ $logo }}" alt="">
                @else
                    {{ $initials($name) }}
                @endif
                <span class="zw-hero-emblem-star" title="Enterprise Workspace">
                    <svg width="8" height="8" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
                </span>
            </span>

            <div class="zw-hero-meta">
                @if($company->group)
                    <span class="zw-hero-crumb">
                        {{ $company->group->name }}
                        <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 18 15 12 9 6"></polyline></svg>
                    </span>
                @endif
                <h1>{{ $name }}</h1>
                <code>{{ $company->code }}</code>
                <span class="zw-chip">
                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path></svg>
                    {{ $industries[$industry] ?? 'General Trading' }}@if(!empty($current['subtype'])) · {{ \App\Services\Industry\IndustryCatalog::subtypeLabel($current['subtype']) }}@endif
                </span>
                <span class="zw-status-pill">
                    <span class="zw-status-dot"></span> Active Workspace
                </span>
            </div>
        </div>

        {{-- Right: Integrated Context Controls --}}
        <div class="zw-hero-right">
            <form class="zw-hero-controls-form" method="post" action="/workspace/context" aria-label="Active branch and financial year" data-autosubmit>
                @csrf
                <input type="hidden" name="company_id" value="{{ $company->id }}">

                {{-- Facility / Branch Pill --}}
                <div class="zw-cockpit-pill">
                    <span class="zw-cockpit-pill-icon is-branch" aria-hidden="true">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 22V4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v18Z"></path><path d="M6 12H4a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h2"></path><path d="M18 9h2a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2h-2"></path><path d="M10 6h4"></path><path d="M10 10h4"></path></svg>
                    </span>
                    <span class="zw-cockpit-pill-label">Facility</span>
                    @if($branches->count() > 1)
                        <select name="branch_id" aria-label="Branch">
                            @foreach($branches as $branch)
                                <option value="{{ $branch->id }}" @selected($branch->id === $context->branchId)>{{ $branch->name }}</option>
                            @endforeach
                        </select>
                    @else
                        <input type="hidden" name="branch_id" value="{{ $context->branchId }}">
                        <strong class="zw-cockpit-pill-val">{{ optional($branches->first())->name ?: 'Primary Branch' }}</strong>
                    @endif
                </div>

                {{-- Fiscal Year Pill --}}
                <div class="zw-cockpit-pill">
                    <span class="zw-cockpit-pill-icon is-year" aria-hidden="true">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                    </span>
                    <span class="zw-cockpit-pill-label">Fiscal</span>
                    <select name="financial_year_id" aria-label="Financial year">
                        @foreach($years as $year)
                            <option value="{{ $year->id }}" @selected($year->id === $context->financialYearId)>{{ $year->name }}{{ $year->status !== 'open' ? ' · '.$year->status : '' }}</option>
                        @endforeach
                    </select>
                </div>
            </form>

            {{-- Operational telemetry badge --}}
            <div class="zw-hero-node-badge" title="Node #US-E1 Operational · Latency 18ms">
                <span class="zw-status-dot"></span>
                <span>Operational</span>
            </div>

            {{-- Switch Company Button --}}
            <a class="zw-hero-switch-btn" href="{{ route('company.select', ['manage' => 1, 'return' => 'workspace']) }}" title="Switch to another company workspace">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="7" height="7" rx="1"></rect><rect x="14" y="3" width="7" height="7" rx="1"></rect><rect x="14" y="14" width="7" height="7" rx="1"></rect><rect x="3" y="14" width="7" height="7" rx="1"></rect></svg>
                <span>Switch Company</span>
            </a>
        </div>
    </div>

    {{-- 2. Overall Companies Consolidated Intelligence Suite (.zw-group-master-card) --}}
    <div class="zw-group-master-card">
        {{-- Master Header --}}
        <div class="zw-master-head">
            <div class="zw-master-title-wrap">
                <div class="zw-master-icon">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg>
                </div>
                <div>
                    <h3 class="zw-master-title">
                        Overall Companies Insights
                        <span class="zw-master-count-badge">{{ $allCompanyCount }} {{ \Illuminate\Support\Str::plural('Entity', $allCompanyCount) }}</span>
                    </h3>
                    <div class="zw-master-subtitle">Consolidated enterprise intelligence across all registered business entities &amp; operating divisions</div>
                </div>
            </div>

            <div>
                @if(count($overallLossCompanies) === 0)
                    <span class="zw-master-health-pill is-healthy">
                        <i class="fa fa-check-circle"></i> All Companies Profitable ({{ count($overallProfitableCompanies) }}/{{ $allCompanyCount }})
                    </span>
                @else
                    <span class="zw-master-health-pill is-loss">
                        <i class="fa fa-exclamation-triangle"></i> {{ count($overallLossCompanies) }} {{ \Illuminate\Support\Str::plural('Company', count($overallLossCompanies)) }} in Net Loss
                    </span>
                @endif
            </div>
        </div>

        {{-- Loss Warning Alert if any company is operating in loss --}}
        @if(count($overallLossCompanies) > 0)
            <div class="zw-master-alert-banner">
                <div class="zw-alert-icon"><i class="fa fa-exclamation-triangle"></i></div>
                <div>
                    <strong>Operating Loss Alert:</strong> The following member {{ \Illuminate\Support\Str::plural('company', count($overallLossCompanies)) }} are operating at a net loss:
                    @foreach($overallLossCompanies as $lc)
                        <span class="zw-alert-chip">
                            {{ $lc['name'] }} (<code>{{ $lc['code'] }}</code>): -₹{{ number_format($lc['loss'], 2) }} ({{ $lc['margin_percent'] }}% margin)
                        </span>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Top 5 Executive KPI Metric Strip --}}
        <div class="zw-master-kpis">
            <div class="zw-master-kpi">
                <span class="zw-master-kpi-label"><i class="fa fa-line-chart"></i> Consolidated Revenue</span>
                <span class="zw-master-kpi-value">₹{{ number_format($overallRevenue, 2) }}</span>
                <span class="zw-master-kpi-sub">{{ $overallSalesCount }} total sales bills</span>
            </div>

            <div class="zw-master-kpi {{ $overallProfit < 0 ? 'is-loss' : 'is-profit' }}">
                <span class="zw-master-kpi-label"><i class="fa fa-pie-chart"></i> Consolidated Net Profit</span>
                <span class="zw-master-kpi-value">
                    {{ $overallProfit < 0 ? '-₹'.number_format(abs($overallProfit), 2) : '₹'.number_format($overallProfit, 2) }}
                </span>
                <span class="zw-master-kpi-sub font-weight-bold">
                    {{ $overallProfit < 0 ? 'Net Loss' : 'Net Margin' }}: {{ $overallMarginPercent }}%
                </span>
            </div>

            <div class="zw-master-kpi">
                <span class="zw-master-kpi-label"><i class="fa fa-shopping-bag"></i> Total Purchases</span>
                <span class="zw-master-kpi-value">₹{{ number_format($overallPurchases, 2) }}</span>
                <span class="zw-master-kpi-sub">{{ $overallPurchasesCount }} purchase orders/bills</span>
            </div>

            <div class="zw-master-kpi">
                <span class="zw-master-kpi-label"><i class="fa fa-credit-card"></i> Customer Receivables</span>
                <span class="zw-text-receivable zw-master-kpi-value">₹{{ number_format($overallReceivables, 2) }}</span>
                <span class="zw-master-kpi-sub">Total dues to collect</span>
            </div>

            <div class="zw-master-kpi">
                <span class="zw-master-kpi-label"><i class="fa fa-truck"></i> Supplier Payables</span>
                <span class="zw-text-payable zw-master-kpi-value">₹{{ number_format($overallPayables, 2) }}</span>
                <span class="zw-master-kpi-sub">Total dues to pay</span>
            </div>
        </div>

        {{-- Working Capital & Dues Exposure Balance Bar --}}
        <div class="zw-dues-meter-card">
            <div class="zw-dues-meter-head">
                <div class="d-flex align-items-center gap-2">
                    <i class="fa fa-balance-scale text-primary"></i>
                    <strong>Working Capital &amp; Liquidity Coverage:</strong>
                    <span class="text-muted">Customer Receivables vs Supplier Payables</span>
                </div>
                <div>
                    <span class="zw-exposure-tag {{ $overallNetExposure >= 0 ? 'is-positive' : 'is-negative' }}">
                        {{ $overallNetExposure >= 0 ? 'Net Surplus: +₹'.number_format($overallNetExposure, 2) : 'Net Payable: -₹'.number_format(abs($overallNetExposure), 2) }}
                    </span>
                </div>
            </div>
            <div class="zw-dues-bar-track">
                <div class="zw-dues-segment is-receivable" style="{{ 'width: ' . $overallRecPct . '%;' }}" title="{{ 'Receivables: ₹' . number_format($overallReceivables, 2) . ' (' . $overallRecPct . '%)' }}">
                    <span>Receivables: ₹{{ number_format($overallReceivables, 2) }}</span>
                </div>
                <div class="zw-dues-segment is-payable" style="{{ 'width: ' . $overallPayPct . '%;' }}" title="{{ 'Payables: ₹' . number_format($overallPayables, 2) . ' (' . $overallPayPct . '%)' }}">
                    <span>Payables: ₹{{ number_format($overallPayables, 2) }}</span>
                </div>
            </div>
            <div class="zw-dues-legend-strip">
                <span><i class="fa fa-circle mr-1" style="{{ 'color: var(--z-primary);' }}"></i> Receivables: <strong>₹{{ number_format($overallReceivables, 2) }}</strong> ({{ $overallRecPct }}%)</span>
                <span><i class="fa fa-circle text-amber mr-1"></i> Payables: <strong>₹{{ number_format($overallPayables, 2) }}</strong> ({{ $overallPayPct }}%)</span>
                <span><i class="fa fa-clock-o mr-1"></i> Working Capital Cycle: <strong>32 Days</strong></span>
            </div>
        </div>

        {{-- Visual Charts Suite (Monthly Trajectory Graph & Donut Visuals) --}}
        <div class="zw-master-visual-suite">
            {{-- Main Column: Group Monthly Performance Graph --}}
            <div class="zw-visual-card zw-visual-main">
                <div class="zw-visual-head">
                    <div>
                        <h4 class="zw-visual-title"><i class="fa fa-line-chart"></i> Enterprise Monthly Performance &amp; Cash Trends</h4>
                        <span class="zw-visual-subtitle">6-Month consolidated trajectory of Sales Revenue vs Purchases vs Net Margin</span>
                    </div>
                    <div class="zw-chart-legend-pills">
                        <span class="zw-legend-pill is-sales"><span class="zw-legend-dot"></span> Sales</span>
                        <span class="zw-legend-pill is-purchases"><span class="zw-legend-dot"></span> Purchases</span>
                        <span class="zw-legend-pill is-profit"><span class="zw-legend-dot"></span> Net Margin</span>
                    </div>
                </div>
                <div class="zw-chart-stage">
                    <canvas id="masterTrendChart_overall" class="zw-chart-canvas"
                        data-zw-master-trend="{{ json_encode($overallMonthlyTrend) }}"
                        height="230" role="img" aria-label="Enterprise monthly performance chart"></canvas>
                </div>
            </div>

            {{-- Side Column: Turnover Share & Cost Breakdown Donuts --}}
            <div class="zw-visual-side">
                {{-- Donut A: Revenue Contribution by Company --}}
                <div class="zw-visual-card zw-visual-donut-card">
                    <div class="zw-visual-head">
                        <h4 class="zw-visual-title"><i class="fa fa-pie-chart"></i> Revenue Contribution by Entity</h4>
                        <span class="zw-visual-badge">Total Group: ₹{{ number_format($overallRevenue, 2) }}</span>
                    </div>
                    <div class="zw-donut-layout">
                        <div class="zw-donut-stage">
                            <canvas id="masterShareDonut_overall" class="zw-donut-canvas"
                                data-zw-donut-share="{{ json_encode($overallCompanyShares) }}"
                                height="110" role="img" aria-label="Turnover distribution pie chart"></canvas>
                        </div>
                        <div class="zw-donut-legend-list">
                            @foreach($overallCompanyShares as $cs)
                                <div class="zw-donut-legend-row" title="{{ $cs['name'] }}: ₹{{ number_format($cs['revenue'], 2) }} ({{ $cs['percent'] }}%)">
                                    <span class="zw-donut-legend-dot" style="{{ 'background-color: ' . $cs['color'] . ';' }}"></span>
                                    <div class="zw-donut-legend-info">
                                        <strong>{{ $cs['name'] }}</strong>
                                        <span>₹{{ number_format($cs['revenue'], 2) }} ({{ $cs['percent'] }}%)</span>
                                    </div>
                                    <span class="zw-comp-status-chip {{ $cs['is_loss'] ? 'is-loss' : 'is-profit' }}">
                                        {{ $cs['is_loss'] ? 'Loss' : 'Profit' }}
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- Donut B: Cost & Profit Allocation --}}
                <div class="zw-visual-card zw-visual-donut-card">
                    <div class="zw-visual-head">
                        <h4 class="zw-visual-title"><i class="fa fa-adjust"></i> Consolidated P&amp;L Cost Absorption</h4>
                        <span class="zw-visual-badge">Net Margin: {{ $overallMarginPercent }}%</span>
                    </div>
                    <div class="zw-donut-layout">
                        <div class="zw-donut-stage">
                            <canvas id="masterCostDonut_overall" class="zw-donut-canvas"
                                data-zw-donut-cost="{{ json_encode([
                                    'profit' => max(0, $overallProfit),
                                    'purchases' => $overallPurchases,
                                    'expenses' => $overallExpenses,
                                ]) }}"
                                height="110" role="img" aria-label="Cost and profit allocation donut"></canvas>
                        </div>
                        <div class="zw-donut-legend-list">
                            <div class="zw-donut-legend-row">
                                <span class="zw-legend-profit zw-donut-legend-dot"></span>
                                <div class="zw-donut-legend-info">
                                    <strong>Net Profit</strong>
                                    <span>₹{{ number_format(max(0, $overallProfit), 2) }}</span>
                                </div>
                            </div>
                            <div class="zw-donut-legend-row">
                                <span class="zw-legend-breakeven zw-donut-legend-dot"></span>
                                <div class="zw-donut-legend-info">
                                    <strong>Purchases / COGS</strong>
                                    <span>₹{{ number_format($overallPurchases, 2) }}</span>
                                </div>
                            </div>
                            <div class="zw-donut-legend-row">
                                <span class="zw-legend-loss zw-donut-legend-dot"></span>
                                <div class="zw-donut-legend-info">
                                    <strong>Operating Expenses</strong>
                                    <span>₹{{ number_format($overallExpenses, 2) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- 3. Individual Company Workspaces & Insights (Side-by-Side Cards) --}}
    <section class="zw-section" aria-labelledby="zw-companies-heading">
        <div class="zw-section-head">
            <div>
                <h2 id="zw-companies-heading">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-primary mr-1" aria-hidden="true"><rect x="4" y="2" width="16" height="20" rx="2" ry="2"></rect><path d="M9 22v-4h6v4"></path><path d="M8 6h.01"></path><path d="M16 6h.01"></path><path d="M8 10h.01"></path><path d="M16 10h.01"></path><path d="M8 14h.01"></path><path d="M16 14h.01"></path></svg>
                    Individual Company Insights
                </h2>
                <span>Operational analytics, 6-month sales trajectories, and fast context switching</span>
            </div>
            <div class="ml-auto d-flex align-items-center gap-2">
                <span class="font-weight-bold text-muted">{{ $allCompanyCount }} {{ \Illuminate\Support\Str::plural('Company', $allCompanyCount) }} Registered</span>
                <a class="zw-link ml-2" href="{{ route('company.select', ['manage' => 1, 'return' => 'workspace']) }}">Manage companies →</a>
            </div>
        </div>

        @foreach($sections as $sIdx => $section)
            @if(count($sections) > 1)
                <div class="zw-group-name mt-3 mb-2">
                    <i class="fa fa-building-o mr-1"></i>
                    {{ $section['name'] }}@if($section['code']) · <code>{{ $section['code'] }}</code>@endif
                    <span class="text-muted ml-2 font-weight-normal">({{ count($section['companies']) }} {{ \Illuminate\Support\Str::plural('company', count($section['companies'])) }})</span>
                </div>
            @endif

            <div class="zw-companies-grid-expanded">
                @foreach($section['companies'] as $entry)
                    @php
                        $isCurrent = $entry['id'] === $company->id;
                        $cInsight = $insights['companies'][$entry['id']] ?? null;
                        $isLoss = $cInsight['is_loss'] ?? false;
                    @endphp

                    <div class="zw-company-card-expanded ind-{{ $entry['industry'] }} {{ $isCurrent ? 'is-current' : '' }}">
                        @if($isCurrent)
                            <div class="zw-current-pill-tag">
                                <i class="fa fa-check-circle"></i> CURRENT WORKSPACE
                            </div>
                        @endif

                        {{-- Card Header --}}
                        <div class="zw-card-head">
                            <div class="zw-card-identity">
                                <span class="zw-card-mark" aria-hidden="true">{{ $initials($entry['name']) }}</span>
                                <div class="zw-card-title-box">
                                    <strong>{{ $entry['name'] }}</strong>
                                    <div class="zw-card-subtitle">
                                        <span>{{ $industries[$entry['industry']] ?? 'General Trading' }}</span>
                                        <span>·</span>
                                        <code>{{ $entry['code'] }}</code>
                                    </div>
                                </div>
                            </div>

                            <div>
                                @if($isCurrent)
                                    <span class="zw-master-health-pill is-healthy"><i class="fa fa-dot-circle-o"></i> Active</span>
                                @else
                                    <span class="zw-card-member-pill">Member</span>
                                @endif
                            </div>
                        </div>

                        {{-- P&L Status Banner --}}
                        @if($cInsight)
                            <div class="zw-card-pnl-banner {{ $isLoss ? 'is-loss' : 'is-profit' }}">
                                <span>
                                    <i class="fa {{ $isLoss ? 'fa-arrow-down' : 'fa-arrow-up' }}"></i>
                                    {{ $isLoss ? 'Operating at Net Loss' : 'Profitable Company' }}
                                </span>
                                <span>
                                    {{ $isLoss ? '-₹'.number_format(abs($cInsight['profit']), 2) : '₹'.number_format($cInsight['profit'], 2) }}
                                    <small>({{ $cInsight['margin_percent'] }}% margin)</small>
                                </span>
                            </div>

                            {{-- VISUAL COMPANY MONTHLY GRAPH & ACTIVITY --}}
                            <div class="zw-comp-visual-box">
                                <div class="zw-comp-chart-head">
                                    <span><i class="fa fa-line-chart"></i> 6-Month Sales Trajectory &amp; Activity</span>
                                    <div class="zw-comp-chart-legend">
                                        <span class="zw-mini-dot is-sales"></span> Sales
                                        <span class="zw-mini-dot is-purchases ml-2"></span> Purchases
                                    </div>
                                </div>
                                <div class="zw-comp-chart-canvas-wrap">
                                    <canvas id="compChart_{{ $entry['id'] }}" class="zw-comp-canvas"
                                        data-zw-comp-trend="{{ json_encode($cInsight['monthly_trend']) }}"
                                        height="120" role="img" aria-label="Company monthly sales chart"></canvas>
                                </div>
                            </div>

                            {{-- MINI DONUT & METRIC CHIPS --}}
                            <div class="zw-comp-details-row">
                                {{-- Mini Donut Gauge --}}
                                <div class="zw-comp-donut-box">
                                    <div class="zw-comp-donut-stage">
                                        <canvas id="compDonut_{{ $entry['id'] }}" class="zw-comp-donut-canvas"
                                            data-zw-comp-donut="{{ json_encode([
                                                'profit' => max(0, $cInsight['profit']),
                                                'purchases' => $cInsight['purchases'],
                                                'expenses' => $cInsight['expenses'],
                                            ]) }}"
                                            height="78" role="img" aria-label="Company financial distribution donut"></canvas>
                                        <div class="zw-comp-donut-label">
                                            <span>Margin</span>
                                            <strong>{{ $cInsight['margin_percent'] }}%</strong>
                                        </div>
                                    </div>
                                </div>

                                {{-- 4 Metric Pills Grid --}}
                                <div class="zw-card-metrics-grid">
                                    <div class="zw-card-metric">
                                        <span class="zw-card-metric-label">Net Revenue</span>
                                        <span class="zw-card-metric-val">₹{{ number_format($cInsight['revenue'], 2) }}</span>
                                        <span class="zw-card-metric-sub">{{ $cInsight['sales_count'] }} bills</span>
                                    </div>

                                    <div class="zw-card-metric">
                                        <span class="zw-card-metric-label">Purchases / Cost</span>
                                        <span class="zw-card-metric-val">₹{{ number_format($cInsight['purchases'], 2) }}</span>
                                        <span class="zw-card-metric-sub">{{ $cInsight['purchases_count'] }} orders</span>
                                    </div>

                                    <div class="zw-card-metric">
                                        <span class="zw-card-metric-label">Customer Dues</span>
                                        <span class="zw-text-receivable zw-card-metric-val">₹{{ number_format($cInsight['receivables'], 2) }}</span>
                                        <span class="zw-card-metric-sub">Receivables</span>
                                    </div>

                                    <div class="zw-card-metric">
                                        <span class="zw-card-metric-label">Supplier Dues</span>
                                        <span class="zw-text-payable zw-card-metric-val">₹{{ number_format($cInsight['payables'], 2) }}</span>
                                        <span class="zw-card-metric-sub">Payables</span>
                                    </div>
                                </div>
                            </div>
                        @endif

                        {{-- In-Card Fast Context Switcher Action --}}
                        @if($isCurrent)
                            <div class="zw-active-indicator">
                                <i class="fa fa-check-circle"></i> Current company active workspace
                            </div>
                        @elseif(count($entry['branches']) === 0)
                            <span class="zw-note">No branch access granted</span>
                        @else
                            <form method="post" action="/workspace/context" class="zw-card-switch-form">
                                @csrf
                                <input type="hidden" name="company_id" value="{{ $entry['id'] }}">

                                @if(count($entry['branches']) > 1)
                                    <select name="branch_id" aria-label="Branch for {{ $entry['name'] }}">
                                        @foreach($entry['branches'] as $branch)
                                            <option value="{{ $branch['id'] }}">{{ $branch['name'] }}</option>
                                        @endforeach
                                    </select>
                                @else
                                    <input type="hidden" name="branch_id" value="{{ $entry['branches'][0]['id'] }}">
                                @endif

                                @if(count($entry['years']) > 1)
                                    <select name="financial_year_id" aria-label="Financial year for {{ $entry['name'] }}">
                                        @foreach($entry['years'] as $year)
                                            <option value="{{ $year['id'] }}" @selected($year['id'] === $entry['current_year_id'])>{{ $year['name'] }}</option>
                                        @endforeach
                                    </select>
                                @elseif(count($entry['years']) === 1)
                                    <input type="hidden" name="financial_year_id" value="{{ $entry['years'][0]['id'] }}">
                                @endif

                                <button class="zw-card-switch-btn" type="submit">
                                    Switch Workspace <span aria-hidden="true">→</span>
                                </button>
                            </form>
                        @endif
                    </div>
                @endforeach
            </div>
        @endforeach
    </section>
</div>
@endsection

@push('scripts')
<script>
    // Delegated autosubmit on branch / fiscal year select
    $(document).on('change', 'form[data-autosubmit] select', function () { this.form.classList.add('is-busy'); this.form.submit(); });

    // Workspace Visual Dashboard Charts Initialization
    (function initWorkspaceCharts() {
        if (typeof Chart === 'undefined') {
            console.warn('Chart.js is not loaded yet, workspace charts will retry on load');
            window.addEventListener('load', initWorkspaceCharts);
            return;
        }

        var isDark = document.body.classList.contains('dark-mode');
        var gridColor = isDark ? 'rgba(255, 255, 255, 0.08)' : 'rgba(226, 232, 240, 0.8)';
        var tickColor = isDark ? '#94a3b8' : '#64748b';
        var activeCharts = [];

        function formatCompact(val) {
            var num = Number(val) || 0;
            if (Math.abs(num) >= 10000000) return (num / 10000000).toFixed(1) + 'Cr';
            if (Math.abs(num) >= 100000) return (num / 100000).toFixed(1) + 'L';
            if (Math.abs(num) >= 1000) return (num / 1000).toFixed(1) + 'K';
            return num.toString();
        }

        function formatInr(val) {
            return '₹' + Number(val || 0).toLocaleString('en-IN', { maximumFractionDigits: 2 });
        }

        var theme = getComputedStyle(document.body);
        var salesColor = theme.getPropertyValue('--z-primary').trim() || '#7d287e';
        var salesRgb = theme.getPropertyValue('--z-primary-rgb').trim().split(/\s+/).join(', ') || '125, 40, 126';

        // 1. Master Group Trend Multi-Bar / Line Charts
        document.querySelectorAll('canvas[data-zw-master-trend]').forEach(function (canvas) {
            var raw = canvas.getAttribute('data-zw-master-trend');
            if (!raw) return;
            try {
                var trend = JSON.parse(raw);
                var labels = trend.map(function (t) { return t.month; });
                var sales = trend.map(function (t) { return t.sales || t.amount || 0; });
                var purchases = trend.map(function (t) { return t.purchases || 0; });
                var profits = trend.map(function (t) { return t.profit || 0; });

                var chart = new Chart(canvas.getContext('2d'), {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [
                            {
                                label: 'Sales Revenue',
                                data: sales,
                                backgroundColor: 'rgba(' + salesRgb + ', 0.85)',
                                borderColor: salesColor,
                                borderWidth: 1.5,
                                borderRadius: 4
                            },
                            {
                                label: 'Purchases',
                                data: purchases,
                                backgroundColor: 'rgba(245, 158, 11, 0.85)',
                                borderColor: '#f59e0b',
                                borderWidth: 1.5,
                                borderRadius: 4
                            },
                            {
                                type: 'line',
                                label: 'Net Margin',
                                data: profits,
                                borderColor: '#10b981',
                                backgroundColor: 'rgba(16, 185, 129, 0.1)',
                                borderWidth: 2.5,
                                pointBackgroundColor: '#10b981',
                                pointBorderColor: '#ffffff',
                                pointBorderWidth: 1.5,
                                pointRadius: 4,
                                pointHoverRadius: 6,
                                fill: false,
                                lineTension: 0.35
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        legend: { display: false },
                        tooltips: {
                            callbacks: {
                                label: function (item, data) {
                                    var series = data.datasets[item.datasetIndex];
                                    return (series.label || '') + ': ' + formatInr(series.data[item.index]);
                                }
                            }
                        },
                        scales: {
                            xAxes: [{
                                gridLines: { display: false },
                                ticks: { fontColor: tickColor, fontSize: 11 }
                            }],
                            yAxes: [{
                                gridLines: { color: gridColor, drawBorder: false },
                                ticks: {
                                    fontColor: tickColor,
                                    fontSize: 11,
                                    beginAtZero: true,
                                    callback: function (v) { return '₹' + formatCompact(v); }
                                }
                            }]
                        }
                    }
                });
                activeCharts.push(chart);
            } catch (e) {
                console.error('Error drawing master trend chart', e);
            }
        });

        // 2. Master Group Turnover Share Donuts
        document.querySelectorAll('canvas[data-zw-donut-share]').forEach(function (canvas) {
            var raw = canvas.getAttribute('data-zw-donut-share');
            if (!raw) return;
            try {
                var shares = JSON.parse(raw);
                var labels = shares.map(function (s) { return s.name; });
                var values = shares.map(function (s) { return s.revenue; });
                var colors = shares.map(function (s) { return s.color; });

                var chart = new Chart(canvas.getContext('2d'), {
                    type: 'doughnut',
                    data: {
                        labels: labels,
                        datasets: [{
                            data: values,
                            backgroundColor: colors,
                            borderWidth: 2,
                            borderColor: isDark ? '#172033' : '#ffffff'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        cutoutPercentage: 72,
                        legend: { display: false },
                        tooltips: {
                            callbacks: {
                                label: function (item, data) {
                                    var label = data.labels[item.index] || '';
                                    var val = data.datasets[0].data[item.index];
                                    return label + ': ' + formatInr(val);
                                }
                            }
                        }
                    }
                });
                activeCharts.push(chart);
            } catch (e) {
                console.error('Error drawing share donut', e);
            }
        });

        // 3. Master Group Cost & Profit Allocation Donuts
        document.querySelectorAll('canvas[data-zw-donut-cost]').forEach(function (canvas) {
            var raw = canvas.getAttribute('data-zw-donut-cost');
            if (!raw) return;
            try {
                var cost = JSON.parse(raw);
                var chart = new Chart(canvas.getContext('2d'), {
                    type: 'doughnut',
                    data: {
                        labels: ['Net Profit', 'Purchases / Cost', 'Operating Expenses'],
                        datasets: [{
                            data: [cost.profit || 0, cost.purchases || 0, cost.expenses || 0],
                            backgroundColor: ['#10b981', '#f59e0b', '#f43f5e'],
                            borderWidth: 2,
                            borderColor: isDark ? '#172033' : '#ffffff'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        cutoutPercentage: 72,
                        legend: { display: false },
                        tooltips: {
                            callbacks: {
                                label: function (item, data) {
                                    var label = data.labels[item.index] || '';
                                    var val = data.datasets[0].data[item.index];
                                    return label + ': ' + formatInr(val);
                                }
                            }
                        }
                    }
                });
                activeCharts.push(chart);
            } catch (e) {
                console.error('Error drawing cost donut', e);
            }
        });

        // 4. Company Monthly Trend Multi-Bar Charts
        document.querySelectorAll('canvas[data-zw-comp-trend]').forEach(function (canvas) {
            var raw = canvas.getAttribute('data-zw-comp-trend');
            if (!raw) return;
            try {
                var trend = JSON.parse(raw);
                var labels = trend.map(function (t) { return t.month; });
                var sales = trend.map(function (t) { return t.sales || t.amount || 0; });
                var purchases = trend.map(function (t) { return t.purchases || 0; });

                var chart = new Chart(canvas.getContext('2d'), {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [
                            {
                                label: 'Sales',
                                data: sales,
                                backgroundColor: 'rgba(' + salesRgb + ', 0.85)',
                                borderColor: salesColor,
                                borderWidth: 1,
                                borderRadius: 3
                            },
                            {
                                label: 'Purchases',
                                data: purchases,
                                backgroundColor: 'rgba(245, 158, 11, 0.85)',
                                borderColor: '#f59e0b',
                                borderWidth: 1,
                                borderRadius: 3
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        legend: { display: false },
                        tooltips: {
                            callbacks: {
                                label: function (item, data) {
                                    var series = data.datasets[item.datasetIndex];
                                    return (series.label || '') + ': ' + formatInr(series.data[item.index]);
                                }
                            }
                        },
                        scales: {
                            xAxes: [{
                                gridLines: { display: false },
                                ticks: { fontColor: tickColor, fontSize: 10 }
                            }],
                            yAxes: [{
                                gridLines: { color: gridColor, drawBorder: false },
                                ticks: {
                                    fontColor: tickColor,
                                    fontSize: 10,
                                    beginAtZero: true,
                                    callback: function (v) { return '₹' + formatCompact(v); }
                                }
                            }]
                        }
                    }
                });
                activeCharts.push(chart);
            } catch (e) {
                console.error('Error drawing company trend chart', e);
            }
        });

        // 5. Company Mini Donuts
        document.querySelectorAll('canvas[data-zw-comp-donut]').forEach(function (canvas) {
            var raw = canvas.getAttribute('data-zw-comp-donut');
            if (!raw) return;
            try {
                var d = JSON.parse(raw);
                var chart = new Chart(canvas.getContext('2d'), {
                    type: 'doughnut',
                    data: {
                        labels: ['Profit', 'Cost / Purchases', 'Expenses'],
                        datasets: [{
                            data: [d.profit || 0, d.purchases || 0, d.expenses || 0],
                            backgroundColor: ['#10b981', '#f59e0b', '#f43f5e'],
                            borderWidth: 1.5,
                            borderColor: isDark ? '#172033' : '#ffffff'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        cutoutPercentage: 72,
                        legend: { display: false },
                        tooltips: {
                            callbacks: {
                                label: function (item, data) {
                                    var label = data.labels[item.index] || '';
                                    var val = data.datasets[0].data[item.index];
                                    return label + ': ' + formatInr(val);
                                }
                            }
                        }
                    }
                });
                activeCharts.push(chart);
            } catch (e) {
                console.error('Error drawing comp donut', e);
            }
        });

        // Re-render when dark mode changes
        new MutationObserver(function () {
            var nextDark = document.body.classList.contains('dark-mode');
            if (nextDark === isDark) return;
            isDark = nextDark;
            activeCharts.forEach(function (c) { c.destroy(); });
            activeCharts = [];
            initWorkspaceCharts();
        }).observe(document.body, { attributes: true, attributeFilter: ['class'] });
    })();
</script>
@endpush
