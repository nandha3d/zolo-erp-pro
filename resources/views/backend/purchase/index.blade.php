@extends('backend.layout.main') 

@section('content')

<style type="text/css">
    .btn-icon i{margin-right:5px}
    .top-fields{margin-top:10px;position: relative;}
    .top-fields label {background:#FFF;font-size:11px;font-weight:600;margin-left:10px;padding:0 3px;position:absolute;top:-8px;z-index:9;}
    .top-fields input{font-size:13px;height:45px}
</style>

<x-success-message key="message" />
<x-error-message key="not_permitted" />


<link rel="stylesheet" href="{{ asset('css/commercial-workspace.css') }}" type="text/css">
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
            <li><a class="nav-link" id="tab-pending-purchases" href="javascript:void(0)" data-status="3"><i class="dripicons-hourglass"></i> Pending Receipts</a></li>
            <li><a class="nav-link" href="{{ route('transfers.index') }}"><i class="dripicons-swap"></i> Stock Transfers</a></li>
            <li><a class="nav-link" href="{{ route('return-purchase.index') }}"><i class="dripicons-return"></i> {{ __('Purchase Returns') }}</a></li>
        </ul>
        <div class="comm-actions">
            <button type="button" class="btn btn-outline-secondary" id="toggle-filter" title="Toggle Filters">
                <i class="dripicons-experiment"></i> {{ __('db.Filter') }}
            </button>
            @can('purchases-add')
            <a href="{{ route('purchases.create') }}" class="btn btn-success" title="Full Purchase Form">
                <i class="dripicons-plus"></i> {{ __('db.Add Purchase') }}
            </a>
            @endcan
            <a href="{{ route('commercial.purchase.entry') }}" class="btn btn-dark" style="background:#7c3aed; border-color:#7c3aed; color:#fff;" title="Modern Desk Purchase Inward (F12)">
                <i class="dripicons-document text-light"></i> Modern Desk Inward <kbd style="font-size:10px; background:#5b21b6; padding:1px 4px; border-radius:3px;">F12</kbd>
            </a>
            <button type="button" class="btn btn-light border" id="toggle-drawer-btn" title="Toggle Quick Drawer (Alt+D)">
                <i class="dripicons-menu"></i>
            </button>
        </div>
    </div>

    <!-- KPI Summary Strip -->
    <div class="comm-kpi-strip">
        <div class="comm-kpi-box">
            <div class="comm-kpi-info">
                <span class="comm-kpi-title">{{ __("Today's Purchases") }}</span>
                <span class="comm-kpi-value">{{ $todayPurchasesCount ?? 0 }} <small style="font-size:11px;font-weight:600;color:#64748b;">({{ number_format($todayPurchasesAmount ?? 0, 2) }})</small></span>
            </div>
            <div class="comm-kpi-icon" style="background:#f0fdf4; color:#16a34a;">
                <i class="dripicons-cart"></i>
            </div>
        </div>
        <div class="comm-kpi-box">
            <div class="comm-kpi-info">
                <span class="comm-kpi-title">{{ __("Paid Outflow") }}</span>
                <span class="comm-kpi-value" style="color:#0284c7;">{{ number_format($totalPaid ?? 0, 2) }}</span>
            </div>
            <div class="comm-kpi-icon" style="background:#f0f9ff; color:#0284c7;">
                <i class="dripicons-checkmark"></i>
            </div>
        </div>
        <div class="comm-kpi-box">
            <div class="comm-kpi-info">
                <span class="comm-kpi-title">{{ __("Due Payables") }}</span>
                <span class="comm-kpi-value" style="color:#ea580c;">{{ number_format($totalDue ?? 0, 2) }}</span>
            </div>
            <div class="comm-kpi-icon" style="background:#fff7ed; color:#ea580c;">
                <i class="dripicons-warning"></i>
            </div>
        </div>
        <div class="comm-kpi-box">
            <div class="comm-kpi-info">
                <span class="comm-kpi-title">{{ __("Total Invoiced") }}</span>
                <span class="comm-kpi-value">{{ number_format(($totalPaid ?? 0) + ($totalDue ?? 0), 2) }}</span>
            </div>
            <div class="comm-kpi-icon" style="background:#eef2ff; color:#4f46e5;">
                <i class="dripicons-archive"></i>
            </div>
        </div>
    </div>

    <!-- 2-Column Command Center Grid -->
    <div class="comm-split-grid" id="comm-split-grid">
        <!-- Left: Purchase Register Table -->
        <div class="comm-register-card">
            <!-- Sleek Inline Filter Bar -->
            <div class="comm-table-filter-bar" id="filter-card">
                <div class="filter-item">
                    <label><i class="dripicons-calendar text-muted"></i></label>
                    <input type="text" class="daterangepicker-field form-control" value="{{$starting_date}} To {{$ending_date}}" required />
                    <input type="hidden" name="starting_date" value="{{$starting_date}}" />
                    <input type="hidden" name="ending_date" value="{{$ending_date}}" />
                </div>
                <div class="filter-item @if(\Auth::user()->role_id > 2){{'d-none'}}@endif">
                    <label>{{__('db.Warehouse')}}:</label>
                    <select id="warehouse_id" name="warehouse_id" class="form-control" style="width:130px;">
                        <option value="0">{{__('db.All Warehouse')}}</option>
                        @foreach($lims_warehouse_list as $warehouse)
                            <option value="{{$warehouse->id}}">{{$warehouse->name}}</option>
                        @endforeach
                    </select>
                </div>
                <div class="filter-item">
                    <label>{{__('db.Purchase Status')}}:</label>
                    <select id="purchase-status" class="form-control" name="purchase_status" style="width:110px;">
                        <option value="0">{{__('db.All')}}</option>
                        <option value="1">{{__('db.Recieved')}}</option>
                        <option value="2">{{__('db.Partial')}}</option>
                        <option value="3">{{__('db.Pending')}}</option>
                        <option value="4">{{__('db.Ordered')}}</option>
                    </select>
                </div>
                <div class="filter-item">
                    <label>{{__('db.Payment Status')}}:</label>
                    <select id="payment-status" class="form-control" name="payment_status" style="width:100px;">
                        <option value="0">{{__('db.All')}}</option>
                        <option value="1">{{__('db.Due')}}</option>
                        <option value="2">{{__('db.Paid')}}</option>
                    </select>
                </div>
                <button type="button" class="btn btn-sm btn-outline-success ml-auto" id="btn-quick-refresh" title="Reload Register">
                    <i class="dripicons-clockwise"></i>
                </button>
            </div>

            <!-- Sticky Header Table Viewport -->
            <div class="comm-table-viewport">
                <table id="purchase-table" class="table purchase-list mt-0" style="width: 100%">
                    <thead>
                        <tr>
                            <th class="not-exported"></th>
                            <th>{{__('db.date')}}</th>
                            <th>{{__('db.reference')}}</th>
                            <th>{{__('db.Created By')}}</th>
                            <th>{{__('db.Supplier')}}</th>
                            @if ($general_setting->show_products_details_in_purchase_table)
                                <th>{{ __('db.Products') }}</th>
                                <th>{{ __('db.Quantity') }}</th>
                            @endif
                            <th>{{__('db.Purchase Status')}}</th>
                            <th>{{__('db.grand total')}}</th>
                            <th>{{__('db.Returned Amount')}}</th>
                            <th>{{__('db.Paid')}}</th>
                            <th>{{__('db.Due')}}</th>
                            <th>{{__('db.Payment Status')}}</th>
                            @foreach($custom_fields as $fieldName)
                            <th>{{$fieldName}}</th>
                            @endforeach
                            <th class="not-exported">{{__('db.action')}}</th>
                        </tr>
                    </thead>
                    <tfoot class="tfoot active">
                        <th></th>
                        <th>{{__('db.Total')}}</th>
                        <th></th>
                        <th></th>
                        <th></th>
                        @if ($general_setting->show_products_details_in_purchase_table)
                            <th></th>
                            <th></th>
                        @endif
                        <th></th>
                        <th></th>
                        <th></th>
                        <th></th>
                        <th></th>
                        <th></th>
                        @foreach($custom_fields as $fieldName)
                        <th></th>
                        @endforeach
                        <th></th>
                    </tfoot>
                </table>
            </div>
        </div>

        <!-- Right: Fast Purchase Console Drawer -->
        <div class="comm-drawer-card" id="comm-drawer">
            <div class="comm-drawer-header">
                <h3 class="comm-drawer-title"><i class="dripicons-bolt text-success"></i> Fast Purchase Console</h3>
                <div class="comm-drawer-tools">
                    <span class="badge badge-light border" style="font-size:10px;">F12 Active</span>
                    <button type="button" class="btn btn-sm btn-link text-muted p-0" id="close-drawer-btn"><i class="dripicons-cross"></i></button>
                </div>
            </div>
            <div class="comm-drawer-body">
                <div class="comm-form-group">
                    <label>{{ __('db.Supplier') }} <a href="{{ route('supplier.create') }}" target="_blank" class="text-success">+ New</a></label>
                    <select id="quick-supplier-id" class="form-control">
                        @if(isset($lims_supplier_list) && count($lims_supplier_list))
                            @foreach($lims_supplier_list as $sup)
                                <option value="{{ $sup->id }}">{{ $sup->name }} @if($sup->company_name)({{ $sup->company_name }})@endif</option>
                            @endforeach
                        @else
                            <option value="1">Default Vendor</option>
                        @endif
                    </select>
                </div>

                <div class="comm-form-row">
                    <div class="comm-form-group">
                        <label>{{ __('db.Warehouse') }}</label>
                        <select id="quick-purchase-warehouse-id" class="form-control">
                            @foreach($lims_warehouse_list as $wh)
                                <option value="{{ $wh->id }}">{{ $wh->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="comm-form-group">
                        <label>{{ __('db.date') }}</label>
                        <input type="date" id="quick-purchase-date" class="form-control" value="{{ date('Y-m-d') }}" />
                    </div>
                </div>

                <div class="comm-form-group position-relative">
                    <label>{{ __('Search Item (Code / Barcode)') }}</label>
                    <div class="input-group">
                        <input type="text" id="quick-purchase-product-search" class="form-control" placeholder="Type product name or scan..." autocomplete="off" />
                        <div class="input-group-append">
                            <button class="btn btn-sm btn-success" type="button" id="btn-quick-purchase-add-item"><i class="dripicons-plus"></i></button>
                        </div>
                    </div>
                    <div id="quick-purchase-search-results" class="list-group position-absolute shadow" style="z-index:999; display:none; max-height:160px; overflow-y:auto; width:100%; top:58px; background:#fff;"></div>
                </div>

                <!-- Fast Line Items -->
                <div class="comm-drawer-lines">
                    <table class="table" id="quick-purchase-items-table">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th style="width:60px;">Qty</th>
                                <th style="width:70px;">Cost</th>
                                <th style="width:70px;">Total</th>
                                <th style="width:30px;"></th>
                            </tr>
                        </thead>
                        <tbody id="quick-purchase-items-body">
                            <tr class="empty-row"><td colspan="5" class="text-center text-muted py-2" style="font-size:11px;">No items added. Search above or press F12.</td></tr>
                        </tbody>
                    </table>
                </div>

                <!-- Totals -->
                <div class="comm-summary-box">
                    <div class="comm-summary-row">
                        <span>Items Subtotal:</span>
                        <span id="quick-purchase-subtotal">0.00</span>
                    </div>
                    <div class="comm-summary-row">
                        <span>Status:</span>
                        <span class="badge badge-success">Received</span>
                    </div>
                    <div class="comm-summary-row grand-total">
                        <span>Total Payable:</span>
                        <span id="quick-purchase-grand-total">0.00</span>
                    </div>
                </div>

                <div class="comm-form-row">
                    <div class="comm-form-group">
                        <label>{{ __('db.Payment Status') }}</label>
                        <select id="quick-purchase-payment-status" class="form-control">
                            <option value="Paid">Paid</option>
                            <option value="Due">Due</option>
                        </select>
                    </div>
                    <div class="comm-form-group">
                        <label>{{ __('Paid Amount') }}</label>
                        <input type="number" id="quick-purchase-paid-amount" class="form-control" placeholder="0.00" />
                    </div>
                </div>

                <div class="comm-drawer-footer">
                    <button type="button" class="btn-comm-primary" style="background:linear-gradient(135deg, #10b981 0%, #059669 100%);" id="btn-quick-post-purchase">
                        <i class="dripicons-checkmark"></i> Post Purchase
                    </button>
                    <a href="{{ route('commercial.purchase.entry') }}" class="btn-comm-secondary" title="Open Full Fast Purchase">
                        <i class="dripicons-external"></i> Full Entry (F12)
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>


<div id="purchase-details" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true" class="modal fade text-left">
    <div role="document" class="modal-dialog">
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
            <div class="table-responsive document-lines" tabindex="0" role="region" aria-label="{{__('db.Purchase Details')}}">
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
</div>

<div id="view-payment" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true" class="modal fade text-left">
    <div role="document" class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 id="exampleModalLabel" class="modal-title">{{__('db.All Payment')}}</h5>
                <button type="button" data-dismiss="modal" aria-label="Close" class="close"><span aria-hidden="true"><i class="dripicons-cross"></i></span></button>
            </div>
            <div class="modal-body">
                <table class="table table-hover payment-list">
                    <thead>
                        <tr>
                            <th>{{__('db.date')}}</th>
                            <th>{{__('db.Reference No')}}</th>
                            <th>{{__('db.Account')}}</th>
                            <th>{{__('db.Amount')}}</th>
                            <th>{{__('db.Paid By')}}</th>
                            <th>{{__('db.Payment Date')}}</th>
                            <th>{{__('db.action')}}</th>
                        </tr>
                    </thead>
                    <tbody>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div id="add-payment" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true" class="modal fade text-left">
    <div role="document" class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 id="exampleModalLabel" class="modal-title">{{__('db.Add Payment')}}</h5>
                <button type="button" data-dismiss="modal" aria-label="Close" class="close"><span aria-hidden="true"><i class="dripicons-cross"></i></span></button>
            </div>
            <div class="modal-body">
                {!! Form::open(['route' => 'purchase.add-payment', 'method' => 'post', 'class' => 'payment-form' ]) !!}
                @if(config('commercial.enabled'))<input type="hidden" name="idempotency_key" value="{{ (string) Illuminate\Support\Str::uuid() }}">@endif
                    <div class="row">
                        <input type="hidden" name="balance">
                        <div class="col-md-6">
                            <label>{{__('db.Recieved Amount')}} *</label>
                            <input type="text" name="paying_amount" class="form-control numkey"  step="any" required>
                        </div>
                        <div class="col-md-6">
                            <label>{{__('db.Paying Amount')}} *</label>
                            <input type="text" id="amount" name="amount" class="form-control"  step="any" required>
                        </div>
                        <div class="col-md-6 mt-1">
                            <label>{{__('db.Change')}} : </label>
                            <p class="change ml-2">{{number_format(0, $general_setting->decimal, '.', '')}}</p>
                        </div>
                        <div class="col-md-6 mt-1">
                            <label>{{__('db.Paid By')}}</label>
                            <select name="paid_by_id" class="form-control">
                                <option value="1">{{ __('db.Cash') }}</option>
                                <option value="3">{{ __('db.Credit Card') }}</option>
                                <option value="4">{{ __('db.Cheque') }}</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group mt-2">
                        <div class="card-element" class="form-control">
                        </div>
                        <div class="card-errors" role="alert"></div>
                    </div>
                    <div id="cheque">
                        <div class="form-group">
                            <label>{{__('db.Cheque Number')}} *</label>
                            <input type="text" name="cheque_no" class="form-control">
                        </div>
                    </div>
                    <div class="form-group row">
                        <div class="col-md-6">
                            <label> {{__('db.Account')}}</label>
                            <select class="form-control selectpicker" name="account_id">
                                @foreach($lims_account_list as $account)
                                    @if($account->is_default)
                                    <option selected value="{{$account->id}}">{{$account->name}} [{{$account->account_no}}]</option>
                                    @else
                                    <option value="{{$account->id}}">{{$account->name}} [{{$account->account_no}}]</option>
                                    @endif
                                @endforeach
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label>{{ __('db.Payment Date') }}</label>
                            <input type="text" name="payment_at" id="payment_at" class="form-control"
                                value="{{ date('Y-m-d') }}" required>
                        </div>
                        <div class="col-md-4">
                            <label>{{__('db.Currency')}} & {{__('db.Exchange Rate')}}</label>
                            <div class="form-group d-flex align-items-center">
                                <p id="currency_display" class="form-control-plaintext mb-0 font-weight-bold mr-3"></p>
                                <p id="exchange_rate_display" class="form-control-plaintext mb-0 font-weight-bold"></p>
                            </div>

                            <!-- Hidden fields for backend -->
                            <input type="hidden" name="currency_id" id="currency_id">
                            <input type="hidden" name="exchange_rate" id="exchange_rate">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>{{__('db.Payment Note')}}</label>
                        <textarea rows="3" class="form-control" name="payment_note"></textarea>
                    </div>

                    <input type="hidden" name="purchase_id">

                    <button type="submit" class="btn btn-primary">{{__('db.submit')}}</button>
                {{ Form::close() }}
            </div>
        </div>
    </div>
</div>

<div id="edit-payment" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true" class="modal fade text-left">
    <div role="document" class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 id="exampleModalLabel" class="modal-title">{{__('db.Update Payment')}}</h5>
                <button type="button" data-dismiss="modal" aria-label="Close" class="close"><span aria-hidden="true"><i class="dripicons-cross"></i></span></button>
            </div>
            <div class="modal-body">
                {!! Form::open(['route' => 'purchase.update-payment', 'method' => 'post', 'class' => 'payment-form' ]) !!}
                    <div class="row">
                        <div class="col-md-6">
                            <label>{{__('db.Recieved Amount')}} *</label>
                            <input type="text" name="edit_paying_amount" class="form-control numkey"  step="any" required>
                        </div>
                        <div class="col-md-6">
                            <label>{{__('db.Paying Amount')}} *</label>
                            <input type="text" name="edit_amount" class="form-control"  step="any" required>
                        </div>
                        <div class="col-md-6 mt-1">
                            <label>{{__('db.Change')}} : </label>
                            <p class="change ml-2">{{number_format(0, $general_setting->decimal, '.', '')}}</p>
                        </div>
                        <div class="col-md-6 mt-1">
                            <label>{{__('db.Paid By')}}</label>
                            <select name="edit_paid_by_id" class="form-control selectpicker">
                                <option value="1">{{ __('db.Cash') }}</option>
                                <option value="3">{{ __('db.Credit Card') }}</option>
                                <option value="4">{{ __('db.Cheque') }}</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group mt-2">
                        <div class="card-element" class="form-control">
                        </div>
                        <div class="card-errors" role="alert"></div>
                    </div>
                    <div id="edit-cheque">
                        <div class="form-group">
                            <label>{{__('db.Cheque Number')}} *</label>
                            <input type="text" name="edit_cheque_no" class="form-control">
                        </div>
                    </div>
                    <div class="form-group row">
                        <div class="col-md-6">
                            <label> {{__('db.Account')}}</label>
                            <select class="form-control selectpicker" name="account_id">
                            @foreach($lims_account_list as $account)
                                <option value="{{$account->id}}">{{$account->name}} [{{$account->account_no}}]</option>
                            @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label>{{ __('db.Payment Date') }}</label>
                            <input type="text" name="payment_at" id="edit_payment_at" class="form-control"
                                value="" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>{{__('db.Payment Note')}}</label>
                        <textarea rows="3" class="form-control" name="edit_payment_note"></textarea>
                    </div>

                    <input type="hidden" name="payment_id">

                    <button type="submit" class="btn btn-primary">{{__('db.update')}}</button>
                {{ Form::close() }}
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script type="text/javascript">

    $('#toggle-filter').on('click', function() {
        $('#filter-card').slideToggle('slow');
    });

    $(function () {
        $('#payment_at').datepicker({
            format: 'yyyy-mm-dd',
            autoclose: true,
            todayHighlight: true
        }).datepicker("setDate", new Date());
        $('#edit_payment_at').datepicker({
            format: 'yyyy-mm-dd',
            autoclose: true,
            todayHighlight: true
        });
    });

    $('.daterangepicker-field').daterangepicker({
        autoUpdateInput: true,
        locale: {
            format: 'YYYY-MM-DD',
            cancelLabel: 'Clear'
        },
        showDropdowns: true,
        ranges: {
            'Today': [moment(), moment()],
            'Last 7 Days': [moment().subtract(6, 'days'), moment()],
            'Last 30 Days': [moment().subtract(29, 'days'), moment()],
            'Last 90 Days': [moment().subtract(89, 'days'), moment()],
            'Last Year': [moment().subtract(1, 'year').startOf('year'), moment().subtract(1, 'year').endOf('year')],
            'All Time': [moment('2000-01-01'), moment()]
        }
    }, function (start, end, label) {
        let starting_date = start.format('YYYY-MM-DD');
        let ending_date = end.format('YYYY-MM-DD');
        let title = starting_date + ' To ' + ending_date;

        $('.daterangepicker-field').val(title);
        $('input[name="starting_date"]').val(starting_date);
        $('input[name="ending_date"]').val(ending_date);

        purchaseTable.ajax.reload();
    });

    $("ul#purchase").siblings('a').attr('aria-expanded','true');
    $("ul#purchase").addClass("show");
    $("ul#purchase #purchase-list-menu").addClass("active");

    @if($lims_pos_setting_data)
        var public_key = <?php echo json_encode($lims_pos_setting_data->stripe_public_key) ?>;
    @endif
    var all_permission = <?php echo json_encode($all_permission) ?>;

    var purchase_id = [];
    var user_verified = <?php echo json_encode(env('USER_VERIFIED')) ?>;
    var starting_date = <?php echo json_encode($starting_date); ?>;
    var ending_date = <?php echo json_encode($ending_date); ?>;
    var warehouse_id = <?php echo json_encode($warehouse_id); ?>;
    var purchase_status = <?php echo json_encode($purchase_status); ?>;
    var payment_status = <?php echo json_encode($payment_status); ?>;

    var show_purchase_product_details = <?php echo json_encode($general_setting->show_products_details_in_purchase_table) ?>;
    if(show_purchase_product_details == 1){
        var columns = [
            {"data": "key"},
            {"data": "date"},
            {"data": "reference_no"},
            {"data": "created_by"},
            {"data": "supplier"},
            {"data": "products"},
            {"data": "products_qty"},
            {"data": "purchase_status"},
            {"data": "grand_total"},
            {"data": "returned_amount"},
            {"data": "paid_amount"},
            {"data": "due"},
            {"data": "payment_status"}
        ];
    }else{
        var columns = [
            {"data": "key"},
            {"data": "date"},
            {"data": "reference_no"},
            {"data": "created_by"},
            {"data": "supplier"},
            {"data": "purchase_status"},
            {"data": "grand_total"},
            {"data": "returned_amount"},
            {"data": "paid_amount"},
            {"data": "due"},
            {"data": "payment_status"}
        ];
    }

    var field_name = <?php echo json_encode($field_name) ?>;
    for(i = 0; i < field_name.length; i++) {
        columns.push({"data": field_name[i]});
    }
    columns.push({"data": "options"});

    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

    $("#warehouse_id").val(warehouse_id);
    $("#purchase-status").val(purchase_status);
    $("#payment-status").val(payment_status);

    $('.selectpicker').selectpicker('refresh');

    function confirmDelete() {
        if (confirm("Are you sure want to delete?")) {
            return true;
        }
        return false;
    }

    function confirmDeletePayment() {
        if (confirm("Are you sure want to delete? If you delete this money will be refunded")) {
            return true;
        }
        return false;
    }

    $(document).on("click", "tr.purchase-link td:not(:first-child, :last-child)", function(){
        var purchase = $(this).parent().data('purchase');
        purchaseDetails(purchase);
    });

    $(document).on("click", ".view", function(){
        var purchase = $(this).parent().parent().parent().parent().parent().data('purchase');
        purchaseDetails(purchase);
    });

    $("#print-btn").on("click", function(){
        var divContents = document.getElementById("purchase-details").innerHTML;
        var a = window.open('');
        a.document.write('<html>');
        a.document.write('<body><style>body{font-family: sans-serif;line-height: 1.15;-webkit-text-size-adjust: 100%;}.d-print-none{display:none}.text-center{text-align:center}.row{width:100%;margin-right: -15px;margin-left: -15px;}.col-md-12{width:100%;display:block;padding: 5px 15px;}.col-md-6{width: 50%;float:left;padding: 5px 15px;}table{width:100%;margin-top:30px;}th{text-aligh:left;}td{padding:10px}table, th, td{border: 1px solid black; border-collapse: collapse;}</style><style>@media print {.modal-dialog { max-width: 1000px;} }</style>');
        a.document.write(divContents);
        a.document.write('</body></html>');
        a.document.close();
        setTimeout(function(){a.close();},10);
        a.print();
    });

    $(document).on("click", "table.purchase-list tbody .add-payment", function(event) {
        $("#cheque").hide();
        $(".card-element").hide();
        $('select[name="paid_by_id"]').val(1);
        rowindex = $(this).closest('tr').index();
        var purchase_id = $(this).data('id').toString();
        let currency_name = $(this).data('currency_name');
        let currency_id = $(this).data('currency_id');
        let exchange_rate = parseFloat($(this).data('exchange_rate')) || 1;
        if(show_purchase_product_details == 1){
            var balance = $('table.purchase-list tbody tr:nth-child(' + (rowindex + 1) + ')').find('td:nth-child(11)').text();
        }else{
            var balance = $('table.purchase-list tbody tr:nth-child(' + (rowindex + 1) + ')').find('td:nth-child(9)').text();
        }

        balance = parseFloat(balance.replace(/,/g, ''));
        $('input[name="amount"]').val(balance);
        $('input[name="balance"]').val(balance);
        $('input[name="paying_amount"]').val(balance);
        $('input[name="purchase_id"]').val(purchase_id);
        // Fill readonly currency info
        $('#currency_display').text(currency_name);
        $('#exchange_rate_display').text(exchange_rate.toFixed(2));

        // Hidden inputs for backend
        $('#currency_id').val(currency_id);
        $('#exchange_rate').val(exchange_rate);
    });

    $(document).on("click", "table.purchase-list tbody .get-payment", function(event) {
        var id = $(this).data('id').toString();
        $.get('purchases/getpayment/' + id, function(data) {
            $(".payment-list tbody").remove();
            var newBody = $("<tbody>");
            payment_date  = data[0];
            payment_reference = data[1];
            paid_amount = data[2];
            paying_method = data[3];
            payment_id = data[4];
            payment_note = data[5];
            cheque_no = data[6];
            change = data[7];
            paying_amount = data[8];
            account_name = data[9];
            account_id = data[10];
            payment_at = data[11];

            $.each(payment_date, function(index){
                var newRow = $("<tr>");
                var cols = '';

                cols += '<td>' + payment_date[index] + '</td>';
                cols += '<td>' + payment_reference[index] + '</td>';
                cols += '<td>' + account_name[index] + '</td>';
                cols += '<td>' + paid_amount[index] + '</td>';
                cols += '<td>' + paying_method[index] + '</td>';
                cols += '<td>' + payment_at[index] + '</td>';
                cols += '<td><div class="btn-group"><button type="button" class="btn btn-default dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">Action<span class="caret"></span><span class="sr-only">Toggle Dropdown</span></button><ul class="dropdown-menu edit-options dropdown-menu-right dropdown-default" user="menu">';
                if(all_permission.indexOf("purchase-payment-edit") != -1)
                    cols += '<li><button type="button" class="btn btn-link edit-btn" data-id="' + payment_id[index] +'" data-clicked=false data-toggle="modal" data-target="#edit-payment"><i class="dripicons-document-edit"></i> Edit</button></li><li class="divider"></li>';
                if(all_permission.indexOf("purchase-payment-delete") != -1)
                    cols += '{{ Form::open(['route' => 'purchase.delete-payment', 'method' => 'post'] ) }}<li><input type="hidden" name="id" value="' + payment_id[index] + '" /> <button type="submit" class="btn btn-link" onclick="return confirmDeletePayment()"><i class="dripicons-trash"></i> Delete</button></li>{{ Form::close() }}';
                cols += '</ul></div></td>';
                newRow.append(cols);
                newBody.append(newRow);
                $("table.payment-list").append(newBody);
            });
            $('#view-payment').modal('show');
        });
    });

    $(document).on("click", "table.payment-list .edit-btn", function(event) {
        $(".edit-btn").attr('data-clicked', true);
        $(".card-element").hide();
        $("#edit-cheque").hide();
        $('#edit-payment select[name="edit_paid_by_id"]').prop('disabled', false);
        var id = $(this).data('id').toString();
        $.each(payment_id, function(index){
            if(payment_id[index] == parseFloat(id)){
                $('input[name="payment_id"]').val(payment_id[index]);
                $('#edit-payment select[name="account_id"]').val(account_id[index]);
                if(paying_method[index] == 'Cash')
                    $('select[name="edit_paid_by_id"]').val(1);
                else if(paying_method[index] == 'Credit Card'){
                    $('select[name="edit_paid_by_id"]').val(3);
                    @if($lims_pos_setting_data && (strlen($lims_pos_setting_data->stripe_public_key)>0) && (strlen($lims_pos_setting_data->stripe_secret_key )>0))
                        $.getScript( "vendor/stripe/checkout.js" );
                        $(".card-element").show();
                    @endif
                    $("#edit-cheque").hide();
                    $('#edit-payment select[name="edit_paid_by_id"]').prop('disabled', true);
                }
                else{
                    $('select[name="edit_paid_by_id"]').val(4);
                    $("#edit-cheque").show();
                    $('input[name="edit_cheque_no"]').val(cheque_no[index]);
                    $('input[name="edit_cheque_no"]').attr('required', true);
                }
                $('input[name="edit_date"]').val(payment_date[index]);
                $("#payment_reference").html(payment_reference[index]);
                $('input[name="edit_amount"]').val(paid_amount[index]);
                $('input[name="edit_paying_amount"]').val(paying_amount[index]);
                $('.change').text(change[index]);
                $('textarea[name="edit_payment_note"]').val(payment_note[index]);
                $('input[name="payment_at"]').val(payment_at[index]);
                return false;
            }
        });
        $('.selectpicker').selectpicker('refresh');
        $('#view-payment').modal('hide');
    });

    $('select[name="paid_by_id"]').on("change", function() {
        var id = $('select[name="paid_by_id"]').val();
        $('input[name="cheque_no"]').attr('required', false);
        $(".payment-form").off("submit");
        if (id == 3) {
            $.getScript( "vendor/stripe/checkout.js" );
            $(".card-element").show();
            $("#cheque").hide();
        } else if (id == 4) {
            $("#cheque").show();
            $(".card-element").hide();
            $('input[name="cheque_no"]').attr('required', true);
        } else {
            $(".card-element").hide();
            $("#cheque").hide();
        }
    });

    $('input[name="paying_amount"]').on("input", function() {
        $(".change").text(parseFloat( $(this).val() - $('input[name="amount"]').val() ).toFixed({{$general_setting->decimal}}));
    });

    $('input[name="amount"]').on("input", function() {
        if( $(this).val() > parseFloat($('input[name="paying_amount"]').val()) ) {
            alert('Paying amount cannot be bigger than recieved amount');
            $(this).val('');
        }
        else if( $(this).val() > parseFloat($('input[name="balance"]').val()) ) {
            alert('Paying amount cannot be bigger than due amount');
            $(this).val('');
        }
        $(".change").text(parseFloat($('input[name="paying_amount"]').val() - $(this).val()).toFixed({{$general_setting->decimal}}));
    });

    $('select[name="edit_paid_by_id"]').on("change", function() {
        var id = $('select[name="edit_paid_by_id"]').val();
        $('input[name="edit_cheque_no"]').attr('required', false);
        $(".payment-form").off("submit");
        if (id == 3) {
            $(".edit-btn").attr('data-clicked', true);
            $.getScript( "vendor/stripe/checkout.js" );
            $(".card-element").show();
            $("#edit-cheque").hide();
        } else if (id == 4) {
            $("#edit-cheque").show();
            $(".card-element").hide();
            $('input[name="edit_cheque_no"]').attr('required', true);
        } else {
            $(".card-element").hide();
            $("#edit-cheque").hide();
        }
    });

    $('input[name="edit_amount"]').on("input", function() {
        if( $(this).val() > parseFloat($('input[name="edit_paying_amount"]').val()) ) {
            alert('Paying amount cannot be bigger than recieved amount');
            $(this).val('');
        }
        $(".change").text(parseFloat($('input[name="edit_paying_amount"]').val() - $(this).val()).toFixed({{$general_setting->decimal}}));
    });

    $('input[name="edit_paying_amount"]').on("input", function() {
        $(".change").text(parseFloat( $(this).val() - $('input[name="edit_amount"]').val() ).toFixed({{$general_setting->decimal}}));
    });

    let targets = [];

    if (show_purchase_product_details == 1) {
        targets = [0, 3, 4, 5, 6, 7, 9, 11, 12];
    } else {
        targets = [0, 3, 4, 5, 7, 9, 10];
    }

    let buttons = [];
    @can('purchase_export')
        buttons.push([
            {
                extend: 'pdf',
                text: '<i title="export to pdf" class="fa fa-file-pdf-o"></i>',
                exportOptions: {
                    columns: ':visible:Not(.not-exported)',
                    rows: ':visible'
                },
                action: function(e, dt, button, config) {
                    datatable_sum(dt, true);
                    $.fn.dataTable.ext.buttons.pdfHtml5.action.call(this, e, dt, button, config);
                    datatable_sum(dt, false);
                },
                footer:true
            },
            {
                extend: 'excel',
                text: '<i title="export to excel" class="dripicons-document-new"></i>',
                exportOptions: {
                    columns: ':visible:not(.not-exported)',
                    rows: ':visible'
                },
                action: function(e, dt, button, config) {
                    datatable_sum(dt, true);
                    $.fn.dataTable.ext.buttons.excelHtml5.action.call(this, e, dt, button, config);
                    datatable_sum(dt, false);
                },
                footer:true
            },
            {
                extend: 'csv',
                text: '<i title="export to csv" class="fa fa-file-text-o"></i>',
                exportOptions: {
                    columns: ':visible:not(.not-exported)',
                    rows: ':visible'
                },
                action: function(e, dt, button, config) {
                    datatable_sum(dt, true);
                    $.fn.dataTable.ext.buttons.csvHtml5.action.call(this, e, dt, button, config);
                    datatable_sum(dt, false);
                },
                footer:true
            },
            {
                extend: 'print',
                text: '<i title="print" class="fa fa-print"></i>',
                exportOptions: {
                    columns: ':visible:not(.not-exported)',
                    rows: ':visible'
                },
                action: function(e, dt, button, config) {
                    datatable_sum(dt, true);
                    $.fn.dataTable.ext.buttons.print.action.call(this, e, dt, button, config);
                    datatable_sum(dt, false);
                },
                footer:true
            },
        ]);
    @endcan

    buttons.push([
        {
            text: '<i title="delete" class="dripicons-cross"></i>',
            className: 'buttons-delete',
            action: function ( e, dt, node, config ) {
                if(user_verified == '1') {
                    purchase_id.length = 0;
                    $(':checkbox:checked').each(function(i){
                        if(i){
                            var purchase = $(this).closest('tr').data('purchase');
                            if(purchase)
                                purchase_id[i-1] = purchase[3];
                        }
                    });
                    if(purchase_id.length && confirm(@json(config('commercial.enabled') ? 'Reverse selected documents and preserve their history?' : 'Are you sure want to delete?'))) {
                        @if(config('commercial.enabled'))
                        var reversalReason = prompt('Reason for reversal (at least 3 characters):');
                        if (!reversalReason || reversalReason.trim().length < 3) return;
                        var reversalDate = prompt('Reversal date (YYYY-MM-DD):', @json(date('Y-m-d')));
                        if (!reversalDate) return;
                        @endif
                        $.ajax({
                            type:'POST',
                            url:'purchases/deletebyselection',
                            data:{
                                purchaseIdArray: purchase_id
                                @if(config('commercial.enabled'))
                                ,reason: reversalReason, business_date: reversalDate
                                @endif
                            },
                            success:function(res) {
                                @if(config('commercial.enabled'))
                                alert('Documents reversed; original history preserved.'); location.reload(); return;
                                @endif
                                if (!res || !Array.isArray(res.deleted)) {
                                    alert(res.message || 'Unexpected server response');
                                    return;
                                }
                                res.deleted.forEach(function(id){
                                    var row = $('#purchase-table tbody tr[data-id="'+id+'"]');
                                    if (row.length) dt.row(row).remove();
                                });
                                dt.draw(false);
                                alert(res.message || 'Deleted');
                                //dt.rows({ page: 'current', selected: true }).deselect();
                                //dt.rows({ page: 'current', selected: true }).remove().draw(false);
                            },
                            error: function(xhr) {
                                // if the server responded with HTML (redirect/login), show a friendly message
                                var contentType = xhr.getResponseHeader('Content-Type') || '';
                                if (contentType.indexOf('text/html') !== -1) {
                                    console.warn('Server returned HTML — likely a redirect to login.', xhr.responseText);
                                    alert('Session expired or not authenticated. Please login again.');
                                    // Optional: redirect user to login page
                                    window.location = '/login';
                                    return;
                                }
                                let json = null;
                                try { json = xhr.responseJSON; } catch(e){}
                                alert((json && json.message) ? json.message : 'Delete failed: ' + xhr.status);
                            }
                        });
                    }
                    else if(!purchase_id.length)
                        alert('Nothing is selected!');
                }
                else
                    alert('This feature is disable for demo!');
            }
        },
        {
            extend: 'colvis',
            text: '<i title="column visibility" class="fa fa-eye"></i>',
            columns: ':gt(0)'
        },
    ]);

    var purchaseTable = $('#purchase-table').DataTable( {
        "processing": true,
        "serverSide": true,
        "ajax":{
            url:"purchases/purchase-data",
            data: function (d) {
                d.all_permission   = all_permission;
                d.starting_date    = $('input[name=starting_date]').val();
                d.ending_date      = $('input[name=ending_date]').val();
                d.warehouse_id     = $('#warehouse_id').val();
                d.purchase_status  = $('#purchase-status').val();
                d.payment_status   = $('#payment-status').val();
            },
            dataType: "json",
            type:"post",
            /*success:function(data){
                console.log(data);
            }*/
        },
        "createdRow": function( row, data, dataIndex ) {
            $(row).addClass('purchase-link');
            $(row).attr('data-purchase', data['purchase']);
        },
        "columns": columns,
        'language': {
            /*'searchPlaceholder': "{{__('db.Type date or purchase reference')}}",*/
            'lengthMenu': '_MENU_ {{__("db.records per page")}}',
             "info":      '<small>{{__("db.Showing")}} _START_ - _END_ (_TOTAL_)</small>',
            "search":  '{{__("db.Search")}}',
            'paginate': {
                    'previous': '<i class="dripicons-chevron-left"></i>',
                    'next': '<i class="dripicons-chevron-right"></i>'
            }
        },
        order:[['1', 'desc']],
        'columnDefs': [
            {
                "orderable": false,
                'targets': targets
            },
            {
                'render': function(data, type, row, meta){
                    if(type === 'display'){
                        data = '<div class="checkbox"><input type="checkbox" class="dt-checkboxes"><label></label></div>';
                    }

                   return data;
                },
                'checkboxes': {
                   'selectRow': true,
                   'selectAllRender': '<div class="checkbox"><input type="checkbox" class="dt-checkboxes"><label></label></div>'
                },
                'targets': [0]
            }
        ],
        'select': { style: 'multi',  selector: 'td:first-child'},
        'lengthMenu': [[10, 25, 50, -1], [10, 25, 50, "All"]],
        dom: '<"row"lfB>rtip',
        buttons: buttons,
        drawCallback: function () {
            var api = this.api();
            datatable_sum(api, false);
        }
    });

    function datatable_sum(dt_selector, is_calling_first) {

        if(show_purchase_product_details == 1){
             if (dt_selector.rows( '.selected' ).any() && is_calling_first) {
                var rows = dt_selector.rows( '.selected' ).indexes();
                $( dt_selector.column( 8 ).footer() ).html(dt_selector.cells( rows, 8, { page: 'current' } ).data().sum().toFixed({{$general_setting->decimal}}));
                $( dt_selector.column( 9 ).footer() ).html(dt_selector.cells( rows, 9, { page: 'current' } ).data().sum().toFixed({{$general_setting->decimal}}));
                $( dt_selector.column( 10 ).footer() ).html(dt_selector.cells( rows, 10, { page: 'current' } ).data().sum().toFixed({{$general_setting->decimal}}));
                $( dt_selector.column( 11 ).footer() ).html(dt_selector.cells( rows, 11, { page: 'current' } ).data().sum().toFixed({{$general_setting->decimal}}));
            }
            else {
                $( dt_selector.column( 8 ).footer() ).html(dt_selector.column( 8, {page:'current'} ).data().sum().toFixed({{$general_setting->decimal}}));
                $( dt_selector.column( 9 ).footer() ).html(dt_selector.column( 9, {page:'current'} ).data().sum().toFixed({{$general_setting->decimal}}));
                $( dt_selector.column( 10 ).footer() ).html(dt_selector.column( 10, {page:'current'} ).data().sum().toFixed({{$general_setting->decimal}}));
                $( dt_selector.column( 11 ).footer() ).html(dt_selector.column( 11, {page:'current'} ).data().sum().toFixed({{$general_setting->decimal}}));
            }
        }else{
            if (dt_selector.rows( '.selected' ).any() && is_calling_first) {
                var rows = dt_selector.rows( '.selected' ).indexes();
                $( dt_selector.column( 6 ).footer() ).html(dt_selector.cells( rows, 6, { page: 'current' } ).data().sum().toFixed({{$general_setting->decimal}}));
                $( dt_selector.column( 7 ).footer() ).html(dt_selector.cells( rows, 7, { page: 'current' } ).data().sum().toFixed({{$general_setting->decimal}}));
                $( dt_selector.column( 8 ).footer() ).html(dt_selector.cells( rows, 8, { page: 'current' } ).data().sum().toFixed({{$general_setting->decimal}}));
                $( dt_selector.column( 9 ).footer() ).html(dt_selector.cells( rows, 9, { page: 'current' } ).data().sum().toFixed({{$general_setting->decimal}}));
            }
            else {
                $( dt_selector.column( 6 ).footer() ).html(dt_selector.column( 6, {page:'current'} ).data().sum().toFixed({{$general_setting->decimal}}));
                $( dt_selector.column( 7 ).footer() ).html(dt_selector.column( 7, {page:'current'} ).data().sum().toFixed({{$general_setting->decimal}}));
                $( dt_selector.column( 8 ).footer() ).html(dt_selector.column( 8, {page:'current'} ).data().sum().toFixed({{$general_setting->decimal}}));
                $( dt_selector.column( 9 ).footer() ).html(dt_selector.column( 9, {page:'current'} ).data().sum().toFixed({{$general_setting->decimal}}));
            }
        }

    }

    $('#warehouse_id, #purchase-status, #payment-status').on('change', function () {
        purchaseTable.ajax.reload();
    });

    // for date range picker
    $('.daterangepicker-field').on('apply.daterangepicker', function(ev, picker) {
        $('input[name=starting_date]').val(picker.startDate.format('YYYY-MM-DD'));
        $('input[name=ending_date]').val(picker.endDate.format('YYYY-MM-DD'));
        purchaseTable.ajax.reload();
    });

    // Show loader on request
    purchaseTable.on('preXhr.dt', function () {
        $('#filter-loading').show();
    });

    // Hide loader after draw
    purchaseTable.on('xhr.dt', function () {
        $('#filter-loading').hide();
    });


    function purchaseDetails(purchase){
        // console.log(purchase);
        var htmltext = '{{__("db.date")}}: '+purchase[0]+'<br>{{__("db.reference")}}: '+purchase[1]+'<br>{{__("db.Purchase Status")}}: '+purchase[2]+'<br>{{__("db.Currency")}}: '+purchase[26];
        if(purchase[27])
            htmltext += '<br>{{__("db.Exchange Rate")}}: '+purchase[27]+'<br>';
        else
            htmltext += '<br>{{__("db.Exchange Rate")}}: N/A<br>';
        if(purchase[25])
            htmltext += '{{__("db.Attach Document")}}: <a href="{{ url('secure-documents/purchase') }}/'+purchase[25]+'">Download</a><br>';
        htmltext += '<br><div class="row"><div class="col-md-6">{{__("db.From")}}:<br>'+purchase[7]+'<br>'+purchase[8]+'<br>'+purchase[9]+'<br>'+purchase[10]+'<br>'+purchase[11]+'<br>'+purchase[12]+'</div><div class="col-md-6"><div class="float-right">{{__("db.To")}}:<br>'+purchase[4]+'<br>'+purchase[5]+'<br>'+purchase[6]+'</div></div></div>';
        $(".product-purchase-list tbody").remove();
        $.get('purchases/product_purchase/' + purchase[3], function(data) {
            // console.log(data);
            if(data == 'Something is wrong!') {
                var newBody = $("<tbody>");
                var newRow = $("<tr>");
                cols = '<td colspan="8">Something is wrong!</td>';
                newRow.append(cols);
                newBody.append(newRow);
            }
            else {
                var name_code = data[0];
                var qty = data[1];
                var unit_code = data[2];
                var tax = data[3];
                var tax_rate = data[4];
                var discount = data[5];
                var subtotal = data[6];
                var batch_no = data[7];
                var returned = data[8];
                var newBody = $("<tbody>");
                $.each(name_code, function(index) {
                    var newRow = $("<tr>");
                    var cols = '';
                    cols += '<td>' + (index+1) + '</td>';
                    cols += '<td>' + name_code[index] + '</td>';
                    cols += '<td>' + batch_no[index] + '</td>';
                    cols += '<td>' + qty[index] + ' ' + unit_code[index] + '</td>';
                    cols += '<td>' + returned[index] + '</td>';
                    cols += '<td>' + (parseFloat(subtotal[index] / qty[index]).toFixed({{$general_setting->decimal}})) + '</td>';
                    cols += '<td>' + tax[index] + '(' + tax_rate[index] + '%)' + '</td>';
                    cols += '<td>' + discount[index] + '</td>';
                    cols += '<td>' + subtotal[index] + '</td>';
                    newRow.append(cols);
                    newBody.append(newRow);
                });

                var newRow = $("<tr>");
                cols = '';
                cols += '<td colspan=6>{{__("db.Total")}}:</td>';
                cols += '<td>' + purchase[13] + '</td>';
                cols += '<td>' + purchase[14] + '</td>';
                cols += '<td>' + purchase[15] + '</td>';
                newRow.append(cols);
                newBody.append(newRow);

                var newRow = $("<tr>");
                cols = '';
                cols += '<td colspan=8>{{__("db.Order Tax")}}:</td>';
                cols += '<td>' + purchase[16] + '(' + purchase[17] + '%)' + '</td>';
                newRow.append(cols);
                newBody.append(newRow);

                var newRow = $("<tr>");
                cols = '';
                cols += '<td colspan=8>{{__("db.Order Discount")}}:</td>';
                cols += '<td>' + purchase[18] + '</td>';
                newRow.append(cols);
                newBody.append(newRow);

                var newRow = $("<tr>");
                cols = '';
                cols += '<td colspan=8>{{__("db.Shipping Cost")}}:</td>';
                cols += '<td>' + purchase[19] + '</td>';
                newRow.append(cols);
                newBody.append(newRow);

                var newRow = $("<tr>");
                cols = '';
                cols += '<td colspan=8>{{__("db.grand total")}}:</td>';
                cols += '<td>' + purchase[20] + '</td>';
                newRow.append(cols);
                newBody.append(newRow);

                var newRow = $("<tr>");
                cols = '';
                cols += '<td colspan=8>{{__("db.Paid Amount")}}:</td>';
                cols += '<td>' + purchase[21] + '</td>';
                newRow.append(cols);
                newBody.append(newRow);

                var newRow = $("<tr>");
                cols = '';
                cols += '<td colspan=8>{{__("db.Due")}}:</td>';
                cols += '<td>' + (purchase[20] - purchase[21]) + '</td>';
                newRow.append(cols);
                newBody.append(newRow);

                 $("table.product-purchase-list").append(newBody);
             }
        });

        var htmlfooter = '<p>{{__("db.Note")}}: '+purchase[22]+'</p>{{__("db.Created By")}}:<br>'+purchase[23]+'<br>'+purchase[24];

        $('#purchase-content').html(htmltext);
        $('#purchase-footer').html(htmlfooter);
        $('#purchase-details').modal('show');
    }

    $(document).on('submit', '.payment-form', function(e) {
        if( $('input[name="paying_amount"]').val() < parseFloat($('#amount').val()) ) {
            alert('Paying amount cannot be bigger than recieved amount');
            $('input[name="amount"]').val('');
            $(".change").text(parseFloat( $('input[name="paying_amount"]').val() - $('#amount').val() ).toFixed({{$general_setting->decimal}}));
            e.preventDefault();
        }
        else if( $('input[name="edit_paying_amount"]').val() < parseFloat($('input[name="edit_amount"]').val()) ) {
            alert('Paying amount cannot be bigger than recieved amount');
            $('input[name="edit_amount"]').val('');
            $(".change").text(parseFloat( $('input[name="edit_paying_amount"]').val() - $('input[name="edit_amount"]').val() ).toFixed({{$general_setting->decimal}}));
            e.preventDefault();
        }

        $('#edit-payment select[name="edit_paid_by_id"]').prop('disabled', false);
    });

    if(all_permission.indexOf("purchases-delete") == -1)
        $('.buttons-delete').addClass('d-none');


</script>
<script type="text/javascript" src="https://js.stripe.com/v3/">
    // --- Commercial Workspace Single-Screen SPA Ergonomics (Purchases) ---
    function showProgressBar() {
        $('#comm-progress-bar').removeClass('done').addClass('active');
    }
    function hideProgressBar() {
        $('#comm-progress-bar').addClass('done');
        setTimeout(function() { $('#comm-progress-bar').removeClass('active done'); }, 300);
    }

    // Tab switching without page reload
    $('#tab-all-purchases').on('click', function(e) {
        e.preventDefault();
        $('.comm-nav-pills .nav-link').removeClass('active');
        $(this).addClass('active');
        $('#purchase-status').val('0');
        showProgressBar();
        $('#purchase-table').DataTable().ajax.reload(function() { hideProgressBar(); });
    });

    $('#tab-pending-purchases').on('click', function(e) {
        e.preventDefault();
        $('.comm-nav-pills .nav-link').removeClass('active');
        $(this).addClass('active');
        $('#purchase-status').val('3');
        showProgressBar();
        $('#purchase-table').DataTable().ajax.reload(function() { hideProgressBar(); });
    });

    $('#btn-quick-refresh').on('click', function() {
        showProgressBar();
        $('#purchase-table').DataTable().ajax.reload(function() { hideProgressBar(); });
    });

    // Drawer collapse / expand
    $('#toggle-drawer-btn, #close-drawer-btn').on('click', function() {
        $('#comm-drawer').toggleClass('collapsed');
        $('#comm-split-grid').toggleClass('drawer-collapsed');
        if ($.fn.DataTable.isDataTable('#purchase-table')) {
            $('#purchase-table').DataTable().columns.adjust().draw(false);
        }
    });

    // Hotkeys: F12 focuses quick purchase / fast entry, Alt+D toggles drawer
    $(document).on('keydown', function(e) {
        if (e.key === 'F12') {
            e.preventDefault();
            if ($('#comm-drawer').hasClass('collapsed')) {
                $('#toggle-drawer-btn').trigger('click');
            }
            $('#quick-purchase-product-search').focus();
        } else if (e.altKey && (e.key === 'd' || e.key === 'D')) {
            e.preventDefault();
            $('#toggle-drawer-btn').trigger('click');
        }
    });

    // Fast product search in drawer
    let quickPurchaseItems = [];
    $('#quick-purchase-product-search').on('input', function() {
        let q = $(this).val().trim();
        if (q.length < 2) {
            $('#quick-purchase-search-results').hide().empty();
            return;
        }
        $.ajax({
            url: '{{ url("purchases/lims_product_search") }}',
            data: { data: q },
            type: 'GET',
            success: function(response) {
                let res = $('#quick-purchase-search-results').empty();
                if (Array.isArray(response) && response.length > 0) {
                    response.slice(0, 8).forEach(function(item) {
                        let parts = item.split('|');
                        let name = parts[0];
                        let code = parts[1];
                        let qty = parts[2];
                        let cost = parseFloat(parts[3]) || 50;
                        let a = $('<a href="javascript:void(0)" class="list-group-item list-group-item-action py-1 px-2" style="font-size:12px;"></a>')
                            .text(name + ' (' + code + ')')
                            .data('item', { name: name, code: code, qty: qty, cost: cost });
                        res.append(a);
                    });
                    res.show();
                } else {
                    res.hide();
                }
            }
        });
    });

    $(document).on('click', '#quick-purchase-search-results a', function() {
        let item = $(this).data('item');
        addQuickPurchaseItem(item);
        $('#quick-purchase-search-results').hide().empty();
        $('#quick-purchase-product-search').val('').focus();
    });

    function addQuickPurchaseItem(item) {
        let existing = quickPurchaseItems.find(i => i.code === item.code);
        if (existing) {
            existing.qty += 1;
        } else {
            quickPurchaseItems.push({
                name: item.name,
                code: item.code,
                qty: 1,
                cost: item.cost || 50
            });
        }
        renderQuickPurchaseItems();
    }

    function renderQuickPurchaseItems() {
        let tbody = $('#quick-purchase-items-body').empty();
        let total = 0;
        if (quickPurchaseItems.length === 0) {
            tbody.append('<tr class="empty-row"><td colspan="5" class="text-center text-muted py-2" style="font-size:11px;">No items added. Search above or press F12.</td></tr>');
        } else {
            quickPurchaseItems.forEach(function(item, idx) {
                let lineTotal = item.qty * item.cost;
                total += lineTotal;
                tbody.append('<tr>' +
                    '<td style="max-width:100px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="' + item.name + '">' + item.name + '</td>' +
                    '<td><input type="number" class="form-control p-1 text-center quick-p-qty-input" data-idx="' + idx + '" value="' + item.qty + '" min="1" style="height:24px;font-size:11px;"></td>' +
                    '<td><input type="number" class="form-control p-1 text-right quick-p-cost-input" data-idx="' + idx + '" value="' + item.cost + '" step="0.5" style="height:24px;font-size:11px;"></td>' +
                    '<td class="text-right font-weight-bold">' + lineTotal.toFixed(2) + '</td>' +
                    '<td class="text-center"><a href="javascript:void(0)" class="text-danger remove-quick-p-item" data-idx="' + idx + '">&times;</a></td>' +
                '</tr>');
            });
        }
        $('#quick-purchase-subtotal').text(total.toFixed(2));
        $('#quick-purchase-grand-total').text(total.toFixed(2));
        if ($('#quick-purchase-payment-status').val() === 'Paid') {
            $('#quick-purchase-paid-amount').val(total.toFixed(2));
        }
    }

    $(document).on('input', '.quick-p-qty-input', function() {
        let idx = $(this).data('idx');
        let val = parseFloat($(this).val()) || 1;
        quickPurchaseItems[idx].qty = val;
        renderQuickPurchaseItems();
    });

    $(document).on('input', '.quick-p-cost-input', function() {
        let idx = $(this).data('idx');
        let val = parseFloat($(this).val()) || 0;
        quickPurchaseItems[idx].cost = val;
        renderQuickPurchaseItems();
    });

    $(document).on('click', '.remove-quick-p-item', function() {
        let idx = $(this).data('idx');
        quickPurchaseItems.splice(idx, 1);
        renderQuickPurchaseItems();
    });

    // Quick Post Purchase button
    $('#btn-quick-post-purchase').on('click', function() {
        if (quickPurchaseItems.length === 0) {
            alert('Please add at least one item before posting purchase.');
            return;
        }
        showProgressBar();
        window.location.href = '{{ route("commercial.purchase.entry") }}';
    });
</script>
@endpush
