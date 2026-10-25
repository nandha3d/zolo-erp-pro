@extends('backend.layout.main') 

@section('content')
<x-success-message key="message" />
<x-error-message key="not_permitted" />

@include('backend.partials.document-entry-layout')
<script>
    document.documentElement.classList.add('commercial-screen-lock');
    document.body.classList.add('commercial-screen-lock');
</script>
<div id="comm-progress-bar"></div>

<section class="commercial-workspace-view material-document-workspace">
    <div class="alert alert-danger" id="material-document-error" role="alert" style="display:none"></div>
    @if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
    <!-- Top Command Bar -->
    <div class="comm-command-bar">
        <div class="comm-title-group">
            <h1><i class="dripicons-box text-primary"></i> Customer DC <small class="text-muted u-fs-60pct u-fw-500">{{ __('Delivery Challan') }} Command Center · goods to customer before invoice</small></h1>
            <span class="comm-title-badge"><i class="dripicons-export"></i> Outbound Material Dispatch</span>
        </div>
        <ul class="comm-nav-pills">
            <li><a class="nav-link active" href="#desk-bill-list-panel" id="material-list-link"><i class="dripicons-list"></i> {{ __('All Challans') }}</a></li>
            <li><a class="nav-link" href="{{ route('sales.index') }}"><i class="dripicons-cart"></i> Sales Bills</a></li>
            <li><a class="nav-link" href="{{ route('packingSlip.index') }}"><i class="dripicons-clipboard"></i> Packing Slips</a></li>
            <li><a class="nav-link" href="{{ route('challan.index') }}"><i class="dripicons-document"></i> Courier Challans</a></li>
            <li><a class="nav-link" href="{{ route('delivery.index') }}"><i class="dripicons-rocket"></i> Deliveries</a></li>
            @if(config('operations.enabled') && in_array('operations.job_work', app(\App\Services\Platform\CapabilityService::class)->forNavigation(), true))
            <li><a class="nav-link" href="{{ url('/operations/job-work') }}" title="Material sent to a job worker goes on a Material DC inside its Job Work Order, not here."><i class="dripicons-network-3"></i> Sending to a job worker? Job Work</a></li>
            @endif
        </ul>
        <div class="comm-actions">
            <button type="button" class="btn btn-primary py-1 px-3 u-bg-primary u-bc-primary u-c-xffffff" id="toggle-drawer-btn" title="Toggle Challan List Panel">
                <i class="dripicons-view-list"></i> Challan list
            </button>
        </div>
    </div>

    @include('backend.partials.document-draft-tabs', ['draftLabel' => 'delivery challan', 'draftKey' => 'Alt+F2'])

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
                            <span class="entry-breadcrumb" id="entry-breadcrumb">Home / Selling / Customer DC / New</span>
                            <span class="badge badge-light-primary px-2 py-1 ml-2 u-fw-600 u-fs-11px u-bg-primary-soft u-c-primary u-b-1px-solid-primary-text u-r-4px" id="status-pill">Draft DC</span>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                        </div>
                    </div>

                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <div class="d-flex align-items-center gap-2">
                            <div class="entry-doc-icon"><i class="dripicons-box u-c-primary u-fs-20px"></i></div>
                            <h2 class="entry-title mb-0 u-fs-17px u-fw-700 u-c-text" id="entry-title-text">New Customer DC</h2>
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
                                <small class="text-muted u-fs-10px">Leave blank for auto-numbering</small>
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
                                    <a href="javascript:void(0)" class="text-primary font-weight-bold u-fs-11px" id="btn-quick-customer">+ New</a>
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
                                <label for="warehouse_id">{{ \App\Services\Industry\IndustryProfileService::label('warehouse', 'Warehouse') }} <span class="text-danger">*</span></label>
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

                        <div class="col-md-3 col-sm-6">
                            <div class="comm-field">
                                <label for="purpose" title="GST Rule 55: goods may leave on a challan before the invoice only in these cases. A regular sale needs the tax invoice at dispatch.">Challan Purpose (Rule 55) <span class="text-danger">*</span></label>
                                <select name="purpose" id="purpose" class="form-control form-control-sm" required>
                                    <option value="">Select purpose; use an invoice for a normal sale</option>
                                    @foreach(\App\Services\Commercial\MaterialDocumentService::DC_PURPOSES as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
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
                            <span class="items-counter font-weight-bold u-c-text u-fs-12px" id="items-counter">ITEMS 0 line(s)</span>
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
                            <button type="button" class="btn btn-sm btn-primary btn-compact u-bg-primary u-bc-primary" id="btn-add-row">
                                <i class="dripicons-plus"></i> Add row
                            </button>
                        </div>
                    </div>

                    <!-- Quick Barcode / Item Search Input -->
                    <div class="search-box-wrapper mb-2">
                        <div class="input-group input-group-sm">
                            <div class="input-group-prepend">
                                <span class="input-group-text u-bg-surface-2 u-bc-border-strong u-c-text-muted"><i class="dripicons-search"></i></span>
                            </div>
                            <input type="text" id="lims_productcodeSearch" class="form-control form-control-sm" placeholder="Scan barcode or type item code / name... (Alt+I to focus)" autocomplete="off">
                            <div class="input-group-append">
                                <span class="input-group-text text-muted u-bg-surface-2 u-bc-border-strong u-fs-11px">Alt+I Quick Search</span>
                            </div>
                        </div>
                    </div>

                    <!-- Purple Header 10-Column Items Table -->
                    <div class="table-responsive order-table-responsive u-b-1px-solid-border u-r-6px u-ov-hidden u-bg-surface">
                        <table class="table order-table mb-0 cozy u-w-100pct" id="order-table">
                            <thead class="u-bg-primary u-c-xffffff">
                                <tr>
                                    <th class="u-w-40px u-ta-center">#</th>
                                    <th>ITEM</th>
                                    <th class="u-w-130px">SALE TYPE</th>
                                    <th class="u-w-80px">UNIT</th>
                                    <th class="u-w-90px u-ta-right">RATE</th>
                                    <th class="u-w-80px u-ta-right">QTY</th>
                                    <th class="u-w-100px u-ta-right">AMOUNT</th>
                                    <th class="u-w-70px u-ta-right">TAX %</th>
                                    <th class="u-w-110px u-ta-right">TOTAL</th>
                                    <th class="u-w-60px u-ta-center">ACTIONS</th>
                                </tr>
                            </thead>
                            <tbody id="order-table-body">
                                <tr class="empty-row-placeholder" id="empty-row-placeholder">
                                    <td colspan="10" class="text-center py-4 text-muted u-fs-13px">
                                        <i class="dripicons-box mr-1"></i> No items added yet. Search or scan above or click "+ Add row".
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Slide-over Drawer for Transport & Remarks -->
                <div class="comm-drawer" id="comm-drawer" hidden>
                    <div class="comm-drawer-header d-flex align-items-center justify-content-between p-3 u-bg-surface-2 u-bb-1px-solid-border">
                        <h4 class="mb-0 font-weight-bold u-fs-14px u-c-text"><i class="dripicons-truck text-primary mr-1"></i> Transport & Logistics Details</h4>
                        <button type="button" class="close u-fs-18px" id="close-drawer-btn">&times;</button>
                    </div>
                    <div class="comm-drawer-body p-3">
                        <div class="form-group mb-2">
                            <label class="font-weight-bold u-fs-11px">Bale / Package No</label>
                            <input type="text" name="bale_no" id="bale_no" class="form-control form-control-sm" placeholder="e.g. BL-101">
                        </div>
                        <div class="form-group mb-2">
                            <label class="font-weight-bold u-fs-11px">No of Bales / Packages</label>
                            <input type="number" name="no_of_bales" id="no_of_bales" class="form-control form-control-sm" placeholder="e.g. 5">
                        </div>
                        <div class="form-group mb-2">
                            <label class="font-weight-bold u-fs-11px">LR Date</label>
                            <input type="date" name="lr_date" id="lr_date" class="form-control form-control-sm">
                        </div>
                        <div class="form-group mb-2">
                            <label class="font-weight-bold u-fs-11px">Destination Station</label>
                            <input type="text" name="station_to" id="station_to" class="form-control form-control-sm" placeholder="e.g. Mumbai, Surat">
                        </div>
                        <div class="form-group mb-2">
                            <label class="font-weight-bold u-fs-11px">Remarks & Delivery Notes</label>
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
                <div class="entry-bottom-bar mt-3 p-2 d-flex align-items-center justify-content-between flex-wrap gap-2 u-bg-surface u-bt-1px-solid-border u-r-6px u-sh-0-2px-8px-rgba-0-0-0-0_03">
                    <div class="d-flex align-items-center gap-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-bottom-transport">
                            <i class="dripicons-truck"></i> Transport Details
                        </button>
                        <span class="text-muted ml-2 u-fs-12px" id="bottom-notes-preview"></span>
                    </div>

                    <div class="d-flex align-items-center gap-3">
                        <div class="summary-metric text-right">
                            <span class="text-muted u-fs-10px u-tt-uppercase">NET</span>
                            <span class="font-weight-bold ml-1 u-fs-13px u-c-text" id="disp-net">₹ 0.00</span>
                        </div>
                        <div class="summary-metric text-right">
                            <span class="text-muted u-fs-10px u-tt-uppercase">TAX</span>
                            <span class="font-weight-bold ml-1 u-fs-13px u-c-text" id="disp-tax">₹ 0.00</span>
                        </div>
                        <div class="summary-metric text-right">
                            <span class="text-muted u-fs-10px u-tt-uppercase">GRAND TOTAL</span>
                            <span class="font-weight-bold ml-1 u-fs-15px u-c-primary" id="disp-grand">₹ 0.00</span>
                        </div>

                        <div class="d-flex align-items-center gap-2 ml-3">
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-discard" title="Discard Changes">
                                <i class="dripicons-trash"></i> Discard
                            </button>
                            <button type="submit" class="btn btn-sm btn-primary u-bg-primary u-bc-primary u-fw-600 u-p-6px-16px" id="btn-save">
                                <i class="dripicons-checkmark"></i> Save Challan
                            </button>
                            <button type="button" class="btn btn-sm btn-success u-fw-600" id="btn-convert-sale" style="display:none">
                                <i class="dripicons-cart"></i> Convert to Sale
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary u-fw-600" id="btn-return-open" style="display:none" title="Send back the goods not yet billed; stock and the accrual are reversed for that quantity.">
                                <i class="dripicons-return"></i> Goods came back
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <!-- Side Challan List Panel -->
        <div class="comm-drawer-card desk-bill-list-panel" id="desk-bill-list-panel">
            <div class="side-panel-header d-flex align-items-center justify-content-between p-2 u-bb-1px-solid-border u-bg-surface-2">
                <div class="d-flex align-items-center gap-2">
                    <span class="font-weight-bold text-dark u-fs-13px"><i class="dripicons-box text-primary mr-1"></i> Customer DC list</span>
                    <span class="badge badge-secondary u-fs-10px">{{ count($recent_challans) }}</span>
                </div>
                <div class="d-flex align-items-center gap-1">
                    <button type="button" class="btn btn-xs btn-outline-secondary" id="btn-dock-toggle" title="Dock Left/Right">⇄ Dock</button>
                    <button type="button" class="btn btn-xs btn-outline-secondary" id="btn-close-panel" title="Close Panel">✕</button>
                </div>
            </div>

            <!-- Side Search Box -->
            <div class="side-search-box p-2 u-bg-surface u-bb-1px-solid-border">
                <input type="text" id="side-search-input" class="form-control form-control-sm" placeholder="Find a challan by no or party...">
            </div>

            <!-- Challan Cards List -->
            <div class="side-challan-cards u-ovy-auto u-maxh-calc-100vh-220px u-p-8px" id="side-challan-cards">
                @forelse($recent_challans as $ch)
                    <div class="bill-card p-2 mb-2 u-b-1px-solid-border u-r-6px u-bg-surface u-cur-pointer u-tr-all-0_15s-ease" data-id="{{ $ch->id }}" data-no="{{ strtolower($ch->challan_no) }}" data-customer="{{ strtolower($ch->customer ? $ch->customer->name : '') }}">
                        <div class="d-flex align-items-center justify-content-between mb-1">
                            <span class="font-weight-bold text-primary u-fs-12px">{{ $ch->challan_no }}</span>
                            <span class="font-weight-bold u-fs-12px u-c-text">₹ {{ number_format((float)$ch->grand_total, 2) }}</span>
                        </div>
                        <div class="d-flex align-items-center justify-content-between text-muted u-fs-11px">
                            <span>{{ $ch->customer ? $ch->customer->name : 'N/A' }}</span>
                            <span>{{ \Carbon\Carbon::parse($ch->challan_date)->format('d M Y') }}</span>
                        </div>
                        <div class="d-flex align-items-center justify-content-between mt-2 pt-1 u-bt-1px-dashed-border">
                            <span class="badge {{ $ch->status === 'converted_to_sale' ? 'badge-success' : ($ch->status === 'cancelled' ? 'badge-danger' : ($ch->status === 'partially_billed' ? 'badge-info' : ($ch->status === 'returned' ? 'badge-secondary' : 'badge-warning'))) }} u-fs-10px">
                                {{ ucfirst(str_replace('_', ' ', $ch->status)) }}
                            </span>
                            <div class="d-flex align-items-center gap-1">
                                <button type="button" class="btn btn-xs btn-outline-primary py-0 px-2 btn-load-challan" data-id="{{ $ch->id }}" title="Edit in Form">✎ Edit</button>
                                @if(in_array($ch->status, ['pending', 'partially_billed'], true))
                                    <a href="{{ route('sales.index', ['from_dc' => $ch->id]) }}" class="btn btn-xs btn-outline-success py-0 px-2" title="Convert to Sale Bill">🗲 Convert</a>
                                @endif
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-4 text-muted u-fs-12px">
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
        <div class="modal-content u-r-8px u-b-1px-solid-border-strong">
            <div class="modal-header py-2 px-3 u-bg-primary u-c-xffffff">
                <h5 class="modal-title font-weight-bold u-fs-14px"><i class="dripicons-checklist mr-1"></i> Fast Batch Item Picker</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body p-3">
                <input type="text" id="multi-item-search" class="form-control form-control-sm mb-2" placeholder="Search item by name or code...">
                <div class="table-responsive u-maxh-340px u-ovy-auto u-b-1px-solid-border u-r-6px">
                    <table class="table table-hover table-sm mb-0">
                        <thead class="u-bg-surface-2">
                            <tr>
                                <th class="u-w-30px"><input type="checkbox" id="select-all-multi"></th>
                                <th>Item Code</th>
                                <th>Item Name</th>
                                <th>Unit</th>
                                <th class="text-right">Price</th>
                            </tr>
                        </thead>
                        <tbody id="multi-item-tbody">
                            @foreach(collect($lims_product_list_without_variant)->merge($lims_product_list_with_variant)->unique('id') as $prod)
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
            <div class="modal-footer py-2 px-3 u-bg-surface-2">
                <button type="button" class="btn btn-sm btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-sm btn-primary u-bg-primary u-bc-primary" id="btn-add-selected-items">Add Selected Items</button>
            </div>
        </div>
    </div>
</div>

<!-- On-The-Fly Quick Item Creation Modal -->
<div class="modal fade" id="quick-create-item-modal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content u-r-8px u-b-1px-solid-border-strong">
            <div class="modal-header py-2 px-3 u-bg-primary u-c-xffffff">
                <h5 class="modal-title font-weight-bold u-fs-14px"><i class="dripicons-plus mr-1"></i> Quick Create Item</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <form id="quick-create-item-form">
                <div class="modal-body p-3">
                    <div class="form-group mb-2">
                        <label class="font-weight-bold u-fs-11px">Item Name <span class="text-danger">*</span></label>
                        <input type="text" id="quick-item-name" class="form-control form-control-sm" required placeholder="e.g. Cotton Grey Fabric 40s">
                    </div>
                    <div class="form-group mb-2">
                        <label class="font-weight-bold u-fs-11px">Item Code / Barcode</label>
                        <input type="text" id="quick-item-code" class="form-control form-control-sm" placeholder="Auto-generated if empty">
                    </div>
                    <div class="row gx-2 gy-2">
                        <div class="col-6">
                            <div class="form-group mb-2">
                                <label class="font-weight-bold u-fs-11px">Selling Rate (₹)</label>
                                <input type="number" step="0.01" id="quick-item-price" class="form-control form-control-sm" value="0.00">
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-group mb-2">
                                <label class="font-weight-bold u-fs-11px">Cost Rate (₹)</label>
                                <input type="number" step="0.01" id="quick-item-cost" class="form-control form-control-sm" value="0.00">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer py-2 px-3 u-bg-surface-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary u-bg-primary u-bc-primary">Save & Add to Challan</button>
                </div>
            </form>
        </div>
    </div>
</div>

@include('backend.partials.material-party-modal', ['partyType' => 'customer'])
@endsection

@push('scripts')
    @php
        $jsProductList = [];
        foreach(collect($lims_product_list_without_variant)->merge($lims_product_list_with_variant)->unique('id') as $prod) {
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
                'unit_id' => (int)$prod->unit_id,
                'is_variant' => (bool)$prod->is_variant,
                'variants' => ($materialVariants[$prod->id] ?? collect())->map(fn ($variant) => ['id' => (int)$variant->variant_id, 'name' => $variant->name, 'code' => $variant->item_code])->values()->all(),
                'unit' => (string)($prod->unit_code ?? ($prod->unit_name ?? 'Unit')),
                'value' => (string)$prod->code . '|' . (string)$prod->name,
                'label' => (string)$prod->code . ' - ' . (string)$prod->name,
            ];
        }
    @endphp

    @php
        $materialConfig = [
            'form' => 'challan-entry-form', 'documentKey' => 'challan', 'idField' => 'challan_id',
            'numberField' => 'challan_no', 'totalField' => 'total_amount', 'rateField' => 'rate',
            'purchase' => false, 'products' => $jsProductList, 'decimals' => $general_setting->decimal ?? 2,
            'typeLabel' => 'Sale', 'label' => 'Customer DC', 'title' => 'New Customer DC',
            'breadcrumb' => 'Home / Selling / Customer DC / New',
            'storeUrl' => route('delivery-challans.store'), 'productUrl' => route('products.quick-store'),
            'partyUrl' => route('parties.quick-store'), 'token' => csrf_token(),
            'partyType' => 'customer', 'partySelect' => '#customer_id', 'partyButton' => '#btn-quick-customer',
            'convertButton' => '#btn-convert-sale', 'convertUrl' => route('sales.index'), 'convertParam' => 'from_dc', 'returnButton' => '#btn-return-open',
            'editId' => request('edit'),
            'draftKind' => 'dc', 'draftUrl' => url('documents/dc'), 'shortcut' => 'delivery-challan',
        ];
    @endphp
    <script type="application/json" id="material-document-data">@json($materialConfig)</script>
    <script src="{{ asset('js/document-drafts.js') }}?v={{ filemtime(public_path('js/document-drafts.js')) }}"></script>
    <script src="{{ asset('js/material-document.js') }}?v={{ filemtime(public_path('js/material-document.js')) }}"></script>
@endpush

@push('css')
    <link rel="stylesheet" href="{{ versioned_asset('css/commercial-assistance.css') }}">
    <link rel="stylesheet" href="{{ versioned_asset('css/pages/shared-purchase-index.css') }}">
    <link rel="stylesheet" href="<?php echo asset('css/commercial-entry.css') . '?v=' . (file_exists(public_path('css/commercial-entry.css')) ? filemtime(public_path('css/commercial-entry.css')) : time()) ?>" type="text/css">
    <link rel="stylesheet" href="<?php echo asset('css/commercial-workspace.css') . '?v=' . (file_exists(public_path('css/commercial-workspace.css')) ? filemtime(public_path('css/commercial-workspace.css')) : time()) ?>" type="text/css">
@endpush
