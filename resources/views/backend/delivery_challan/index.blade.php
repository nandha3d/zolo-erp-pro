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
            <h1><i class="dripicons-box text-primary"></i> {{ __('Delivery Challan') }} Command Center</h1>
            <span class="comm-title-badge"><i class="dripicons-export"></i> Outbound Material Dispatch</span>
        </div>
        <ul class="comm-nav-pills">
            <li><a class="nav-link active" href="javascript:void(0)"><i class="dripicons-list"></i> {{ __('All Challans') }}</a></li>
            <li><a class="nav-link" href="{{ route('sales.index') }}"><i class="dripicons-cart"></i> Sales Bills</a></li>
            <li><a class="nav-link" href="{{ route('packingSlip.index') }}"><i class="dripicons-clipboard"></i> Packing Slips</a></li>
        </ul>
        <div class="comm-actions">
            <button type="button" class="btn btn-outline-secondary py-1 px-3" id="btn-top-new" title="Add New Challan">
                <i class="dripicons-plus"></i> {{ __('New Challan') }}
            </button>
            <button type="button" class="btn btn-primary py-1 px-3" id="toggle-drawer-btn" title="Toggle Challan List Panel" style="background:#7c3aed; border-color:#7c3aed; color:#fff;">
                <i class="dripicons-view-list"></i> Challan list
            </button>
        </div>
    </div>

    <!-- 2-Column Command Center Grid: Main Entry Workspace + Side Challan List Panel -->
    <div class="comm-split-grid" id="comm-split-grid">
        <!-- Main: Delivery Challan Entry Workspace -->
        <div class="comm-entry-workspace" id="comm-entry-workspace">
            <form id="challan-entry-form" method="POST" action="{{ route('delivery-challans.store') }}" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="_method" id="form-method" value="POST">
                <input type="hidden" name="challan_id" id="edit-challan-id" value="">

                <!-- Header Strip Card -->
                <div class="entry-header-card">
                    <div class="entry-header-top d-flex align-items-center justify-content-between mb-2">
                        <div class="d-flex align-items-center gap-2">
                            <span class="entry-breadcrumb" id="entry-breadcrumb">Home / Selling / Delivery Challans / New</span>
                            <span class="badge badge-light-primary px-2 py-1 ml-2" id="status-pill" style="font-weight:600;font-size:11px;background:#f3e8ff;color:#7c3aed;border:1px solid #d8b4fe;border-radius:4px;">Draft DC</span>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary btn-compact" id="open-transport-drawer-btn">
                                <i class="dripicons-truck"></i> Transport Details
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary btn-compact" id="reopen-panel-btn">
                                <i class="dripicons-view-list"></i> Challan list
                            </button>
                        </div>
                    </div>

                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <div class="d-flex align-items-center gap-2">
                            <div class="entry-doc-icon"><i class="dripicons-box" style="color:#7c3aed;font-size:20px;"></i></div>
                            <h2 class="entry-title mb-0" id="entry-title-text" style="font-size:17px;font-weight:700;color:#0f172a;">New Delivery Challan</h2>
                        </div>
                    </div>
                </div>

                <!-- Primary Fields Form Row -->
                <div class="entry-fields-card mt-2">
                    <div class="row gx-2 gy-2">
                        <div class="col-md-2 col-sm-6">
                            <div class="comm-field">
                                <label for="challan_no">Challan No <span class="text-danger">*</span></label>
                                <input type="text" name="challan_no" id="challan_no" class="form-control form-control-sm" placeholder="Auto / DC-..." value="">
                                <small class="text-muted" style="font-size:10px;">Leave blank for auto-numbering</small>
                            </div>
                        </div>

                        <div class="col-md-2 col-sm-6">
                            <div class="comm-field">
                                <label for="challan_date">Challan Date <span class="text-danger">*</span></label>
                                <input type="date" name="challan_date" id="challan_date" class="form-control form-control-sm" value="{{ date('Y-m-d') }}" required>
                            </div>
                        </div>

                        <div class="col-md-3 col-sm-6">
                            <div class="comm-field">
                                <div class="d-flex justify-content-between align-items-center">
                                    <label for="customer_id">Customer <span class="text-danger">*</span></label>
                                    <a href="javascript:void(0)" class="text-primary font-weight-bold" id="btn-quick-customer" style="font-size:11px;">+ New</a>
                                </div>
                                <select name="customer_id" id="customer_id" class="form-control form-control-sm" required>
                                    <option value="">Choose Customer...</option>
                                    @foreach($lims_customer_list as $cust)
                                        <option value="{{ $cust->id }}" data-city="{{ $cust->city }}" data-address="{{ $cust->address }}">{{ $cust->name }} ({{ $cust->phone_number }})</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="col-md-2 col-sm-6">
                            <div class="comm-field">
                                <label for="warehouse_id">Warehouse <span class="text-danger">*</span></label>
                                <select name="warehouse_id" id="warehouse_id" class="form-control form-control-sm" required>
                                    @foreach($lims_warehouse_list as $wh)
                                        <option value="{{ $wh->id }}">{{ $wh->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="col-md-3 col-sm-6">
                            <div class="comm-field">
                                <label for="sale_type_id">Sale Type</label>
                                <select name="sale_type_id" id="sale_type_id" class="form-control form-control-sm">
                                    <option value="">General Dispatch</option>
                                    @foreach($saleTypes as $st)
                                        <option value="{{ $st->id }}">{{ $st->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row gx-2 gy-2 mt-1">
                        <div class="col-md-3 col-sm-6">
                            <div class="comm-field">
                                <label for="agent_id">Through Agent</label>
                                <select name="agent_id" id="agent_id" class="form-control form-control-sm">
                                    <option value="">Direct Dispatch</option>
                                    @foreach($agents as $ag)
                                        <option value="{{ $ag->id }}">{{ $ag->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="col-md-3 col-sm-6">
                            <div class="comm-field">
                                <label for="area_id">Area / Route</label>
                                <select name="area_id" id="area_id" class="form-control form-control-sm">
                                    <option value="">Select Area...</option>
                                    @foreach($areas as $ar)
                                        <option value="{{ $ar->id }}">{{ $ar->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="col-md-3 col-sm-6">
                            <div class="comm-field">
                                <label for="transport_name">Transporter</label>
                                <input type="text" name="transport_name" id="transport_name" class="form-control form-control-sm" placeholder="e.g. VRL Logistics, Sri Ram">
                            </div>
                        </div>

                        <div class="col-md-3 col-sm-6">
                            <div class="comm-field">
                                <label for="lr_no">LR / Bale No</label>
                                <input type="text" name="lr_no" id="lr_no" class="form-control form-control-sm" placeholder="LR No / Consignment No">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Items Grid Section -->
                <div class="items-grid-section mt-2">
                    <div class="items-toolbar d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
                        <div class="d-flex align-items-center gap-2">
                            <span class="items-counter font-weight-bold" id="items-counter" style="color:#0f172a;font-size:12px;">ITEMS 0 line(s)</span>
                            <div class="density-segmented ml-3">
                                <button type="button" class="density-btn" data-density="compact">Compact</button>
                                <button type="button" class="density-btn active" data-density="cozy">Cozy</button>
                                <button type="button" class="density-btn" data-density="large">Large</button>
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary btn-compact" id="btn-multi-item" data-toggle="modal" data-target="#multi-item-modal">
                                <i class="dripicons-checklist"></i> Multi item
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-primary btn-compact" id="btn-create-item-modal" data-toggle="modal" data-target="#quick-create-item-modal">
                                <i class="dripicons-plus"></i> Create item
                            </button>
                            <button type="button" class="btn btn-sm btn-primary btn-compact" id="btn-add-row" style="background:#7c3aed;border-color:#7c3aed;">
                                <i class="dripicons-plus"></i> Add row
                            </button>
                        </div>
                    </div>

                    <!-- Quick Barcode / Item Search Input -->
                    <div class="search-box-wrapper mb-2">
                        <div class="input-group input-group-sm">
                            <div class="input-group-prepend">
                                <span class="input-group-text" style="background:#f8fafc; border-color:#cbd5e1; color:#64748b;"><i class="dripicons-search"></i></span>
                            </div>
                            <input type="text" id="lims_productcodeSearch" class="form-control form-control-sm" placeholder="Scan barcode or type item code / name... (Press F2 to focus)" autocomplete="off">
                            <div class="input-group-append">
                                <span class="input-group-text text-muted" style="background:#f8fafc; border-color:#cbd5e1; font-size:11px;">F2 Quick Search</span>
                            </div>
                        </div>
                    </div>

                    <!-- Purple Header 10-Column Items Table -->
                    <div class="table-responsive order-table-responsive" style="border: 1px solid #e2e8f0; border-radius: 6px; overflow: hidden; background: #fff;">
                        <table class="table order-table mb-0 cozy" id="order-table" style="width: 100%;">
                            <thead style="background: #7c3aed; color: #fff;">
                                <tr>
                                    <th style="width: 40px; text-align: center;">#</th>
                                    <th>ITEM</th>
                                    <th style="width: 130px;">SALE TYPE</th>
                                    <th style="width: 80px;">UNIT</th>
                                    <th style="width: 90px; text-align: right;">RATE</th>
                                    <th style="width: 80px; text-align: right;">QTY</th>
                                    <th style="width: 100px; text-align: right;">AMOUNT</th>
                                    <th style="width: 70px; text-align: right;">TAX %</th>
                                    <th style="width: 110px; text-align: right;">TOTAL</th>
                                    <th style="width: 60px; text-align: center;">ACTIONS</th>
                                </tr>
                            </thead>
                            <tbody id="order-table-body">
                                <tr class="empty-row-placeholder" id="empty-row-placeholder">
                                    <td colspan="10" class="text-center py-4 text-muted" style="font-size: 13px;">
                                        <i class="dripicons-box mr-1"></i> No items added yet. Search or scan above or click "+ Add row".
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Slide-over Drawer for Transport & Remarks -->
                <div class="comm-drawer" id="comm-drawer">
                    <div class="comm-drawer-header d-flex align-items-center justify-content-between p-3" style="background:#f8fafc; border-bottom:1px solid #e2e8f0;">
                        <h4 class="mb-0 font-weight-bold" style="font-size:14px; color:#0f172a;"><i class="dripicons-truck text-primary mr-1"></i> Transport & Logistics Details</h4>
                        <button type="button" class="close" id="close-drawer-btn" style="font-size:18px;">&times;</button>
                    </div>
                    <div class="comm-drawer-body p-3">
                        <div class="form-group mb-2">
                            <label class="font-weight-bold" style="font-size:11px;">Bale / Package No</label>
                            <input type="text" name="bale_no" id="bale_no" class="form-control form-control-sm" placeholder="e.g. BL-101">
                        </div>
                        <div class="form-group mb-2">
                            <label class="font-weight-bold" style="font-size:11px;">No of Bales / Packages</label>
                            <input type="number" name="no_of_bales" id="no_of_bales" class="form-control form-control-sm" placeholder="e.g. 5">
                        </div>
                        <div class="form-group mb-2">
                            <label class="font-weight-bold" style="font-size:11px;">LR Date</label>
                            <input type="date" name="lr_date" id="lr_date" class="form-control form-control-sm">
                        </div>
                        <div class="form-group mb-2">
                            <label class="font-weight-bold" style="font-size:11px;">Destination Station</label>
                            <input type="text" name="station_to" id="station_to" class="form-control form-control-sm" placeholder="e.g. Mumbai, Surat">
                        </div>
                        <div class="form-group mb-2">
                            <label class="font-weight-bold" style="font-size:11px;">Remarks & Delivery Notes</label>
                            <textarea name="remarks" id="remarks" class="form-control form-control-sm" rows="3" placeholder="Additional instructions for transporter or customer..."></textarea>
                        </div>
                    </div>
                </div>

                <!-- Hidden Financial Input Fields -->
                <input type="hidden" name="total_qty" id="input_total_qty" value="0">
                <input type="hidden" name="total_amount" id="input_total_amount" value="0">
                <input type="hidden" name="total_tax" id="input_total_tax" value="0">
                <input type="hidden" name="grand_total" id="input_grand_total" value="0">

                <!-- Fixed Bottom Summary & Action Bar -->
                <div class="entry-bottom-bar mt-3 p-2 d-flex align-items-center justify-content-between flex-wrap gap-2" style="background:#fff; border-top:1px solid #e2e8f0; border-radius:6px; box-shadow:0 -2px 8px rgba(0,0,0,0.03);">
                    <div class="d-flex align-items-center gap-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-bottom-transport">
                            <i class="dripicons-truck"></i> Transport Details
                        </button>
                        <span class="text-muted ml-2" style="font-size:12px;" id="bottom-notes-preview"></span>
                    </div>

                    <div class="d-flex align-items-center gap-3">
                        <div class="summary-metric text-right">
                            <span class="text-muted" style="font-size:10px;text-transform:uppercase;">NET</span>
                            <span class="font-weight-bold ml-1" id="disp-net" style="font-size:13px;color:#0f172a;">₹ 0.00</span>
                        </div>
                        <div class="summary-metric text-right">
                            <span class="text-muted" style="font-size:10px;text-transform:uppercase;">TAX</span>
                            <span class="font-weight-bold ml-1" id="disp-tax" style="font-size:13px;color:#0f172a;">₹ 0.00</span>
                        </div>
                        <div class="summary-metric text-right">
                            <span class="text-muted" style="font-size:10px;text-transform:uppercase;">GRAND TOTAL</span>
                            <span class="font-weight-bold ml-1" id="disp-grand" style="font-size:15px;color:#7c3aed;">₹ 0.00</span>
                        </div>

                        <div class="d-flex align-items-center gap-2 ml-3">
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-discard" title="Discard Changes">
                                <i class="dripicons-trash"></i> Discard
                            </button>
                            <button type="submit" class="btn btn-sm btn-primary" id="btn-save" style="background:#7c3aed; border-color:#7c3aed; font-weight:600; padding:6px 16px;">
                                <i class="dripicons-checkmark"></i> Save Challan
                            </button>
                            <button type="button" class="btn btn-sm btn-success" id="btn-convert-sale" style="display:none; font-weight:600;">
                                <i class="dripicons-cart"></i> Convert to Sale
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <!-- Side Challan List Panel -->
        <div class="desk-bill-list-panel" id="desk-bill-list-panel">
            <div class="side-panel-header d-flex align-items-center justify-content-between p-2" style="border-bottom:1px solid #e2e8f0; background:#f8fafc;">
                <div class="d-flex align-items-center gap-2">
                    <span class="font-weight-bold text-dark" style="font-size:13px;"><i class="dripicons-box text-primary mr-1"></i> Delivery Challans</span>
                    <span class="badge badge-secondary" style="font-size:10px;">{{ count($recent_challans) }}</span>
                </div>
                <div class="d-flex align-items-center gap-1">
                    <button type="button" class="btn btn-xs btn-outline-secondary" id="btn-dock-toggle" title="Dock Left/Right">⇄ Dock</button>
                    <button type="button" class="btn btn-xs btn-outline-secondary" id="btn-close-panel" title="Close Panel">✕</button>
                </div>
            </div>

            <!-- Side Search Box -->
            <div class="side-search-box p-2" style="background:#fff; border-bottom:1px solid #f1f5f9;">
                <input type="text" id="side-search-input" class="form-control form-control-sm" placeholder="Find a challan by no or party...">
            </div>

            <!-- Challan Cards List -->
            <div class="side-challan-cards" id="side-challan-cards" style="overflow-y:auto; max-height: calc(100vh - 220px); padding:8px;">
                @forelse($recent_challans as $ch)
                    <div class="bill-card p-2 mb-2" data-id="{{ $ch->id }}" data-no="{{ strtolower($ch->challan_no) }}" data-customer="{{ strtolower($ch->customer ? $ch->customer->name : '') }}" style="border:1px solid #e2e8f0; border-radius:6px; background:#fff; cursor:pointer; transition:all 0.15s ease;">
                        <div class="d-flex align-items-center justify-content-between mb-1">
                            <span class="font-weight-bold text-primary" style="font-size:12px;">{{ $ch->challan_no }}</span>
                            <span class="font-weight-bold" style="font-size:12px; color:#0f172a;">₹ {{ number_format((float)$ch->grand_total, 2) }}</span>
                        </div>
                        <div class="d-flex align-items-center justify-content-between text-muted" style="font-size:11px;">
                            <span>{{ $ch->customer ? $ch->customer->name : 'N/A' }}</span>
                            <span>{{ \Carbon\Carbon::parse($ch->challan_date)->format('d M Y') }}</span>
                        </div>
                        <div class="d-flex align-items-center justify-content-between mt-2 pt-1" style="border-top:1px dashed #f1f5f9;">
                            <span class="badge {{ $ch->status === 'converted_to_sale' ? 'badge-success' : ($ch->status === 'cancelled' ? 'badge-danger' : 'badge-warning') }}" style="font-size:10px;">
                                {{ ucfirst(str_replace('_', ' ', $ch->status)) }}
                            </span>
                            <div class="d-flex align-items-center gap-1">
                                <button type="button" class="btn btn-xs btn-outline-primary py-0 px-2 btn-load-challan" data-id="{{ $ch->id }}" title="Edit in Form">✎ Edit</button>
                                @if($ch->status === 'pending')
                                    <a href="{{ route('sales.index', ['from_dc' => $ch->id]) }}" class="btn btn-xs btn-outline-success py-0 px-2" title="Convert to Sale Bill">🗲 Convert</a>
                                @endif
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-4 text-muted" style="font-size:12px;">
                        No delivery challans recorded yet.
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</section>

<!-- Fast Multi-Item Batch Picker Modal -->
<div class="modal fade" id="multi-item-modal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content" style="border-radius:8px; border:1px solid #cbd5e1;">
            <div class="modal-header py-2 px-3" style="background:#7c3aed; color:#fff;">
                <h5 class="modal-title font-weight-bold" style="font-size:14px;"><i class="dripicons-checklist mr-1"></i> Fast Batch Item Picker</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body p-3">
                <input type="text" id="multi-item-search" class="form-control form-control-sm mb-2" placeholder="Search item by name or code...">
                <div class="table-responsive" style="max-height: 340px; overflow-y:auto; border:1px solid #e2e8f0; border-radius:6px;">
                    <table class="table table-hover table-sm mb-0">
                        <thead style="background:#f8fafc;">
                            <tr>
                                <th style="width:30px;"><input type="checkbox" id="select-all-multi"></th>
                                <th>Item Code</th>
                                <th>Item Name</th>
                                <th>Unit</th>
                                <th class="text-right">Price</th>
                            </tr>
                        </thead>
                        <tbody id="multi-item-tbody">
                            @foreach($lims_product_list_without_variant as $prod)
                                <tr class="multi-item-row" data-id="{{ $prod->id }}" data-name="{{ $prod->name }}" data-code="{{ $prod->code }}" data-price="{{ $prod->price }}" data-cost="{{ $prod->cost }}" data-unit="{{ $prod->unit_code ?? ($prod->unit_name ?? 'Unit') }}">
                                    <td><input type="checkbox" class="multi-item-cb" value="{{ $prod->id }}"></td>
                                    <td><code>{{ $prod->code }}</code></td>
                                    <td>{{ $prod->name }}</td>
                                    <td>{{ $prod->unit_code ?? ($prod->unit_name ?? 'Unit') }}</td>
                                    <td class="text-right font-weight-bold text-success">₹ {{ number_format((float)$prod->price, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer py-2 px-3" style="background:#f8fafc;">
                <button type="button" class="btn btn-sm btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-sm btn-primary" id="btn-add-selected-items" style="background:#7c3aed; border-color:#7c3aed;">Add Selected Items</button>
            </div>
        </div>
    </div>
</div>

<!-- On-The-Fly Quick Item Creation Modal -->
<div class="modal fade" id="quick-create-item-modal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content" style="border-radius:8px; border:1px solid #cbd5e1;">
            <div class="modal-header py-2 px-3" style="background:#7c3aed; color:#fff;">
                <h5 class="modal-title font-weight-bold" style="font-size:14px;"><i class="dripicons-plus mr-1"></i> Quick Create Item</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <form id="quick-create-item-form">
                <div class="modal-body p-3">
                    <div class="form-group mb-2">
                        <label class="font-weight-bold" style="font-size:11px;">Item Name <span class="text-danger">*</span></label>
                        <input type="text" id="quick-item-name" class="form-control form-control-sm" required placeholder="e.g. Cotton Grey Fabric 40s">
                    </div>
                    <div class="form-group mb-2">
                        <label class="font-weight-bold" style="font-size:11px;">Item Code / Barcode</label>
                        <input type="text" id="quick-item-code" class="form-control form-control-sm" placeholder="Auto-generated if empty">
                    </div>
                    <div class="row gx-2 gy-2">
                        <div class="col-6">
                            <div class="form-group mb-2">
                                <label class="font-weight-bold" style="font-size:11px;">Selling Rate (₹)</label>
                                <input type="number" step="0.01" id="quick-item-price" class="form-control form-control-sm" value="0.00">
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-group mb-2">
                                <label class="font-weight-bold" style="font-size:11px;">Cost Rate (₹)</label>
                                <input type="number" step="0.01" id="quick-item-cost" class="form-control form-control-sm" value="0.00">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer py-2 px-3" style="background:#f8fafc;">
                    <button type="button" class="btn btn-sm btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary" style="background:#7c3aed; border-color:#7c3aed;">Save & Add to Challan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script type="text/javascript">
    'use strict';

    // --- Master Data Prepared in JSON ---
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
    @endphp

    var allProducts = @json($jsProductList);
    var rowCounter = 0;
    var decimalPlaces = {{ $general_setting->decimal ?? 2 }};

    // --- Table Density Switcher ---
    $('.density-btn').on('click', function() {
        $('.density-btn').removeClass('active');
        $(this).addClass('active');
        var density = $(this).data('density');
        $('#order-table').removeClass('compact cozy large').addClass(density);
        localStorage.setItem('zolo_dc_density', density);
    });
    var savedDensity = localStorage.getItem('zolo_dc_density') || 'cozy';
    $('.density-btn[data-density="' + savedDensity + '"]').addClass('active').siblings().removeClass('active');
    $('#order-table').removeClass('compact cozy large').addClass(savedDensity);

    // --- Autocomplete Setup ---
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
            var rateStr = '₹ ' + (parseFloat(item.price || 0)).toFixed(decimalPlaces);
            return $("<li>")
                .append(`
                    <div class="custom-ac-item d-flex align-items-center justify-content-between">
                        <div style="flex:1;min-width:0;padding-right:8px;">
                            <div class="custom-ac-title" style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;font-weight:600;font-size:12px;">${item.name}</div>
                            <div class="custom-ac-sub" style="font-size:11px;color:#64748b;">
                                <span class="badge badge-light" style="border:1px solid #cbd5e1;padding:1px 5px;font-family:monospace;">${item.code}</span>
                                <span class="ml-1 text-muted">${item.unit}</span>
                            </div>
                        </div>
                        <div class="text-right" style="white-space:nowrap;">
                            <span class="custom-ac-rate" style="font-weight:700;color:#16a34a;font-size:12px;">${rateStr}</span>
                            <div style="font-size:10px;color:#7c3aed;font-weight:600;">+ Add</div>
                        </div>
                    </div>
                `)
                .appendTo(ul);
        };
    }

    // --- Keyboard Shortcut F2 for Quick Search ---
    $(document).on('keydown', function(e) {
        if (e.key === 'F2') {
            e.preventDefault();
            $('#lims_productcodeSearch').focus().select();
        }
    });

    // --- Add Product Row Function ---
    window.addProductRow = function(itemData) {
        $('#empty-row-placeholder').hide();

        // Check if item already exists in table
        var existingRow = null;
        $('#order-table-body tr.item-row').each(function() {
            if ($(this).find('.row-product-id').val() == itemData.product_id) {
                existingRow = $(this);
                return false;
            }
        });

        if (existingRow) {
            var $qtyInput = existingRow.find('.row-qty');
            var curQty = parseFloat($qtyInput.val()) || 0;
            $qtyInput.val(curQty + (itemData.qty || 1));
            recalculateRow(existingRow);
            recalculateTotals();
            return;
        }

        rowCounter++;
        var rate = parseFloat(itemData.price || itemData.cost || 0);
        var qty = parseFloat(itemData.qty || 1);
        var taxRate = parseFloat(itemData.tax_rate || 0);
        var amount = rate * qty;
        var taxAmount = (amount * taxRate) / 100;
        var total = amount + taxAmount;

        var rowHtml = `
            <tr class="item-row" data-row-idx="${rowCounter}">
                <td style="text-align:center; vertical-align:middle;">
                    <span class="row-num text-muted" style="font-size:11px;">${rowCounter}</span>
                    <input type="hidden" name="product_id[]" class="row-product-id" value="${itemData.product_id || ''}">
                    <input type="hidden" name="unit_id[]" class="row-unit-id" value="${itemData.unit_id || 1}">
                    <input type="hidden" name="tax_amount[]" class="row-tax-amount" value="${taxAmount.toFixed(4)}">
                    <input type="hidden" name="total[]" class="row-total" value="${total.toFixed(4)}">
                </td>
                <td style="vertical-align:middle;">
                    <div class="font-weight-bold" style="font-size:12px;color:#0f172a;">${itemData.product_name}</div>
                    <small class="text-muted" style="font-family:monospace;font-size:11px;">${itemData.product_code || ''}</small>
                </td>
                <td style="vertical-align:middle;">
                    <select name="item_sale_type_id[]" class="form-control form-control-xs row-sale-type" style="font-size:11px; height:26px; padding:2px 4px;">
                        <option value="">Standard</option>
                        @foreach($saleTypes as $st)
                            <option value="{{ $st->id }}">{{ $st->name }}</option>
                        @endforeach
                    </select>
                </td>
                <td style="vertical-align:middle;">
                    <span class="badge badge-light" style="border:1px solid #cbd5e1; font-size:11px;">${itemData.unit || 'Unit'}</span>
                </td>
                <td style="text-align:right; vertical-align:middle;">
                    <input type="number" step="0.01" name="rate[]" class="form-control form-control-xs text-right row-rate" value="${rate.toFixed(decimalPlaces)}" style="font-size:12px; height:26px; padding:2px 4px;">
                </td>
                <td style="text-align:right; vertical-align:middle;">
                    <input type="number" step="any" min="0.01" name="qty[]" class="form-control form-control-xs text-right row-qty font-weight-bold" value="${qty}" style="font-size:12px; height:26px; padding:2px 4px;">
                </td>
                <td style="text-align:right; vertical-align:middle;">
                    <input type="text" readonly name="amount[]" class="form-control form-control-xs text-right row-amount" value="${amount.toFixed(decimalPlaces)}" style="font-size:12px; height:26px; padding:2px 4px; background:#f8fafc;">
                </td>
                <td style="text-align:right; vertical-align:middle;">
                    <input type="number" step="0.01" name="tax_rate[]" class="form-control form-control-xs text-right row-tax-rate" value="${taxRate.toFixed(2)}" style="font-size:11px; height:26px; padding:2px 4px;">
                </td>
                <td style="text-align:right; vertical-align:middle;">
                    <span class="font-weight-bold row-total-disp" style="font-size:12px; color:#0f172a;">₹ ${total.toFixed(decimalPlaces)}</span>
                </td>
                <td style="text-align:center; vertical-align:middle;">
                    <button type="button" class="btn btn-xs btn-link text-danger p-0 btn-remove-row" title="Remove Item"><i class="dripicons-trash"></i></button>
                </td>
            </tr>
        `;

        $('#order-table-body').append(rowHtml);
        renumberRows();
        recalculateTotals();
    };

    // --- Recalculate Row ---
    function recalculateRow($row) {
        var rate = parseFloat($row.find('.row-rate').val()) || 0;
        var qty = parseFloat($row.find('.row-qty').val()) || 0;
        var taxRate = parseFloat($row.find('.row-tax-rate').val()) || 0;

        var amount = rate * qty;
        var taxAmount = (amount * taxRate) / 100;
        var total = amount + taxAmount;

        $row.find('.row-amount').val(amount.toFixed(decimalPlaces));
        $row.find('.row-tax-amount').val(taxAmount.toFixed(4));
        $row.find('.row-total').val(total.toFixed(4));
        $row.find('.row-total-disp').text('₹ ' + total.toFixed(decimalPlaces));
    }

    // --- Row Input Change Handlers ---
    $('#order-table-body').on('input', '.row-rate, .row-qty, .row-tax-rate', function() {
        var $row = $(this).closest('tr');
        recalculateRow($row);
        recalculateTotals();
    });

    // --- Remove Row ---
    $('#order-table-body').on('click', '.btn-remove-row', function() {
        $(this).closest('tr').remove();
        if ($('#order-table-body tr.item-row').length === 0) {
            $('#empty-row-placeholder').show();
        }
        renumberRows();
        recalculateTotals();
    });

    function renumberRows() {
        var count = 0;
        $('#order-table-body tr.item-row').each(function(idx) {
            count++;
            $(this).find('.row-num').text(count);
        });
        $('#items-counter').text('ITEMS ' + count + ' line(s)');
    }

    // --- Recalculate Grand Totals ---
    function recalculateTotals() {
        var totalQty = 0;
        var totalAmount = 0;
        var totalTax = 0;
        var grandTotal = 0;

        $('#order-table-body tr.item-row').each(function() {
            var qty = parseFloat($(this).find('.row-qty').val()) || 0;
            var amount = parseFloat($(this).find('.row-amount').val()) || 0;
            var tax = parseFloat($(this).find('.row-tax-amount').val()) || 0;
            var total = parseFloat($(this).find('.row-total').val()) || 0;

            totalQty += qty;
            totalAmount += amount;
            totalTax += tax;
            grandTotal += total;
        });

        $('#input_total_qty').val(totalQty);
        $('#input_total_amount').val(totalAmount.toFixed(4));
        $('#input_total_tax').val(totalTax.toFixed(4));
        $('#input_grand_total').val(grandTotal.toFixed(4));

        $('#disp-net').text('₹ ' + totalAmount.toFixed(decimalPlaces));
        $('#disp-tax').text('₹ ' + totalTax.toFixed(decimalPlaces));
        $('#disp-grand').text('₹ ' + grandTotal.toFixed(decimalPlaces));
    }

    // --- + Add Row Manual Button ---
    $('#btn-add-row').on('click', function() {
        $('#empty-row-placeholder').hide();
        rowCounter++;
        var rowHtml = `
            <tr class="item-row" data-row-idx="${rowCounter}">
                <td style="text-align:center; vertical-align:middle;">
                    <span class="row-num text-muted" style="font-size:11px;">${rowCounter}</span>
                    <input type="hidden" name="product_id[]" class="row-product-id" value="1">
                    <input type="hidden" name="unit_id[]" class="row-unit-id" value="1">
                    <input type="hidden" name="tax_amount[]" class="row-tax-amount" value="0.0000">
                    <input type="hidden" name="total[]" class="row-total" value="0.0000">
                </td>
                <td style="vertical-align:middle;">
                    <input type="text" name="item_name_manual[]" class="form-control form-control-xs" placeholder="Type item name..." style="font-size:12px; height:26px; padding:2px 4px;" required>
                </td>
                <td style="vertical-align:middle;">
                    <select name="item_sale_type_id[]" class="form-control form-control-xs row-sale-type" style="font-size:11px; height:26px; padding:2px 4px;">
                        <option value="">Standard</option>
                        @foreach($saleTypes as $st)
                            <option value="{{ $st->id }}">{{ $st->name }}</option>
                        @endforeach
                    </select>
                </td>
                <td style="vertical-align:middle;">
                    <span class="badge badge-light" style="border:1px solid #cbd5e1; font-size:11px;">Unit</span>
                </td>
                <td style="text-align:right; vertical-align:middle;">
                    <input type="number" step="0.01" name="rate[]" class="form-control form-control-xs text-right row-rate" value="0.00" style="font-size:12px; height:26px; padding:2px 4px;">
                </td>
                <td style="text-align:right; vertical-align:middle;">
                    <input type="number" step="any" min="0.01" name="qty[]" class="form-control form-control-xs text-right row-qty font-weight-bold" value="1" style="font-size:12px; height:26px; padding:2px 4px;">
                </td>
                <td style="text-align:right; vertical-align:middle;">
                    <input type="text" readonly name="amount[]" class="form-control form-control-xs text-right row-amount" value="0.00" style="font-size:12px; height:26px; padding:2px 4px; background:#f8fafc;">
                </td>
                <td style="text-align:right; vertical-align:middle;">
                    <input type="number" step="0.01" name="tax_rate[]" class="form-control form-control-xs text-right row-tax-rate" value="0.00" style="font-size:11px; height:26px; padding:2px 4px;">
                </td>
                <td style="text-align:right; vertical-align:middle;">
                    <span class="font-weight-bold row-total-disp" style="font-size:12px; color:#0f172a;">₹ 0.00</span>
                </td>
                <td style="text-align:center; vertical-align:middle;">
                    <button type="button" class="btn btn-xs btn-link text-danger p-0 btn-remove-row" title="Remove Item"><i class="dripicons-trash"></i></button>
                </td>
            </tr>
        `;
        $('#order-table-body').append(rowHtml);
        renumberRows();
        recalculateTotals();
    });

    // --- Fast Batch Picker Filter & Addition ---
    $('#multi-item-search').on('input', function() {
        var term = $(this).val().toLowerCase().trim();
        $('#multi-item-tbody tr.multi-item-row').each(function() {
            var name = ($(this).data('name') || '').toLowerCase();
            var code = ($(this).data('code') || '').toLowerCase();
            if (name.includes(term) || code.includes(term)) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
    });

    $('#select-all-multi').on('change', function() {
        var checked = $(this).is(':checked');
        $('#multi-item-tbody tr.multi-item-row:visible .multi-item-cb').prop('checked', checked);
    });

    $('#btn-add-selected-items').on('click', function() {
        $('#multi-item-tbody .multi-item-cb:checked').each(function() {
            var $row = $(this).closest('tr');
            addProductRow({
                product_id: $row.data('id'),
                product_name: $row.data('name'),
                product_code: $row.data('code'),
                price: $row.data('price'),
                cost: $row.data('cost'),
                tax_rate: 0,
                unit: $row.data('unit'),
                qty: 1
            });
        });
        $('#multi-item-modal').modal('hide');
        $('#multi-item-tbody .multi-item-cb').prop('checked', false);
        $('#select-all-multi').prop('checked', false);
    });

    // --- Quick Create Item Modal Submission ---
    $('#quick-create-item-form').on('submit', function(e) {
        e.preventDefault();
        var name = $('#quick-item-name').val().trim();
        var code = $('#quick-item-code').val().trim() || ('ITM-' + Date.now().toString().slice(-6));
        var price = parseFloat($('#quick-item-price').val()) || 0;
        var cost = parseFloat($('#quick-item-cost').val()) || 0;

        addProductRow({
            product_id: 1,
            product_name: name,
            product_code: code,
            price: price,
            cost: cost,
            tax_rate: 0,
            unit: 'Unit',
            qty: 1
        });

        $('#quick-create-item-modal').modal('hide');
        this.reset();
    });

    // --- Slide-over Drawer Toggles ---
    $('#open-transport-drawer-btn, #btn-bottom-transport').on('click', function() {
        $('#comm-drawer').addClass('open');
    });
    $('#close-drawer-btn').on('click', function() {
        $('#comm-drawer').removeClass('open');
    });

    // --- Side Panel Toggles & Docking ---
    $('#toggle-drawer-btn, #reopen-panel-btn').on('click', function() {
        $('#desk-bill-list-panel').toggle();
    });
    $('#btn-close-panel').on('click', function() {
        $('#desk-bill-list-panel').hide();
    });

    $('#btn-dock-toggle').on('click', function() {
        $('#comm-split-grid').toggleClass('dock-left');
        var isLeft = $('#comm-split-grid').hasClass('dock-left');
        localStorage.setItem('zolo_dc_dock', isLeft ? 'left' : 'right');
    });
    if (localStorage.getItem('zolo_dc_dock') === 'left') {
        $('#comm-split-grid').addClass('dock-left');
    }

    // --- Side Panel Live Filter ---
    $('#side-search-input').on('input', function() {
        var term = $(this).val().toLowerCase().trim();
        $('#side-challan-cards .bill-card').each(function() {
            var no = ($(this).data('no') || '');
            var cust = ($(this).data('customer') || '');
            if (no.includes(term) || cust.includes(term)) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
    });

    // --- In-Place Edit Loading ---
    $(document).on('click', '.btn-load-challan, .bill-card', function(e) {
        if ($(e.target).is('a') || $(e.target).closest('a').length) return;
        var id = $(this).data('id');
        loadChallanToForm(id);
    });

    window.loadChallanToForm = function(id) {
        $.getJSON('/delivery-challans/' + id, function(res) {
            if (!res || !res.challan) return;
            var ch = res.challan;

            $('#edit-challan-id').val(ch.id);
            $('#form-method').val('PUT');
            $('#challan-entry-form').attr('action', '/delivery-challans/' + ch.id);

            $('#entry-title-text').text('Edit Delivery Challan: ' + ch.challan_no);
            $('#entry-breadcrumb').text('Home / Selling / Delivery Challans / Edit: ' + ch.challan_no);
            $('#status-pill').text(ch.status.toUpperCase().replace('_', ' '));

            $('#challan_no').val(ch.challan_no);
            $('#challan_date').val(ch.challan_date ? ch.challan_date.substring(0, 10) : '');
            $('#customer_id').val(ch.customer_id);
            $('#warehouse_id').val(ch.warehouse_id);
            $('#sale_type_id').val(ch.sale_type_id || '');
            $('#agent_id').val(ch.agent_id || '');
            $('#area_id').val(ch.area_id || '');
            $('#transport_name').val(ch.transport_name || '');
            $('#lr_no').val(ch.lr_no || '');
            $('#bale_no').val(ch.bale_no || '');
            $('#no_of_bales').val(ch.no_of_bales || '');
            $('#station_to').val(ch.station_to || '');
            $('#remarks').val(ch.remarks || '');

            // Populate items
            $('#order-table-body tr.item-row').remove();
            if (res.items && res.items.length) {
                $('#empty-row-placeholder').hide();
                res.items.forEach(function(item) {
                    addProductRow(item);
                });
            } else {
                $('#empty-row-placeholder').show();
            }

            if (ch.status === 'pending') {
                $('#btn-convert-sale').show().off('click').on('click', function() {
                    window.location.href = '/sales?from_dc=' + ch.id;
                });
            } else {
                $('#btn-convert-sale').hide();
            }

            $('#side-challan-cards .bill-card').removeClass('active-editing');
            $('#side-challan-cards .bill-card[data-id="' + ch.id + '"]').addClass('active-editing');
        });
    };

    // --- Discard & Reset to New Form ---
    $('#btn-discard, #btn-top-new').on('click', function() {
        $('#edit-challan-id').val('');
        $('#form-method').val('POST');
        $('#challan-entry-form').attr('action', '{{ route("delivery-challans.store") }}');
        $('#entry-title-text').text('New Delivery Challan');
        $('#entry-breadcrumb').text('Home / Selling / Delivery Challans / New');
        $('#status-pill').text('Draft DC');
        $('#challan-entry-form')[0].reset();
        $('#order-table-body tr.item-row').remove();
        $('#empty-row-placeholder').show();
        $('#btn-convert-sale').hide();
        $('#side-challan-cards .bill-card').removeClass('active-editing');
        recalculateTotals();
    });
</script>
@endsection
