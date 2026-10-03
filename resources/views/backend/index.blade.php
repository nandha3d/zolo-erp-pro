@extends('backend.layout.main')
@section('content')

@push('css')
<style>
/* Compact Executive Command Center Overrides */
.dashboard-counts {
  padding: 0 !important;
}
.dashboard-counts .count-number,
.compact-kpi .count-number,
.zolo-kpi-value .count-number {
  font-size: 1.45rem !important;
  font-weight: 700 !important;
  line-height: 1.2 !important;
  font-variant-numeric: tabular-nums;
  color: var(--neo-text-primary) !important;
}
.zolo-kpi-card {
  padding: 13px 15px !important;
  border-radius: 12px !important;
  background: var(--neo-surface-card) !important;
  border: 1px solid var(--neo-border) !important;
  box-shadow: var(--neo-shadow-xs) !important;
  display: flex !important;
  flex-direction: column !important;
  justify-content: space-between !important;
  height: 100% !important;
  transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1) !important;
}
.zolo-kpi-card:hover {
  transform: translateY(-2px) !important;
  box-shadow: var(--neo-shadow-sm) !important;
  border-color: var(--neo-border-focus) !important;
}
.zolo-kpi-header {
  display: flex !important;
  align-items: center !important;
  justify-content: space-between !important;
  margin-bottom: 8px !important;
}
.zolo-kpi-icon {
  width: 34px !important;
  height: 34px !important;
  min-width: 34px !important;
  border-radius: 8px !important;
  display: flex !important;
  align-items: center !important;
  justify-content: center !important;
  font-size: 16px !important;
}
.zolo-kpi-badge {
  font-size: 10.5px !important;
  font-weight: 600 !important;
  padding: 2px 7px !important;
  border-radius: 12px !important;
  display: inline-flex !important;
  align-items: center !important;
  gap: 3px !important;
}
.zolo-kpi-body {
  display: flex !important;
  flex-direction: column !important;
}
.zolo-kpi-value {
  font-size: 1.45rem !important;
  font-weight: 750 !important;
  font-family: var(--neo-font) !important;
  color: var(--neo-text-primary) !important;
  line-height: 1.2 !important;
  letter-spacing: -0.02em !important;
  margin-bottom: 2px !important;
  display: flex !important;
  align-items: baseline !important;
  gap: 2px !important;
}
.zolo-kpi-currency {
  font-size: 0.95rem !important;
  font-weight: 600 !important;
  opacity: 0.8 !important;
  margin-right: 1px !important;
}
.zolo-kpi-label {
  font-size: 11px !important;
  font-weight: 600 !important;
  color: var(--neo-text-secondary) !important;
  display: flex !important;
  align-items: center !important;
  gap: 5px !important;
  text-transform: uppercase !important;
  letter-spacing: 0.04em !important;
}
.zolo-kpi-subtext {
  font-size: 11px !important;
  color: var(--neo-text-muted) !important;
  margin-top: 4px !important;
  display: flex !important;
  align-items: center !important;
  justify-content: space-between !important;
}

/* Color Accents */
.kpi-purple .zolo-kpi-icon { background: rgba(139, 92, 246, 0.12); color: #8b5cf6; }
.kpi-purple .zolo-kpi-badge { background: rgba(139, 92, 246, 0.12); color: #8b5cf6; }

.kpi-blue .zolo-kpi-icon { background: rgba(59, 130, 246, 0.12); color: #3b82f6; }
.kpi-blue .zolo-kpi-badge { background: rgba(59, 130, 246, 0.12); color: #3b82f6; }

.kpi-cyan .zolo-kpi-icon { background: rgba(6, 182, 212, 0.12); color: #06b6d4; }
.kpi-cyan .zolo-kpi-badge { background: rgba(6, 182, 212, 0.12); color: #06b6d4; }

.kpi-emerald .zolo-kpi-icon { background: rgba(16, 185, 129, 0.12); color: #10b981; }
.kpi-emerald .zolo-kpi-badge { background: rgba(16, 185, 129, 0.12); color: #10b981; }

.kpi-orange .zolo-kpi-icon { background: rgba(249, 115, 22, 0.12); color: #f97316; }
.kpi-orange .zolo-kpi-badge { background: rgba(249, 115, 22, 0.12); color: #f97316; }

.kpi-rose .zolo-kpi-icon { background: rgba(244, 63, 94, 0.12); color: #f43f5e; }
.kpi-rose .zolo-kpi-badge { background: rgba(244, 63, 94, 0.12); color: #f43f5e; }

.kpi-amber .zolo-kpi-icon { background: rgba(245, 158, 11, 0.12); color: #f59e0b; }
.kpi-amber .zolo-kpi-badge { background: rgba(245, 158, 11, 0.12); color: #f59e0b; }

.kpi-teal .zolo-kpi-icon { background: rgba(20, 184, 166, 0.12); color: #14b8a6; }
.kpi-teal .zolo-kpi-badge { background: rgba(20, 184, 166, 0.12); color: #14b8a6; }

/* Constrained Chart Canvas Heights */
.zolo-chart-box {
  position: relative;
  height: 250px !important;
  max-height: 250px !important;
  width: 100%;
}
.zolo-donut-box {
  position: relative;
  height: 220px !important;
  max-height: 220px !important;
  width: 100%;
}
.zolo-yearly-box {
  position: relative;
  height: 220px !important;
  max-height: 220px !important;
  width: 100%;
}

/* Quick Action Toolbar */
.zolo-quick-actions-bar {
  display: flex;
  align-items: center;
  gap: 6px;
  flex-wrap: wrap;
  margin-top: 10px;
}
.zolo-quick-btn {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 5px 12px !important;
  border-radius: 8px !important;
  font-size: 12px !important;
  font-weight: 600 !important;
  border: 1px solid var(--neo-border) !important;
  background: var(--neo-surface-card) !important;
  color: var(--neo-text-primary) !important;
  text-decoration: none !important;
  transition: all 0.2s ease !important;
}
.zolo-quick-btn:hover {
  background: var(--neo-primary-light) !important;
  color: var(--neo-primary) !important;
  border-color: var(--neo-primary) !important;
  transform: translateY(-1px);
}
.zolo-quick-btn.primary {
  background: var(--neo-primary-gradient) !important;
  color: #ffffff !important;
  border: none !important;
  box-shadow: var(--neo-shadow-sm) !important;
}
.zolo-quick-btn.primary:hover {
  box-shadow: var(--neo-shadow-glow) !important;
  color: #ffffff !important;
}

/* Addon Pulse Cards */
.zolo-addon-pulse-card {
  background: var(--neo-surface-card);
  border: 1px solid var(--neo-border);
  border-radius: var(--neo-radius-md);
  padding: 10px 14px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  transition: all 0.2s ease;
  height: 100%;
}
.zolo-addon-pulse-card:hover {
  border-color: var(--neo-primary);
  transform: translateY(-1px);
}
.zolo-pulse-indicator {
  display: inline-block;
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: #10b981;
  box-shadow: 0 0 6px #10b981;
}

/* Donut legend pills */
.donut-legend-pill {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  font-size: 11.5px;
  font-weight: 600;
  color: var(--neo-text-secondary);
}
.donut-legend-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
}
</style>
@endpush

<x-success-message key="message" />
<x-error-message key="not_permitted" />

@php
    $general_setting = $general_setting ?? \App\Models\GeneralSetting::latest()->first();
    if (!isset($role_has_permissions_list) || is_null($role_has_permissions_list)) {
        if (Auth::check() && Auth::user()->role_id > 2) {
            $role_has_permissions_list = DB::table('permissions')
                ->join('role_has_permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
                ->where('role_id', Auth::user()->role_id)
                ->select('permissions.name')
                ->get();
        } else {
            $role_has_permissions_list = collect([
                (object)['name' => 'revenue_profit_summary'],
                (object)['name' => 'cash_flow'],
                (object)['name' => 'monthly_summary'],
                (object)['name' => 'yearly_report']
            ]);
        }
    }

    if ($general_setting && $general_setting->theme == 'default.css') {
        $color = '#733686';
        $color_rgba = 'rgba(115, 54, 134, 0.8)';
    } elseif ($general_setting && $general_setting->theme == 'green.css') {
        $color = '#2ecc71';
        $color_rgba = 'rgba(46, 204, 113, 0.8)';
    } elseif ($general_setting && $general_setting->theme == 'blue.css') {
        $color = '#3498db';
        $color_rgba = 'rgba(52, 152, 219, 0.8)';
    } else {
        $color = '#34495e';
        $color_rgba = 'rgba(52, 73, 94, 0.8)';
    }
    $lims_warehouse_list = App\Models\Warehouse::where('is_active', true)->get();
@endphp

<div class="row">
    <div class="container-fluid">
        @if (!config('database.connections.saleprosaas_landlord') && \Auth::user()->role_id <= 2)
            @if (isset($versionUpgradeData['alert_version_upgrade_enable']) && $versionUpgradeData['alert_version_upgrade_enable'] == true)
                <div id="alertSection" class="zolo-banner-card alert alert-dismissible fade show mb-3" role="alert">
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
        @endif

        <!-- Executive Command Header & Controls -->
        <div class="col-md-12 mt-2 mb-3">
            <div class="zolo-dashboard-header">
                <div class="d-flex align-items-center justify-content-between flex-wrap">
                    <div class="zolo-greeting">
                        <h2 class="zolo-greeting-title">Welcome back, <span class="zolo-accent-name">{{ Auth::user()->name }}</span> 👋</h2>
                        <p class="zolo-greeting-subtitle">zoloERP &bull; Commercial, Financial &amp; Operational Command Center</p>
                    </div>

                    @php
                        $revenue_profit_summary = $role_has_permissions_list
                            ->where('name', 'revenue_profit_summary')
                            ->first();
                    @endphp
                    @if ($revenue_profit_summary)
                    <div class="zolo-filter-bar d-flex align-items-center flex-wrap mt-2 mt-md-0">
                        @if (\Auth::user()->role_id <= 2)
                        <div class="zolo-warehouse-picker mr-2">
                            <i class="dripicons-location text-primary mr-1"></i>
                            <select name="warehouse_id" class="selectpicker" id="warehouse_btn" data-live-search="true" data-live-search-style="begins">
                                <option value="0">{{ __('db.All Warehouse') }}</option>
                                @foreach ($lims_warehouse_list as $warehouse)
                                    <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        @endif

                        <div class="dropdown">
                            <button type="button" class="btn btn-outline-secondary dropdown-toggle zolo-date-filter-btn" data-toggle="dropdown">
                                <i class="dripicons-calendar mr-1"></i> {{ __('db.Date Range') }}
                            </button>
                            <div class="dropdown-menu dropdown-menu-right zolo-dropdown-menu">
                                <button class="dropdown-item date-btn" data-start_date="{{ date('Y-m-d') }}" data-end_date="{{ date('Y-m-d') }}">{{ __('db.Today') }}</button>
                                <button class="dropdown-item date-btn" data-start_date="{{ date('Y-m-d', strtotime(' -7 day')) }}" data-end_date="{{ date('Y-m-d') }}">{{ __('db.Last 7 Days') }}</button>
                                <button class="dropdown-item date-btn active" data-start_date="{{ date('Y') . '-' . date('m') . '-' . '01' }}" data-end_date="{{ date('Y-m-d') }}">{{ __('db.This Month') }}</button>
                                <button class="dropdown-item date-btn" data-start_date="{{ date('Y') . '-01' . '-01' }}" data-end_date="{{ date('Y') . '-12' . '-31' }}">{{ __('db.This Year') }}</button>
                            </div>
                        </div>
                    </div>
                    @endif
                </div>

                <!-- Executive Quick Action Bar -->
                <div class="zolo-quick-actions-bar pt-2 mt-2 border-top border-secondary-subtle">
                    <span class="text-muted mr-2" style="font-size: 11.5px; font-weight: 600;"><i class="dripicons-broadcast mr-1"></i>QUICK ACTIONS:</span>
                    <a href="{{ route('sale.pos') }}" class="zolo-quick-btn primary">
                        <i class="dripicons-shopping-bag"></i> POS Terminal
                    </a>
                    <a href="{{ route('sales.create') }}" class="zolo-quick-btn">
                        <i class="dripicons-plus"></i> New Sale
                    </a>
                    <a href="{{ route('purchases.create') }}" class="zolo-quick-btn">
                        <i class="dripicons-cart"></i> New Purchase
                    </a>
                    <a href="{{ route('expenses.index') }}" class="zolo-quick-btn">
                        <i class="dripicons-wallet"></i> Add Expense
                    </a>
                    @if (Route::has('accounting.coa'))
                    <a href="{{ route('accounting.coa') }}" class="zolo-quick-btn">
                        <i class="dripicons-list"></i> Chart of Accounts
                    </a>
                    @endif
                    @if (Route::has('accounting.general-ledger'))
                    <a href="{{ route('accounting.general-ledger') }}" class="zolo-quick-btn">
                        <i class="dripicons-document-edit"></i> General Ledger
                    </a>
                    @endif
                    <a href="{{ url('products') }}" class="zolo-quick-btn">
                        <i class="dripicons-stack"></i> Stock ({{ $total_products ?? 0 }})
                    </a>
                </div>
            </div>

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
                @elseif(Auth::user()->role_id <= 2)
                    @php
                        $cooked = DB::table('sales')
                            ->where('sale_status', 6)
                            ->where('sales.created_at', '>=', now()->subDay())
                            ->count();
                    @endphp
                @endif
                <a href="{{ route('kitchen.dashboard') }}">
                    <div class="alert alert-warning alert-dismissible text-center mb-2">
                        <strong>{{ $cooked }} {{ __('db.Orders to serve') }}</strong>
                    </div>
                </a>
            @endif
        </div>
    </div>
</div>

<!-- Executive KPI Strip (8 Core Business Indicators) -->
<section class="dashboard-counts pt-0">
    <div class="container-fluid">
        @if ($revenue_profit_summary)
        <div class="row">
            <!-- KPI 1: Net Revenue -->
            <div class="col-xl-3 col-lg-3 col-md-6 col-sm-6 mb-3">
                <div class="zolo-kpi-card kpi-purple">
                    <div class="zolo-kpi-header">
                        <div class="zolo-kpi-icon">
                            <i class="dripicons-graph-bar"></i>
                        </div>
                        <span class="zolo-kpi-badge">
                            <i class="dripicons-arrow-thin-up"></i> Inflow
                        </span>
                    </div>
                    <div class="zolo-kpi-body">
                        <div class="zolo-kpi-value">
                            <span class="zolo-kpi-currency">{{ $currency_symbol }}</span>
                            <span class="count-number revenue-data">{{ number_format((float) $revenue, $general_setting->decimal, '.', '') }}</span>
                        </div>
                        <div class="zolo-kpi-label">
                            <span>{{ __('db.revenue') }}</span>
                            <x-info title="(grand_total - shipping_cost) - Return + income = Revenue" type="info" />
                        </div>
                        <div class="zolo-kpi-subtext">
                            <span>Sale Return:</span>
                            <span class="text-danger font-weight-bold">{{ $currency_symbol }}<span class="return-data">{{ number_format((float) $return, $general_setting->decimal, '.', '') }}</span></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- KPI 2: Net Profit -->
            <div class="col-xl-3 col-lg-3 col-md-6 col-sm-6 mb-3">
                <div class="zolo-kpi-card kpi-blue">
                    <div class="zolo-kpi-header">
                        <div class="zolo-kpi-icon">
                            <i class="dripicons-trophy"></i>
                        </div>
                        <span class="zolo-kpi-badge">
                            <i class="dripicons-star"></i> Net Margin
                        </span>
                    </div>
                    <div class="zolo-kpi-body">
                        <div class="zolo-kpi-value">
                            <span class="zolo-kpi-currency">{{ $currency_symbol }}</span>
                            <span class="count-number profit-data">{{ number_format((float) $profit, $general_setting->decimal, '.', '') }}</span>
                        </div>
                        <div class="zolo-kpi-label">
                            <span>{{ __('db.profit') }}</span>
                            <x-info title="Revenue + Purchase Return - Product Cost (COGS) - Expense" type="info" />
                        </div>
                        <div class="zolo-kpi-subtext">
                            <span>Purchase Return:</span>
                            <span class="text-success font-weight-bold">{{ $currency_symbol }}<span class="purchase_return-data">{{ number_format((float) $purchase_return, $general_setting->decimal, '.', '') }}</span></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- KPI 3: Gross Sales Volume -->
            <div class="col-xl-3 col-lg-3 col-md-6 col-sm-6 mb-3">
                <div class="zolo-kpi-card kpi-cyan">
                    <div class="zolo-kpi-header">
                        <div class="zolo-kpi-icon">
                            <i class="dripicons-cart"></i>
                        </div>
                        <span class="zolo-kpi-badge">
                            <i class="dripicons-tag"></i> Sales Volume
                        </span>
                    </div>
                    <div class="zolo-kpi-body">
                        <div class="zolo-kpi-value">
                            <span class="zolo-kpi-currency">{{ $currency_symbol }}</span>
                            <span class="count-number total_sale-data">{{ number_format((float) $total_sale, $general_setting->decimal, '.', '') }}</span>
                        </div>
                        <div class="zolo-kpi-label">
                            <span>{{ __('db.Sale') }} Total</span>
                            <x-info title="Total gross sales value before deductions" type="info" />
                        </div>
                        <div class="zolo-kpi-subtext">
                            <span>Customers:</span>
                            <span class="font-weight-bold">{{ $total_customers ?? 0 }} Active</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- KPI 4: Total Purchases (COGS) -->
            <div class="col-xl-3 col-lg-3 col-md-6 col-sm-6 mb-3">
                <div class="zolo-kpi-card kpi-orange">
                    <div class="zolo-kpi-header">
                        <div class="zolo-kpi-icon">
                            <i class="dripicons-archive"></i>
                        </div>
                        <span class="zolo-kpi-badge">
                            <i class="dripicons-arrow-thin-down"></i> Acquisition
                        </span>
                    </div>
                    <div class="zolo-kpi-body">
                        <div class="zolo-kpi-value">
                            <span class="zolo-kpi-currency">{{ $currency_symbol }}</span>
                            <span class="count-number total_purchase-data">{{ number_format((float) $purchase, $general_setting->decimal, '.', '') }}</span>
                        </div>
                        <div class="zolo-kpi-label">
                            <span>{{ __('db.Purchase') }} Total</span>
                            <x-info title="Total merchandise purchase cost" type="info" />
                        </div>
                        <div class="zolo-kpi-subtext">
                            <span>Suppliers:</span>
                            <span class="font-weight-bold">{{ $total_suppliers ?? 0 }} Vendors</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- KPI 5: Operating Expenses -->
            <div class="col-xl-3 col-lg-3 col-md-6 col-sm-6 mb-3">
                <div class="zolo-kpi-card kpi-rose">
                    <div class="zolo-kpi-header">
                        <div class="zolo-kpi-icon">
                            <i class="dripicons-wallet"></i>
                        </div>
                        <span class="zolo-kpi-badge">
                            <i class="dripicons-crosshair"></i> Overheads
                        </span>
                    </div>
                    <div class="zolo-kpi-body">
                        <div class="zolo-kpi-value">
                            <span class="zolo-kpi-currency">{{ $currency_symbol }}</span>
                            <span class="count-number expense-data">{{ number_format((float) $expense, $general_setting->decimal, '.', '') }}</span>
                        </div>
                        <div class="zolo-kpi-label">
                            <span>{{ __('db.Expense') }}</span>
                            <x-info title="Operational overhead and direct business expenditures" type="info" />
                        </div>
                        <div class="zolo-kpi-subtext">
                            <span>Cost Control:</span>
                            <span class="text-warning font-weight-bold">Direct &amp; SG&amp;A</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- KPI 6: Customer Receivables (AR Due) -->
            <div class="col-xl-3 col-lg-3 col-md-6 col-sm-6 mb-3">
                <div class="zolo-kpi-card kpi-amber">
                    <div class="zolo-kpi-header">
                        <div class="zolo-kpi-icon">
                            <i class="dripicons-clock"></i>
                        </div>
                        <span class="zolo-kpi-badge">
                            <i class="dripicons-warning"></i> Customer Due
                        </span>
                    </div>
                    <div class="zolo-kpi-body">
                        <div class="zolo-kpi-value">
                            <span class="zolo-kpi-currency">{{ $currency_symbol }}</span>
                            <span class="count-number invoice-due-data">{{ number_format((float) $invoice_due, $general_setting->decimal, '.', '') }}</span>
                        </div>
                        <div class="zolo-kpi-label">
                            <span>Receivables (AR)</span>
                            <x-info title="Unpaid invoices due from customers" type="info" />
                        </div>
                        <div class="zolo-kpi-subtext">
                            <span>Collections:</span>
                            <span class="text-warning font-weight-bold">Pending Inflow</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- KPI 7: Supplier Payables (AP Due) -->
            <div class="col-xl-3 col-lg-3 col-md-6 col-sm-6 mb-3">
                <div class="zolo-kpi-card kpi-teal">
                    <div class="zolo-kpi-header">
                        <div class="zolo-kpi-icon">
                            <i class="dripicons-calendar"></i>
                        </div>
                        <span class="zolo-kpi-badge">
                            <i class="dripicons-information"></i> Vendor Due
                        </span>
                    </div>
                    <div class="zolo-kpi-body">
                        <div class="zolo-kpi-value">
                            <span class="zolo-kpi-currency">{{ $currency_symbol }}</span>
                            <span class="count-number purchase_due-data">{{ number_format((float) $purchase_due, $general_setting->decimal, '.', '') }}</span>
                        </div>
                        <div class="zolo-kpi-label">
                            <span>Payables (AP)</span>
                            <x-info title="Outstanding bills due to vendors" type="info" />
                        </div>
                        <div class="zolo-kpi-subtext">
                            <span>Obligations:</span>
                            <span class="text-info font-weight-bold">Supplier Credit</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- KPI 8: Cash & Bank Liquidity -->
            <div class="col-xl-3 col-lg-3 col-md-6 col-sm-6 mb-3">
                <div class="zolo-kpi-card kpi-emerald">
                    <div class="zolo-kpi-header">
                        <div class="zolo-kpi-icon">
                            <i class="dripicons-briefcase"></i>
                        </div>
                        <span class="zolo-kpi-badge">
                            <i class="dripicons-checkmark"></i> Liquid Capital
                        </span>
                    </div>
                    <div class="zolo-kpi-body">
                        <div class="zolo-kpi-value">
                            <span class="zolo-kpi-currency">{{ $currency_symbol }}</span>
                            <span class="count-number liquid-balance-data">{{ number_format((float) $liquid_balance, $general_setting->decimal, '.', '') }}</span>
                        </div>
                        <div class="zolo-kpi-label">
                            <span>Cash &amp; Bank Liquidity</span>
                            <x-info title="Total available liquidity across all active bank and cash accounts" type="info" />
                        </div>
                        <div class="zolo-kpi-subtext">
                            <span>Inventory Value:</span>
                            <span class="text-success font-weight-bold">{{ $currency_symbol }}{{ number_format((float)$stock_valuation, 0, '.', ',') }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endif
    </div>
</section>

<!-- Visual Financial & Cash Flow Analytics Row -->
<div class="container-fluid">
    <div class="row">
        @php
            $cash_flow = $role_has_permissions_list->where('name', 'cash_flow')->first();
            $monthly_summary = $role_has_permissions_list->where('name', 'monthly_summary')->first();
        @endphp

        @if ($cash_flow)
        <div class="col-lg-7 col-md-12 mb-3">
            <div class="card line-chart-example h-100 mb-0">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center">
                        <i class="dripicons-pulse text-primary mr-2" style="font-size: 1.1rem;"></i>
                        <h4 class="mb-0">{{ __('db.Cash Flow') }} <span class="text-muted" style="font-size: 12px; font-weight: 500;">(Last 6 Months)</span></h4>
                    </div>
                    <span class="badge badge-success font-weight-semibold">Live Flow</span>
                </div>
                <div class="card-body pt-1 pb-2" style="overflow: hidden;">
                    <div class="zolo-chart-box" style="position: relative; height: 230px; max-height: 230px; width: 100%; overflow: hidden;">
                        <canvas id="cashFlow" height="230" style="max-height: 230px; width: 100%; display: block;"
                            data-color = "{{ $color }}"
                            data-color_rgba = "{{ $color_rgba }}"
                            data-recieved = "{{ json_encode($payment_recieved) }}"
                            data-sent = "{{ json_encode($payment_sent) }}"
                            data-month = "{{ json_encode($month) }}"
                            data-label1="{{ __('db.Payment Recieved') }}"
                            data-label2="{{ __('db.Payment Sent') }}"></canvas>
                    </div>
                </div>
            </div>
        </div>
        @endif

        @if ($monthly_summary)
        <div class="col-lg-5 col-md-12 mb-3">
            <div class="card h-100 mb-0" style="overflow: hidden;">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center">
                        <i class="dripicons-pie-chart text-primary mr-2" style="font-size: 1.1rem;"></i>
                        <h4 class="mb-0">{{ date('F') }} {{ date('Y') }} Breakdown</h4>
                    </div>
                    <span class="badge badge-primary font-weight-semibold">Summary</span>
                </div>
                <div class="card-body pt-1 pb-2 d-flex flex-column justify-content-between" style="overflow: hidden;">
                    <div class="zolo-donut-box" style="position: relative; height: 180px; max-height: 180px; width: 100%; display: flex; align-items: center; justify-content: center; overflow: hidden;">
                        <canvas id="transactionChart" height="180" style="max-height: 180px; width: 100%; display: block;"
                            data-color = "{{ $color }}"
                            data-color_rgba = "{{ $color_rgba }}" data-revenue={{ $revenue }}
                            data-purchase={{ $purchase }} data-expense={{ $expense }}
                            data-label1="{{ __('db.Purchase') }}" data-label2="{{ __('db.revenue') }}"
                            data-label3="{{ __('db.Expense') }}"> </canvas>
                    </div>
                    <!-- Quick Metric Badges -->
                    <div class="d-flex justify-content-around align-items-center pt-2 border-top border-secondary-subtle">
                        <div class="donut-legend-pill">
                            <span class="donut-legend-dot" style="background: #6366f1;"></span>
                            <span>Purchase: {{ $currency_symbol }}{{ number_format((float)$purchase, 0, '.', ',') }}</span>
                        </div>
                        <div class="donut-legend-pill">
                            <span class="donut-legend-dot" style="background: #10b981;"></span>
                            <span>Revenue: {{ $currency_symbol }}{{ number_format((float)$revenue, 0, '.', ',') }}</span>
                        </div>
                        <div class="donut-legend-pill">
                            <span class="donut-legend-dot" style="background: #f59e0b;"></span>
                            <span>Expense: {{ $currency_symbol }}{{ number_format((float)$expense, 0, '.', ',') }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endif
    </div>
</div>

<!-- Modular Industry Addon Live Pulse Bar -->
@if(!empty($addon_stats))
<div class="container-fluid mt-1 mb-3">
    <div class="row">
        @if(isset($addon_stats['water_tankers']) || isset($addon_stats['cans_delivered_today']))
        <div class="col-md-4 mb-2 mb-md-0">
            <div class="zolo-addon-pulse-card">
                <div class="d-flex align-items-center">
                    <div class="zolo-kpi-icon mr-2" style="background: rgba(2, 132, 199, 0.15); color: #0284c7;">
                        <i class="dripicons-truck"></i>
                    </div>
                    <div>
                        <div style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: #0284c7;">DK Track Logistics</div>
                        <div style="font-size: 13px; font-weight: 700; color: var(--neo-text-primary);">
                            {{ $addon_stats['water_tankers'] ?? 0 }} Tankers Active &bull; {{ $addon_stats['water_trips_today'] ?? 0 }} Trips
                        </div>
                    </div>
                </div>
                <div class="text-right">
                    <span class="zolo-pulse-indicator mr-1"></span>
                    <a href="{{ url('water-logistics') }}" class="btn btn-xs btn-outline-info ml-1">Fleet &rarr;</a>
                </div>
            </div>
        </div>
        @endif

        @if(isset($addon_stats['open_drawers']))
        <div class="col-md-4 mb-2 mb-md-0">
            <div class="zolo-addon-pulse-card">
                <div class="d-flex align-items-center">
                    <div class="zolo-kpi-icon mr-2" style="background: rgba(234, 88, 12, 0.15); color: #ea580c;">
                        <i class="dripicons-store"></i>
                    </div>
                    <div>
                        <div style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: #ea580c;">Bakery &amp; Cafe POS</div>
                        <div style="font-size: 13px; font-weight: 700; color: var(--neo-text-primary);">
                            {{ $addon_stats['open_drawers'] ?? 0 }} Active Drawers &bull; Fast Register
                        </div>
                    </div>
                </div>
                <div class="text-right">
                    <span class="zolo-pulse-indicator mr-1"></span>
                    <a href="{{ url('cafe') }}" class="btn btn-xs btn-outline-warning ml-1">Terminal &rarr;</a>
                </div>
            </div>
        </div>
        @endif

        @if(isset($addon_stats['repair_services_count']))
        <div class="col-md-4 mb-2 mb-md-0">
            <div class="zolo-addon-pulse-card">
                <div class="d-flex align-items-center">
                    <div class="zolo-kpi-icon mr-2" style="background: rgba(99, 102, 241, 0.15); color: #6366f1;">
                        <i class="dripicons-gear"></i>
                    </div>
                    <div>
                        <div style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: #6366f1;">RMA Repair Service</div>
                        <div style="font-size: 13px; font-weight: 700; color: var(--neo-text-primary);">
                            {{ $addon_stats['repair_services_count'] ?? 0 }} Services Configured &bull; Live Bench
                        </div>
                    </div>
                </div>
                <div class="text-right">
                    <span class="zolo-pulse-indicator mr-1"></span>
                    <a href="{{ url('repair/dashboard') }}" class="btn btn-xs btn-outline-primary ml-1">Queue &rarr;</a>
                </div>
            </div>
        </div>
        @endif
    </div>
</div>
@endif

<!-- Operational Activity & Inventory Alerts Hub -->
<div class="container-fluid">
    <div class="row">
        <!-- Left: Recent Transaction Activity Hub -->
        <div class="col-lg-7 col-md-12 mb-3">
            <div class="card h-100 mb-0">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center">
                        <i class="dripicons-swap text-primary mr-2" style="font-size: 1.1rem;"></i>
                        <h4 class="mb-0">{{ __('db.Recent Transaction') }}</h4>
                    </div>
                    <div class="badge badge-primary font-weight-semibold">{{ __('db.latest') }} 5</div>
                </div>
                <div class="card-body pt-1 pb-2">
                    <ul class="nav nav-tabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" href="#sale-latest" role="tab" data-toggle="tab">{{ __('db.Sale') }}</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#purchase-latest" role="tab" data-toggle="tab">{{ __('db.Purchase') }}</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#quotation-latest" role="tab" data-toggle="tab">{{ __('db.Quotation') }}</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#payment-latest" role="tab" data-toggle="tab">{{ __('db.Payment') }}</a>
                        </li>
                    </ul>

                    <div class="tab-content pt-2">
                        <div role="tabpanel" class="tab-pane fade show active" id="sale-latest">
                            <div class="table-responsive">
                                <table id="recent-sale" class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>{{ __('db.date') }}</th>
                                            <th>{{ __('db.reference') }}</th>
                                            <th>{{ __('db.customer') }}</th>
                                            <th>{{ __('db.status') }}</th>
                                            <th>{{ __('db.grand total') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                        <div role="tabpanel" class="tab-pane fade" id="purchase-latest">
                            <div class="table-responsive">
                                <table id="recent-purchase" class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>{{ __('db.date') }}</th>
                                            <th>{{ __('db.reference') }}</th>
                                            <th>{{ __('db.Supplier') }}</th>
                                            <th>{{ __('db.status') }}</th>
                                            <th>{{ __('db.grand total') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                        <div role="tabpanel" class="tab-pane fade" id="quotation-latest">
                            <div class="table-responsive">
                                <table id="recent-quotation" class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>{{ __('db.date') }}</th>
                                            <th>{{ __('db.reference') }}</th>
                                            <th>{{ __('db.customer') }}</th>
                                            <th>{{ __('db.status') }}</th>
                                            <th>{{ __('db.grand total') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                        <div role="tabpanel" class="tab-pane fade" id="payment-latest">
                            <div class="table-responsive">
                                <table id="recent-payment" class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>{{ __('db.date') }}</th>
                                            <th>{{ __('db.reference') }}</th>
                                            <th>{{ __('db.Amount') }}</th>
                                            <th>{{ __('db.Paid By') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right: Critical Inventory Health & Best Sellers -->
        <div class="col-lg-5 col-md-12 mb-3">
            <!-- Critical Stock Alerts -->
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center">
                        <i class="dripicons-warning text-warning mr-2" style="font-size: 1.1rem;"></i>
                        <h4 class="mb-0">Critical Stock Alerts</h4>
                    </div>
                    @if($low_stock_count > 0)
                        <span class="badge badge-danger">{{ $low_stock_count }} Low Stock</span>
                    @else
                        <span class="badge badge-success">Inventory Healthy</span>
                    @endif
                </div>
                <div class="card-body pt-1 pb-2">
                    @if(isset($low_stock_products) && count($low_stock_products) > 0)
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th>Warehouse</th>
                                    <th>Stock</th>
                                    <th>Threshold</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($low_stock_products as $item)
                                <tr>
                                    <td>
                                        <div class="font-weight-bold" style="font-size: 12.5px;">{{ $item->name }}</div>
                                        <small class="text-muted">[{{ $item->code }}]</small>
                                    </td>
                                    <td><span class="badge badge-secondary">{{ $item->warehouse_name }}</span></td>
                                    <td><span class="badge badge-danger">{{ $item->qty }}</span></td>
                                    <td><span class="text-muted" style="font-size: 12px;">{{ $item->alert_quantity }}</span></td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @else
                    <div class="text-center py-3 text-muted">
                        <i class="dripicons-checkmark text-success mr-1" style="font-size: 20px;"></i>
                        <p class="mb-0 mt-1" style="font-size: 12.5px;">All items are comfortably stocked above reorder thresholds.</p>
                    </div>
                    @endif
                </div>
            </div>

            <!-- Best Sellers (Monthly) -->
            <div class="card mb-0">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center">
                        <i class="dripicons-star text-warning mr-2" style="font-size: 1.1rem;"></i>
                        <h4 class="mb-0">{{ __('db.Best Seller') . ' ' . date('F') }}</h4>
                    </div>
                    <div class="badge badge-primary font-weight-semibold">{{ __('db.top') }} 5</div>
                </div>
                <div class="table-responsive">
                    <table id="monthly-best-selling-qty" class="table table-sm mb-0">
                        <thead>
                            <tr>
                                <th>{{ __('db.Product Details') }}</th>
                                <th>{{ __('db.qty') }}</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Annual Commercial Growth Section (Collapsible / Compact) -->
<div class="container-fluid mb-4">
    <div class="row">
        @php
            $yearly_report = $role_has_permissions_list->where('name', 'yearly_report')->first();
        @endphp
        @if ($yearly_report)
        <div class="col-md-12 mb-3">
            <div class="card mb-0">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center">
                        <i class="dripicons-calendar text-primary mr-2" style="font-size: 1.1rem;"></i>
                        <h4 class="mb-0">{{ __('db.yearly report') }} &bull; Commercial Annual Velocity</h4>
                    </div>
                    <span class="badge badge-primary font-weight-semibold">{{ date('Y') }} Fiscal</span>
                </div>
                <div class="card-body pt-1 pb-2" style="overflow: hidden;">
                    <div class="zolo-yearly-box" style="position: relative; height: 210px; max-height: 210px; width: 100%; overflow: hidden;">
                        <canvas id="saleChart" height="210" style="max-height: 210px; width: 100%; display: block;"
                            data-sale_chart_value = "{{ json_encode($yearly_sale_amount) }}"
                            data-purchase_chart_value = "{{ json_encode($yearly_purchase_amount) }}"
                            data-label1="{{ __('db.Purchased Amount') }}"
                            data-label2="{{ __('db.Sold Amount') }}"></canvas>
                    </div>
                </div>
            </div>
        </div>
        @endif

        <div class="col-md-6 mb-3">
            <div class="card mb-0 h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center">
                        <i class="dripicons-trophy text-primary mr-2" style="font-size: 1.1rem;"></i>
                        <h4 class="mb-0">{{ __('db.Best Seller') . ' ' . date('Y') . ' (' . __('db.qty') . ')' }}</h4>
                    </div>
                    <div class="badge badge-primary font-weight-semibold">{{ __('db.top') }} 5</div>
                </div>
                <div class="table-responsive">
                    <table id="yearly-best-selling-qty" class="table table-sm mb-0">
                        <thead>
                            <tr>
                                <th>{{ __('db.Product Details') }}</th>
                                <th>{{ __('db.qty') }}</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-6 mb-3">
            <div class="card mb-0 h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center">
                        <i class="dripicons-jewel text-primary mr-2" style="font-size: 1.1rem;"></i>
                        <h4 class="mb-0">{{ __('db.Best Seller') . ' ' . date('Y') . ' (' . __('db.Price') . ')' }}</h4>
                    </div>
                    <div class="badge badge-primary font-weight-semibold">{{ __('db.top') }} 5</div>
                </div>
                <div class="table-responsive">
                    <table id="yearly-best-selling-price" class="table table-sm mb-0">
                        <thead>
                            <tr>
                                <th>{{ __('db.Product Details') }}</th>
                                <th>{{ __('db.grand total') }}</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script type="text/javascript">
    $(document).ready(function() {
        // Load Annual Best Selling by Price
        $.ajax({
            url: '{{ url('/yearly-best-selling-price') }}',
            type: 'GET',
            dataType: 'json',
            success: function(data) {
                var url = '{{ url('/images/product') }}';
                data.forEach(function(item) {
                    var images = item.product_images ? item.product_images.split(',') : ['zummXD2dvAtI.png'];
                    $('#yearly-best-selling-price').find('tbody').append(
                        '<tr><td><div class="d-flex align-items-center"><img src="' +
                        url + '/' + images[0] +
                        '" width="28" height="24" class="mr-2 rounded"> <span class="font-weight-medium">' + item
                        .product_name + '</span> <span class="text-muted ml-1">[' + item.product_code + ']</span></div></td><td class="font-weight-bold">' +
                        (item.total_price / item.exchange_rate).toFixed({{ $general_setting->decimal }}) + '</td></tr>');
                });
            }
        });

        // Load Annual Best Selling by Quantity
        $.ajax({
            url: '{{ url('/yearly-best-selling-qty') }}',
            type: 'GET',
            dataType: 'json',
            success: function(data) {
                var url = '{{ url('/images/product') }}';
                data.forEach(function(item) {
                    var images = item.product_images ? item.product_images.split(',') : ['zummXD2dvAtI.png'];
                    $('#yearly-best-selling-qty').find('tbody').append(
                        '<tr><td><div class="d-flex align-items-center"><img src="' +
                        url + '/' + images[0] +
                        '" width="28" height="24" class="mr-2 rounded"> <span class="font-weight-medium">' + item
                        .product_name + '</span> <span class="text-muted ml-1">[' + item.product_code + ']</span></div></td><td class="font-weight-bold">' +
                        item.sold_qty + '</td></tr>');
                });
            }
        });

        // Load Monthly Best Selling by Quantity
        $.ajax({
            url: '{{ url('/monthly-best-selling-qty') }}',
            type: 'GET',
            dataType: 'json',
            success: function(data) {
                var url = '{{ url('/images/product') }}';
                data.forEach(function(item) {
                    var images = item.product_images ? item.product_images.split(',') : ['zummXD2dvAtI.png'];
                    $('#monthly-best-selling-qty').find('tbody').append(
                        '<tr><td><div class="d-flex align-items-center"><img src="' +
                        url + '/' + images[0] +
                        '" width="28" height="24" class="mr-2 rounded"> <span class="font-weight-medium">' + item
                        .product_name + '</span> <span class="text-muted ml-1">[' + item.product_code + ']</span></div></td><td class="font-weight-bold">' +
                        item.sold_qty + '</td></tr>');
                });
            }
        });

        // Load Recent Sales Invoices
        $.ajax({
            url: "{{ url('/recent-sale') }}",
            type: 'GET',
            dataType: 'json',
            success: function(data) {
                data.forEach(function(item) {
                    var sale_date = item.created_at ? dateFormat(item.created_at.split('T')[0], '{{ $general_setting->date_format }}') : '';
                    var status = '';
                    if (item.sale_status == 1) {
                        status = '<span class="badge badge-success">{{ __('db.Completed') }}</span>';
                    } else if (item.sale_status == 2) {
                        status = '<span class="badge badge-danger">{{ __('db.Pending') }}</span>';
                    } else {
                        status = '<span class="badge badge-warning">{{ __('db.Draft') }}</span>';
                    }
                    $('#recent-sale').find('tbody').append('<tr><td>' + sale_date +
                        '</td><td><span class="font-weight-medium text-primary">' + item.reference_no + '</span></td><td>' + item.name +
                        '</td><td>' + status + '</td><td class="font-weight-bold">' + (item.grand_total/item.exchange_rate).toString()
                        .replace(/\B(?=(\d{3})+(?!\d))/g, ",") + '</td></tr>');
                });
            }
        });

        // Load Recent Purchases
        $.ajax({
            url: '{{ url('/recent-purchase') }}',
            type: 'GET',
            dataType: 'json',
            success: function(data) {
                data.forEach(function(item) {
                    var payment_date = item.created_at ? dateFormat(item.created_at.split('T')[0], '{{ $general_setting->date_format }}') : '';
                    var status = '';
                    if (item.status == 1) {
                        status = '<span class="badge badge-success">{{ __('db.Recieved') }}</span>';
                    } else if (item.status == 2) {
                        status = '<span class="badge badge-danger">{{ __('db.Partial') }}</span>';
                    } else if (item.status == 3) {
                        status = '<span class="badge badge-danger">{{ __('db.Pending') }}</span>';
                    } else {
                        status = '<span class="badge badge-warning">{{ __('db.Ordered') }}</span>';
                    }
                    $('#recent-purchase').find('tbody').append('<tr><td>' + payment_date +
                        '</td><td><span class="font-weight-medium text-primary">' + item.reference_no + '</span></td><td>' + (item.name || 'N/A') +
                        '</td><td>' + status + '</td><td class="font-weight-bold">' + (item.grand_total/item.exchange_rate).toString()
                        .replace(/\B(?=(\d{3})+(?!\d))/g, ",") + '</td></tr>');
                });
            }
        });

        // Load Recent Quotations
        $.ajax({
            url: '{{ url('/recent-quotation') }}',
            type: 'GET',
            dataType: 'json',
            success: function(data) {
                data.forEach(function(item) {
                    var quotation_date = item.created_at ? dateFormat(item.created_at.split('T')[0], '{{ $general_setting->date_format }}') : '';
                    var status = item.quotation_status == 1 ? '<span class="badge badge-success">{{ __('db.Pending') }}</span>' : '<span class="badge badge-danger">{{ __('db.Sent') }}</span>';
                    $('#recent-quotation').find('tbody').append('<tr><td>' + quotation_date + '</td><td><span class="font-weight-medium text-primary">' + item.reference_no + '</span></td><td>' + item.name + '</td><td>' + status + '</td><td class="font-weight-bold">' + item.grand_total.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",") + '</td></tr>');
                });
            }
        });

        // Load Recent Payments
        $.ajax({
            url: '{{ url('/recent-payment') }}',
            type: 'GET',
            dataType: 'json',
            success: function(data) {
                data.forEach(function(item) {
                    var payment_date = item.created_at ? dateFormat(item.created_at.split('T')[0], '{{ $general_setting->date_format }}') : '';
                    $('#recent-payment').find('tbody').append('<tr><td>' + payment_date +
                        '</td><td><span class="font-weight-medium text-primary">' + item.payment_reference + '</span></td><td class="font-weight-bold">' + (item.amount/item.exchange_rate)
                        .toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",") +
                        '</td><td><span class="badge badge-info">' + item.paying_method + '</span></td></tr>');
                });
            }
        });
    });

    function dateFormat(inputDate, format) {
        const date = new Date(inputDate);
        const day = date.getDate();
        const month = date.getMonth() + 1;
        const year = date.getFullYear();
        format = format.replace("m", month.toString().padStart(2, "0"));
        format = format.replace("Y", year.toString());
        format = format.replace("d", day.toString().padStart(2, "0"));
        return format;
    }

    $(".date-btn").on("click", function() {
        $(".date-btn").removeClass("active");
        $(this).addClass("active");
        var start_date = $(this).data('start_date');
        var end_date = $(this).data('end_date');
        var warehouse_id = $("#warehouse_btn").val();
        $.get('dashboard-filter/' + start_date + '/' + end_date + '/' + warehouse_id, function(data) {
            dashboardFilter(data);
        });
    });

    $("#warehouse_btn").on("change", function() {
        var warehouse_id = $(this).val();
        var start_date = $('.date-btn.active').data('start_date');
        var end_date = $('.date-btn.active').data('end_date');
        $.get('dashboard-filter/' + start_date + '/' + end_date + '/' + warehouse_id, function(data) {
            dashboardFilter(data);
        });
    });

    function dashboardFilter(data) {
        // data array:
        // [0: revenue, 1: return, 2: profit, 3: purchase_return, 4: total_sale, 5: invoice_due, 6: purchase - return, 7: purchase_due, 8: expense]
        $('.revenue-data').hide().html(parseFloat(data[0] ?? 0).toFixed({{ $general_setting->decimal }})).fadeIn(300);
        $('.return-data').hide().html(parseFloat(data[1] ?? 0).toFixed({{ $general_setting->decimal }})).fadeIn(300);
        $('.profit-data').hide().html(parseFloat(data[2] ?? 0).toFixed({{ $general_setting->decimal }})).fadeIn(300);
        $('.purchase_return-data').hide().html(parseFloat(data[3] ?? 0).toFixed({{ $general_setting->decimal }})).fadeIn(300);
        $('.total_sale-data').hide().html(parseFloat(data[4] ?? 0).toFixed({{ $general_setting->decimal }})).fadeIn(300);
        $('.invoice-due-data').hide().html(parseFloat(data[5] ?? 0).toFixed({{ $general_setting->decimal }})).fadeIn(300);
        $('.total_purchase-data').hide().html(parseFloat(data[6] ?? 0).toFixed({{ $general_setting->decimal }})).fadeIn(300);
        $('.purchase_due-data').hide().html(parseFloat(data[7] ?? 0).toFixed({{ $general_setting->decimal }})).fadeIn(300);
        if (data[8] !== undefined) {
            $('.expense-data').hide().html(parseFloat(data[8] ?? 0).toFixed({{ $general_setting->decimal }})).fadeIn(300);
        }
    }
</script>
@endpush
