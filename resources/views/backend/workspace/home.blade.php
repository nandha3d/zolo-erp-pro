@extends('backend.layout.main')

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
                <span class="d-block text-muted small mt-1">Consolidated master analytics, P&L health status, and direct company context switching</span>
            </div>
            <div class="ml-auto d-flex align-items-center gap-2">
                <span>{{ $otherCount > 0 ? $otherCount.' other '.\Illuminate\Support\Str::plural('company', $otherCount) : 'Single company' }}</span>
                <a class="zw-link ml-2" href="{{ route('company.select', ['manage' => 1, 'return' => 'workspace']) }}">Manage companies</a>
            </div>
        </div>

        @php
            $colorPalette = ['#7c3aed', '#0284c7', '#059669', '#d97706', '#ec4899', '#6366f1'];
        @endphp

        @foreach($sections as $sIdx => $section)
            @php
                $groupId = $section['id'] ?? null;
                $gRollup = $groupId && isset($insights['groups'][$groupId]) ? $insights['groups'][$groupId] : null;
            @endphp

            {{-- 1. MASTER GROUP CONSOLIDATED CARD (For company groups) --}}
            @if($gRollup && $gRollup['company_count'] > 0)
                <div class="zw-group-master-card">
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

                    {{-- Executive 5-KPI Strip --}}
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
                            <span class="zw-master-kpi-value text-purple" style="color: #7c3aed;">₹{{ number_format($gRollup['total_receivables'], 2) }}</span>
                            <span class="zw-master-kpi-sub">Total dues to collect</span>
                        </div>

                        <div class="zw-master-kpi">
                            <span class="zw-master-kpi-label"><i class="fa fa-truck"></i> Supplier Payables</span>
                            <span class="zw-master-kpi-value text-amber" style="color: #d97706;">₹{{ number_format($gRollup['total_payables'], 2) }}</span>
                            <span class="zw-master-kpi-sub">Total dues to pay</span>
                        </div>
                    </div>

                    {{-- Revenue Share Distribution Bar --}}
                    <div class="zw-dist-bar-wrap">
                        <div class="zw-dist-label">
                            <span><i class="fa fa-bar-chart mr-1"></i> Revenue Distribution & Contribution by Company</span>
                            <span>Total Group Sales: ₹{{ number_format($gRollup['total_revenue'], 2) }}</span>
                        </div>
                        <div class="zw-dist-bar">
                            @foreach($section['companies'] as $cIndex => $entry)
                                @php
                                    $cInsight = $insights['companies'][$entry['id']] ?? null;
                                    $pct = $gRollup['total_revenue'] > 0 && $cInsight ? round(($cInsight['revenue'] / $gRollup['total_revenue']) * 100, 1) : 0;
                                    $barColor = $colorPalette[$cIndex % count($colorPalette)];
                                @endphp
                                @if($pct > 0)
                                    <div class="zw-dist-segment" style="width: {{ $pct }}%; background-color: {{ $barColor }};" title="{{ $entry['name'] }}: ₹{{ number_format($cInsight['revenue'] ?? 0, 2) }} ({{ $pct }}%)"></div>
                                @endif
                            @endforeach
                        </div>
                        <div class="zw-dist-legend">
                            @foreach($section['companies'] as $cIndex => $entry)
                                @php
                                    $cInsight = $insights['companies'][$entry['id']] ?? null;
                                    $pct = $gRollup['total_revenue'] > 0 && $cInsight ? round(($cInsight['revenue'] / $gRollup['total_revenue']) * 100, 1) : 0;
                                    $barColor = $colorPalette[$cIndex % count($colorPalette)];
                                    $isLoss = $cInsight['is_loss'] ?? false;
                                @endphp
                                <div class="zw-dist-item">
                                    <span class="zw-dist-dot" style="background-color: {{ $barColor }};"></span>
                                    <strong>{{ $entry['name'] }}:</strong>
                                    <span>₹{{ number_format($cInsight['revenue'] ?? 0, 2) }} ({{ $pct }}%)</span>
                                    <span class="zw-comp-status-chip {{ $isLoss ? 'is-loss' : 'is-profit' }}">
                                        {{ $isLoss ? 'Loss: -₹'.number_format(abs($cInsight['profit'] ?? 0), 2) : 'Profit: ₹'.number_format($cInsight['profit'] ?? 0, 2) }}
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif

            {{-- 2. BIG EXPANDED COMPANY INSIGHT CARDS (With in-card switcher) --}}
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
                            $maxMonth = $cInsight && count($cInsight['monthly_trend']) ? max(1, max(array_column($cInsight['monthly_trend'], 'amount'))) : 1;
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

                                {{-- 2x2 Metric Grid --}}
                                <div class="zw-card-metrics-grid">
                                    <div class="zw-card-metric">
                                        <span class="zw-card-metric-label">Net Revenue</span>
                                        <span class="zw-card-metric-val">₹{{ number_format($cInsight['revenue'], 2) }}</span>
                                        <span class="zw-card-metric-sub">{{ $cInsight['sales_count'] }} bills posted</span>
                                    </div>

                                    <div class="zw-card-metric">
                                        <span class="zw-card-metric-label">Purchases / Cost</span>
                                        <span class="zw-card-metric-val">₹{{ number_format($cInsight['purchases'], 2) }}</span>
                                        <span class="zw-card-metric-sub">{{ $cInsight['purchases_count'] }} orders/GRNs</span>
                                    </div>

                                    <div class="zw-card-metric">
                                        <span class="zw-card-metric-label">Customer Dues</span>
                                        <span class="zw-card-metric-val text-purple" style="color: #7c3aed;">₹{{ number_format($cInsight['receivables'], 2) }}</span>
                                        <span class="zw-card-metric-sub">Open receivables</span>
                                    </div>

                                    <div class="zw-card-metric">
                                        <span class="zw-card-metric-label">Supplier Dues</span>
                                        <span class="zw-card-metric-val text-amber" style="color: #d97706;">₹{{ number_format($cInsight['payables'], 2) }}</span>
                                        <span class="zw-card-metric-sub">Open payables</span>
                                    </div>
                                </div>

                                {{-- Mini 6-Month Sales Trend SVG Chart --}}
                                <div class="zw-card-trend-wrap">
                                    <div class="zw-card-trend-head">
                                        <span><i class="fa fa-line-chart"></i> 6-Month Sales Trajectory</span>
                                        <span>Recent Activity</span>
                                    </div>
                                    <div class="zw-spark-chart">
                                        @foreach($cInsight['monthly_trend'] as $mt)
                                            @php
                                                $barHeight = $maxMonth > 0 ? max(4, round(($mt['amount'] / $maxMonth) * 32)) : 4;
                                            @endphp
                                            <div class="zw-spark-col" title="{{ $mt['month'] }} ({{ $mt['year_month'] }}): ₹{{ number_format($mt['amount'], 2) }}">
                                                <div class="zw-spark-bar" style="height: {{ $barHeight }}px;"></div>
                                                <span class="zw-spark-lbl">{{ $mt['month'] }}</span>
                                            </div>
                                        @endforeach
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
    // Delegated through jQuery so changes made through bootstrap-select menus submit too.
    $(document).on('change', 'form[data-autosubmit] select', function () { this.form.classList.add('is-busy'); this.form.submit(); });
</script>
@endpush
