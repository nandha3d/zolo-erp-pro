@extends('backend.layout.main') 

@section('content')

<style type="text/css">
    .btn-icon i { margin-right: 5px; }
    .top-fields { margin-top: 10px; position: relative; }
    .top-fields label { background: #FFF; font-size: 11px; font-weight: 600; margin-left: 10px; padding: 0 3px; position: absolute; top: -8px; z-index: 9; }
    .top-fields input { font-size: 13px; height: 45px; }
</style>

<x-success-message key="message" />
<x-error-message key="not_permitted" />

<link rel="stylesheet" href="<?php echo asset('css/commercial-entry.css') . '?v=' . (file_exists(public_path('css/commercial-entry.css')) ? filemtime(public_path('css/commercial-entry.css')) : time()) ?>" type="text/css">
<link rel="stylesheet" href="<?php echo asset('css/commercial-workspace.css') . '?v=' . (file_exists(public_path('css/commercial-workspace.css')) ? filemtime(public_path('css/commercial-workspace.css')) : time()) ?>" type="text/css">
<script>
    document.documentElement.classList.add('commercial-screen-lock');
    document.body.classList.add('commercial-screen-lock');
</script>
<div id="comm-progress-bar"></div>

<section class="commercial-workspace-view">
    <!-- Top Command Bar -->
    <div class="comm-command-bar">
        <div class="comm-title-group">
            <h1><i class="dripicons-cart text-primary"></i> {{ __('Sales') }} Command Center</h1>
            <span class="comm-title-badge"><i class="dripicons-wallet"></i> Invoices & Counter Orders</span>
        </div>
        <ul class="comm-nav-pills">
            <li><a class="nav-link active" id="tab-all-sales" href="javascript:void(0)" data-sale-type="0"><i class="dripicons-list"></i> {{ __('db.All') }} Invoices</a></li>
            <li><a class="nav-link" id="tab-pos-sales" href="{{ route('sale.pos') }}"><i class="dripicons-shopping-bag"></i> POS / Counter</a></li>
            <li><a class="nav-link" href="{{ route('challan.index') }}"><i class="dripicons-box"></i> {{ __('Delivery Challans') }}</a></li>
            <li><a class="nav-link" href="{{ route('quotations.index') }}"><i class="dripicons-document-edit"></i> {{ __('db.Quotation') }}</a></li>
        </ul>
        <div class="comm-actions">
            <button type="button" class="btn btn-outline-secondary py-1 px-3" id="btn-top-new" title="Add New Sale">
                <i class="dripicons-plus"></i> {{ __('db.Add Sale') }}
            </button>
            <button type="button" class="btn btn-primary py-1 px-3" id="toggle-drawer-btn" title="Toggle Bill List Panel (Alt+D)" style="background:#7c3aed; border-color:#7c3aed; color:#fff;">
                <i class="dripicons-view-list"></i> Bill list
            </button>
        </div>
    </div>

    <!-- 2-Column Command Center Grid: Main Entry Workspace + Side Bill List Panel -->
    <div class="comm-split-grid" id="comm-split-grid">
        <!-- Main: Sales Entry Workspace (Matching Screenshot 3) -->
        <div class="comm-entry-workspace" id="comm-entry-workspace">
            <form id="sale-entry-form" method="POST" action="{{ route('sales.store') }}" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="_method" id="entry-form-method" value="POST">
                <input type="hidden" name="sale_id" id="edit-sale-id" value="">
                <input type="hidden" name="sale_status" id="sale-status-val" value="1">
                <input type="hidden" name="exchange_rate" id="exchange-rate-val" value="{{ $currency->exchange_rate ?? 1 }}">
                <input type="hidden" name="currency_id" id="currency-id-val" value="{{ $currency->id ?? 1 }}">
                <input type="hidden" name="paying_method" id="input-paying-method" value="Credit">
                <input type="hidden" name="pos" value="0">
                <input type="hidden" name="total_qty" id="hidden-total-qty" value="0">
                <input type="hidden" name="total_discount" id="hidden-total-discount" value="0">
                <input type="hidden" name="total_tax" id="hidden-total-tax" value="0">
                <input type="hidden" name="total_price" id="hidden-total-price" value="0">
                <input type="hidden" name="order_tax" id="hidden-order-tax" value="0">
                <input type="hidden" name="grand_total" id="hidden-grand-total" value="0">
                <input type="hidden" name="payment_status" id="hidden-payment-status" value="1">
                <input type="hidden" name="paid_amount" id="hidden-paid-amount" value="0">
                @if(config('commercial.enabled'))<input type="hidden" name="idempotency_key" value="{{ (string) Illuminate\Support\Str::uuid() }}">@endif

                <!-- 1. Header Strip (Matching Screenshot 3) -->
                <div class="doc-header-strip">
                    <div class="doc-title-block">
                        <div class="doc-title-meta">
                            <div class="desk-breadcrumb-trail" style="font-size:11px; color:#64748b; margin-bottom:2px;">
                                <a href="{{ url('/') }}" style="color:#64748b;">Home</a> / <a href="{{ url('/sales') }}" style="color:#64748b;">Selling</a> / <a href="{{ url('/sales') }}" style="color:#64748b;">Sales Bills</a> / <span id="doc-breadcrumb-mode" class="text-primary font-weight-bold">New</span>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <div class="doc-icon-box" style="width:34px;height:34px;font-size:16px;">📄</div>
                                <div>
                                    <h2 id="doc-title-text" style="font-size:17px;font-weight:700;margin:0;color:#0f172a;line-height:1.2;">New Sales Bill</h2>
                                    <span class="doc-sub" id="doc-sub-text" style="font-size:11px;color:#64748b;">Sale • New bill</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Mode Toggles (Cash/Credit & Line Nature) -->
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

                    <!-- Metadata & Header Tools -->
                    <div class="doc-header-meta">
                        <div class="meta-terms">
                            <span>Due <strong id="display-due-date">{{ date('d-m-Y') }}</strong></span>
                            <span>Credit days <strong id="header-credit-days">—</strong></span>
                            <span>Terms <strong>Standard</strong></span>
                        </div>
                        <div class="header-action-btns">
                            <button type="button" class="btn-desk-action" id="btn-header-toggle-list" title="Toggle Bill List">
                                📖 Bill list
                            </button>
                            <button type="button" class="btn-desk-action" id="btn-open-details" title="Open Charges & Transport Details">
                                ⚙ Details
                            </button>
                        </div>
                    </div>
                </div>

                <!-- 2. Primary Document Fields (Row 1 matching Screenshot 3) -->
                <div class="desk-card doc-primary-fields mb-2" style="background:#ffffff;border:1px solid #e2e8f0;border-radius:8px;padding:12px 16px;">
                    <div class="fields-grid-neo">
                        <!-- Our Bill Number -->
                        <div class="field-item">
                            <label for="reference_no">Our bill number</label>
                            <input type="text" id="reference_no" name="reference_no" class="form-control" placeholder="Series number assigned on save" autocomplete="off">
                            <span class="field-hint">Series preview • editable</span>
                        </div>

                        <!-- Customer PO / Reference -->
                        <div class="field-item">
                            <label for="customer_po_no">Customer PO / Ref</label>
                            <input type="text" id="customer_po_no" name="customer_po_no" class="form-control" placeholder="From purchase order">
                        </div>

                        <!-- Bill Date -->
                        <div class="field-item">
                            <label for="bill_date">Bill date</label>
                            <input type="date" id="bill_date" name="created_at" class="form-control" value="{{ date('Y-m-d') }}">
                        </div>

                        <!-- Entry Date -->
                        <div class="field-item">
                            <label for="entry_date">Entry date</label>
                            <input type="date" id="entry_date" name="entry_date" class="form-control" value="{{ date('Y-m-d') }}">
                        </div>

                        <!-- Party (Customer) -->
                        <div class="field-item field-item-wide">
                            <div class="label-with-action">
                                <label for="customer_id">Party *</label>
                                <a href="{{ route('customer.index') }}" target="_blank" class="link-btn-add">+ New Party</a>
                            </div>
                            <select id="customer_id" name="customer_id" class="form-control selectpicker" data-live-search="true" title="Select Customer" required>
                                @foreach($lims_customer_list as $customer)
                                    <option value="{{ $customer->id }}">{{ $customer->name }} ({{ $customer->phone_number ?? 'No Phone' }})</option>
                                @endforeach
                            </select>
                            <span class="field-hint" id="party-hint-note">Party address loads automatically</span>
                        </div>

                        <!-- Tax Classification -->
                        <div class="field-item">
                            <label for="sale_type_id">Tax classification *</label>
                            <select id="sale_type_id" name="sale_type_id" class="form-control">
                                <option value="0">GST • Multiple rates</option>
                                @foreach($saleTypes as $st)
                                    <option value="{{ $st->id }}">{{ $st->name }}</option>
                                @endforeach
                            </select>
                            <span class="field-hint text-muted">Choose each item's tax slab</span>
                        </div>

                        <!-- Series -->
                        <div class="field-item">
                            <label for="series_id">Series</label>
                            <select id="series_id" name="series_id" class="form-control">
                                <option value="0">Sale</option>
                                @foreach($documentSeries as $ds)
                                    <option value="{{ $ds->id }}">{{ $ds->prefix ?? $ds->code }}</option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Warehouse Selection -->
                        <div class="field-item">
                            <label for="form_warehouse_id">Warehouse *</label>
                            <select id="form_warehouse_id" name="warehouse_id" class="form-control" required>
                                @foreach($lims_warehouse_list as $wh)
                                    <option value="{{ $wh->id }}">{{ $wh->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Biller Selection -->
                        <div class="field-item">
                            <label for="form_biller_id">Biller *</label>
                            <select id="form_biller_id" name="biller_id" class="form-control" required>
                                @foreach($lims_biller_list as $biller)
                                    <option value="{{ $biller->id }}">{{ $biller->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                <!-- 3. ITEMS Section (Matching Screenshot 3) -->
                <div class="desk-card items-container mb-2" style="background:#ffffff;border:1px solid #e2e8f0;border-radius:8px;padding:0;overflow:hidden;">
                    <div class="items-section-header" style="padding:8px 14px;border-bottom:1px solid #e2e8f0;display:flex;align-items:center;justify-content:space-between;background:#f8fafc;">
                        <div class="items-counter-group" style="display:flex;align-items:center;gap:8px;">
                            <span class="items-title" style="font-weight:700;font-size:12px;color:#0f172a;">ITEMS</span>
                            <span class="items-meta-badge text-muted" id="items-meta-count" style="font-size:11px;">0 line(s) • 7 per page</span>
                        </div>
                        <div class="items-controls-group" style="display:flex;align-items:center;gap:6px;">
                            <div class="density-segmented">
                                <button type="button" class="density-btn" data-density="compact">Compact</button>
                                <button type="button" class="density-btn active" data-density="cozy">Cozy</button>
                                <button type="button" class="density-btn" data-density="large">Large</button>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-secondary py-1 px-2" style="font-size:11px;" id="btn-multi-item">
                                <i class="dripicons-menu"></i> Multi item
                            </button>
                            <a href="{{ route('products.create') }}" target="_blank" class="btn btn-sm btn-outline-primary py-1 px-2" style="font-size:11px;">
                                + Create item
                            </a>
                            <button type="button" class="btn btn-sm btn-primary py-1 px-2" style="font-size:11px;background:#7c3aed;border-color:#7c3aed;" id="btn-add-item-row">
                                + Add row
                            </button>
                        </div>
                    </div>

                    <!-- Quick Barcode / Item Search Bar -->
                    <div class="item-quick-search-bar" style="padding:6px 14px;background:#ffffff;border-bottom:1px solid #f1f5f9;display:flex;align-items:center;gap:10px;">
                        <div style="position:relative;flex:1;">
                            <i class="fa fa-barcode text-muted" style="position:absolute;left:10px;top:50%;transform:translateY(-50%);font-size:14px;"></i>
                            <input type="text" id="lims_productcodeSearch" class="form-control" placeholder="Scan barcode, enter item code or name... (Press Enter or Alt+UpArrow for search)" style="padding-left:32px;height:30px;font-size:12px;" autocomplete="off">
                        </div>
                        <span style="font-size:11px;color:#94a3b8;"><kbd style="background:#f1f5f9;color:#475569;border:1px solid #cbd5e1;padding:1px 4px;font-size:10px;">F2</kbd> Quick Search</span>
                    </div>

                    <!-- Items Grid Table -->
                    <div class="table-responsive" style="max-height: calc(100vh - 430px); min-height: 180px; overflow-y:auto;">
                        <table class="desk-grid-table cozy table table-sm mb-0" id="order-table" style="width:100%;">
                            <thead>
                                <tr style="background:#7c3aed;color:#ffffff;font-size:11px;">
                                    <th style="width:40px;text-align:center;">#</th>
                                    <th style="min-width:220px;">ITEM</th>
                                    <th style="width:130px;">SALE TYPE</th>
                                    <th style="width:80px;">UNIT</th>
                                    <th style="width:100px;text-align:right;">RATE</th>
                                    <th style="width:80px;text-align:center;">QTY</th>
                                    <th style="width:110px;text-align:right;">AMOUNT</th>
                                    <th style="width:120px;">TAX</th>
                                    <th style="width:110px;text-align:right;">TOTAL</th>
                                    <th style="width:70px;text-align:center;">ACTIONS</th>
                                </tr>
                            </thead>
                            <tbody id="order-table-body">
                                <!-- Initial Blank Row matching Screenshot 3 -->
                                <tr class="empty-placeholder-row">
                                    <td colspan="10" class="text-center text-muted py-4" style="font-size:12px;">
                                        No items added yet. Search or scan above or click "+ Add row".
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="table-pagination-footer" style="padding:4px 14px;border-top:1px solid #f1f5f9;display:flex;justify-content:center;gap:10px;font-size:11px;color:#64748b;background:#f8fafc;">
                        <span class="pagination-arrow">&lt;</span>
                        <span>Page 1/1</span>
                        <span class="pagination-arrow">&gt;</span>
                    </div>
                </div>

                <!-- 4. Fixed Bottom Action & Summary Bar (Matching Screenshot 3) -->
                <div class="desk-summary-bottom-bar" style="background:#ffffff;border:1px solid #e2e8f0;border-radius:8px;padding:8px 14px;display:flex;align-items:center;justify-content:space-between;box-shadow:0 -2px 10px rgba(0,0,0,0.03);">
                    <div class="d-flex align-items-center gap-3">
                        <button type="button" class="btn btn-outline-secondary py-1 px-3 d-inline-flex align-items-center gap-2" id="btn-bottom-charges" style="font-size:11.5px;font-weight:600;">
                            <span>Charges & remarks</span>
                            <span class="badge badge-light" id="charges-badge-count">0</span>
                        </button>
                        <span class="text-muted" style="font-size:11px;" id="remarks-summary-preview">Remarks: Add transport, LR and bale details</span>
                    </div>

                    <div class="d-flex align-items-center gap-2">
                        <button type="button" class="btn btn-outline-secondary py-1 px-3" id="btn-form-discard" style="font-size:12px;font-weight:600;">
                            <i class="dripicons-clockwise"></i> Discard
                        </button>
                        <button type="button" class="btn btn-outline-secondary py-1 px-3" id="btn-form-save-as" style="font-size:12px;font-weight:600;">
                            <i class="dripicons-copy"></i> Save as
                        </button>
                        <button type="submit" class="btn btn-primary py-1 px-3" id="btn-form-save" style="font-size:12px;font-weight:700;background:#7c3aed;border-color:#7c3aed;">
                            <i class="dripicons-document-edit"></i> Save
                        </button>
                        <button type="button" class="btn btn-success py-1 px-3" id="btn-form-submit" style="font-size:12px;font-weight:700;background:#4338ca;border-color:#4338ca;">
                            <i class="dripicons-checkmark"></i> Submit
                        </button>
                        <button type="button" class="btn btn-outline-secondary py-1 px-2" id="btn-form-review" style="font-size:12px;">
                            Review
                        </button>
                    </div>

                    <div class="d-flex align-items-center gap-3">
                        <div style="text-align:right;">
                            <div style="font-size:9.5px;font-weight:700;color:#64748b;letter-spacing:0.04em;">NET</div>
                            <div style="font-size:13px;font-weight:700;color:#0f172a;" id="display-net-amount">₹ 0.00</div>
                        </div>
                        <div style="text-align:right;">
                            <div style="font-size:9.5px;font-weight:700;color:#64748b;letter-spacing:0.04em;">GST / TAX</div>
                            <div style="font-size:13px;font-weight:700;color:#0f172a;" id="display-tax-amount">₹ 0.00</div>
                        </div>
                        <div style="text-align:right;border-left:1px solid #cbd5e1;padding-left:12px;">
                            <div style="font-size:9.5px;font-weight:800;color:#d97706;letter-spacing:0.04em;">GRAND TOTAL</div>
                            <div style="font-size:16px;font-weight:800;color:#d97706;" id="display-grand-total">₹ 0.00</div>
                        </div>
                    </div>
                </div>

                <!-- Hidden inputs for Optech details -->
                <div style="display:none;">
                    <input type="text" name="bale_no" id="hidden-bale-no">
                    <input type="number" name="no_of_bales" id="hidden-no-of-bales">
                    <input type="text" name="lr_no" id="hidden-lr-no">
                    <input type="date" name="lr_date" id="hidden-lr-date">
                    <input type="text" name="transporter_name" id="hidden-transporter-name">
                    <input type="text" name="station_to" id="hidden-station-to">
                    <input type="text" name="order_no" id="hidden-order-no">
                    <input type="number" name="credit_days" id="hidden-credit-days">
                    <input type="text" name="sale_note" id="hidden-note">
                    <input type="text" name="staff_note" id="hidden-staff-note">
                    <select name="account_id" id="hidden-account-id">
                        @foreach($lims_account_list as $acc)
                            <option value="{{ $acc->id }}">{{ $acc->name }}</option>
                        @endforeach
                    </select>
                </div>
            </form>
        </div>

        <!-- Right / Dockable Side Panel: Bill List with Screenshot 2 Filters & Dropdowns -->
        <aside class="comm-drawer-card desk-bill-list-panel" id="comm-drawer" aria-label="Transaction List Panel">
            <div class="comm-drawer-header">
                <div class="comm-drawer-title-wrap">
                    <h3 class="comm-drawer-title"><i class="dripicons-view-list text-primary"></i> Bill list</h3>
                </div>
                <div class="comm-drawer-tools">
                    <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2" id="btn-side-new" style="font-size:11px; height:24px; display:inline-flex; align-items:center;" title="Create New Sale (Clear Form)">
                        <i class="dripicons-plus"></i> ++ New
                    </button>
                    <button type="button" class="side-icon-btn" id="btn-dock-toggle" title="Arrange Panel Side (Dock Left / Dock Right)">
                        <span id="dock-icon">⇄</span>
                        <span class="dock-tooltip" id="dock-label">Dock Left</span>
                    </button>
                    <button type="button" class="side-icon-btn text-muted" id="close-drawer-btn" title="Collapse Panel">✕</button>
                </div>
            </div>

            <div class="comm-drawer-body">
                <!-- Toolbar Export & Action Icons (Screenshot 2) -->
                <div class="side-toolbar-actions">
                    <button type="button" class="side-tool-btn text-danger" id="side-export-pdf" title="Export PDF"><i class="fa fa-file-pdf-o"></i></button>
                    <button type="button" class="side-tool-btn text-success" id="side-export-excel" title="Export Excel"><i class="fa fa-file-excel-o"></i></button>
                    <button type="button" class="side-tool-btn text-info" id="side-export-csv" title="Export CSV"><i class="fa fa-file-text-o"></i></button>
                    <button type="button" class="side-tool-btn text-primary" id="side-export-print" title="Print Register"><i class="fa fa-print"></i></button>
                    <button type="button" class="side-tool-btn text-danger" id="side-filter-reset" title="Reset Filters"><i class="fa fa-times"></i></button>
                </div>

                <!-- Search Filter -->
                <div class="side-panel-search">
                    <input type="text" id="side-search-input" class="form-control" placeholder="Find a bill..." autocomplete="off">
                </div>

                <!-- Series / Bill Number Filter -->
                <div class="side-panel-filter-label">BILL NUMBER</div>
                <div class="side-panel-series-filter">
                    <input type="text" id="side-filter-series" class="form-control" placeholder="Series or edited number" autocomplete="off">
                </div>

                <!-- Filter Tabs -->
                <div class="side-filter-tabs">
                    <button type="button" class="side-tab active" data-filter="all">All</button>
                    <button type="button" class="side-tab" data-filter="draft">Draft</button>
                    <button type="button" class="side-tab" data-filter="date">Date</button>
                    <button type="button" class="side-tab" data-filter="range">Range</button>
                </div>

                <!-- Date Inputs (revealed if Date/Range selected) -->
                <div class="side-date-picker-box" id="side-date-picker-box" style="display:none; padding:4px 0;">
                    <input type="date" id="side-filter-date-val" class="form-control" style="height:28px; font-size:11px;" value="{{ date('Y-m-d') }}" />
                </div>

                <!-- Dropdown Filters (Moved from Top Bar) -->
                <div class="side-dropdown-filters">
                    <div class="side-filter-group">
                        <label>Warehouse</label>
                        <select id="side-filter-warehouse" class="form-control">
                            <option value="0">All Warehouse</option>
                            @foreach($lims_warehouse_list as $wh)
                                <option value="{{ $wh->id }}">{{ $wh->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="side-filter-group">
                        <label>Sale Status</label>
                        <select id="side-filter-status" class="form-control">
                            <option value="0">All</option>
                            <option value="1">Completed</option>
                            <option value="2">Pending</option>
                            <option value="4">Returned</option>
                        </select>
                    </div>
                    <div class="side-filter-group">
                        <label>Payment Status</label>
                        <select id="side-filter-payment" class="form-control">
                            <option value="0">All</option>
                            <option value="1">Pending</option>
                            <option value="2">Due</option>
                            <option value="3">Partial</option>
                            <option value="4">Paid</option>
                        </select>
                    </div>
                </div>

                <!-- Recent Sales List -->
                <div class="side-list-container" id="side-bill-list">
                    @forelse($recent_bills as $bill)
                        @php
                            $isPaid = ($bill->payment_status == 4 || ($bill->grand_total > 0 && $bill->paid_amount >= $bill->grand_total));
                            $isPartial = ($bill->payment_status == 3 || ($bill->paid_amount > 0 && $bill->paid_amount < $bill->grand_total));
                            $isDraft = ($bill->sale_status == 3);
                            $statusLabel = $isDraft ? 'Draft' : ($isPaid ? 'Paid' : ($isPartial ? 'Partial' : 'Due'));
                            $statusClass = $isDraft ? 'draft' : ($isPaid ? 'paid' : ($isPartial ? 'partial' : 'due'));
                            $customerName = $bill->customer->name ?? 'Walk-in Customer';
                        @endphp
                        <div class="side-bill-card" data-bill-id="{{ $bill->id }}" data-ref="{{ strtolower($bill->reference_no ?? '') }}" data-party="{{ strtolower($customerName) }}" data-status="{{ strtolower($statusLabel) }}" data-status-id="{{ $bill->sale_status }}" data-payment-status-id="{{ $bill->payment_status }}" data-warehouse-id="{{ $bill->warehouse_id }}" data-date="{{ substr($bill->created_at ?? '', 0, 10) }}">
                            <div class="side-card-top">
                                <strong class="side-card-ref">{{ $bill->reference_no ?? ('#'.$bill->id) }}</strong>
                                <span class="side-card-amount">₹ {{ number_format($bill->grand_total ?? 0, 2) }}</span>
                            </div>
                            <div class="side-card-party">
                                <i class="dripicons-user" style="font-size:10px; color:#94a3b8;"></i> {{ $customerName }}
                            </div>
                            <div class="side-card-bottom">
                                <span class="side-card-date"><i class="dripicons-calendar" style="font-size:10px;"></i> {{ substr($bill->created_at ?? '', 0, 10) }}</span>
                                <span class="side-card-status {{ $statusClass }}">{{ $statusLabel }}</span>
                            </div>
                            <div class="side-card-actions">
                                <a href="javascript:void(0)" class="side-action-btn edit btn-side-load-edit" data-id="{{ $bill->id }}" title="Edit this bill in main area">
                                    <i class="dripicons-document-edit"></i> Edit
                                </a>
                                <a href="javascript:void(0)" class="side-action-btn view btn-side-view" data-id="{{ $bill->id }}" title="View bill details">
                                    <i class="dripicons-preview"></i> View
                                </a>
                                <a href="javascript:void(0)" class="side-action-btn print btn-side-print" data-id="{{ $bill->id }}" title="Print invoice">
                                    <i class="dripicons-print"></i> Print
                                </a>
                            </div>
                        </div>
                    @empty
                        <div class="side-empty-state">
                            <p>No bills match these filters</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </aside>
    </div>
</section>

<!-- Slide-Over Drawer: Charges, Transport & Remarks Modal -->
<div class="modal fade right" id="charges-drawer" tabindex="-1" role="dialog" aria-labelledby="chargesDrawerLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-slideout modal-md" role="document">
        <div class="modal-content" style="border-radius:10px 0 0 10px; border:none; box-shadow: -4px 0 20px rgba(0,0,0,0.15);">
            <div class="modal-header" style="background:#f8fafc; border-bottom:1px solid #e2e8f0; padding:12px 18px;">
                <h5 class="modal-title font-weight-bold" id="chargesDrawerLabel" style="font-size:14px; color:#0f172a;">
                    <i class="dripicons-gear text-primary mr-1"></i> Charges, Transport & Remarks
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="font-size:18px;">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" style="padding:16px 20px; font-size:12px;">
                <ul class="nav nav-tabs mb-3" id="drawerTabs" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active font-weight-bold" id="tab-transport" data-toggle="tab" href="#pane-transport" role="tab" style="font-size:11.5px; padding:6px 12px;">Transport & Logistics</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link font-weight-bold" id="tab-remarks" data-toggle="tab" href="#pane-remarks" role="tab" style="font-size:11.5px; padding:6px 12px;">Notes & Remarks</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link font-weight-bold" id="tab-settlement" data-toggle="tab" href="#pane-settlement" role="tab" style="font-size:11.5px; padding:6px 12px;">Payment</a>
                    </li>
                </ul>

                <div class="tab-content" id="drawerTabContent">
                    <!-- Transport Tab -->
                    <div class="tab-pane fade show active" id="pane-transport" role="tabpanel">
                        <div class="form-row">
                            <div class="form-group col-md-6 mb-2">
                                <label class="text-muted font-weight-bold">Bale No</label>
                                <input type="text" id="drawer-bale-no" class="form-control form-control-sm" placeholder="e.g. BL-101">
                            </div>
                            <div class="form-group col-md-6 mb-2">
                                <label class="text-muted font-weight-bold">No. of Bales</label>
                                <input type="number" id="drawer-no-of-bales" class="form-control form-control-sm" placeholder="e.g. 5">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-6 mb-2">
                                <label class="text-muted font-weight-bold">LR No.</label>
                                <input type="text" id="drawer-lr-no" class="form-control form-control-sm" placeholder="Lorry Receipt No">
                            </div>
                            <div class="form-group col-md-6 mb-2">
                                <label class="text-muted font-weight-bold">LR Date</label>
                                <input type="date" id="drawer-lr-date" class="form-control form-control-sm" value="{{ date('Y-m-d') }}">
                            </div>
                        </div>
                        <div class="form-group mb-2">
                            <label class="text-muted font-weight-bold">Transporter Name</label>
                            <input type="text" id="drawer-transporter-name" class="form-control form-control-sm" placeholder="e.g. VRL Logistics">
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-6 mb-2">
                                <label class="text-muted font-weight-bold">Station To / Destination</label>
                                <input type="text" id="drawer-station-to" class="form-control form-control-sm" placeholder="Delivery station">
                            </div>
                            <div class="form-group col-md-6 mb-2">
                                <label class="text-muted font-weight-bold">Order No</label>
                                <input type="text" id="drawer-order-no" class="form-control form-control-sm" placeholder="Purchase/Work Order">
                            </div>
                        </div>
                        <div class="form-group mb-2">
                            <label class="text-muted font-weight-bold">Credit Days</label>
                            <input type="number" id="drawer-credit-days" class="form-control form-control-sm" placeholder="e.g. 30">
                        </div>
                    </div>

                    <!-- Remarks Tab -->
                    <div class="tab-pane fade" id="pane-remarks" role="tabpanel">
                        <div class="form-group mb-2">
                            <label class="text-muted font-weight-bold">Standard Remark</label>
                            <select id="drawer-std-remark-select" class="form-control form-control-sm mb-2">
                                <option value="">-- Choose Preset Remark --</option>
                                @foreach($standardRemarks as $sr)
                                    <option value="{{ $sr->remark }}">{{ $sr->remark }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group mb-2">
                            <label class="text-muted font-weight-bold">Custom Remarks / Notes</label>
                            <textarea id="drawer-note" class="form-control form-control-sm" rows="4" placeholder="Enter terms, delivery instructions, or notes..."></textarea>
                        </div>
                    </div>

                    <!-- Payment / Account Tab -->
                    <div class="tab-pane fade" id="pane-settlement" role="tabpanel">
                        <div class="form-group mb-2">
                            <label class="text-muted font-weight-bold">Paying Account</label>
                            <select id="drawer-account-id" class="form-control form-control-sm">
                                @foreach($lims_account_list as $acc)
                                    <option value="{{ $acc->id }}">{{ $acc->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group mb-2">
                            <label class="text-muted font-weight-bold">Paid Outflow Amount (₹)</label>
                            <input type="number" step="0.01" id="drawer-paid-amount" class="form-control form-control-sm" value="0.00">
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer" style="background:#f8fafc; border-top:1px solid #e2e8f0; padding:10px 18px;">
                <button type="button" class="btn btn-sm btn-secondary" data-dismiss="modal">Close</button>
                <button type="button" class="btn btn-sm btn-primary" id="btn-save-drawer-details" data-dismiss="modal" style="background:#7c3aed; border-color:#7c3aed;">Apply Details</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Sale Details Preview -->
<div id="sale-details" tabindex="-1" role="dialog" aria-labelledby="saleDetailsLabel" aria-hidden="true" class="modal fade text-left">
    <div role="document" class="modal-dialog modal-lg">
      <div class="modal-content" style="border-radius:10px; border:none; box-shadow:0 10px 25px rgba(0,0,0,0.15);">
        <div class="container mt-3 pb-2 border-bottom">
            <div class="row align-items-center">
                <div class="col-md-3">
                    <button id="sale-print-btn" type="button" class="btn btn-outline-secondary btn-sm"><i class="dripicons-print"></i> {{__('db.Print')}}</button>
                </div>
                <div class="col-md-6 text-center">
                    <h3 id="saleDetailsLabel" class="modal-title font-weight-bold" style="font-size:16px;">Sale Voucher Details</h3>
                </div>
                <div class="col-md-3 text-right">
                    <button type="button" id="close-sale-modal-btn" data-dismiss="modal" aria-label="Close" class="close"><span aria-hidden="true">&times;</span></button>
                </div>
            </div>
        </div>
        <div id="sale-content" class="modal-body"></div>
        <br>
        <div class="table-responsive document-lines px-3" tabindex="0" role="region" aria-label="{{__('db.Sale Details')}}">
            <table class="table table-bordered product-sale-list">
                <thead>
                    <th>#</th>
                    <th>{{__('db.product')}}</th>
                    <th>{{__('db.Batch No')}}</th>
                    <th>Qty</th>
                    <th>{{__('db.Returned')}}</th>
                    <th>{{__('db.Unit Price')}}</th>
                    <th>{{__('db.Tax')}}</th>
                    <th>{{__('db.Discount')}}</th>
                    <th>{{__('db.Subtotal')}}</th>
                </thead>
                <tbody>
                </tbody>
            </table>
        </div>
        <div id="sale-footer" class="modal-body"></div>
      </div>
    </div>
</div>

@endsection

@push('scripts')
<script type="text/javascript">
(function() {
    'use strict';

    // --- Product List Autocomplete Data ---
    var productArray = [];
    var lims_product_code = [
        @foreach($lims_product_list_without_variant as $product)
            "{{ htmlspecialchars($product->code) }}|{{ preg_replace('/[\n\r]/', ' ', htmlspecialchars($product->name)) }}",
        @endforeach
        @foreach($lims_product_list_with_variant as $product)
            "{{ htmlspecialchars($product->item_code) }}|{{ preg_replace('/[\n\r]/', ' ', htmlspecialchars($product->name)) }}",
        @endforeach
    ];

    // --- State variables ---
    var rowCounter = 0;
    var taxList = @json($lims_tax_list);
    var decimalPlaces = {{ $general_setting->decimal ?? 2 }};

    // --- Autocomplete setup ---
    $('#lims_productcodeSearch').autocomplete({
        source: function(request, response) {
            var matcher = new RegExp($.ui.autocomplete.escapeRegex(request.term), "i");
            response($.grep(lims_product_code, function(item) {
                return matcher.test(item);
            }).slice(0, 20));
        },
        select: function(event, ui) {
            fetchProductAndAddRow(ui.item.value);
            $(this).val('');
            return false;
        }
    }).on('keydown', function(e) {
        if (e.which === 13) {
            e.preventDefault();
            var val = $(this).val().trim();
            if (val) {
                fetchProductAndAddRow(val);
                $(this).val('');
            }
        }
    });

    // Quick F2 focus
    $(document).on('keydown', function(e) {
        if (e.which === 113) { // F2
            e.preventDefault();
            $('#lims_productcodeSearch').focus();
        }
    });

    // --- Fetch Product via AJAX & Add Row ---
    function fetchProductAndAddRow(searchTerm) {
        $.ajax({
            type: 'GET',
            url: '{{ route("product_sale.search") }}',
            data: { data: searchTerm },
            success: function(data) {
                if (data && data.length) {
                    addProductRow({
                        product_id: data[9],
                        product_name: data[0],
                        product_code: data[1],
                        price: parseFloat(data[2]) || 0,
                        tax_rate: parseFloat(data[3]) || 0,
                        unit: (data[6] ? data[6].split(',')[0] : 'Unit'),
                        qty: 1
                    });
                } else {
                    alert('Product not found: ' + searchTerm);
                }
            },
            error: function() {
                // Fallback manual line
                addProductRow({
                    product_id: 0,
                    product_name: searchTerm.split('|')[1] || searchTerm,
                    product_code: searchTerm.split('|')[0] || '',
                    price: 0,
                    tax_rate: 0,
                    unit: 'Unit',
                    qty: 1
                });
            }
        });
    }

    // --- Add Row to Items Grid Table ---
    function addProductRow(item) {
        // Remove empty placeholder row if exists
        $('#order-table-body .empty-placeholder-row').remove();

        rowCounter++;
        var rate = item.price || 0;
        var qty = item.qty || 1;
        var taxRate = item.tax_rate || 0;
        var amount = rate * qty;
        var taxAmount = amount * (taxRate / 100);
        var lineTotal = amount + taxAmount;

        var tr = $(`
            <tr class="order-item-row" data-row-id="${rowCounter}">
                <td style="text-align:center;font-weight:600;color:#64748b;">${$('#order-table-body tr').length + 1}</td>
                <td>
                    <div style="font-weight:600;color:#0f172a;">${item.product_name}</div>
                    <small style="color:#64748b;">${item.product_code}</small>
                    <input type="hidden" name="product_id[]" value="${item.product_id}">
                    <input type="hidden" name="product_code[]" value="${item.product_code}">
                </td>
                <td>
                    <span class="grid-type-pill" style="background:#e0f2fe; color:#0369a1; border-color:#bae6fd;">Sale</span>
                </td>
                <td>
                    <span class="badge badge-light border" style="font-size:11px;">${item.unit || 'Unit'}</span>
                    <input type="hidden" name="sale_unit[]" value="${item.unit || 'Unit'}">
                </td>
                <td style="text-align:right;">
                    <input type="number" name="net_unit_price[]" class="form-control form-control-sm row-rate text-right" style="height:26px;font-size:12px;padding:2px 6px;" value="${rate.toFixed(decimalPlaces)}" step="0.01">
                </td>
                <td style="text-align:center;">
                    <input type="number" name="qty[]" class="form-control form-control-sm row-qty text-center" style="height:26px;font-size:12px;padding:2px 4px;max-width:70px;margin:auto;" value="${qty}" step="any" min="0.01">
                </td>
                <td style="text-align:right;font-weight:600;color:#0f172a;">
                    <span class="row-amount-display">₹ ${amount.toFixed(decimalPlaces)}</span>
                    <input type="hidden" name="subtotal[]" class="row-subtotal-input" value="${lineTotal.toFixed(decimalPlaces)}">
                </td>
                <td>
                    <div class="d-flex align-items-center gap-1">
                        <select name="tax_rate[]" class="form-control form-control-sm row-tax-rate" style="height:26px;font-size:11px;padding:1px 4px;">
                            <option value="0" ${taxRate == 0 ? 'selected' : ''}>0%</option>
                            <option value="5" ${taxRate == 5 ? 'selected' : ''}>5%</option>
                            <option value="12" ${taxRate == 12 ? 'selected' : ''}>12%</option>
                            <option value="18" ${taxRate == 18 ? 'selected' : ''}>18%</option>
                            <option value="28" ${taxRate == 28 ? 'selected' : ''}>28%</option>
                        </select>
                        <input type="hidden" name="tax[]" class="row-tax-amount-input" value="${taxAmount.toFixed(decimalPlaces)}">
                    </div>
                </td>
                <td style="text-align:right;font-weight:700;color:#059669;">
                    <span class="row-total-display">₹ ${lineTotal.toFixed(decimalPlaces)}</span>
                </td>
                <td style="text-align:center;">
                    <button type="button" class="btn btn-sm btn-outline-danger py-0 px-1 btn-delete-row" title="Delete Row" style="height:24px;font-size:11px;">
                        <i class="dripicons-trash"></i>
                    </button>
                    <input type="hidden" name="discount[]" value="0">
                </td>
            </tr>
        `);

        $('#order-table-body').append(tr);
        recalcTableSummary();
    }

    // Manual Add Row
    $('#btn-add-item-row').on('click', function() {
        $('#lims_productcodeSearch').focus();
    });

    // Delete Row
    $(document).on('click', '.btn-delete-row', function() {
        $(this).closest('tr').remove();
        reindexRows();
        recalcTableSummary();
    });

    // Live Row Calculation on Change
    $(document).on('input change', '.row-rate, .row-qty, .row-tax-rate', function() {
        var tr = $(this).closest('tr');
        var rate = parseFloat(tr.find('.row-rate').val()) || 0;
        var qty = parseFloat(tr.find('.row-qty').val()) || 0;
        var taxRate = parseFloat(tr.find('.row-tax-rate').val()) || 0;

        var amount = rate * qty;
        var taxAmount = amount * (taxRate / 100);
        var lineTotal = amount + taxAmount;

        tr.find('.row-amount-display').text('₹ ' + amount.toFixed(decimalPlaces));
        tr.find('.row-tax-amount-input').val(taxAmount.toFixed(decimalPlaces));
        tr.find('.row-subtotal-input').val(lineTotal.toFixed(decimalPlaces));
        tr.find('.row-total-display').text('₹ ' + lineTotal.toFixed(decimalPlaces));

        recalcTableSummary();
    });

    function reindexRows() {
        var rows = $('#order-table-body tr.order-item-row');
        if (rows.length === 0) {
            $('#order-table-body').html(`
                <tr class="empty-placeholder-row">
                    <td colspan="10" class="text-center text-muted py-4" style="font-size:12px;">
                        No items added yet. Search or scan above or click "+ Add row".
                    </td>
                </tr>
            `);
        } else {
            rows.each(function(index) {
                $(this).find('td:first').text(index + 1);
            });
        }
    }

    // --- Recalculate Table Summary Totals ---
    function recalcTableSummary() {
        var net = 0;
        var tax = 0;
        var grand = 0;
        var totalQty = 0;
        var count = 0;

        $('#order-table-body tr.order-item-row').each(function() {
            count++;
            var rate = parseFloat($(this).find('.row-rate').val()) || 0;
            var qty = parseFloat($(this).find('.row-qty').val()) || 0;
            var taxRate = parseFloat($(this).find('.row-tax-rate').val()) || 0;
            var amount = rate * qty;
            var taxAmount = amount * (taxRate / 100);

            net += amount;
            tax += taxAmount;
            grand += (amount + taxAmount);
            totalQty += qty;
        });

        $('#display-net-amount').text('₹ ' + net.toFixed(decimalPlaces));
        $('#display-tax-amount').text('₹ ' + tax.toFixed(decimalPlaces));
        $('#display-grand-total').text('₹ ' + grand.toFixed(decimalPlaces));
        $('#items-meta-count').text(count + ' line(s) • 7 per page');

        $('#hidden-total-qty').val(totalQty);
        $('#hidden-total-price').val(net.toFixed(decimalPlaces));
        $('#hidden-total-tax').val(tax.toFixed(decimalPlaces));
        $('#hidden-grand-total').val(grand.toFixed(decimalPlaces));
    }

    // --- Load Sale To Form for In-Place Editing ---
    window.loadSaleToForm = function(id) {
        $('#comm-progress-bar').addClass('active');
        $.ajax({
            type: 'GET',
            url: '{{ url("sales") }}/' + id + '/json',
            dataType: 'json',
            success: function(res) {
                $('#comm-progress-bar').removeClass('active').addClass('done');
                setTimeout(() => $('#comm-progress-bar').removeClass('done'), 300);

                if (!res || !res.sale) {
                    alert('Could not load sale details.');
                    return;
                }

                var s = res.sale;
                // Switch Form to Update Mode
                $('#entry-form-method').val('PUT');
                $('#edit-sale-id').val(s.id);
                $('#sale-entry-form').attr('action', '{{ url("sales") }}/' + s.id);

                // Update UI Labels
                $('#doc-breadcrumb-mode').text('Edit: ' + (s.reference_no || '#' + s.id)).removeClass('text-primary').addClass('text-success');
                $('#doc-title-text').text('Edit Sales Bill: ' + (s.reference_no || '#' + s.id));
                $('#doc-sub-text').text('Editing saved sale record');

                // Populate Header Inputs
                $('#reference_no').val(s.reference_no || '');
                $('#customer_po_no').val(s.customer_po_no || '');
                if (s.created_at) {
                    $('#bill_date').val(s.created_at.slice(0, 10));
                    $('#entry_date').val(s.created_at.slice(0, 10));
                }
                
                $('#customer_id').val(s.customer_id).trigger('change');
                $('.selectpicker').selectpicker('refresh');
                if (s.warehouse_id) $('#form_warehouse_id').val(s.warehouse_id);
                if (s.biller_id) $('#form_biller_id').val(s.biller_id);

                // Highlight active card in side list
                $('.side-bill-card').removeClass('active-editing');
                $('.side-bill-card[data-bill-id="' + id + '"]').addClass('active-editing');

                // Populate Items
                $('#order-table-body').empty();
                rowCounter = 0;
                if (res.items && res.items.length) {
                    res.items.forEach(function(item) {
                        addProductRow({
                            product_id: item.product_id,
                            product_name: item.product_name,
                            product_code: item.product_code,
                            price: parseFloat(item.net_unit_price) || 0,
                            tax_rate: parseFloat(item.tax_rate) || 0,
                            unit: item.unit_code || 'Unit',
                            qty: parseFloat(item.qty) || 1
                        });
                    });
                } else {
                    reindexRows();
                }

                // Scroll smoothly to top of form
                $('#comm-entry-workspace').animate({ scrollTop: 0 }, 200);
            },
            error: function() {
                $('#comm-progress-bar').removeClass('active');
                alert('Failed to fetch sale details from server.');
            }
        });
    };

    // --- Reset Form to Blank New Sale ---
    window.resetFormToNew = function() {
        $('#entry-form-method').val('POST');
        $('#edit-sale-id').val('');
        $('#sale-entry-form').attr('action', '{{ route("sales.store") }}');

        $('#doc-breadcrumb-mode').text('New').removeClass('text-success').addClass('text-primary');
        $('#doc-title-text').text('New Sales Bill');
        $('#doc-sub-text').text('Sale • New bill');

        $('#reference_no').val('');
        $('#customer_po_no').val('');
        $('#bill_date').val('{{ date("Y-m-d") }}');
        $('#entry_date').val('{{ date("Y-m-d") }}');
        $('#customer_id').val('').trigger('change');
        $('.selectpicker').selectpicker('refresh');

        $('.side-bill-card').removeClass('active-editing');
        $('#order-table-body').empty();
        rowCounter = 0;
        reindexRows();
        recalcTableSummary();
    };

    // Actions triggering reset to new
    $('#btn-side-new, #btn-top-new, #btn-form-discard').on('click', function(e) {
        e.preventDefault();
        resetFormToNew();
    });

    // Card click & edit button click
    $(document).on('click', '.side-bill-card', function(e) {
        if ($(e.target).closest('.side-action-btn.view, .side-action-btn.print').length) return;
        var id = $(this).data('bill-id');
        if (id) loadSaleToForm(id);
    });

    $(document).on('click', '.btn-side-load-edit', function(e) {
        e.stopPropagation();
        var id = $(this).data('id');
        if (id) loadSaleToForm(id);
    });

    // --- Slideover Drawer Handlers ---
    $('#btn-open-details, #btn-bottom-charges').on('click', function() {
        $('#charges-drawer').modal('show');
    });

    $('#btn-save-drawer-details').on('click', function() {
        $('#hidden-bale-no').val($('#drawer-bale-no').val());
        $('#hidden-no-of-bales').val($('#drawer-no-of-bales').val());
        $('#hidden-lr-no').val($('#drawer-lr-no').val());
        $('#hidden-lr-date').val($('#drawer-lr-date').val());
        $('#hidden-transporter-name').val($('#drawer-transporter-name').val());
        $('#hidden-station-to').val($('#drawer-station-to').val());
        $('#hidden-order-no').val($('#drawer-order-no').val());
        $('#hidden-credit-days').val($('#drawer-credit-days').val());
        $('#hidden-note').val($('#drawer-note').val());
        $('#hidden-account-id').val($('#drawer-account-id').val());
        $('#hidden-paid-amount').val($('#drawer-paid-amount').val());

        var count = 0;
        if ($('#drawer-bale-no').val()) count++;
        if ($('#drawer-lr-no').val()) count++;
        if ($('#drawer-note').val()) count++;
        if (parseFloat($('#drawer-paid-amount').val()) > 0) count++;
        $('#charges-badge-count').text(count);
        if ($('#drawer-note').val()) {
            $('#remarks-summary-preview').text('Remarks: ' + $('#drawer-note').val().slice(0, 40) + '...');
        }
    });

    // Pill Segmented Mode Toggle (Cash/Credit)
    $('.pill-segmented button[data-mode]').on('click', function() {
        $('.pill-segmented button[data-mode]').removeClass('active');
        $(this).addClass('active');
        var mode = $(this).data('mode');
        $('#input-paying-method').val(mode);
    });

    // Density segmented
    $('.density-segmented button').on('click', function() {
        $('.density-segmented button').removeClass('active');
        $(this).addClass('active');
        var d = $(this).data('density');
        $('#order-table').removeClass('compact cozy large').addClass(d);
    });

    // --- Side Panel Filtering Functions ---
    function filterSideBills() {
        var query = $('#side-search-input').val().toLowerCase().trim();
        var seriesQuery = $('#side-filter-series').val().toLowerCase().trim();
        var activeTab = $('.side-filter-tabs .side-tab.active').data('filter') || 'all';
        var dateVal = $('#side-filter-date-val').val();
        var whId = $('#side-filter-warehouse').val();
        var statusId = $('#side-filter-status').val();
        var paymentId = $('#side-filter-payment').val();

        var visibleCount = 0;
        $('#side-bill-list .side-bill-card').each(function() {
            var $c = $(this);
            var ref = $c.data('ref') || '';
            var party = $c.data('party') || '';
            var status = $c.data('status') || '';
            var cardStatusId = String($c.data('status-id') || '');
            var cardPayId = String($c.data('payment-status-id') || '');
            var cardWhId = String($c.data('warehouse-id') || '');
            var cardDate = $c.data('date') || '';

            var matchSearch = !query || ref.indexOf(query) !== -1 || party.indexOf(query) !== -1;
            var matchSeries = !seriesQuery || ref.indexOf(seriesQuery) !== -1;
            var matchTab = true;

            if (activeTab === 'draft') matchTab = (status === 'draft');
            else if (activeTab === 'date') matchTab = (dateVal && cardDate === dateVal);

            var matchWh = (!whId || whId == '0' || cardWhId === whId);
            var matchStatus = (!statusId || statusId == '0' || cardStatusId === statusId);
            var matchPayment = (!paymentId || paymentId == '0' || cardPayId === paymentId);

            if (matchSearch && matchSeries && matchTab && matchWh && matchStatus && matchPayment) {
                $c.show();
                visibleCount++;
            } else {
                $c.hide();
            }
        });

        $('.side-empty-state').toggle(visibleCount === 0);
    }

    $('#side-search-input, #side-filter-series').on('input', filterSideBills);
    $('#side-filter-warehouse, #side-filter-status, #side-filter-payment').on('change', filterSideBills);
    $('#side-filter-date-val').on('change', filterSideBills);

    $('.side-filter-tabs .side-tab').on('click', function() {
        $('.side-filter-tabs .side-tab').removeClass('active');
        $(this).addClass('active');
        var f = $(this).data('filter');
        $('#side-date-picker-box').toggle(f === 'date' || f === 'range');
        filterSideBills();
    });

    // Reset toolbar button
    $('#side-filter-reset').on('click', function() {
        $('#side-search-input').val('');
        $('#side-filter-series').val('');
        $('#side-filter-warehouse').val('0');
        $('#side-filter-status').val('0');
        $('#side-filter-payment').val('0');
        $('.side-filter-tabs .side-tab').removeClass('active');
        $('.side-filter-tabs .side-tab[data-filter="all"]').addClass('active');
        $('#side-date-picker-box').hide();
        filterSideBills();
    });

    // Export toolbar buttons
    $('#side-export-print').on('click', function() { window.print(); });
    $('#side-export-excel, #side-export-csv').on('click', function() {
        alert('Exporting recent sales list...');
    });
    $('#side-export-pdf').on('click', function() {
        var activeId = $('#edit-sale-id').val();
        if (activeId) window.open('{{ url("sales/gen_invoice") }}/' + activeId, '_blank');
        else window.print();
    });

    // --- View Modal Handler ---
    $(document).on('click', '.btn-side-view', function(e) {
        e.stopPropagation();
        var id = $(this).data('id');
        if (id) {
            $.get('{{ url("sales/product_sale") }}/' + id, function(data) {
                $(".product-sale-list tbody").empty();
                if (data && data[0]) {
                    var names = data[0];
                    var qtys = data[1];
                    var units = data[2];
                    var taxes = data[3];
                    var subtotals = data[6];
                    for (var i = 0; i < names.length; i++) {
                        $(".product-sale-list tbody").append(`
                            <tr>
                                <td>${i+1}</td>
                                <td>${names[i]}</td>
                                <td>${data[7] ? data[7][i] : 'N/A'}</td>
                                <td>${qtys[i]} ${units[i]}</td>
                                <td>${data[8] ? data[8][i] : 0}</td>
                                <td>${parseFloat(subtotals[i]/qtys[i]).toFixed(decimalPlaces)}</td>
                                <td>${taxes[i]}</td>
                                <td>${data[5] ? data[5][i] : 0}</td>
                                <td>${subtotals[i]}</td>
                            </tr>
                        `);
                    }
                }
                $('#sale-details').modal('show');
            });
        }
    });

    // --- Docking & Drawer Collapse Mechanics ---
    var STORAGE_DOCK_KEY = 'zolo_bill_panel_dock';
    var STORAGE_OPEN_KEY = 'zolo_bill_panel_open';

    function initPanelState() {
        var dock = localStorage.getItem(STORAGE_DOCK_KEY) || 'right';
        var open = localStorage.getItem(STORAGE_OPEN_KEY) !== 'false';
        applyDock(dock);
        applyDrawer(open);
    }

    function applyDock(dock) {
        var grid = document.getElementById('comm-split-grid');
        var label = document.getElementById('dock-label');
        if (!grid) return;
        if (dock === 'left') {
            grid.classList.add('dock-left');
            if (label) label.textContent = 'Dock Right';
        } else {
            grid.classList.remove('dock-left');
            if (label) label.textContent = 'Dock Left';
        }
        localStorage.setItem(STORAGE_DOCK_KEY, dock);
    }

    function applyDrawer(open) {
        var grid = document.getElementById('comm-split-grid');
        var drawer = document.getElementById('comm-drawer');
        if (!grid || !drawer) return;
        if (open) {
            grid.classList.remove('drawer-collapsed');
            drawer.classList.remove('collapsed');
        } else {
            grid.classList.add('drawer-collapsed');
            drawer.classList.add('collapsed');
        }
        localStorage.setItem(STORAGE_OPEN_KEY, open ? 'true' : 'false');
    }

    $('#btn-dock-toggle').on('click', function(e) {
        e.preventDefault();
        var current = localStorage.getItem(STORAGE_DOCK_KEY) || 'right';
        applyDock(current === 'right' ? 'left' : 'right');
    });

    $('#close-drawer-btn').on('click', function(e) {
        e.preventDefault();
        applyDrawer(false);
    });

    $('#toggle-drawer-btn, #btn-header-toggle-list').on('click', function(e) {
        e.preventDefault();
        var isOpen = localStorage.getItem(STORAGE_OPEN_KEY) !== 'false';
        applyDrawer(!isOpen);
    });

    // Form submission buttons
    $('#btn-form-save-as').on('click', function() {
        $('#sale-status-val').val(3); // Draft
        $('#sale-entry-form').submit();
    });

    $('#btn-form-submit').on('click', function() {
        $('#sale-status-val').val(1); // Completed
        $('#sale-entry-form').submit();
    });

    initPanelState();
})();
</script>
@endpush
