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
    $modules = [
        'Sales' => ['fa-shopping-cart', 'Bills, quotations and delivery challans'],
        'Purchases' => ['fa-truck', 'Purchase bills, orders and goods received'],
        'Inventory' => ['fa-cubes', 'Stock, transfers and adjustments'],
        'Accounts' => ['fa-book', 'Vouchers, receipts and payments'],
        'Manufacturing' => ['fa-industry', 'Bills of material and production'],
        'Job Work' => ['fa-random', 'Send to and receive from job workers'],
        'Projects' => ['fa-briefcase', 'Sites, installation and service'],
        'Reports' => ['fa-bar-chart', 'Trial balance and statements'],
        'Settings' => ['fa-cog', 'Branches, warehouses and financial years'],
    ];
    $otherCount = $sections->sum(fn ($section) => count($section['companies'])) - 1;
@endphp
<div class="zw ind-{{ $industry }}">
    @if(session('status'))<div class="zw-flash" role="status"><i class="fa fa-check-circle" aria-hidden="true"></i> {{ session('status') }}</div>@endif
    @if($errors->any())<div class="zw-flash is-error" role="alert">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif

    <div class="zw-hero">
        <div class="zw-identity">
            <span class="zw-mark" aria-hidden="true">@if($logo)<img src="{{ $logo }}" alt="">@else{{ $initials($name) }}@endif</span>
            <div class="zw-identity-text">
                <p class="zw-eyebrow">@if($company->group){{ $company->group->name }} <span aria-hidden="true">›</span> @endif<code>{{ $company->code }}</code></p>
                <h1>{{ $name }}</h1>
                <p class="zw-tags">
                    <span class="zw-chip">{{ $industries[$industry] ?? 'General Trading' }}@if(!empty($current['subtype'])) · {{ \App\Services\Industry\IndustryCatalog::subtypeLabel($current['subtype']) }}@endif</span>
                    @if($company->trade_name && $company->legal_name !== $company->trade_name)<span class="zw-legal">{{ $company->legal_name }}</span>@endif
                </p>
            </div>
        </div>
        <form class="zw-context" method="post" action="/workspace/context" aria-label="Active branch and financial year" data-autosubmit>
            @csrf
            <input type="hidden" name="company_id" value="{{ $company->id }}">
            <span class="zw-context-title">Active branch and financial year</span>
            <div class="zw-context-fields">
                @if($branches->count() > 1)
                    <label><span>Branch</span><select name="branch_id">@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected($branch->id === $context->branchId)>{{ $branch->name }}</option>@endforeach</select></label>
                @else
                    <input type="hidden" name="branch_id" value="{{ $context->branchId }}">
                    <div class="zw-static"><span>Branch</span><strong>{{ optional($branches->first())->name }}</strong></div>
                @endif
                <label><span>Financial year</span><select name="financial_year_id">@foreach($years as $year)<option value="{{ $year->id }}" @selected($year->id === $context->financialYearId)>{{ $year->name }}{{ $year->status !== 'open' ? ' · '.$year->status : '' }}</option>@endforeach</select></label>
            </div>
            <noscript><button class="zw-btn">Apply</button></noscript>
        </form>
    </div>

    <section class="zw-section" aria-labelledby="zw-modules">
        <div class="zw-section-head"><h2 id="zw-modules">Workspaces</h2><span>{{ $name }}</span></div>
        <div class="zw-modules">
            @forelse($navigation as $item)
                @php
                    [$icon, $hint] = $modules[$item['label']] ?? ['fa-th-large', 'Open workspace'];
                @endphp
                <a class="zw-module" href="{{ $item['url'] }}">
                    <span class="zw-module-icon" aria-hidden="true"><i class="fa {{ $icon }}"></i></span>
                    <span class="zw-module-text"><strong>{{ $item['label'] }}</strong><small>{{ $hint }}</small></span>
                    <span class="zw-module-go" aria-hidden="true">→</span>
                </a>
            @empty
                <p class="zw-empty">Ask your company administrator to enable the workspaces your role needs.</p>
            @endforelse
        </div>
    </section>

    <section class="zw-section zw-section-insights" aria-labelledby="zw-companies">
        <div class="zw-section-head">
            <div>
                <h2 id="zw-companies">Switch company & Master Group Insights</h2>
                <span class="d-block text-muted small mt-1">Consolidated master analytics, interactive charts, and direct company context switching</span>
            </div>
            <div class="ml-auto d-flex align-items-center gap-2">
                <span>{{ $otherCount > 0 ? $otherCount.' other '.\Illuminate\Support\Str::plural('company', $otherCount) : 'Single company' }}</span>
                <a class="zw-link ml-2" href="{{ route('company.select', ['manage' => 1, 'return' => 'workspace']) }}">Manage companies</a>
            </div>
        </div>

        @php
            $colorPalette = ['#7c3aed', '#0284c7', '#059669', '#d97706', '#ec4899', '#6366f1', '#14b8a6', '#f43f5e'];
        @endphp

        @foreach($sections as $sIdx => $section)
            @php
                $groupId = $section['id'] ?? null;
                $gRollup = $groupId && isset($insights['groups'][$groupId]) ? $insights['groups'][$groupId] : null;

                // Build company shares list for pie charts
                $companyShares = [];
                if ($gRollup) {
                    foreach ($section['companies'] as $cIdx => $entry) {
                        $cId = $entry['id'];
                        $cData = $insights['companies'][$cId] ?? null;
                        $rev = (float) ($cData['revenue'] ?? 0);
                        $profit = (float) ($cData['profit'] ?? 0);
                        $color = $colorPalette[$cIdx % count($colorPalette)];
                        $pct = $gRollup['total_revenue'] > 0 ? round(($rev / $gRollup['total_revenue']) * 100, 1) : 0;
                        $companyShares[] = [
                            'id' => $cId,
                            'name' => $entry['name'],
                            'code' => $entry['code'],
                            'revenue' => $rev,
                            'profit' => $profit,
                            'color' => $color,
                            'percent' => $pct,
                            'is_loss' => $cData['is_loss'] ?? false,
                        ];
                    }
                }
            @endphp

            {{-- 1. MASTER GROUP CONSOLIDATED CARD (With Rich Visual Charts) --}}
            @if($gRollup && $gRollup['company_count'] > 0)
                <div class="zw-group-master-card">
                    {{-- Master Header --}}
                    <div class="zw-master-head">
                        <div class="zw-master-title-wrap">
                            <div class="zw-master-icon"><i class="fa fa-cubes"></i></div>
                            <div>
                                <h3 class="zw-master-title">
                                    {{ $gRollup['name'] }}
                                    @if($gRollup['code'])<code>{{ $gRollup['code'] }}</code>@endif
                                </h3>
                                <div class="zw-master-subtitle">Master Group Rollup · Consolidated across {{ $gRollup['company_count'] }} member {{ \Illuminate\Support\Str::plural('company', $gRollup['company_count']) }}</div>
                            </div>
                        </div>

                        <div>
                            @if($gRollup['loss_count'] === 0)
                                <span class="zw-master-health-pill is-healthy">
                                    <i class="fa fa-check-circle"></i> All Companies Profitable ({{ $gRollup['profitable_count'] }}/{{ $gRollup['company_count'] }})
                                </span>
                            @else
                                <span class="zw-master-health-pill is-loss">
                                    <i class="fa fa-exclamation-triangle"></i> {{ $gRollup['loss_count'] }} {{ \Illuminate\Support\Str::plural('Company', $gRollup['loss_count']) }} in Net Loss
                                </span>
                            @endif
                        </div>
                    </div>

                    {{-- Loss Warning Alert if any company is operating in loss --}}
                    @if($gRollup['loss_count'] > 0)
                        <div class="zw-master-alert-banner">
                            <div class="zw-alert-icon"><i class="fa fa-exclamation-triangle"></i></div>
                            <div>
                                <strong>Operating Loss Alert:</strong> The following member {{ \Illuminate\Support\Str::plural('company', $gRollup['loss_count']) }} are operating at a net loss:
                                @foreach($gRollup['loss_companies'] as $lc)
                                    <span class="zw-alert-chip">
                                        {{ $lc['name'] }} (<code>{{ $lc['code'] }}</code>): -₹{{ number_format($lc['loss'], 2) }} ({{ $lc['margin_percent'] }}% margin)
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- 2. VISUAL CHARTS SUITE (Monthly Trajectory Graph & Donut Visuals) --}}
                    <div class="zw-master-visual-suite">
                        {{-- Main Column: Group Monthly Performance Graph --}}
                        <div class="zw-visual-card zw-visual-main">
                            <div class="zw-visual-head">
                                <div>
                                    <h4 class="zw-visual-title"><i class="fa fa-line-chart"></i> Group Monthly Performance & Cash Trends</h4>
                                    <span class="zw-visual-subtitle">6-Month trajectory of Sales Revenue vs Purchases vs Net Margin</span>
                                </div>
                                <div class="zw-chart-legend-pills">
                                    <span class="zw-legend-pill is-sales"><span class="zw-legend-dot"></span> Sales</span>
                                    <span class="zw-legend-pill is-purchases"><span class="zw-legend-dot"></span> Purchases</span>
                                    <span class="zw-legend-pill is-profit"><span class="zw-legend-dot"></span> Net Margin</span>
                                </div>
                            </div>
                            <div class="zw-chart-stage">
                                <canvas id="masterTrendChart_{{ $groupId }}" class="zw-chart-canvas"
                                    data-zw-master-trend="{{ json_encode($gRollup['monthly_trend']) }}"
                                    height="220" role="img" aria-label="Group monthly performance chart"></canvas>
                            </div>
                        </div>

                        {{-- Side Column: Turnover Share & Cost Breakdown Donuts --}}
                        <div class="zw-visual-side">
                            {{-- Donut A: Revenue Contribution by Company --}}
                            <div class="zw-visual-card zw-visual-donut-card">
                                <div class="zw-visual-head">
                                    <h4 class="zw-visual-title"><i class="fa fa-pie-chart"></i> Revenue Distribution & Contribution</h4>
                                    <span class="zw-visual-badge">Total Group Turnover: ₹{{ number_format($gRollup['total_revenue'], 2) }}</span>
                                </div>
                                <div class="zw-donut-layout">
                                    <div class="zw-donut-stage">
                                        <canvas id="masterShareDonut_{{ $groupId }}" class="zw-donut-canvas"
                                            data-zw-donut-share="{{ json_encode($companyShares) }}"
                                            height="140" role="img" aria-label="Turnover distribution pie chart"></canvas>
                                    </div>
                                    <div class="zw-donut-legend-list">
                                        @foreach($companyShares as $cs)
                                            <div class="zw-donut-legend-row" title="{{ $cs['name'] }}: ₹{{ number_format($cs['revenue'], 2) }} ({{ $cs['percent'] }}%)">
                                                <span class="zw-donut-legend-dot" style="background-color: {{ $cs['color'] }};"></span>
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
                                    <h4 class="zw-visual-title"><i class="fa fa-adjust"></i> P&L Financial Cost Distribution</h4>
                                    <span class="zw-visual-badge">Profit Margin: {{ $gRollup['margin_percent'] }}%</span>
                                </div>
                                <div class="zw-donut-layout">
                                    <div class="zw-donut-stage">
                                        <canvas id="masterCostDonut_{{ $groupId }}" class="zw-donut-canvas"
                                            data-zw-donut-cost="{{ json_encode([
                                                'profit' => max(0, $gRollup['net_profit']),
                                                'purchases' => $gRollup['total_purchases'],
                                                'expenses' => $gRollup['total_expenses'],
                                            ]) }}"
                                            height="140" role="img" aria-label="Cost and profit allocation donut"></canvas>
                                    </div>
                                    <div class="zw-donut-legend-list">
                                        <div class="zw-donut-legend-row">
                                            <span class="zw-legend-profit zw-donut-legend-dot"></span>
                                            <div class="zw-donut-legend-info">
                                                <strong>Net Profit</strong>
                                                <span>₹{{ number_format(max(0, $gRollup['net_profit']), 2) }}</span>
                                            </div>
                                        </div>
                                        <div class="zw-donut-legend-row">
                                            <span class="zw-legend-breakeven zw-donut-legend-dot"></span>
                                            <div class="zw-donut-legend-info">
                                                <strong>Purchases / COGS</strong>
                                                <span>₹{{ number_format($gRollup['total_purchases'], 2) }}</span>
                                            </div>
                                        </div>
                                        <div class="zw-donut-legend-row">
                                            <span class="zw-legend-loss zw-donut-legend-dot"></span>
                                            <div class="zw-donut-legend-info">
                                                <strong>Operating Expenses</strong>
                                                <span>₹{{ number_format($gRollup['total_expenses'], 2) }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- 3. WORKING CAPITAL & DUES EXPOSURE BALANCE BAR --}}
                    @php
                        $totDues = $gRollup['total_receivables'] + $gRollup['total_payables'];
                        $recPct = $totDues > 0 ? round(($gRollup['total_receivables'] / $totDues) * 100, 1) : 50;
                        $payPct = $totDues > 0 ? (100 - $recPct) : 50;
                        $netExposure = $gRollup['total_receivables'] - $gRollup['total_payables'];
                    @endphp
                    <div class="zw-dues-meter-card">
                        <div class="zw-dues-meter-head">
                            <div class="d-flex align-items-center gap-2">
                                <i class="fa fa-balance-scale text-primary"></i>
                                <strong>Working Capital & Dues Balance:</strong>
                                <span class="text-muted">Customer Receivables vs Supplier Payables</span>
                            </div>
                            <div>
                                <span class="zw-exposure-tag {{ $netExposure >= 0 ? 'is-positive' : 'is-negative' }}">
                                    {{ $netExposure >= 0 ? 'Net Surplus: +₹'.number_format($netExposure, 2) : 'Net Payable: -₹'.number_format(abs($netExposure), 2) }}
                                </span>
                            </div>
                        </div>
                        <div class="zw-dues-bar-track">
                            <div class="zw-dues-segment is-receivable" style="width: {{ $recPct }}%;" title="Receivables: ₹{{ number_format($gRollup['total_receivables'], 2) }} ({{ $recPct }}%)">
                                <span>Receivables: ₹{{ number_format($gRollup['total_receivables'], 2) }}</span>
                            </div>
                            <div class="zw-dues-segment is-payable" style="width: {{ $payPct }}%;" title="Payables: ₹{{ number_format($gRollup['total_payables'], 2) }} ({{ $payPct }}%)">
                                <span>Payables: ₹{{ number_format($gRollup['total_payables'], 2) }}</span>
                            </div>
                        </div>
                    </div>

                    {{-- 4. EXECUTIVE 5-KPI STRIP (Compact & Intuitive) --}}
                    <div class="zw-master-kpis">
                        <div class="zw-master-kpi">
                            <span class="zw-master-kpi-label"><i class="fa fa-line-chart"></i> Consolidated Revenue</span>
                            <span class="zw-master-kpi-value">₹{{ number_format($gRollup['total_revenue'], 2) }}</span>
                            <span class="zw-master-kpi-sub">{{ $gRollup['total_sales_count'] }} total sales bills</span>
                        </div>

                        <div class="zw-master-kpi {{ $gRollup['is_loss'] ? 'is-loss' : 'is-profit' }}">
                            <span class="zw-master-kpi-label"><i class="fa fa-pie-chart"></i> Consolidated Net Profit</span>
                            <span class="zw-master-kpi-value">
                                {{ $gRollup['is_loss'] ? '-₹'.number_format(abs($gRollup['net_profit']), 2) : '₹'.number_format($gRollup['net_profit'], 2) }}
                            </span>
                            <span class="zw-master-kpi-sub font-weight-bold">
                                {{ $gRollup['is_loss'] ? 'Net Loss' : 'Net Margin' }}: {{ $gRollup['margin_percent'] }}%
                            </span>
                        </div>

                        <div class="zw-master-kpi">
                            <span class="zw-master-kpi-label"><i class="fa fa-shopping-bag"></i> Total Purchases</span>
                            <span class="zw-master-kpi-value">₹{{ number_format($gRollup['total_purchases'], 2) }}</span>
                            <span class="zw-master-kpi-sub">{{ $gRollup['total_purchases_count'] }} purchase orders/bills</span>
                        </div>

                        <div class="zw-master-kpi">
                            <span class="zw-master-kpi-label"><i class="fa fa-credit-card"></i> Customer Receivables</span>
                            <span class="zw-text-receivable zw-master-kpi-value text-purple">₹{{ number_format($gRollup['total_receivables'], 2) }}</span>
                            <span class="zw-master-kpi-sub">Total dues to collect</span>
                        </div>

                        <div class="zw-master-kpi">
                            <span class="zw-master-kpi-label"><i class="fa fa-truck"></i> Supplier Payables</span>
                            <span class="zw-text-payable zw-master-kpi-value text-amber">₹{{ number_format($gRollup['total_payables'], 2) }}</span>
                            <span class="zw-master-kpi-sub">Total dues to pay</span>
                        </div>
                    </div>
                </div>
            @endif

            {{-- 2. BIG EXPANDED COMPANY INSIGHT CARDS (With Dedicated Monthly Performance Charts) --}}
            <div class="zw-group mb-4">
                <p class="zw-group-name">
                    <i class="fa fa-building-o mr-1"></i>
                    {{ $section['name'] }}@if($section['code']) · <code>{{ $section['code'] }}</code>@endif
                    <span class="text-muted ml-2 font-weight-normal">({{ count($section['companies']) }} {{ \Illuminate\Support\Str::plural('company', count($section['companies'])) }})</span>
                </p>

                <div class="zw-companies-grid-expanded">
                    @foreach($section['companies'] as $entry)
                        @php
                            $isCurrent = $entry['id'] === $company->id;
                            $cInsight = $insights['companies'][$entry['id']] ?? null;
                            $isLoss = $cInsight['is_loss'] ?? false;
                        @endphp

                        <div class="zw-company-card-expanded ind-{{ $entry['industry'] }} {{ $isCurrent ? 'is-current' : '' }}">
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
                                        <span class="zw-card-active-pill"><i class="fa fa-dot-circle-o"></i> Current company</span>
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
                                        <span><i class="fa fa-line-chart"></i> 6-Month Sales Trajectory & Activity</span>
                                        <div class="zw-comp-chart-legend">
                                            <span class="zw-mini-dot is-sales"></span> Sales
                                            <span class="zw-mini-dot is-purchases ml-2"></span> Purchases
                                        </div>
                                    </div>
                                    <div class="zw-comp-chart-canvas-wrap">
                                        <canvas id="compChart_{{ $entry['id'] }}" class="zw-comp-canvas"
                                            data-zw-comp-trend="{{ json_encode($cInsight['monthly_trend']) }}"
                                            height="130" role="img" aria-label="Company monthly sales chart"></canvas>
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
                                                height="80" role="img" aria-label="Company financial distribution donut"></canvas>
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
                                            <span class="zw-text-receivable zw-card-metric-val text-purple">₹{{ number_format($cInsight['receivables'], 2) }}</span>
                                            <span class="zw-card-metric-sub">Receivables</span>
                                        </div>

                                        <div class="zw-card-metric">
                                            <span class="zw-card-metric-label">Supplier Dues</span>
                                            <span class="zw-text-payable zw-card-metric-val text-amber">₹{{ number_format($cInsight['payables'], 2) }}</span>
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
                                        Switch <span aria-hidden="true">→</span>
                                    </button>
                                </form>
                            @endif
                        </div>
                    @endforeach
                </div>
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
        var salesColor = theme.getPropertyValue('--z-primary').trim();
        var salesRgb = theme.getPropertyValue('--z-primary-rgb').trim().split(/\s+/).join(', ');

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
                                backgroundColor: 'rgba(' + salesRgb + ', 0.8)',
                                borderColor: salesColor,
                                borderWidth: 1.5
                            },
                            {
                                label: 'Purchases',
                                data: purchases,
                                backgroundColor: 'rgba(245, 158, 11, 0.8)',
                                borderColor: '#f59e0b',
                                borderWidth: 1.5
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
                                fill: false,
                                lineTension: 0.2
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
                        cutoutPercentage: 68,
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
                        cutoutPercentage: 68,
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
                                borderWidth: 1
                            },
                            {
                                label: 'Purchases',
                                data: purchases,
                                backgroundColor: 'rgba(245, 158, 11, 0.85)',
                                borderColor: '#f59e0b',
                                borderWidth: 1
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
