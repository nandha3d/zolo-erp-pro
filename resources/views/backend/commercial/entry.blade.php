<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $kind === 'sale' ? 'Sales Bills' : 'Purchase Bills' }} · {{ $company->trade_name ?? $company->legal_name ?? 'Zolo ERP' }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/zolo-erp-neo.css') }}">
    <link rel="stylesheet" href="{{ asset('css/commercial-entry.css') }}">
    <script defer src="{{ asset('js/commercial-entry.js') }}"></script>
</head>
<body data-kind="{{ $kind }}" data-base="{{ url('/commercial/'.$kind) }}" data-quantity-scale="{{ $industry['settings']['quantity_scale'] }}" @if($project) data-project-id="{{ $project->id }}" data-project-customer="{{ $project->client_id }}" @endif data-compliance="{{ config('compliance.enabled') ? '1' : '0' }}" data-post-url="{{ isset($exchangeReturn) ? url('/compliance/exchange/'.$exchangeReturn->id) : '' }}">

<div class="desk-app">
    <!-- LEFT DESK WORKSPACE SIDEBAR -->
    <aside id="desk-sidebar" class="desk-sidebar" aria-label="Main Navigation">
        <div class="desk-sidebar-header">
            <div class="desk-brand-badge">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                    <path d="M12 2L2 7V17L12 22L22 17V7L12 2Z" fill="#7c3aed" opacity="0.2"/>
                    <path d="M12 2L2 7L12 12L22 7L12 2Z" fill="#8b5cf6"/>
                    <path d="M2 17L12 22V12L2 7V17Z" fill="#7c3aed"/>
                    <path d="M22 17L12 22V12L22 7V17Z" fill="#6d28d9"/>
                </svg>
            </div>
            <div class="desk-brand-text">
                <span class="desk-brand-title">Zolo ERP</span>
                <span class="desk-brand-sub">Desk workspace</span>
            </div>
        </div>

        <div class="desk-sidebar-search">
            <svg class="search-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="text" placeholder="Find a menu item..." aria-label="Find menu item">
        </div>

        <nav class="desk-sidebar-nav">
            <a href="{{ url('/dashboard') }}" class="desk-nav-item">
                <span class="desk-nav-icon">⌂</span>
                <span>Home</span>
            </a>

            <div class="desk-nav-section-title">MODULES</div>

            <!-- Selling Module -->
            <div class="desk-nav-group {{ $kind === 'sale' ? 'open active-group' : '' }}">
                <a href="{{ url('/sales') }}" class="desk-nav-item {{ $kind === 'sale' ? 'active-parent' : '' }}">
                    <span class="desk-nav-icon">🛒</span>
                    <span>Selling</span>
                    <span class="desk-chevron">▾</span>
                </a>
                <div class="desk-subnav">
                    <div class="desk-subnav-label">ENTRY</div>
                    <a href="{{ url('/commercial/sale/entry') }}" class="desk-subnav-item {{ $kind === 'sale' ? 'active-pill' : '' }}">Sales Bills</a>
                    <a href="{{ url('/sales') }}" class="desk-subnav-item">Sales Orders</a>
                    <a href="{{ url('/return-sale') }}" class="desk-subnav-item">Sales Returns</a>
                    <a href="{{ url('/quotations') }}" class="desk-subnav-item">Quotations</a>
                    
                    <div class="desk-subnav-label">MASTERS</div>
                    <a href="{{ url('/customer') }}" class="desk-subnav-item">Customer Group</a>
                    <a href="{{ url('/agent') }}" class="desk-subnav-item">Sales Partner (Agents)</a>
                    <a href="{{ url('/area') }}" class="desk-subnav-item">Territory (Routes)</a>
                    <a href="{{ url('/products') }}" class="desk-subnav-item">Price List</a>
                    <a href="{{ url('/products') }}" class="desk-subnav-item">Item Price</a>
                    <a href="{{ url('/sale-type') }}" class="desk-subnav-item">Sales Type</a>

                    <div class="desk-subnav-label">SETTINGS</div>
                    <a href="{{ url('/general_setting') }}" class="desk-subnav-item">Selling Settings</a>
                </div>
            </div>

            <!-- Buying Module -->
            <div class="desk-nav-group {{ $kind === 'purchase' ? 'open active-group' : '' }}">
                <a href="{{ url('/purchases') }}" class="desk-nav-item {{ $kind === 'purchase' ? 'active-parent' : '' }}">
                    <span class="desk-nav-icon">🛍</span>
                    <span>Buying</span>
                    <span class="desk-chevron">▾</span>
                </a>
                <div class="desk-subnav">
                    <div class="desk-subnav-label">ENTRY</div>
                    <a href="{{ url('/commercial/purchase/entry') }}" class="desk-subnav-item {{ $kind === 'purchase' ? 'active-pill' : '' }}">Purchase Bills</a>
                    <a href="{{ url('/purchases') }}" class="desk-subnav-item">Purchase Orders</a>
                    <a href="{{ url('/return-purchase') }}" class="desk-subnav-item">Purchase Returns</a>

                    <div class="desk-subnav-label">MASTERS</div>
                    <a href="{{ url('/supplier') }}" class="desk-subnav-item">Suppliers</a>
                    <a href="{{ url('/purchase-type') }}" class="desk-subnav-item">Purchase Types</a>
                    <a href="{{ url('/bill-sundry') }}" class="desk-subnav-item">Bill Sundries</a>
                    <a href="{{ url('/document-series') }}" class="desk-subnav-item">Series</a>
                </div>
            </div>

            <a href="{{ url('/operations/stock') }}" class="desk-nav-item">
                <span class="desk-nav-icon">📦</span>
                <span>Stock</span>
            </a>

            <a href="{{ url('/accounting/vouchers') }}" class="desk-nav-item">
                <span class="desk-nav-icon">💳</span>
                <span>Accounts</span>
            </a>

            <a href="{{ url('/report/profit-loss') }}" class="desk-nav-item">
                <span class="desk-nav-icon">📊</span>
                <span>Reports</span>
            </a>
        </nav>

        <div class="desk-sidebar-footer">
            <button type="button" class="desk-btn-ghost" id="btn-show-shortcuts" title="Keyboard Shortcuts">
                <span>?</span> Shortcuts
            </button>
        </div>
    </aside>

    <!-- MAIN APP WRAPPER -->
    <div class="desk-main">
        <!-- TOP DESK NAVBAR -->
        <header class="desk-topbar">
            <div class="desk-topbar-left">
                <button type="button" class="desk-icon-btn" id="toggle-sidebar" title="Toggle Sidebar" aria-label="Toggle Navigation">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
                </button>
                <div class="desk-breadcrumbs">
                    <span class="breadcrumb-pill">{{ $kind === 'sale' ? 'Selling' : 'Buying' }}</span>
                    <span class="breadcrumb-separator">/</span>
                    <span class="breadcrumb-title">{{ $kind === 'sale' ? 'Sales Bills' : 'Purchase Bills' }}</span>
                </div>
            </div>

            <div class="desk-topbar-center">
                <div class="desk-omnisearch">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <input type="text" placeholder="Search screens, actions, record" aria-label="Search">
                    <kbd class="desk-kbd">⌘K</kbd>
                </div>
                <div class="desk-quick-pills">
                    <button type="button" class="quick-pill primary" id="btn-new-bill">+ New Bill</button>
                    <a href="{{ $kind === 'sale' ? url('/customer') : url('/supplier') }}" class="quick-pill">{{ $kind === 'sale' ? 'Customers' : 'Suppliers' }}</a>
                    <a href="{{ $kind === 'sale' ? url('/sales') : url('/purchases') }}" class="quick-pill">{{ $kind === 'sale' ? 'Sales Orders' : 'Purchase Orders' }}</a>
                    <a href="{{ url('/accounting/vouchers') }}" class="quick-pill">{{ $kind === 'sale' ? 'Receivables' : 'Payables' }}</a>
                </div>
            </div>

            <div class="desk-topbar-right">
                <div class="desk-company-badge" title="Active Legal Entity">
                    <span class="pulse-dot"></span>
                    <span class="company-name">{{ $company->trade_name ?? $company->legal_name ?? 'Sri Murugan Textiles' }}</span>
                </div>
                <div class="desk-user-badge">
                    <div class="user-avatar" title="{{ Auth::user()->name ?? 'Administrator' }}">
                        {{ strtoupper(substr(Auth::user()->name ?? 'A', 0, 1)) }}
                    </div>
                    <div class="user-meta">
                        <span class="user-name">{{ Auth::user()->name ?? 'Administrator' }}</span>
                        <span class="user-status">Signed in</span>
                    </div>
                </div>
            </div>
        </header>

        <!-- MULTI-TAB DOCUMENT STRIP -->
        <div class="desk-tab-strip">
            <button type="button" class="desk-tab-btn" id="btn-browse-bills">
                <span class="tab-icon">📄</span>
                <span class="tab-label">{{ $kind === 'sale' ? 'Bills' : 'Purchases' }}</span>
                <span class="tab-sub">Browse list</span>
            </button>
            <div class="desk-tab-btn active" id="tab-active-draft">
                <span class="tab-dot"></span>
                <span class="tab-icon">📄</span>
                <span class="tab-label" id="tab-draft-title">Draft 1</span>
                <span class="tab-sub" id="tab-draft-party">New bill</span>
            </div>
            <button type="button" class="desk-tab-add" id="tab-add-new" title="Create another new bill">+</button>
        </div>

        <!-- MAIN TWO-PANEL WORKSPACE -->
        <div class="desk-workspace-layout panel-dock-left" id="desk-workspace">

            <!-- TOGGLEABLE & DOCKABLE SIDE PANEL (BILL LIST / RECENT) -->
            <aside class="desk-side-panel" id="side-panel" aria-label="Transaction List Panel">
                <div class="side-panel-header">
                    <div class="side-panel-title-wrap">
                        <h3>{{ $kind === 'sale' ? 'Bill list' : 'Purchase list' }}</h3>
                    </div>
                    <div class="side-panel-actions">
                        <button type="button" class="side-icon-btn" id="btn-dock-toggle" title="Arrange Panel Side (Dock Left / Dock Right)">
                            <span id="dock-icon">⇄</span>
                            <span class="dock-tooltip" id="dock-label">Dock Right</span>
                        </button>
                        <button type="button" class="side-icon-btn" id="btn-side-close" title="Collapse Panel">✕</button>
                    </div>
                </div>

                <div class="side-panel-search">
                    <input type="text" id="side-search-input" placeholder="Find a {{ $kind === 'sale' ? 'bill' : 'purchase' }}..." autocomplete="off">
                </div>

                <div class="side-panel-filter-label">BILL NUMBER</div>
                <div class="side-panel-series-filter">
                    <input type="text" id="side-filter-series" placeholder="Series or edited number">
                </div>

                <div class="side-filter-tabs">
                    <button type="button" class="side-tab active" data-filter="all">All</button>
                    <button type="button" class="side-tab" data-filter="draft">Draft</button>
                    <button type="button" class="side-tab" data-filter="date">Date</button>
                    <button type="button" class="side-tab" data-filter="range">Range</button>
                </div>

                <div class="side-list-container" id="side-bill-list">
                    @forelse($recentBills as $bill)
                        <div class="side-bill-card" data-bill-id="{{ $bill->id }}">
                            <div class="side-card-top">
                                <strong class="side-card-ref">{{ $bill->reference_no ?? ('#'.$bill->id) }}</strong>
                                <span class="side-card-amount">₹ {{ number_format($bill->grand_total ?? 0, 2) }}</span>
                            </div>
                            <div class="side-card-party">
                                {{ $kind === 'sale' ? ($bill->customer->name ?? 'Walk-in Customer') : ($bill->supplier->name ?? 'Default Supplier') }}
                            </div>
                            <div class="side-card-bottom">
                                <span class="side-card-date">{{ substr($bill->created_at ?? $businessDate, 0, 10) }}</span>
                                <span class="side-card-status {{ ($bill->payment_status ?? '') == 4 ? 'paid' : 'pending' }}">
                                    {{ ($bill->payment_status ?? '') == 4 ? 'Paid' : 'Unpaid' }}
                                </span>
                            </div>
                        </div>
                    @empty
                        <div class="side-empty-state">
                            <p>No bills match these filters</p>
                        </div>
                    @endforelse
                </div>

                <div class="side-pagination">
                    <span class="side-page-size">Show 40 ▾</span>
                    <div class="side-page-nav">
                        <button type="button" class="page-nav-btn">&lt;</button>
                        <span class="page-number">1</span>
                        <button type="button" class="page-nav-btn">&gt;</button>
                    </div>
                </div>
            </aside>

            <!-- MAIN DOCUMENT CONTAINER -->
            <main class="desk-doc-container">
                <form id="entry-form">
                    <!-- DOCUMENT BREADCRUMB & HEADER STRIP -->
                    <div class="doc-header-strip">
                        <div class="doc-title-block">
                            <div class="doc-icon-box">📄</div>
                            <div class="doc-title-meta">
                                <h2>New {{ $kind === 'sale' ? 'Sales Bill' : 'Purchase Bill' }}</h2>
                                <span class="doc-sub">- New bill</span>
                            </div>
                        </div>

                        <!-- TOGGLE CONTROLS: CASH/CREDIT & LINE NATURE -->
                        <div class="doc-toggle-group">
                            <div class="pill-segmented" role="group" aria-label="Payment Mode">
                                <button type="button" class="segment-btn" id="pill-mode-cash" data-mode="Cash">Cash</button>
                                <button type="button" class="segment-btn active" id="pill-mode-credit" data-mode="Credit">Credit</button>
                            </div>
                            <div class="pill-segmented" role="group" aria-label="Product Mode">
                                <button type="button" class="segment-btn active" data-nature="product">Product</button>
                                <button type="button" class="segment-btn" data-nature="service">Service</button>
                                <button type="button" class="segment-btn" data-nature="mixed">Mixed</button>
                            </div>
                        </div>

                        <!-- METADATA & ACTIONS -->
                        <div class="doc-header-meta">
                            <div class="meta-terms">
                                <span>Due <strong>{{ $businessDate }}</strong></span>
                                <span>Credit days <strong id="header-credit-days">—</strong></span>
                                <span>Terms <strong>Standard</strong></span>
                            </div>
                            <div class="header-action-btns">
                                <button type="button" class="btn-desk-action" id="btn-toggle-bill-list">
                                    <span class="btn-icon">📖</span> Bill list
                                </button>
                                <button type="button" class="btn-desk-action" id="btn-open-details">
                                    <span class="btn-icon">⚙</span> Details
                                </button>
                            </div>
                        </div>
                    </div>

                    <p id="status" role="status" aria-live="polite">Ready. Search a party, then add items.</p>
                    @isset($exchangeReturn)<p class="panel" style="margin: 0 0 12px 0;">Exchange against return {{ $exchangeReturn->reference_no }}. Select the same customer and enter replacement items.</p>@endisset

                    <!-- PRIMARY DOCUMENT FIELDS ROW (Matching Screenshot) -->
                    <div class="desk-card doc-primary-fields">
                        <div class="fields-grid-neo">
                            <!-- Bill Number / Series Preview -->
                            <div class="field-item">
                                <label for="bill-number-preview">Bill number</label>
                                <input type="text" id="bill-number-preview" class="input-prominent" value="ACC-SINV-{{ date('Y') }}-00001" placeholder="Voucher #">
                                <span class="field-hint">Series preview - editable</span>
                            </div>

                            <!-- Bill Date -->
                            <div class="field-item">
                                <label for="business-date">Bill Date</label>
                                <div class="input-with-icon">
                                    <input id="business-date" type="date" value="{{ $businessDate }}" required>
                                </div>
                            </div>

                            <!-- Party (Customer / Supplier) -->
                            <div class="field-item field-item-wide">
                                <div class="label-with-action">
                                    <label for="party-search">Party ({{ $kind === 'sale' ? 'Customer' : 'Supplier' }}) *</label>
                                    <button type="button" class="link-btn-add" data-inline="parties" title="New Party (Alt+C)">+ New Party</button>
                                </div>
                                <div class="search-party-wrap">
                                    <input id="party-search" autocomplete="off" placeholder="Search party name, city, alias or phone..." aria-controls="party-results">
                                    <div id="party-results" class="results" aria-label="Party search results"></div>
                                </div>
                                <span class="field-hint">Party address loads automatically</span>
                            </div>

                            <!-- Tax Classification / Nature -->
                            <div class="field-item">
                                <div class="label-with-action">
                                    <label for="{{ $kind === 'sale' ? 'sale-type' : 'purchase-type' }}">
                                        {{ $kind === 'sale' ? 'Sale Type' : 'Purchase Type' }} *
                                    </label>
                                    <button type="button" class="link-btn-add" data-inline="{{ $kind === 'sale' ? 'sale-types' : 'purchase-types' }}" title="Add Type">+</button>
                                </div>
                                @if($kind === 'sale')
                                    <select id="sale-type" name="sale_type_id" required>
                                        @foreach($saleTypes as $st)
                                            <option value="{{ $st->id }}" data-rate="{{ $st->tax_rate }}" data-nature="{{ $st->tax_nature }}">{{ $st->name }}</option>
                                        @endforeach
                                    </select>
                                @else
                                    <select id="purchase-type" name="purchase_type_id" required>
                                        @foreach($purchaseTypes as $pt)
                                            <option value="{{ $pt->id }}" data-rate="{{ $pt->tax_rate }}" data-nature="{{ $pt->tax_nature }}">{{ $pt->name }}</option>
                                        @endforeach
                                    </select>
                                @endif
                                <span class="field-hint text-warning">GST mapping required</span>
                            </div>

                            <!-- Document Series -->
                            <div class="field-item">
                                <div class="label-with-action">
                                    <label for="series">Series</label>
                                    <button type="button" class="link-btn-add" data-inline="series" title="Add Series">+</button>
                                </div>
                                <select id="series" name="series_id">
                                    @forelse($documentSeries as $series)
                                        <option value="{{ $series->id }}" data-prefix="{{ $series->prefix }}" data-next="{{ $series->next_number }}" @selected($series->is_default)>{{ $series->code ?? $series->prefix }} ({{ $series->prefix }}{{ $series->next_number }})</option>
                                    @empty
                                        <option value="">Default Series</option>
                                    @endforelse
                                </select>
                            </div>

                            <!-- Warehouse -->
                            <div class="field-item">
                                <label for="warehouse">Warehouse *</label>
                                <select id="warehouse" required>
                                    @foreach($warehouses as $warehouse)
                                        <option value="{{ $warehouse->id }}" @selected($project && $warehouse->id === $project->site_warehouse_id)>{{ $warehouse->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <!-- Agent / Through -->
                            <div class="field-item">
                                <div class="label-with-action">
                                    <label for="agent">Agent / Through</label>
                                    <button type="button" class="link-btn-add" data-inline="agents" title="Add Agent">+</button>
                                </div>
                                <select id="agent" name="agent_id">
                                    <option value="">-- Direct --</option>
                                    @foreach($agents as $ag)
                                        <option value="{{ $ag->id }}" data-rate="{{ $ag->commission_rate }}">{{ $ag->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <!-- Area / Route -->
                            <div class="field-item">
                                <div class="label-with-action">
                                    <label for="area">Area / Route</label>
                                    <button type="button" class="link-btn-add" data-inline="areas" title="Add Area">+</button>
                                </div>
                                <select id="area" name="area_id">
                                    <option value="">-- General Area --</option>
                                    @foreach($areas as $ar)
                                        <option value="{{ $ar->id }}">{{ $ar->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <!-- Purchase Specific Inward Fields -->
                            @if($kind === 'purchase')
                            <div class="field-item">
                                <label for="supplier-invoice-no">Supplier Inv No</label>
                                <input id="supplier-invoice-no" name="supplier_invoice_no" maxlength="100" placeholder="Vendor Invoice #">
                            </div>
                            <div class="field-item">
                                <label for="supplier-invoice-date">Supplier Inv Date</label>
                                <input id="supplier-invoice-date" name="supplier_invoice_date" type="date">
                            </div>
                            <div class="field-item">
                                <label for="receipt-status">Receipt status</label>
                                <select id="receipt-status">
                                    <option value="1">Received</option>
                                    <option value="2">Partial</option>
                                    <option value="3">Pending</option>
                                    <option value="4">Unbilled order</option>
                                </select>
                            </div>
                            @endif

                            @if(config('compliance.enabled'))
                            <div class="field-item">
                                <label for="place-of-supply">Place of supply</label>
                                <input id="place-of-supply" maxlength="2" pattern="[0-9]{2}" placeholder="State code">
                            </div>
                            <div class="field-item" style="justify-content: center;">
                                <label class="checkbox-inline" for="reverse-charge">
                                    <input id="reverse-charge" type="checkbox"> Reverse charge
                                </label>
                            </div>
                            @endif
                        </div>
                    </div>

                    <!-- PARTY FINANCIAL HUD STRIP -->
                    <section class="desk-card hud-strip" aria-label="Party balance">
                        <div class="hud-item">
                            <span class="hud-label">Previous outstanding</span>
                            <strong id="previous" class="hud-value">—</strong>
                        </div>
                        <div class="hud-item">
                            <span class="hud-label">Current invoice</span>
                            <strong id="current" class="hud-value">₹ 0.00</strong>
                        </div>
                        <div class="hud-item">
                            <span class="hud-label">Total to settle</span>
                            <strong id="combined" class="hud-value">—</strong>
                        </div>
                        @if($kind === 'sale')
                        <div class="hud-item hud-credit-info">
                            <span class="hud-label">Credit Status</span>
                            <span id="credit-summary" class="credit-pill">Select a customer to view credit balance.</span>
                        </div>
                        @endif
                    </section>

                    <!-- ITEMS SECTION (Matching Screenshot) -->
                    <div class="desk-card items-container">
                        <!-- ITEMS SECTION HEADER BAR -->
                        <div class="items-section-header">
                            <div class="items-counter-group">
                                <span class="items-title">ITEMS</span>
                                <span class="items-meta-badge" id="items-meta-count">1 line(s) • 7 per page</span>
                            </div>

                            <div class="items-controls-group">
                                <!-- Density Selector -->
                                <div class="density-segmented">
                                    <button type="button" class="density-btn" data-density="compact">Compact</button>
                                    <button type="button" class="density-btn active" data-density="cozy">Cozy</button>
                                    <button type="button" class="density-btn" data-density="large">Large</button>
                                </div>

                                <button type="button" class="btn-control-ghost">
                                    <span class="control-icon">☷</span> Columns (12)
                                </button>
                                <button type="button" class="btn-control-ghost">
                                    <span class="control-icon">⇋</span> Multi item
                                </button>
                                <button type="button" class="btn-control-ghost" data-inline="products" title="Create Item (F6)">
                                    + Create item
                                </button>
                                <button type="button" class="btn-control-purple" id="btn-add-item-row">
                                    + Add row
                                </button>
                            </div>
                        </div>

                        <!-- QUICK SEARCH BAR FOR BARCODE / ITEM INPUT -->
                        <div class="item-quick-search-bar">
                            <div class="search product-search">
                                <svg class="search-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                                <input id="product-search" autocomplete="off" placeholder="Scan barcode, enter item code or name... (Press Enter to add)" aria-controls="product-results">
                                <div id="product-results" class="results" aria-label="Item search results"></div>
                            </div>
                            <span class="search-tip">Tip: Press F6 for new item · Alt+UpArrow for rate history</span>
                        </div>

                        <!-- ITEMS GRID TABLE -->
                        <div class="table-responsive-neo">
                            <table class="desk-grid-table cozy" id="grid-table">
                                <thead>
                                    <tr>
                                        <th scope="col" style="width: 50px;">S.NO</th>
                                        <th scope="col" style="min-width: 220px;">ITEM</th>
                                        <th scope="col" style="min-width: 130px;">{{ $kind === 'sale' ? 'SALES TYPE' : 'PURCHASE TYPE' }}</th>
                                        <th scope="col" style="width: 100px;">UNIT</th>
                                        <th scope="col" style="width: 90px;">QTY</th>
                                        <th scope="col" style="width: 110px;">RATE + TAX</th>
                                        <th scope="col" style="width: 110px;">RATE</th>
                                        <th scope="col" style="width: 120px;">TAXABLE AMOUNT</th>
                                        <th scope="col" style="width: 110px;">GST / IGST %</th>
                                        <th scope="col" style="width: 110px;">TAX AMOUNT</th>
                                        <th scope="col" style="width: 120px;">LINE TOTAL</th>
                                        <th scope="col" style="width: 80px; text-align: center;">ACTIONS</th>
                                    </tr>
                                </thead>
                                <tbody id="item-lines">
                                    <!-- Populated by JavaScript -->
                                </tbody>
                            </table>
                            <div id="empty-lines" class="table-empty-placeholder">
                                <p>No items added yet. Type or scan an item in the search box above or click "+ Add row".</p>
                            </div>
                        </div>

                        <!-- TABLE PAGINATION FOOTER -->
                        <div class="table-pagination-footer">
                            <span class="pagination-arrow">&lt;</span>
                            <span class="pagination-page">Page 1/14</span>
                            <span class="pagination-arrow">&gt;</span>
                        </div>
                    </div>

                    <!-- HIDDEN / PERSISTED CONTROLS FOR JAVASCRIPT COMPATIBILITY -->
                    <div style="display:none;" aria-hidden="true">
                        <select id="method">
                            <option value="Credit" selected>Credit</option>
                            <option value="Cash">Cash</option>
                            <option value="Bank">Bank</option>
                            <option value="Cheque">Cheque</option>
                            <option value="Credit Card">Credit Card</option>
                        </select>
                        <select id="account">
                            @foreach($accounts as $account)
                                <option value="{{ $account->id }}">{{ $account->name }}</option>
                            @endforeach
                        </select>
                        <input id="paid" type="number" value="0">
                        <input id="discount" type="number" value="0">
                        <input id="freight" type="number" value="0">
                        <input id="sundry-amount" type="number" value="0">
                        <select id="bill-sundry-select">
                            <option value="">-- Select Sundry --</option>
                            @foreach($billSundries as $bs)
                                <option value="{{ $bs->id }}" data-calc="{{ $bs->calculation_type }}" data-val="{{ $bs->default_value }}">{{ $bs->name }}</option>
                            @endforeach
                        </select>
                        <input id="bale-no" name="bale_no">
                        <input id="no-of-bales" name="no_of_bales" type="number">
                        <input id="lr-number" name="lr_no">
                        <input id="lr-date" name="lr_date" type="date">
                        <input id="transport" name="transport_name">
                        <input id="station-to" name="station_to">
                        <input id="order-no" name="order_no">
                        <input id="credit-days" name="credit_days" type="number">
                        <select id="standard-remark">
                            <option value="">-- Select Predefined Remark --</option>
                            @foreach($remarks as $rm)
                                <option value="{{ $rm->remark }}">{{ $rm->title }}</option>
                            @endforeach
                        </select>
                        <textarea id="note"></textarea>
                        @if($kind === 'sale')<input id="override" name="credit_override_reason">@endif
                        @if($kind === 'purchase')
                        <select id="landed-method"><option value="value">By value</option><option value="quantity">By quantity</option><option value="weight">By weight</option><option value="manual">Manual</option></select>
                        <input id="purchase-order" type="number">
                        <input id="receipt-number">
                        <input id="update-cost" type="checkbox">
                        <input id="update-hsn" type="checkbox">
                        @endif
                        <button type="button" id="restore-draft">Restore draft</button>
                        <button type="button" id="save-draft">Save draft</button>
                        <button type="button" id="clone">Clone prior bill</button>
                        <button type="button" id="pending">Pending bills</button>
                        <button type="submit" id="post">Post invoice</button>
                    </div>

                    <!-- FIXED BOTTOM ACTION & SUMMARY BAR (Matching Screenshot) -->
                    <footer class="desk-sticky-footer">
                        <div class="footer-left">
                            <button type="button" class="btn-charges-remarks" id="btn-open-charges-drawer">
                                <span>Charges & remarks</span>
                                <span class="badge-count" id="charges-count-badge">0</span>
                            </button>
                            <span class="footer-sub-note">Bill remarks: Add transport, bale and shipping details</span>
                        </div>

                        <div class="footer-center-actions">
                            <button type="button" class="btn-footer-action" id="btn-discard-bill">
                                <span class="footer-icon">↺</span> Discard
                            </button>
                            <button type="button" class="btn-footer-action" id="btn-footer-save-draft">
                                <span class="footer-icon">💾</span> Save as
                            </button>
                            <button type="button" class="btn-footer-purple" id="btn-footer-post">
                                <span class="footer-icon">💾</span> Save
                            </button>
                            <button type="button" class="btn-footer-action" id="btn-footer-review">
                                Review
                            </button>
                        </div>

                        <div class="footer-right-totals">
                            <div class="total-chunk">
                                <span class="total-lbl">NET</span>
                                <span class="total-val" id="summary-net">₹ 0.00</span>
                            </div>
                            <div class="total-chunk">
                                <span class="total-lbl">GST / TAX</span>
                                <span class="total-val" id="summary-tax">₹ 0.00</span>
                            </div>
                            <div class="total-chunk grand-total-chunk">
                                <span class="total-lbl">GRAND TOTAL</span>
                                <strong class="total-val-grand" id="summary-grand">₹ 0.00</strong>
                            </div>
                        </div>
                    </footer>
                </form>
            </main>
        </div>
    </div>
</div>

<!-- CHARGES, TRANSPORT, BILL SUNDRIES & REMARKS DRAWER -->
<dialog id="charges-drawer" class="desk-drawer">
    <div class="drawer-header">
        <h3>Charges, Transport, Sundries &amp; Remarks</h3>
        <button type="button" class="drawer-close-btn" data-close>✕</button>
    </div>
    <div class="drawer-body">
        <div class="drawer-tabs">
            <button type="button" class="drawer-tab-btn active" data-dtab="sundries">Bill Sundries</button>
            <button type="button" class="drawer-tab-btn" data-dtab="transport">Transport &amp; Bales</button>
            <button type="button" class="drawer-tab-btn" data-dtab="remarks">Terms &amp; Notes</button>
            <button type="button" class="drawer-tab-btn" data-dtab="settlement">Payment Settlement</button>
        </div>

        <!-- TAB 1: BILL SUNDRIES -->
        <div class="drawer-tab-content active" id="dtab-sundries">
            <div class="sundry-box-heading">
                <strong>Bill Sundries &amp; Additional Charges</strong>
                <button type="button" class="link-btn-add" data-inline="bill-sundries">+ New Sundry</button>
            </div>
            <div class="drawer-fields-grid">
                <label for="drawer-discount">Invoice discount
                    <input id="drawer-discount" type="number" min="0" step="0.0001" value="0">
                </label>
                <label for="drawer-freight">Freight / Shipping
                    <input id="drawer-freight" type="number" min="0" step="0.0001" value="0">
                </label>
                <label for="drawer-sundry-select">Apply Bill Sundry
                    <select id="drawer-sundry-select">
                        <option value="">-- Select Sundry --</option>
                        @foreach($billSundries as $bs)
                            <option value="{{ $bs->id }}" data-calc="{{ $bs->calculation_type }}" data-val="{{ $bs->default_value }}">{{ $bs->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label for="drawer-sundry-amount">Sundry Amount / %
                    <input id="drawer-sundry-amount" type="number" step="0.0001" value="0">
                </label>
            </div>
        </div>

        <!-- TAB 2: TRANSPORT & BALES -->
        <div class="drawer-tab-content" id="dtab-transport">
            <div class="sundry-box-heading">
                <strong>Transport Details &amp; Add-Ins</strong>
            </div>
            <div class="drawer-fields-grid">
                <label for="drawer-bale-no">Bale NO
                    <input id="drawer-bale-no" maxlength="100" placeholder="e.g. MJ-22">
                </label>
                <label for="drawer-no-of-bales">No Of Bales
                    <input id="drawer-no-of-bales" type="number" min="0" step="1" placeholder="e.g. 4">
                </label>
                <label for="drawer-lr-number">LR No
                    <input id="drawer-lr-number" maxlength="100" placeholder="Lorry Receipt #">
                </label>
                <label for="drawer-lr-date">LR Date
                    <input id="drawer-lr-date" type="date">
                </label>
                <label for="drawer-transport">Transport / Carrier
                    <input id="drawer-transport" maxlength="150" placeholder="Transporter name">
                </label>
                <label for="drawer-station-to">Station To
                    <input id="drawer-station-to" maxlength="150" placeholder="Destination city / station">
                </label>
                <label for="drawer-order-no">Order NO
                    <input id="drawer-order-no" maxlength="100" placeholder="Order / PO #">
                </label>
                @if($kind === 'sale')
                <label for="drawer-credit-days">Credit Days
                    <input id="drawer-credit-days" type="number" min="0" step="1" placeholder="e.g. 30">
                </label>
                @endif
            </div>
        </div>

        <!-- TAB 3: REMARKS & TERMS -->
        <div class="drawer-tab-content" id="dtab-remarks">
            <div class="sundry-box-heading">
                <strong>Standard Remark / Terms</strong>
                <button type="button" class="link-btn-add" data-inline="remarks">+ New Remark</button>
            </div>
            <div class="drawer-fields-grid" style="grid-template-columns: 1fr;">
                <label for="drawer-standard-remark">Predefined Remark
                    <select id="drawer-standard-remark">
                        <option value="">-- Select Predefined Remark --</option>
                        @foreach($remarks as $rm)
                            <option value="{{ $rm->remark }}">{{ $rm->title }}</option>
                        @endforeach
                    </select>
                </label>
                <label for="drawer-note">Note / Terms Description
                    <textarea id="drawer-note" rows="3" placeholder="Enter custom invoice remarks..."></textarea>
                </label>
            </div>
        </div>

        <!-- TAB 4: SETTLEMENT -->
        <div class="drawer-tab-content" id="dtab-settlement">
            <div class="sundry-box-heading">
                <strong>Initial Payment Settlement</strong>
            </div>
            <div class="drawer-fields-grid">
                <label for="drawer-paid">Initial payment
                    <input id="drawer-paid" type="number" min="0" step="0.0001" value="0">
                </label>
                <label for="drawer-method">Payment method
                    <select id="drawer-method">
                        <option>Credit</option>
                        <option>Cash</option>
                        <option>Bank</option>
                        <option>Cheque</option>
                        <option>Credit Card</option>
                    </select>
                </label>
                <label for="drawer-account">Payment account
                    <select id="drawer-account">
                        @foreach($accounts as $account)
                            <option value="{{ $account->id }}">{{ $account->name }}</option>
                        @endforeach
                    </select>
                </label>
                @if($kind === 'sale')
                <label for="drawer-override">Credit override reason
                    <input id="drawer-override" maxlength="500">
                </label>
                @endif
            </div>
        </div>
    </div>
    <div class="drawer-footer">
        <button type="button" class="primary" data-close>Done</button>
    </div>
</dialog>

<!-- DIALOGS & MODALS (INLINE MASTER CREATION & TRACKING) -->
<dialog id="info-dialog">
    <div class="dialog-heading">
        <h2 id="info-title">Details</h2>
        <button type="button" data-close aria-label="Close details">Close</button>
    </div>
    <div id="info-content"></div>
</dialog>

<dialog id="inline-dialog">
    <form id="inline-form">
        <div class="dialog-heading">
            <h2 id="inline-title">New master</h2>
            <button type="button" data-close>Close</button>
        </div>
        <label for="master-name">Name / Title
            <input id="master-name" name="name" required maxlength="150">
        </label>
        
        <div id="party-fields">
            <label for="master-phone">Phone<input id="master-phone" name="phone_number"></label>
            <label for="master-city">City<input id="master-city" name="city"></label>
            <label for="master-alias">Search alias<input id="master-alias" name="search_alias" maxlength="100"></label>
            <label for="master-address">Address<input id="master-address" name="address"></label>
            <label for="master-area">Area / Route
                <select id="master-area" name="area_id">
                    <option value="">-- Select Area --</option>
                    @foreach($areas as $ar)<option value="{{ $ar->id }}">{{ $ar->name }}</option>@endforeach
                </select>
            </label>
            <label for="master-agent">Agent / Through
                <select id="master-agent" name="agent_id">
                    <option value="">-- Direct --</option>
                    @foreach($agents as $ag)<option value="{{ $ag->id }}">{{ $ag->name }}</option>@endforeach
                </select>
            </label>
            @if($kind === 'sale')
            <label for="master-credit-days">Credit days<input id="master-credit-days" name="credit_days" type="number" min="0" max="3650" value="0"></label>
            <label for="master-credit-limit">Credit limit<input id="master-credit-limit" name="credit_limit" type="number" min="0" step="0.0001" value="0"></label>
            <label for="master-group">Customer group<select id="master-group" name="customer_group_id">@foreach($groups as $group)<option value="{{ $group->id }}">{{ $group->name }}</option>@endforeach</select></label>
            @endif
        </div>

        <div id="product-fields" hidden>
            <label for="master-code">Code<input id="master-code" name="code"></label>
            <label for="master-category">Category<select id="master-category" name="category_id">@foreach($categories as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach</select></label>
            <label for="master-unit">Unit<select id="master-unit" name="unit_id">@foreach($units as $unit)<option value="{{ $unit->id }}">{{ $unit->unit_name }}</option>@endforeach</select></label>
            <label for="master-price">Price<input id="master-price" name="price" type="number" min="0" step="0.0001" value="0"></label>
            <label for="master-cost">Cost<input id="master-cost" name="cost" type="number" min="0" step="0.0001" value="0"></label>
        </div>

        <div id="agent-fields" hidden>
            <label for="agent-code">Agent Code<input id="agent-code" name="code" maxlength="50"></label>
            <label for="agent-phone">Phone<input id="agent-phone" name="phone" maxlength="50"></label>
            <label for="agent-commission">Commission Rate (%)<input id="agent-commission" name="commission_rate" type="number" step="0.01" min="0" max="100" value="0"></label>
        </div>

        <div id="area-fields" hidden>
            <label for="area-code">Area Code<input id="area-code" name="code" maxlength="50"></label>
            <label for="area-city">City<input id="area-city" name="city" maxlength="100"></label>
            <label for="area-pincode">Pincode<input id="area-pincode" name="pincode" maxlength="20"></label>
        </div>

        <div id="sundry-fields" hidden>
            <label for="sundry-nature">Nature
                <select id="sundry-nature" name="nature">
                    <option value="{{ $kind === 'sale' ? 'sales' : 'purchase' }}">{{ ucfirst($kind) }} Only</option>
                    <option value="both">Both Sales & Purchase</option>
                </select>
            </label>
            <label for="sundry-calc">Calculation Type
                <select id="sundry-calc" name="calculation_type">
                    <option value="percentage">Percentage (%)</option>
                    <option value="amount">Fixed Amount</option>
                </select>
            </label>
            <label for="sundry-default">Default Value / Rate<input id="sundry-default" name="default_value" type="number" step="0.0001" value="0"></label>
            <label for="sundry-tax">GST Tax Rate (%)<input id="sundry-tax" name="tax_rate" type="number" step="0.01" min="0" max="100" value="0"></label>
        </div>

        <div id="sale-type-fields" hidden>
            <label for="st-code">Code<input id="st-code" name="code" maxlength="50"></label>
            <label for="st-nature">Tax Nature
                <select id="st-nature" name="tax_nature">
                    <option value="local">Local GST</option>
                    <option value="interstate">Interstate IGST</option>
                    <option value="export">Export</option>
                    <option value="sez">SEZ</option>
                    <option value="exempted">Exempted</option>
                </select>
            </label>
            <label for="st-rate">Tax Rate (%)<input id="st-rate" name="tax_rate" type="number" step="0.01" min="0" max="100" value="18"></label>
        </div>

        <div id="purchase-type-fields" hidden>
            <label for="pt-code">Code<input id="pt-code" name="code" maxlength="50"></label>
            <label for="pt-nature">Tax Nature
                <select id="pt-nature" name="tax_nature">
                    <option value="local">Local GST</option>
                    <option value="interstate">Interstate IGST</option>
                    <option value="import">Import</option>
                    <option value="exempted">Exempted</option>
                </select>
            </label>
            <label for="pt-rate">Tax Rate (%)<input id="pt-rate" name="tax_rate" type="number" step="0.01" min="0" max="100" value="18"></label>
        </div>

        <div id="remark-fields" hidden>
            <label for="remark-text">Remark / Terms Text<textarea id="remark-text" name="remark" rows="3"></textarea></label>
        </div>

        <div id="series-fields" hidden>
            <label for="series-code">Series Code / Name *<input id="series-code" name="code" maxlength="50" placeholder="e.g. SALES-2, MJ-23"></label>
            <label for="series-prefix">Prefix<input id="series-prefix" name="prefix" maxlength="100" placeholder="e.g. MJ-, INV-"></label>
            <label for="series-start">Start No<input id="series-start" name="next_number" type="number" min="1" value="1"></label>
        </div>

        <p id="inline-error" role="alert"></p>
        <button class="primary" type="submit">Create and select</button>
    </form>
</dialog>

<template id="line-unit-options">
    <select>
        @foreach($units as $unit)
            <option value="{{ $unit->id }}">{{ $unit->unit_name }}</option>
        @endforeach
    </select>
</template>

<dialog id="tracking-dialog">
    <form id="tracking-form">
        <h2>Stock tracking and freight</h2>
        <p>Enter the identities for this line. Stock rules validate these when posting.</p>
        <label for="serials">Serials (comma separated)<textarea id="serials"></textarea></label>
        <label for="batch-id">Existing batch ID<input id="batch-id" type="number" min="1"></label>
        @if($kind === 'purchase')
        <label for="batch-no">New batch number<input id="batch-no"></label>
        <label for="expiry">Expiry date<input id="expiry" type="date"></label>
        @if($industry['profile']==='fmcg')
        <label for="mfg-date">Manufacturing date<input id="mfg-date" type="date"></label>
        <label for="batch-mrp">Batch MRP<input id="batch-mrp" type="number" min="0" step="0.0001"></label>
        @endif
        <label for="hsn-code">Item HSN<input id="hsn-code" inputmode="numeric" maxlength="8"></label>
        <label for="weight">Line weight<input id="weight" type="number" min="0" step="0.0001"></label>
        <label for="manual-freight">Manual freight allocation<input id="manual-freight" type="number" min="0" step="0.0001"></label>
        @if($dimensionsEnabled)
        <fieldset>
            <legend>New dimensioned piece</legend>
            <label for="piece-uom">Dimension unit
                <select id="piece-uom">
                    @foreach(['mm','cm','m','in','ft'] as $dimensionUnit)
                        <option value="{{ $dimensionUnit }}" @selected($dimensionUnit===($industry['settings']['dimension_unit']??'mm'))>{{ $dimensionUnit }}</option>
                    @endforeach
                </select>
            </label>
            <label for="piece-count">Number of pieces<input id="piece-count" type="number" min="1" step="1" value="1"></label>
            <label for="piece-grade">Grade<input id="piece-grade" maxlength="50"></label>
            <label for="piece-number">Piece number<input id="piece-number" maxlength="100"></label>
            <div class="fields">
                <label for="piece-length">Length<input id="piece-length" type="number" min="0.000001" step="0.000001"></label>
                <label for="piece-width">Width<input id="piece-width" type="number" min="0.000001" step="0.000001"></label>
                <label for="piece-thickness">Thickness<input id="piece-thickness" type="number" min="0.000001" step="0.000001"></label>
            </div>
        </fieldset>
        @endif
        @endif
        @if($kind==='sale' && $dimensionsEnabled)
        <label for="piece-filter">Find piece (species, grade or dimensions)<input id="piece-filter" placeholder="Grade or species"></label>
        <button type="button" id="find-pieces">Find available pieces</button>
        <label for="piece-select">Available piece<select id="piece-select"><option value="">Find pieces first</option></select></label>
        @endif
        @if($kind==='sale' && $schemes->isNotEmpty())
        <label for="quantity-scheme">Quantity scheme
            <select id="quantity-scheme">
                <option value="">No scheme</option>
                @foreach($schemes as $scheme)
                    <option value="{{ $scheme->id }}" data-product="{{ $scheme->product_id }}">{{ $scheme->name }} · {{ $scheme->buy_qty }}+{{ $scheme->free_qty }}</option>
                @endforeach
            </select>
        </label>
        @endif
        <label for="identity">Stock identity ID<input id="identity" type="number" min="1"></label>
        <label for="variant">Variant ID<input id="variant" type="number" min="1"></label>
        <button type="button" data-close>Cancel</button>
        <button type="submit" class="primary">Apply</button>
    </form>
</dialog>

</body>
</html>
