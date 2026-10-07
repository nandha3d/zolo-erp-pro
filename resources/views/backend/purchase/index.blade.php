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
            <h1><i class="dripicons-download text-success"></i> {{ __('db.Purchase') }} Command Center</h1>
            <span class="comm-title-badge"><i class="dripicons-box"></i> Procurement & Inward GRN</span>
        </div>
        <ul class="comm-nav-pills">
            <li><a class="nav-link active" id="tab-all-purchases" href="javascript:void(0)" data-status="0"><i class="dripicons-list"></i> {{ __('db.All') }} Purchases</a></li>
            <li><a class="nav-link" href="{{ route('goods-received-notes.index') }}"><i class="dripicons-box"></i> Goods Received Notes (GRN)</a></li>
            <li><a class="nav-link" href="{{ route('transfers.index') }}"><i class="dripicons-swap"></i> Stock Transfers</a></li>
            <li><a class="nav-link" href="{{ route('return-purchase.index') }}"><i class="dripicons-return"></i> {{ __('Purchase Returns') }}</a></li>
        </ul>
        <div class="comm-actions">
            <button type="button" class="btn btn-outline-secondary py-1 px-3" id="btn-top-new" title="Add New Purchase">
                <i class="dripicons-plus"></i> {{ __('db.Add Purchase') }}
            </button>
            <button type="button" class="btn btn-success py-1 px-3" id="toggle-drawer-btn" title="Toggle Purchase List Panel (Alt+D)" style="background:#059669; border-color:#059669; color:#fff;">
                <i class="dripicons-view-list"></i> Purchase list
            </button>
        </div>
    </div>

    <!-- 2-Column Command Center Grid: Main Entry Workspace + Side Bill List Panel -->
    <div class="comm-split-grid" id="comm-split-grid">
        <!-- Main: Purchase Entry Workspace (Matching Screenshot 3) -->
        <div class="comm-entry-workspace" id="comm-entry-workspace">
            <form id="purchase-entry-form" method="POST" action="{{ route('purchases.store') }}" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="_method" id="entry-form-method" value="POST">
                <input type="hidden" name="purchase_id" id="edit-purchase-id" value="">
                <input type="hidden" name="status" id="purchase-status-val" value="1">
                <input type="hidden" name="exchange_rate" id="exchange-rate-val" value="{{ $currency->exchange_rate ?? 1 }}">
                <input type="hidden" name="currency_id" id="currency-id-val" value="{{ $currency->id ?? 1 }}">
                <input type="hidden" name="paying_method" id="input-paying-method" value="Credit">
                <input type="hidden" name="total_qty" id="hidden-total-qty" value="0">
                <input type="hidden" name="total_discount" id="hidden-total-discount" value="0">
                <input type="hidden" name="total_tax" id="hidden-total-tax" value="0">
                <input type="hidden" name="total_cost" id="hidden-total-cost" value="0">
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
                                <a href="{{ url('/') }}" style="color:#64748b;">Home</a> / <a href="{{ url('/purchases') }}" style="color:#64748b;">Buying</a> / <a href="{{ url('/purchases') }}" style="color:#64748b;">Purchase Bills</a> / <span id="doc-breadcrumb-mode" class="text-primary font-weight-bold">New</span>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <div class="doc-icon-box" style="width:34px;height:34px;font-size:16px;">📄</div>
                                <div>
                                    <h2 id="doc-title-text" style="font-size:17px;font-weight:700;margin:0;color:#0f172a;line-height:1.2;">New Purchase Bill</h2>
                                    <span class="doc-sub" id="doc-sub-text" style="font-size:11px;color:#64748b;">Purchase • New bill</span>
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
                            <button type="button" class="btn-desk-action" id="btn-header-toggle-list" title="Toggle Purchase List">
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

                        <!-- Supplier Bill No -->
                        <div class="field-item">
                            <label for="supplier_invoice_no">Supplier bill no</label>
                            <input type="text" id="supplier_invoice_no" name="supplier_invoice_no" class="form-control" placeholder="From the paper invoice">
                        </div>

                        <!-- Bill Date -->
                        <div class="field-item">
                            <label for="supplier_invoice_date">Bill date</label>
                            <input type="date" id="supplier_invoice_date" name="supplier_invoice_date" class="form-control" value="{{ date('Y-m-d') }}">
                        </div>

                        <!-- Entry Date -->
                        <div class="field-item">
                            <label for="created_at">Entry date</label>
                            <input type="date" id="created_at" name="created_at" class="form-control" value="{{ date('Y-m-d') }}">
                        </div>

                        <!-- Party (Supplier) -->
                        <div class="field-item field-item-wide">
                            <div class="label-with-action">
                                <label for="supplier_id">Party *</label>
                                <a href="{{ route('supplier.index') }}" target="_blank" class="link-btn-add">+ New Party</a>
                            </div>
                            <select id="supplier_id" name="supplier_id" class="form-control selectpicker" data-live-search="true" title="Select Supplier" required>
                                @foreach($lims_supplier_list as $supplier)
                                    <option value="{{ $supplier->id }}">{{ $supplier->name }} ({{ $supplier->company_name ?? 'Individual' }})</option>
                                @endforeach
                            </select>
                            <span class="field-hint" id="party-hint-note">Sri Murugan Textiles Private Limited Party address loads automatically</span>
                        </div>

                        <!-- Tax Classification -->
                        <div class="field-item">
                            <label for="purchase_type_id">Tax classification *</label>
                            <select id="purchase_type_id" name="purchase_type_id" class="form-control">
                                <option value="0">GST • Multiple rates</option>
                                @foreach($purchaseTypes as $pt)
                                    <option value="{{ $pt->id }}">{{ $pt->name }}</option>
                                @endforeach
                            </select>
                            <span class="field-hint text-muted">Choose each item's tax slab</span>
                        </div>

                        <!-- Series -->
                        <div class="field-item">
                            <label for="series_id">Series</label>
                            <select id="series_id" name="series_id" class="form-control">
                                <option value="0">Purchase</option>
                                @foreach($documentSeries as $ds)
                                    <option value="{{ $ds->id }}">{{ $ds->prefix ?? $ds->code }}</option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Warehouse Selection (Hidden or compact) -->
                        <div class="field-item">
                            <label for="form_warehouse_id">Warehouse *</label>
                            <select id="form_warehouse_id" name="warehouse_id" class="form-control" required>
                                @foreach($lims_warehouse_list as $wh)
                                    <option value="{{ $wh->id }}">{{ $wh->name }}</option>
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
                            <button type="button" class="btn btn-sm btn-outline-secondary py-1 px-2" style="font-size:11px;" id="btn-multi-item" data-toggle="modal" data-target="#multi-item-modal">
                                <i class="dripicons-menu"></i> Multi item
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-primary py-1 px-2" style="font-size:11px;" id="btn-create-item-modal" data-toggle="modal" data-target="#quick-create-item-modal">
                                + Create item
                            </button>
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
                                    <th style="width:130px;">PURCHASE TYPE</th>
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
                    <input type="text" name="note" id="hidden-note">
                    <select name="account_id" id="hidden-account-id">
                        @foreach($lims_account_list as $acc)
                            <option value="{{ $acc->id }}">{{ $acc->name }}</option>
                        @endforeach
                    </select>
                </div>
            </form>
        </div>

        <!-- Right / Dockable Side Panel: Purchase List with Screenshot 2 Filters & Dropdowns -->
        <aside class="comm-drawer-card desk-bill-list-panel" id="comm-drawer" aria-label="Transaction List Panel">
            <div class="comm-drawer-header">
                <div class="comm-drawer-title-wrap">
                    <h3 class="comm-drawer-title"><i class="dripicons-view-list text-success"></i> Purchase list</h3>
                </div>
                <div class="comm-drawer-tools">
                    <button type="button" class="btn btn-sm btn-outline-success py-0 px-2" id="btn-side-new" style="font-size:11px; height:24px; display:inline-flex; align-items:center;" title="Create New Purchase (Clear Form)">
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
                    <input type="text" id="side-search-input" class="form-control" placeholder="Find a purchase..." autocomplete="off">
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
                        <label>Purchase Status</label>
                        <select id="side-filter-status" class="form-control">
                            <option value="0">All</option>
                            <option value="1">Received</option>
                            <option value="2">Partial</option>
                            <option value="3">Pending</option>
                            <option value="4">Ordered</option>
                        </select>
                    </div>
                    <div class="side-filter-group">
                        <label>Payment Status</label>
                        <select id="side-filter-payment" class="form-control">
                            <option value="0">All</option>
                            <option value="1">Due</option>
                            <option value="2">Paid</option>
                        </select>
                    </div>
                </div>

                <!-- Recent Purchases List -->
                <div class="side-list-container" id="side-bill-list">
                    @forelse($recent_bills as $bill)
                        @php
                            $isPaid = ($bill->payment_status == 2 || ($bill->grand_total > 0 && $bill->paid_amount >= $bill->grand_total));
                            $isPartial = ($bill->payment_status == 1 || ($bill->paid_amount > 0 && $bill->paid_amount < $bill->grand_total));
                            $isDraft = ($bill->status == 3);
                            $statusLabel = $isDraft ? 'Draft' : ($isPaid ? 'Paid' : ($isPartial ? 'Partial' : 'Due'));
                            $statusClass = $isDraft ? 'draft' : ($isPaid ? 'paid' : ($isPartial ? 'partial' : 'due'));
                            $supplierName = $bill->supplier->name ?? 'Default Supplier';
                        @endphp
                        <div class="side-bill-card" data-bill-id="{{ $bill->id }}" data-ref="{{ strtolower($bill->reference_no ?? '') }}" data-party="{{ strtolower($supplierName) }}" data-status="{{ strtolower($statusLabel) }}" data-status-id="{{ $bill->status }}" data-payment-status-id="{{ $bill->payment_status }}" data-warehouse-id="{{ $bill->warehouse_id }}" data-date="{{ substr($bill->created_at ?? '', 0, 10) }}">
                            <div class="side-card-top">
                                <strong class="side-card-ref">{{ $bill->reference_no ?? ('#'.$bill->id) }}</strong>
                                <span class="side-card-amount">₹ {{ number_format($bill->grand_total ?? 0, 2) }}</span>
                            </div>
                            <div class="side-card-party">
                                <i class="dripicons-user" style="font-size:10px; color:#94a3b8;"></i> {{ $supplierName }}
                            </div>
                            <div class="side-card-bottom">
                                <span class="side-card-date"><i class="dripicons-calendar" style="font-size:10px;"></i> {{ substr($bill->created_at ?? '', 0, 10) }}</span>
                                <span class="side-card-status {{ $statusClass }}">{{ $statusLabel }}</span>
                            </div>
                            <div class="side-card-actions">
                                <a href="javascript:void(0)" class="side-action-btn edit btn-side-load-edit" data-id="{{ $bill->id }}" title="Edit this purchase in main area">
                                    <i class="dripicons-document-edit"></i> Edit
                                </a>
                                <a href="javascript:void(0)" class="side-action-btn view btn-side-view" data-id="{{ $bill->id }}" title="View purchase details">
                                    <i class="dripicons-preview"></i> View
                                </a>
                                <a href="javascript:void(0)" class="side-action-btn print btn-side-print" data-id="{{ $bill->id }}" title="Print purchase">
                                    <i class="dripicons-print"></i> Print
                                </a>
                            </div>
                        </div>
                    @empty
                        <div class="side-empty-state">
                            <p>No purchases match these filters</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </aside>
    </div>
</section>

<!-- CHARGES, TRANSPORT & REMARKS MODAL DRAWER -->
<div id="charges-drawer" class="modal fade text-left" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title font-weight-bold"><i class="dripicons-gear text-primary"></i> Charges, Transport, Sundries &amp; Remarks</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <ul class="nav nav-tabs mb-3" id="chargesTab" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" id="tab-transport" data-toggle="tab" href="#content-transport" role="tab">Transport &amp; Bales</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="tab-sundries" data-toggle="tab" href="#content-sundries" role="tab">Bill Sundries</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="tab-remarks" data-toggle="tab" href="#content-remarks" role="tab">Remarks &amp; Notes</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="tab-settlement" data-toggle="tab" href="#content-settlement" role="tab">Settlement</a>
                    </li>
                </ul>
                <div class="tab-content" id="chargesTabContent">
                    <!-- Transport & Bales -->
                    <div class="tab-pane fade show active" id="content-transport" role="tabpanel">
                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label>Bale No</label>
                                <input type="text" id="drawer-bale-no" class="form-control" placeholder="Bale / Package identifier">
                            </div>
                            <div class="col-md-6 form-group">
                                <label>No of Bales / Packages</label>
                                <input type="number" id="drawer-no-of-bales" class="form-control" placeholder="Count of bales">
                            </div>
                            <div class="col-md-6 form-group">
                                <label>LR No / Consignment Note</label>
                                <input type="text" id="drawer-lr-no" class="form-control" placeholder="LR tracking number">
                            </div>
                            <div class="col-md-6 form-group">
                                <label>LR Date</label>
                                <input type="date" id="drawer-lr-date" class="form-control">
                            </div>
                            <div class="col-md-6 form-group">
                                <label>Transporter Name</label>
                                <input type="text" id="drawer-transporter-name" class="form-control" placeholder="Logistics carrier">
                            </div>
                            <div class="col-md-6 form-group">
                                <label>Station To / Destination</label>
                                <input type="text" id="drawer-station-to" class="form-control" placeholder="Delivery destination">
                            </div>
                            <div class="col-md-6 form-group">
                                <label>Order No / Purchase Order</label>
                                <input type="text" id="drawer-order-no" class="form-control" placeholder="PO reference">
                            </div>
                            <div class="col-md-6 form-group">
                                <label>Credit Days</label>
                                <input type="number" id="drawer-credit-days" class="form-control" placeholder="e.g. 30">
                            </div>
                        </div>
                    </div>

                    <!-- Bill Sundries -->
                    <div class="tab-pane fade" id="content-sundries" role="tabpanel">
                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label>Bill Sundry</label>
                                <select id="drawer-bill-sundry-select" class="form-control">
                                    <option value="">-- Select Sundry --</option>
                                    @foreach($billSundries as $bs)
                                        <option value="{{ $bs->id }}" data-val="{{ $bs->default_value }}">{{ $bs->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6 form-group">
                                <label>Amount (₹)</label>
                                <input type="number" id="drawer-sundry-amount" class="form-control" value="0" step="0.01">
                            </div>
                        </div>
                    </div>

                    <!-- Remarks & Notes -->
                    <div class="tab-pane fade" id="content-remarks" role="tabpanel">
                        <div class="form-group">
                            <label>Standard Remark</label>
                            <select id="drawer-standard-remark" class="form-control">
                                <option value="">-- Select Predefined Remark --</option>
                                @foreach($standardRemarks as $rm)
                                    <option value="{{ $rm->remark }}">{{ $rm->title }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Bill Remarks &amp; Notes</label>
                            <textarea id="drawer-note" class="form-control" rows="4" placeholder="Enter notes or shipping instructions..."></textarea>
                        </div>
                    </div>

                    <!-- Settlement -->
                    <div class="tab-pane fade" id="content-settlement" role="tabpanel">
                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label>Settlement Account</label>
                                <select id="drawer-account-id" class="form-control">
                                    @foreach($lims_account_list as $acc)
                                        <option value="{{ $acc->id }}">{{ $acc->name }} [{{ $acc->account_no }}]</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6 form-group">
                                <label>Paid Amount</label>
                                <input type="number" id="drawer-paid-amount" class="form-control" value="0" step="0.01">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" id="btn-save-drawer-details" data-dismiss="modal">Apply &amp; Close</button>
            </div>
        </div>
    </div>
</div>

<!-- VIEW DETAILS MODAL -->
<div id="purchase-details" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true" class="modal fade text-left">
    <div role="document" class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="container mt-3 pb-2 border-bottom">
            <div class="row">
                <div class="col-md-6 d-print-none">
                    <button id="print-btn" type="button" class="btn btn-default btn-sm"><i class="dripicons-print"></i> {{__('db.Print')}}</button>
                </div>
                <div class="col-md-6 d-print-none">
                    <button type="button" id="close-btn" data-dismiss="modal" aria-label="Close" class="close"><span aria-hidden="true"><i class="dripicons-cross"></i></span></button>
                </div>
                <div class="col-md-12">
                    <h3 id="exampleModalLabel" class="modal-title text-center container-fluid">{{$general_setting->site_title}}</h3>
                </div>
                <div class="col-md-12 text-center">
                    <i style="font-size: 15px;">{{__('db.Purchase Details')}}</i>
                </div>
            </div>
        </div>
        <div id="purchase-content" class="modal-body"></div>
        <br>
        <div class="table-responsive document-lines px-3" tabindex="0" role="region" aria-label="{{__('db.Purchase Details')}}">
            <table class="table table-bordered product-purchase-list">
                <thead>
                    <th>#</th>
                    <th>{{__('db.product')}}</th>
                    <th>{{__('db.Batch No')}}</th>
                    <th>Qty</th>
                    <th>{{__('db.Returned')}}</th>
                    <th>{{__('db.Unit Cost')}}</th>
                    <th>{{__('db.Tax')}}</th>
                    <th>{{__('db.Discount')}}</th>
                    <th>{{__('db.Subtotal')}}</th>
                </thead>
                <tbody>
                </tbody>
            </table>
        </div>
        <div id="purchase-footer" class="modal-body"></div>
      </div>
    </div>
<!-- Multi-Item Selection Modal (Optech Grid) -->
<div id="multi-item-modal" tabindex="-1" role="dialog" aria-hidden="true" class="modal fade text-left">
    <div class="modal-dialog modal-lg" style="max-width:850px;">
        <div class="modal-content" style="border-radius:10px;border:1px solid #cbd5e1;">
            <div class="modal-header d-flex align-items-center justify-content-between" style="background:#f8fafc;padding:12px 18px;border-bottom:1px solid #e2e8f0;">
                <h5 class="modal-title" style="font-size:14px;font-weight:700;color:#0f172a;display:flex;align-items:center;gap:8px;">
                    <i class="dripicons-menu" style="color:#7c3aed;"></i> Multi-Item Fast Batch Picker
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="font-size:20px;outline:none;">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" style="padding:14px 18px;">
                <div class="d-flex align-items-center justify-content-between gap-3 mb-3">
                    <div style="position:relative;flex:1;">
                        <i class="fa fa-search text-muted" style="position:absolute;left:10px;top:50%;transform:translateY(-50%);font-size:12px;"></i>
                        <input type="text" id="multi-item-filter" class="form-control form-control-sm" placeholder="Filter items by name or code..." style="padding-left:30px;height:32px;font-size:12px;">
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge badge-light border" id="multi-item-count-badge" style="font-size:11px;padding:5px 8px;">0 selected</span>
                    </div>
                </div>
                <div class="table-responsive" style="max-height:360px;overflow-y:auto;border:1px solid #e2e8f0;border-radius:6px;">
                    <table class="table table-sm table-hover mb-0" id="multi-item-table" style="font-size:12px;">
                        <thead style="background:#f1f5f9;position:sticky;top:0;z-index:10;">
                            <tr>
                                <th style="width:36px;text-align:center;">
                                    <input type="checkbox" id="multi-item-select-all">
                                </th>
                                <th style="min-width:240px;">Item Name</th>
                                <th style="width:130px;">Item Code</th>
                                <th style="width:110px;text-align:right;">Cost</th>
                                <th style="width:90px;text-align:center;">Qty</th>
                                <th style="width:70px;text-align:center;">Unit</th>
                            </tr>
                        </thead>
                        <tbody id="multi-item-tbody">
                            <!-- Populated dynamically via JS from allProducts -->
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer d-flex align-items-center justify-content-between" style="background:#f8fafc;padding:10px 18px;border-top:1px solid #e2e8f0;">
                <button type="button" class="btn btn-sm btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-sm btn-primary" id="btn-add-selected-items" style="background:#7c3aed;border-color:#7c3aed;font-weight:600;">
                    + Add Selected Items to Voucher
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Quick Create Item Modal -->
<div id="quick-create-item-modal" tabindex="-1" role="dialog" aria-hidden="true" class="modal fade text-left">
    <div class="modal-dialog" style="max-width:520px;">
        <div class="modal-content" style="border-radius:10px;border:1px solid #cbd5e1;">
            <div class="modal-header d-flex align-items-center justify-content-between" style="background:#f8fafc;padding:12px 18px;border-bottom:1px solid #e2e8f0;">
                <h5 class="modal-title" style="font-size:14px;font-weight:700;color:#0f172a;display:flex;align-items:center;gap:8px;">
                    <i class="fa fa-plus-circle" style="color:#7c3aed;"></i> Quick Create Item
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="font-size:20px;outline:none;">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="quick-create-item-form">
                <div class="modal-body" style="padding:16px 18px;">
                    <div class="form-group mb-2">
                        <label style="font-size:11px;font-weight:700;color:#475569;margin-bottom:2px;">Item Name *</label>
                        <input type="text" id="quick-item-name" class="form-control form-control-sm" placeholder="e.g. Cotton Grey Yarn 40s" required style="height:32px;font-size:12px;">
                    </div>
                    <div class="form-group mb-2">
                        <label style="font-size:11px;font-weight:700;color:#475569;margin-bottom:2px;">Item Code / Barcode *</label>
                        <div class="input-group input-group-sm">
                            <input type="text" id="quick-item-code" class="form-control form-control-sm" placeholder="e.g. ITM-1002" required style="height:32px;font-size:12px;">
                            <div class="input-group-append">
                                <button type="button" class="btn btn-outline-secondary" id="btn-quick-gen-code" style="font-size:11px;">⚡ Auto</button>
                            </div>
                        </div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-6">
                            <label style="font-size:11px;font-weight:700;color:#475569;margin-bottom:2px;">Cost / Purchase Rate (₹) *</label>
                            <input type="number" id="quick-item-cost" class="form-control form-control-sm" placeholder="0.00" step="0.01" min="0" required style="height:32px;font-size:12px;">
                        </div>
                        <div class="col-6">
                            <label style="font-size:11px;font-weight:700;color:#475569;margin-bottom:2px;">Unit</label>
                            <select id="quick-item-unit" class="form-control form-control-sm" style="height:32px;font-size:12px;">
                                <option value="Pc">Pc (Piece)</option>
                                <option value="Kg">Kg (Kilogram)</option>
                                <option value="Mtr">Mtr (Meter)</option>
                                <option value="Box">Box</option>
                                <option value="Unit">Unit</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-6">
                            <label style="font-size:11px;font-weight:700;color:#475569;margin-bottom:2px;">GST Tax Rate</label>
                            <select id="quick-item-tax" class="form-control form-control-sm" style="height:32px;font-size:12px;">
                                <option value="0">0% (Nil)</option>
                                <option value="5">5%</option>
                                <option value="12">12%</option>
                                <option value="18" selected>18%</option>
                                <option value="28">28%</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label style="font-size:11px;font-weight:700;color:#475569;margin-bottom:2px;">Type</label>
                            <input type="text" class="form-control form-control-sm" value="Standard" readonly style="height:32px;font-size:12px;background:#f8fafc;">
                        </div>
                    </div>
                </div>
                <div class="modal-footer d-flex align-items-center justify-content-between" style="background:#f8fafc;padding:10px 18px;border-top:1px solid #e2e8f0;">
                    <button type="button" class="btn btn-sm btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary" style="background:#7c3aed;border-color:#7c3aed;font-weight:600;">
                        💾 Save & Add to Voucher
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script type="text/javascript">
(function() {
    'use strict';

    // --- Product List Autocomplete & Item Master Data ---
    @php
        $jsProductList = [];
        foreach($lims_product_list_without_variant as $prod) {
            $taxVal = 0;
            if (!empty($prod->tax_id)) {
                $taxObj = collect($lims_tax_list)->firstWhere('id', $prod->tax_id);
                $taxVal = $taxObj ? (float)$taxObj->rate : 0;
            }
            $jsProductList[] = [
                'id' => (int)$prod->id,
                'name' => (string)$prod->name,
                'code' => (string)$prod->code,
                'price' => (float)($prod->price ?? 0),
                'cost' => (float)($prod->cost ?? 0),
                'tax_rate' => $taxVal,
                'unit' => (string)($prod->unit_code ?? ($prod->unit_name ?? 'Unit')),
                'value' => (string)$prod->code . '|' . (string)$prod->name,
                'label' => (string)$prod->code . ' - ' . (string)$prod->name,
            ];
        }
        foreach($lims_product_list_with_variant as $prod) {
            $taxVal = 0;
            if (!empty($prod->tax_id)) {
                $taxObj = collect($lims_tax_list)->firstWhere('id', $prod->tax_id);
                $taxVal = $taxObj ? (float)$taxObj->rate : 0;
            }
            $jsProductList[] = [
                'id' => (int)$prod->id,
                'name' => (string)$prod->name,
                'code' => (string)($prod->item_code ?? $prod->code),
                'price' => (float)(($prod->price ?? 0) + ($prod->additional_price ?? 0)),
                'cost' => (float)(($prod->cost ?? 0) + ($prod->additional_cost ?? 0)),
                'tax_rate' => $taxVal,
                'unit' => (string)($prod->unit_code ?? ($prod->unit_name ?? 'Unit')),
                'value' => (string)($prod->item_code ?? $prod->code) . '|' . (string)$prod->name,
                'label' => (string)($prod->item_code ?? $prod->code) . ' - ' . (string)$prod->name,
            ];
        }
    @endphp

    var allProducts = @json($jsProductList);
    var lims_product_code = allProducts.map(function(p) { return p.value; });

    // --- State variables ---
    var rowCounter = 0;
    var taxList = @json($lims_tax_list);
    var decimalPlaces = {{ $general_setting->decimal ?? 2 }};

    // --- Table Density Switcher & Persistence ---
    $('.density-btn').on('click', function() {
        $('.density-btn').removeClass('active');
        $(this).addClass('active');
        var density = $(this).data('density');
        $('#order-table').removeClass('compact cozy large').addClass(density);
        localStorage.setItem('zolo_voucher_density', density);
    });
    var savedDensity = localStorage.getItem('zolo_voucher_density') || 'cozy';
    $('.density-btn[data-density="' + savedDensity + '"]').addClass('active').siblings().removeClass('active');
    $('#order-table').removeClass('compact cozy large').addClass(savedDensity);

    // --- Autocomplete setup with Custom Render Item ---
    var $productSearch = $('#lims_productcodeSearch');
    $productSearch.autocomplete({
        minLength: 1,
        autoFocus: true,
        source: function(request, response) {
            var term = request.term.toLowerCase().trim();
            var matches = allProducts.filter(function(p) {
                return (p.name && p.name.toLowerCase().includes(term)) ||
                       (p.code && p.code.toLowerCase().includes(term));
            });
            response(matches.slice(0, 20));
        },
        select: function(event, ui) {
            if (ui && ui.item) {
                addProductRow({
                    product_id: ui.item.id,
                    product_name: ui.item.name,
                    product_code: ui.item.code,
                    price: ui.item.price,
                    cost: ui.item.cost,
                    tax_rate: ui.item.tax_rate,
                    unit: ui.item.unit,
                    qty: 1
                });
            }
            $(this).val('');
            return false;
        }
    });

    if ($productSearch.data('ui-autocomplete')) {
        $productSearch.data('ui-autocomplete')._renderItem = function(ul, item) {
            var rateStr = '₹ ' + (parseFloat(item.cost || item.price || 0)).toFixed(decimalPlaces);
            return $("<li>")
                .append(`
                    <div class="custom-ac-item d-flex align-items-center justify-content-between">
                        <div style="flex:1;min-width:0;padding-right:8px;">
                            <div class="item-title">${item.name}</div>
                            <div style="font-size:11px;color:#64748b;display:flex;align-items:center;gap:6px;margin-top:2px;">
                                <span class="item-code-badge">${item.code}</span>
                                <span>•</span>
                                <span class="item-rate">${rateStr}</span>
                                <span>•</span>
                                <span>${item.unit || 'Unit'}</span>
                            </div>
                        </div>
                        <div style="flex-shrink:0;">
                            <span class="badge" style="background:#f3e8ff;color:#7c3aed;font-size:10px;font-weight:600;padding:2px 6px;border-radius:4px;">+ Add</span>
                        </div>
                    </div>
                `)
                .appendTo(ul);
        };
    }

    $productSearch.on('keydown', function(e) {
        if (e.which === 13) {
            e.preventDefault();
            var val = $(this).val().trim();
            if (val) {
                var exact = allProducts.find(function(p) {
                    return (p.code && p.code.toLowerCase() === val.toLowerCase()) ||
                           (p.name && p.name.toLowerCase() === val.toLowerCase());
                });
                if (exact) {
                    addProductRow({
                        product_id: exact.id,
                        product_name: exact.name,
                        product_code: exact.code,
                        price: exact.price,
                        cost: exact.cost,
                        tax_rate: exact.tax_rate,
                        unit: exact.unit,
                        qty: 1
                    });
                    $(this).val('');
                } else {
                    fetchProductAndAddRow(val);
                    $(this).val('');
                }
            }
        }
    });

    // Quick F2 focus
    $(document).on('keydown', function(e) {
        if (e.which === 113) { // F2
            e.preventDefault();
            $productSearch.focus();
        }
    });

    // --- Fetch Product via AJAX & Add Row ---
    function fetchProductAndAddRow(searchTerm) {
        $.ajax({
            type: 'GET',
            url: '{{ route("product_purchase.search") }}',
            data: { data: searchTerm },
            success: function(data) {
                if (data && data.length) {
                    addProductRow({
                        product_id: data[9],
                        product_name: data[0],
                        product_code: data[1],
                        cost: parseFloat(data[2]) || 0,
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
                    cost: 0,
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

        // If product already in grid, increment qty
        if (item.product_id && item.product_id > 0) {
            var existing = $('#order-table-body tr.order-item-row[data-product-id="' + item.product_id + '"]');
            if (existing.length) {
                var qtyInput = existing.find('.row-qty');
                var currentQty = parseFloat(qtyInput.val()) || 0;
                qtyInput.val((currentQty + (item.qty || 1)).toFixed(2)).trigger('input');
                existing.css('background-color', '#f5f3ff');
                setTimeout(function() { existing.css('background-color', ''); }, 400);
                return;
            }
        }

        rowCounter++;
        var rate = item.cost || item.price || 0;
        var qty = item.qty || 1;
        var taxRate = item.tax_rate || 0;
        var amount = rate * qty;
        var taxAmount = amount * (taxRate / 100);
        var lineTotal = amount + taxAmount;

        var itemColHtml = '';
        if (item.is_manual) {
            itemColHtml = `
                <input type="text" name="product_name_manual[]" class="form-control form-control-sm row-item-name" placeholder="Type item name..." value="${item.product_name || ''}" style="height:26px;font-size:12px;font-weight:600;">
                <input type="hidden" name="product_id[]" value="0">
                <input type="hidden" name="product_code[]" value="">
            `;
        } else {
            itemColHtml = `
                <div style="font-weight:600;color:#0f172a;">${item.product_name}</div>
                <small style="color:#64748b;">${item.product_code}</small>
                <input type="hidden" name="product_id[]" value="${item.product_id}">
                <input type="hidden" name="product_code[]" value="${item.product_code}">
            `;
        }

        var tr = $(`
            <tr class="order-item-row" data-row-id="${rowCounter}" data-product-id="${item.product_id || 0}">
                <td style="text-align:center;font-weight:600;color:#64748b;">${$('#order-table-body tr.order-item-row').length + 1}</td>
                <td>
                    ${itemColHtml}
                </td>
                <td>
                    <span class="grid-type-pill">Purchase</span>
                </td>
                <td>
                    <span class="badge badge-light border" style="font-size:11px;">${item.unit || 'Unit'}</span>
                    <input type="hidden" name="purchase_unit[]" value="${item.unit || 'Unit'}">
                </td>
                <td style="text-align:right;">
                    <input type="number" name="net_unit_cost[]" class="form-control form-control-sm row-rate text-right" style="height:26px;font-size:12px;padding:2px 6px;" value="${rate.toFixed(decimalPlaces)}" step="0.01">
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
        if (item.is_manual) {
            tr.find('.row-item-name').focus();
        }
        recalcTableSummary();
        $('#items-meta-count').text($('#order-table-body tr.order-item-row').length + ' line(s) • 7 per page');
    }

    // Manual Add Row (+ Add row button)
    $('#btn-add-item-row').on('click', function() {
        addProductRow({
            product_id: 0,
            product_name: '',
            product_code: '',
            price: 0,
            cost: 0,
            tax_rate: 0,
            unit: 'Unit',
            qty: 1,
            is_manual: true
        });
    });

    // Multi-Item Modal Batch Picker
    function populateMultiItemModal() {
        var tbody = $('#multi-item-tbody');
        tbody.empty();
        allProducts.forEach(function(p, idx) {
            tbody.append(`
                <tr class="multi-item-row" data-id="${p.id}" data-name="${(p.name || '').toLowerCase()}" data-code="${(p.code || '').toLowerCase()}">
                    <td style="text-align:center;">
                        <input type="checkbox" class="multi-item-check" data-index="${idx}">
                    </td>
                    <td>
                        <div style="font-weight:600;color:#0f172a;">${p.name}</div>
                    </td>
                    <td>
                        <span class="badge badge-light border">${p.code}</span>
                    </td>
                    <td style="text-align:right;font-weight:600;color:#059669;">
                        ₹ ${(p.cost || p.price || 0).toFixed(decimalPlaces)}
                    </td>
                    <td style="text-align:center;">
                        <input type="number" class="form-control form-control-sm multi-item-qty text-center" value="1" min="1" style="height:24px;width:60px;margin:auto;font-size:11px;">
                    </td>
                    <td style="text-align:center;color:#64748b;font-size:11px;">
                        ${p.unit || 'Unit'}
                    </td>
                </tr>
            `);
        });
        updateMultiItemCount();
    }

    $('#multi-item-modal').on('show.bs.modal', function() {
        if ($('#multi-item-tbody tr').length === 0) {
            populateMultiItemModal();
        }
    });

    $('#multi-item-filter').on('input', function() {
        var term = $(this).val().toLowerCase().trim();
        $('#multi-item-tbody tr').each(function() {
            var name = $(this).data('name') || '';
            var code = $(this).data('code') || '';
            if (name.includes(term) || code.includes(term)) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
    });

    $('#multi-item-select-all').on('change', function() {
        var checked = $(this).is(':checked');
        $('#multi-item-tbody tr:visible .multi-item-check').prop('checked', checked);
        updateMultiItemCount();
    });

    $(document).on('change', '.multi-item-check', function() {
        updateMultiItemCount();
    });

    function updateMultiItemCount() {
        var count = $('.multi-item-check:checked').length;
        $('#multi-item-count-badge').text(count + ' selected');
        $('#btn-add-selected-items').text('+ Add ' + count + ' Selected Item' + (count === 1 ? '' : 's') + ' to Voucher');
    }

    $('#btn-add-selected-items').on('click', function() {
        $('.multi-item-check:checked').each(function() {
            var idx = $(this).data('index');
            var tr = $(this).closest('tr');
            var qty = parseFloat(tr.find('.multi-item-qty').val()) || 1;
            var prod = allProducts[idx];
            if (prod) {
                addProductRow({
                    product_id: prod.id,
                    product_name: prod.name,
                    product_code: prod.code,
                    price: prod.price,
                    cost: prod.cost,
                    tax_rate: prod.tax_rate,
                    unit: prod.unit,
                    qty: qty
                });
            }
        });
        $('#multi-item-modal').modal('hide');
        $('.multi-item-check').prop('checked', false);
        $('#multi-item-select-all').prop('checked', false);
        updateMultiItemCount();
        $productSearch.focus();
    });

    // Quick Create Item
    $('#btn-quick-gen-code').on('click', function() {
        $('#quick-item-code').val('ITM-' + Math.floor(100000 + Math.random() * 900000));
    });

    $('#quick-create-item-form').on('submit', function(e) {
        e.preventDefault();
        var name = $('#quick-item-name').val().trim();
        var code = $('#quick-item-code').val().trim();
        var cost = parseFloat($('#quick-item-cost').val()) || 0;
        var unit = $('#quick-item-unit').val();
        var taxRate = parseFloat($('#quick-item-tax').val()) || 0;

        if (!name || !code) return;

        var newProduct = {
            id: 0,
            name: name,
            code: code,
            price: cost,
            cost: cost,
            tax_rate: taxRate,
            unit: unit,
            value: code + '|' + name,
            label: code + ' - ' + name
        };

        allProducts.unshift(newProduct);
        lims_product_code.unshift(newProduct.value);

        addProductRow({
            product_id: 0,
            product_name: name,
            product_code: code,
            price: cost,
            cost: cost,
            tax_rate: taxRate,
            unit: unit,
            qty: 1
        });

        $('#quick-create-item-modal').modal('hide');
        $('#quick-create-item-form')[0].reset();
        $productSearch.focus();
    });

    // Delete Row
    $(document).on('click', '.btn-delete-row', function() {
        $(this).closest('tr').remove();
        reindexRows();
        recalcTableSummary();
        $('#items-meta-count').text($('#order-table-body tr.order-item-row').length + ' line(s) • 7 per page');
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
        $('#hidden-total-cost').val(net.toFixed(decimalPlaces));
        $('#hidden-total-tax').val(tax.toFixed(decimalPlaces));
        $('#hidden-grand-total').val(grand.toFixed(decimalPlaces));
    }

    // --- Load Purchase To Form for In-Place Editing ---
    window.loadPurchaseToForm = function(id) {
        $('#comm-progress-bar').addClass('active');
        $.ajax({
            type: 'GET',
            url: '{{ url("purchases") }}/' + id,
            dataType: 'json',
            success: function(res) {
                $('#comm-progress-bar').removeClass('active').addClass('done');
                setTimeout(() => $('#comm-progress-bar').removeClass('done'), 300);

                if (!res || !res.purchase) {
                    alert('Could not load purchase details.');
                    return;
                }

                var p = res.purchase;
                // Switch Form to Update Mode
                $('#entry-form-method').val('PUT');
                $('#edit-purchase-id').val(p.id);
                $('#purchase-entry-form').attr('action', '{{ url("purchases") }}/' + p.id);

                // Update UI Labels
                $('#doc-breadcrumb-mode').text('Edit: ' + (p.reference_no || '#' + p.id)).removeClass('text-primary').addClass('text-success');
                $('#doc-title-text').text('Edit Purchase Bill: ' + (p.reference_no || '#' + p.id));
                $('#doc-sub-text').text('Editing saved purchase record');

                // Populate Header Inputs
                $('#reference_no').val(p.reference_no || '');
                $('#supplier_invoice_no').val(p.supplier_invoice_no || '');
                if (p.supplier_invoice_date) $('#supplier_invoice_date').val(p.supplier_invoice_date.slice(0, 10));
                if (p.created_at) $('#created_at').val(p.created_at.slice(0, 10));
                
                $('#supplier_id').val(p.supplier_id).trigger('change');
                $('.selectpicker').selectpicker('refresh');
                if (p.warehouse_id) $('#form_warehouse_id').val(p.warehouse_id);

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
                            cost: parseFloat(item.net_unit_cost) || 0,
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
                alert('Failed to fetch purchase details from server.');
            }
        });
    };

    // --- Reset Form to Blank New Purchase ---
    window.resetFormToNew = function() {
        $('#entry-form-method').val('POST');
        $('#edit-purchase-id').val('');
        $('#purchase-entry-form').attr('action', '{{ route("purchases.store") }}');

        $('#doc-breadcrumb-mode').text('New').removeClass('text-success').addClass('text-primary');
        $('#doc-title-text').text('New Purchase Bill');
        $('#doc-sub-text').text('Purchase • New bill');

        $('#reference_no').val('');
        $('#supplier_invoice_no').val('');
        $('#supplier_invoice_date').val('{{ date("Y-m-d") }}');
        $('#created_at').val('{{ date("Y-m-d") }}');
        $('#supplier_id').val('').trigger('change');
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
        if (id) loadPurchaseToForm(id);
    });

    $(document).on('click', '.btn-side-load-edit', function(e) {
        e.stopPropagation();
        var id = $(this).data('id');
        if (id) loadPurchaseToForm(id);
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
    function filterSidePurchases() {
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

    $('#side-search-input, #side-filter-series').on('input', filterSidePurchases);
    $('#side-filter-warehouse, #side-filter-status, #side-filter-payment').on('change', filterSidePurchases);
    $('#side-filter-date-val').on('change', filterSidePurchases);

    $('.side-filter-tabs .side-tab').on('click', function() {
        $('.side-filter-tabs .side-tab').removeClass('active');
        $(this).addClass('active');
        var f = $(this).data('filter');
        $('#side-date-picker-box').toggle(f === 'date' || f === 'range');
        filterSidePurchases();
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
        filterSidePurchases();
    });

    // Export toolbar buttons
    $('#side-export-print').on('click', function() { window.print(); });
    $('#side-export-excel, #side-export-csv').on('click', function() {
        alert('Exporting recent purchases list...');
    });
    $('#side-export-pdf').on('click', function() {
        var activeId = $('#edit-purchase-id').val();
        if (activeId) window.open('{{ url("purchases/gen_invoice") }}/' + activeId, '_blank');
        else window.print();
    });

    // --- View Modal Handler ---
    $(document).on('click', '.btn-side-view', function(e) {
        e.stopPropagation();
        var id = $(this).data('id');
        if (id) {
            $.get('{{ url("purchases/product_purchase") }}/' + id, function(data) {
                $(".product-purchase-list tbody").empty();
                if (data && data[0]) {
                    var names = data[0];
                    var qtys = data[1];
                    var units = data[2];
                    var taxes = data[3];
                    var subtotals = data[6];
                    for (var i = 0; i < names.length; i++) {
                        $(".product-purchase-list tbody").append(`
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
                $('#purchase-details').modal('show');
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
        $('#purchase-status-val').val(3); // Draft
        $('#purchase-entry-form').submit();
    });

    $('#btn-form-submit').on('click', function() {
        $('#purchase-status-val').val(1); // Received
        $('#purchase-entry-form').submit();
    });

    // --- Auto load Goods Received Note if from_grn param present ---
    var urlParams = new URLSearchParams(window.location.search);
    var fromGrnId = urlParams.get('from_grn');
    if (fromGrnId) {
        $.getJSON('/goods-received-notes/' + fromGrnId, function(res) {
            if (res && res.grn) {
                var gn = res.grn;
                $('#supplier_id_select').val(gn.supplier_id).trigger('change');
                if (gn.warehouse_id) $('#warehouse_id_select').val(gn.warehouse_id).trigger('change');
                if (gn.purchase_type_id) $('#purchase_type_id').val(gn.purchase_type_id);
                if (gn.agent_id) $('#agent_id').val(gn.agent_id);
                if (gn.transport_name) $('#transporter_name').val(gn.transport_name);
                if (gn.lr_no) $('#lr_no').val(gn.lr_no);
                if (gn.lr_date) $('#lr_date').val(gn.lr_date.substring(0, 10));
                if (gn.order_no) $('#supplier_invoice_no').val(gn.order_no);
                if (gn.remarks) $('#custom_remarks').val(gn.remarks);

                if (!$('#goods_received_note_id_input').length) {
                    $('#purchase-entry-form').append('<input type="hidden" name="goods_received_note_id" id="goods_received_note_id_input" value="' + gn.id + '">');
                } else {
                    $('#goods_received_note_id_input').val(gn.id);
                }

                if (res.items && res.items.length) {
                    $('#order-table-body tr.item-row').remove();
                    res.items.forEach(function(item) {
                        addProductRow(item);
                    });
                }
                $('#entry-title-text').text('New Purchase Bill (from GRN #' + gn.grn_no + ')');
            }
        });
    }

    initPanelState();
})();
</script>
@endpush
