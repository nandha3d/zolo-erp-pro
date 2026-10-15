@extends('backend.layout.main') @section('content')

<link rel="stylesheet" href="<?php echo asset('css/commercial-entry.css') . '?v=' . (file_exists(public_path('css/commercial-entry.css')) ? filemtime(public_path('css/commercial-entry.css')) : time()) ?>" type="text/css">
<link rel="stylesheet" href="<?php echo asset('css/commercial-workspace.css') . '?v=' . (file_exists(public_path('css/commercial-workspace.css')) ? filemtime(public_path('css/commercial-workspace.css')) : time()) ?>" type="text/css">
<link rel="stylesheet" href="<?php echo asset('css/zolo-erp-neo.css') . '?v=' . (file_exists(public_path('css/zolo-erp-neo.css')) ? filemtime(public_path('css/zolo-erp-neo.css')) : time()) ?>" type="text/css">

<style>
    .supplier-workspace-view {
        padding: 12px 18px 24px;
        background: #f8fafc;
        min-height: calc(100vh - 40px);
    }
    body.dark-mode .supplier-workspace-view {
        background: #0b0f19;
    }
    .kpi-summary-strip {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 12px;
        margin-bottom: 14px;
    }
    .kpi-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 12px 16px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03);
    }
    body.dark-mode .kpi-card {
        background: #141c2e;
        border-color: #334155;
    }
    .kpi-card .kpi-label {
        font-size: 10px;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        font-weight: 700;
        color: #64748b;
        margin-bottom: 2px;
    }
    .kpi-card .kpi-number {
        font-size: 19px;
        font-weight: 700;
        color: #0f172a;
    }
    body.dark-mode .kpi-card .kpi-number {
        color: #f8fafc;
    }
    .desk-card-wrapper {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
        padding: 14px 16px;
    }
    body.dark-mode .desk-card-wrapper {
        background: #141c2e;
        border-color: #334155;
    }
    #supplier-table {
        border-collapse: collapse !important;
        width: 100% !important;
    }
    #supplier-table thead th {
        background: #0f172a !important;
        color: #ffffff !important;
        font-size: 10.5px !important;
        font-weight: 700 !important;
        text-transform: uppercase !important;
        letter-spacing: 0.04em !important;
        padding: 9px 12px !important;
        border: none !important;
        white-space: nowrap;
    }
    body.dark-mode #supplier-table thead th {
        background: #090d16 !important;
        color: #f8fafc !important;
    }
    #supplier-table tbody tr:nth-child(even) {
        background: #f8fafc;
    }
    #supplier-table tbody tr:nth-child(odd) {
        background: #ffffff;
    }
    body.dark-mode #supplier-table tbody tr:nth-child(even) {
        background: #141c2c;
    }
    body.dark-mode #supplier-table tbody tr:nth-child(odd) {
        background: #1e293b;
    }
    #supplier-table tbody tr:hover {
        background: #e2e8f0 !important;
    }
    body.dark-mode #supplier-table tbody tr:hover {
        background: #27354f !important;
    }
    #supplier-table td {
        padding: 9px 12px !important;
        border-bottom: 1px solid #e2e8f0 !important;
        vertical-align: middle !important;
        font-size: 12.5px;
    }
    body.dark-mode #supplier-table td {
        border-color: #334155 !important;
        color: #e2e8f0;
    }
    .supplier-avatar-circle {
        width: 44px;
        height: 44px;
        border-radius: 50%;
        background: #ede9fe;
        color: #6d28d9;
        font-weight: 700;
        font-size: 15px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border: 2px solid #ddd6fe;
        box-shadow: 0 1px 2px rgba(109, 40, 217, 0.1);
        text-transform: uppercase;
    }
    body.dark-mode .supplier-avatar-circle {
        background: #2e1065;
        color: #c4b5fd;
        border-color: #5b21b6;
    }
    .supplier-name-title {
        font-weight: 700;
        color: #0f172a;
        font-size: 13.5px;
    }
    body.dark-mode .supplier-name-title {
        color: #f8fafc;
    }
    .supplier-meta-tag {
        font-size: 11px;
        color: #64748b;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        margin-right: 8px;
    }
    body.dark-mode .supplier-meta-tag {
        color: #94a3b8;
    }
    .due-badge-pill {
        font-size: 11px;
        font-weight: 700;
        padding: 3px 9px;
        border-radius: 12px;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    .due-has-balance {
        background: #fef2f2;
        color: #b91c1c;
        border: 1px solid #fecaca;
    }
    body.dark-mode .due-has-balance {
        background: #450a0a;
        color: #fca5a5;
        border-color: #7f1d1d;
    }
    .due-cleared {
        background: #f0fdf4;
        color: #15803d;
        border: 1px solid #bbf7d0;
    }
    body.dark-mode .due-cleared {
        background: #052e16;
        color: #86efac;
        border-color: #14532d;
    }
    .btn-action-pill {
        height: 26px !important;
        font-size: 11px !important;
        padding: 0 10px !important;
        border-radius: 4px !important;
        border: 1px solid #cbd5e1 !important;
        background: #ffffff !important;
        color: #334155 !important;
        font-weight: 600 !important;
        display: inline-flex !important;
        align-items: center !important;
        gap: 4px !important;
        box-shadow: 0 1px 2px rgba(15,23,42,0.04) !important;
    }
    body.dark-mode .btn-action-pill {
        background: #1e293b !important;
        color: #f8fafc !important;
        border-color: #475569 !important;
    }
    .btn-action-pill:hover {
        background: #f1f5f9 !important;
        color: #0f172a !important;
    }
    /* DataTables Single-Box Normalization */
    .dataTables_wrapper .dataTables_length select,
    .dataTables_wrapper .dataTables_filter input {
        height: 28px !important;
        font-size: 12px !important;
        border: 1px solid #cbd5e1 !important;
        border-radius: 5px !important;
        padding: 2px 8px !important;
        background: #ffffff !important;
        color: #0f172a !important;
    }
    body.dark-mode .dataTables_wrapper .dataTables_length select,
    body.dark-mode .dataTables_wrapper .dataTables_filter input {
        background: #0f172a !important;
        color: #f8fafc !important;
        border-color: #334155 !important;
    }
    .dt-buttons .dt-button {
        height: 28px !important;
        line-height: 26px !important;
        padding: 0 10px !important;
        font-size: 11.5px !important;
        border-radius: 5px !important;
        border: 1px solid #cbd5e1 !important;
        background: #ffffff !important;
        color: #334155 !important;
        margin-right: 4px !important;
    }
    body.dark-mode .dt-buttons .dt-button {
        background: #1e293b !important;
        color: #f8fafc !important;
        border-color: #475569 !important;
    }
</style>

<x-success-message key="message" />
<x-error-message key="not_permitted" />

<!-- Top Command Bar -->
<div class="comm-command-bar">
    <div class="comm-title-group">
        <h1><i class="dripicons-user text-primary"></i> {{ __('db.Supplier') }} Command Center</h1>
        <span class="comm-title-badge"><i class="dripicons-card"></i> Vendor Master Directory &amp; Accounts</span>
    </div>
    <ul class="comm-nav-pills">
        <li><a class="nav-link active" href="{{ route('supplier.index') }}"><i class="dripicons-list"></i> All Suppliers</a></li>
        <li><a class="nav-link" href="{{ route('purchases.index') }}"><i class="dripicons-card"></i> Purchase Bills</a></li>
        <li><a class="nav-link" href="{{ route('purchases.index', ['view' => 'orders', 'purchase_status' => 4]) }}"><i class="dripicons-clipboard"></i> Purchase Orders</a></li>
        <li><a class="nav-link" href="{{ route('return-purchase.index') }}"><i class="dripicons-return"></i> Returns</a></li>
    </ul>
    <div class="comm-actions">
        @can('suppliers-add')
            <a href="{{route('supplier.create')}}" class="btn btn-primary py-1 px-3" style="background:#7c3aed; border-color:#7c3aed; font-size:11.5px; border-radius:4px;">
                <i class="dripicons-plus"></i> {{__('db.Add Supplier')}}
            </a>
        @endcan
        @can('suppliers-import')
            <a href="#" data-toggle="modal" data-target="#importSupplier" class="btn btn-outline-secondary py-1 px-3" style="font-size:11.5px; border-radius:4px;">
                <i class="dripicons-upload"></i> {{__('db.Import Supplier')}}
            </a>
        @endcan
    </div>
</div>

<div class="supplier-workspace-view">
    @php
        $totalSuppliersCount = count($lims_supplier_all);
        $totalDueSum = 0;
        $suppliersWithDueCount = 0;
        foreach($lims_supplier_all as $s) {
            $retAmt = \Illuminate\Support\Facades\DB::table('purchases')
                ->join('return_purchases', 'purchases.id', '=', 'return_purchases.purchase_id')
                ->where('purchases.supplier_id', $s->id)
                ->where('purchases.payment_status', 1)
                ->sum('return_purchases.grand_total');
            $pData = \App\Models\Purchase::where('supplier_id', $s->id)
                ->where('payment_status', 1)
                ->selectRaw('SUM(grand_total) as grand_total, SUM(paid_amount) as paid_amount')
                ->first();
            $due = ($pData ? ($pData->grand_total - $retAmt - $pData->paid_amount) : 0);
            if ($due > 0) {
                $totalDueSum += $due;
                $suppliersWithDueCount++;
            }
        }
    @endphp

    <!-- KPI Summary Strip -->
    <div class="kpi-summary-strip">
        <div class="kpi-card">
            <div>
                <div class="kpi-label">Total Suppliers</div>
                <div class="kpi-number">{{ $totalSuppliersCount }}</div>
            </div>
            <div style="background:#ede9fe; color:#7c3aed; width:38px; height:38px; border-radius:6px; display:flex; align-items:center; justify-content:center;">
                <i class="dripicons-user" style="font-size:18px;"></i>
            </div>
        </div>
        <div class="kpi-card">
            <div>
                <div class="kpi-label">Vendors With Due</div>
                <div class="kpi-number" style="color: {{ $suppliersWithDueCount > 0 ? '#dc2626' : '#16a34a' }};">{{ $suppliersWithDueCount }}</div>
            </div>
            <div style="background:#fee2e2; color:#dc2626; width:38px; height:38px; border-radius:6px; display:flex; align-items:center; justify-content:center;">
                <i class="dripicons-wallet" style="font-size:18px;"></i>
            </div>
        </div>
        <div class="kpi-card">
            <div>
                <div class="kpi-label">Total Outstanding Due</div>
                <div class="kpi-number" style="color: {{ $totalDueSum > 0 ? '#b91c1c' : '#15803d' }};">₹ {{ number_format($totalDueSum, 2) }}</div>
            </div>
            <div style="background:#fef3c7; color:#d97706; width:38px; height:38px; border-radius:6px; display:flex; align-items:center; justify-content:center;">
                <i class="fa fa-inr" style="font-size:18px;"></i>
            </div>
        </div>
    </div>

    <!-- Desk Table Card -->
    <div class="desk-card-wrapper">
        <div class="table-responsive">
            <table id="supplier-table" class="table">
                <thead>
                    <tr>
                        <th class="not-exported" style="width:30px;"></th>
                        <th style="width:60px; text-align:center;">{{__('db.Image')}}</th>
                        <th>{{__('db.Supplier Details')}}</th>
                        <th style="width:160px; text-align:right;">{{__('db.Total Due')}}</th>
                        <th class="not-exported" style="width:100px; text-align:center;">{{__('db.action')}}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($lims_supplier_all as $key => $supplier)
                    @php
                        $returned_amount = \Illuminate\Support\Facades\DB::table('purchases')
                            ->join('return_purchases', 'purchases.id', '=', 'return_purchases.purchase_id')
                            ->where([
                                ['purchases.supplier_id', $supplier->id],
                                ['purchases.payment_status', 1]
                            ])
                            ->sum('return_purchases.grand_total');
                        $purchaseData = \App\Models\Purchase::where([
                                ['supplier_id', $supplier->id],
                                ['payment_status', 1]
                            ])
                            ->selectRaw('SUM(grand_total) as grand_total, SUM(paid_amount) as paid_amount')
                            ->first();
                        $totalDue = $purchaseData ? ($purchaseData->grand_total - $returned_amount - $purchaseData->paid_amount) : 0;
                        $hasDiskImage = !empty($supplier->image) && file_exists(public_path('documents/supplier/' . $supplier->image));
                        $initials = strtoupper(substr($supplier->name ?? 'S', 0, 1)) . strtoupper(substr($supplier->company_name ?? '', 0, 1));
                        if (strlen($initials) < 2) {
                            $initials = strtoupper(substr($supplier->name ?? 'SP', 0, 2));
                        }
                    @endphp
                    <tr data-id="{{$supplier->id}}">
                        <td>{{$key}}</td>
                        <td style="text-align:center;">
                            @if($hasDiskImage)
                                <img src="{{ route('documents.file', ['supplier', $supplier->image]) }}" class="rounded-circle border" width="42" height="42" style="object-fit:cover;" alt="{{ $supplier->name }}">
                            @else
                                <div class="supplier-avatar-circle" title="{{ $supplier->name }}">
                                    {{ $initials }}
                                </div>
                            @endif
                        </td>
                        <td>
                            <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                                <span class="supplier-name-title">{{ $supplier->name }}</span>
                                @if($supplier->company_name)
                                    <span class="badge badge-light-primary px-2" style="font-size:11px; font-weight:600;">
                                        <i class="fa fa-building-o"></i> {{ $supplier->company_name }}
                                    </span>
                                @endif
                                @if($supplier->vat_number)
                                    <span class="badge badge-light-secondary px-2" style="font-size:10px;">
                                        GSTIN/VAT: {{ $supplier->vat_number }}
                                    </span>
                                @endif
                            </div>
                            <div class="d-flex align-items-center flex-wrap">
                                @if($supplier->phone_number)
                                    <span class="supplier-meta-tag"><i class="fa fa-phone"></i> {{ $supplier->phone_number }}</span>
                                @endif
                                @if($supplier->email)
                                    <span class="supplier-meta-tag"><i class="fa fa-envelope-o"></i> {{ $supplier->email }}</span>
                                @endif
                                @if($supplier->address || $supplier->city)
                                    <span class="supplier-meta-tag"><i class="fa fa-map-marker"></i> {{ $supplier->address }}{{ $supplier->city ? ', '.$supplier->city : '' }}{{ $supplier->state ? ', '.$supplier->state : '' }}</span>
                                @endif
                            </div>
                        </td>
                        <td style="text-align:right;">
                            @if($totalDue > 0)
                                <span class="due-badge-pill due-has-balance">
                                    <i class="fa fa-exclamation-circle"></i> ₹ {{ number_format($totalDue, 2) }}
                                </span>
                            @else
                                <span class="due-badge-pill due-cleared">
                                    <i class="fa fa-check-circle"></i> ₹ 0.00
                                </span>
                            @endif
                        </td>
                        <td style="text-align:center;">
                            <div class="btn-group">
                                <button type="button" class="btn btn-action-pill dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                    {{__('db.action')}} <i class="fa fa-angle-down"></i>
                                </button>
                                <ul class="dropdown-menu edit-options dropdown-menu-right dropdown-default" role="menu">
                                    @if(in_array("suppliers-index", $all_permission))
                                        <li>
                                            <a href="{{ route('supplier.show', $supplier->id) }}" class="btn btn-link">
                                                <i class="dripicons-preview"></i> {{ __('db.Supplier Details') }}
                                            </a>
                                        </li>
                                    @endif
                                    @if(in_array("suppliers-edit", $all_permission))
                                    <li>
                                        <a href="{{ route('supplier.edit', $supplier->id) }}" class="btn btn-link"><i class="dripicons-document-edit"></i> {{__('db.edit')}}</a>
                                    </li>
                                    @endif
                                    @if(in_array("supplier-due-report", $all_permission))
                                    <li>
                                        {!! Form::open(['route' => 'report.supplierDueByDate', 'method' => 'post', 'id' => 'supplier-due-report-form-'.$supplier->id]) !!}
                                        <input type="hidden" name="start_date" value="{{date('Y-m-d', strtotime('-30 year'))}}" />
                                        <input type="hidden" name="end_date" value="{{date('Y-m-d')}}" />
                                        <input type="hidden" name="supplier_id" value="{{$supplier->id}}" />
                                        <button type="submit" class="btn btn-link"><i class="dripicons-pulse"></i> {{__('db.Supplier Due Report')}}</button>
                                        {!! Form::close() !!}
                                    </li>
                                    @endif
                                    <li>
                                        <button type="button" data-id="{{$supplier->id}}" class="clear-due btn btn-link" data-toggle="modal" data-target="#clearDueModal"><i class="dripicons-brush"></i> {{__('db.Clear Due')}}</button>
                                    </li>
                                    <li class="divider"></li>
                                    @php
                                        $settings = \App\Models\WhatsappSetting::first();
                                        $phone = preg_replace('/\D/', '', $supplier->wa_number ?? '');

                                        if (!$settings || empty($settings->phone_number_id) || empty($settings->permanent_access_token)) {
                                            $href = "https://web.whatsapp.com/send/?phone={$phone}";
                                        } else {
                                            $href = route('whatsapp.send.page', [
                                                'group' => 'Suppliers',
                                                'phone' => $phone
                                            ]);
                                        }
                                    @endphp
                                    @if($phone)
                                    <li>
                                        <a href="{{ $href }}" class="btn btn-link" target="_blank">
                                            <i class="fa fa-whatsapp"></i> {{ __('db.Whatsapp Notification') }}
                                        </a>
                                    </li>
                                    @endif
                                    <li class="divider"></li>
                                    @if(in_array("suppliers-delete", $all_permission))
                                    {{ Form::open(['route' => ['supplier.destroy', $supplier->id], 'method' => 'DELETE'] ) }}
                                    <li>
                                        <button type="submit" class="btn btn-link text-danger" onclick="return confirmDelete()"><i class="dripicons-trash"></i> {{__('db.delete')}}</button>
                                    </li>
                                    {{ Form::close() }}
                                    @endif
                                </ul>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

<div id="clearDueModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true" class="modal fade text-left">
    <div role="document" class="modal-dialog">
      <div class="modal-content">
        {!! Form::open(['route' => 'supplier.clearDue', 'method' => 'post']) !!}
        <div class="modal-header">
          <h5 id="exampleModalLabel" class="modal-title font-weight-bold">{{__('db.Clear Due')}}</h5>
          <button type="button" data-dismiss="modal" aria-label="Close" class="close"><span aria-hidden="true"><i class="dripicons-cross"></i></span></button>
        </div>
        <div class="modal-body">
          <p class="italic text-muted"><small>{{__('db.The field labels marked with * are required input fields')}}.</small></p>
            <div class="form-group">
                <input type="hidden" name="supplier_id">
                <label>{{__('db.Amount')}} *</label>
                <input type="number" name="amount" step="any" class="form-control" required>
            </div>
            <div class="form-group">
                <label>{{__('db.Note')}}</label>
                <textarea name="note" rows="3" class="form-control"></textarea>
            </div>
            <input type="submit" value="{{__('db.submit')}}" class="btn btn-primary btn-block" style="background:#7c3aed; border-color:#7c3aed;" id="submit-button">
        </div>
        {!! Form::close() !!}
      </div>
    </div>
</div>

<div id="importSupplier" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true" class="modal fade text-left">
	<div role="document" class="modal-dialog">
	  <div class="modal-content">
	  	{!! Form::open(['route' => 'supplier.import', 'method' => 'post', 'files' => true]) !!}
	    <div class="modal-header">
	      <h5 id="exampleModalLabel" class="modal-title font-weight-bold">{{__('db.Import Supplier')}}</h5>
	      <button type="button" data-dismiss="modal" aria-label="Close" class="close"><span aria-hidden="true"><i class="dripicons-cross"></i></span></button>
	    </div>
	    <div class="modal-body">
	      <p class="italic text-muted"><small>{{__('db.The field labels marked with * are required input fields')}}.</small></p>
	       <p style="font-size:12px; color:#64748b;">{{__('db.The correct column order is')}} (name*, image, company_name*, vat_number, email*, phone_number*, address*, city*, state, postal_code, country) {{__('db.and you must follow this')}}.</p>
           <p style="font-size:11px; color:#64748b;">{{__('db.To display Image it must be stored in')}} images/supplier {{__('db.directory')}}</p>
	        <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>{{__('db.Upload CSV File')}} *</label>
                        {{Form::file('file', array('class' => 'form-control','required'))}}
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label> {{__('db.Sample File')}}</label>
                        <a href="sample_file/sample_supplier.csv" class="btn btn-info btn-block btn-md"><i class="dripicons-download"></i> {{__('db.Download')}}</a>
                    </div>
                </div>
            </div>
	        <input type="submit" value="{{__('db.submit')}}" class="btn btn-primary btn-block" style="background:#7c3aed; border-color:#7c3aed;" id="submit-button">
		</div>
		{!! Form::close() !!}
	  </div>
	</div>
</div>

@endsection

@push('scripts')
<script type="text/javascript">
    $("ul#people").siblings('a').attr('aria-expanded','true');
    $("ul#people").addClass("show");
    $("ul#people #supplier-list-menu").addClass("active");

    var all_permission = <?php echo json_encode($all_permission) ?>;
    var supplier_id = [];
    var user_verified = <?php echo json_encode(config('app.user_verified')) ?>;

    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

    $(".clear-due").on("click", function() {
        var id = $(this).data('id').toString();
        $("#clearDueModal input[name='supplier_id']").val(id);
    });

	function confirmDelete() {
	    if (confirm("Are you sure want to delete?")) {
	        return true;
	    }
	    return false;
	}

    $('#supplier-table').DataTable( {
        "order": [],
        'language': {
            'lengthMenu': '_MENU_ {{__("db.records per page")}}',
             "info":      '<small>{{__("db.Showing")}} _START_ - _END_ (_TOTAL_)</small>',
            "search":  '{{__("db.Search")}}',
            'paginate': {
                    'previous': '<i class="dripicons-chevron-left"></i>',
                    'next': '<i class="dripicons-chevron-right"></i>'
            }
        },
        'columnDefs': [
            {
                "orderable": false,
                'targets': [0, 1, 4]
            },
            {
                'checkboxes': {
                   'selectRow': true
                },
                'targets': 0
            }
        ],
        'select': { style: 'multi',  selector: 'td:first-child'},
        'lengthMenu': [[10, 25, 50, -1], [10, 25, 50, "All"]],
        dom: '<"row align-items-center mb-2"<"col-md-4"l><"col-md-4 text-center"B><"col-md-4"f>>rt<"row align-items-center mt-2"<"col-md-6"i><"col-md-6"p>>',
        buttons: [
            {
                extend: 'pdf',
                text: '<i title="export to pdf" class="fa fa-file-pdf-o text-danger"></i>',
                exportOptions: {
                    columns: ':visible:Not(.not-exported)',
                    rows: ':visible',
                    stripHtml: true
                }
            },
            {
                extend: 'excel',
                text: '<i title="export to excel" class="fa fa-file-excel-o text-success"></i>',
                exportOptions: {
                    columns: ':visible:Not(.not-exported)',
                    rows: ':visible'
                }
            },
            {
                extend: 'csv',
                text: '<i title="export to csv" class="fa fa-file-text-o text-info"></i>',
                exportOptions: {
                    columns: ':visible:Not(.not-exported)',
                    rows: ':visible'
                }
            },
            {
                extend: 'print',
                text: '<i title="print" class="fa fa-print text-primary"></i>',
                exportOptions: {
                    columns: ':visible:Not(.not-exported)',
                    rows: ':visible'
                }
            },
            {
                extend: 'colvis',
                text: '<i title="column visibility" class="fa fa-eye"></i>',
                columns: ':gt(0)'
            },
        ],
    } );

    if(all_permission.indexOf("suppliers-delete") == -1)
        $('.buttons-delete').addClass('d-none');

</script>
@endpush
