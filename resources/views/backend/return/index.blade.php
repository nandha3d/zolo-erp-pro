@extends('backend.layout.main') @section('content')


<x-success-message key="message" />
<x-error-message key="not_permitted" />

@php
    $totalSalesReturnsCount = \App\Models\Returns::count();
    $totalSalesReturnsSum = \App\Models\Returns::sum('grand_total');
    $activeWarehousesCount = $lims_warehouse_list->count();
    $currencyCode = $currency->code ?? '₹';
@endphp

<section class="return-workspace-view">
    <!-- Top Command Bar -->
    <div class="comm-command-bar">
        <div class="comm-title-group">
            <h1><i class="dripicons-return text-primary"></i> {{ __('db.Sale Return List') }} Command Center</h1>
            <span class="comm-title-badge"><i class="dripicons-archive"></i> Outward Credit Notes</span>
        </div>
        <ul class="comm-nav-pills">
            <li><a class="nav-link active" href="{{ route('return-sale.index') }}"><i class="dripicons-list"></i> Returns</a></li>
            <li><a class="nav-link" href="{{ route('sales.index') }}"><i class="dripicons-cart"></i> Bills</a></li>
            <li><a class="nav-link" href="{{ config('commercial.enabled') ? route('sales.index', ['orders' => 1]) : route('sales.index', ['view' => 'orders', 'sale_status' => 2]) }}"><i class="dripicons-clipboard"></i> Orders</a></li>
            <li><a class="nav-link" href="{{ route('delivery-challans.index') }}"><i class="dripicons-truck"></i> Customer DC</a></li>
            <li><a class="nav-link" href="{{ route('customer.index') }}"><i class="dripicons-user"></i> Customers</a></li>
            <li><a class="nav-link" href="{{ route('quotations.index') }}"><i class="dripicons-document"></i> Quotations</a></li>
        </ul>
        <div class="comm-actions">
            @if(in_array("returns-add", $all_permission))
                <a href="#" data-new-document data-toggle="modal" data-target="#add-sale-return" class="btn btn-primary btn-action-pill u-bg-primary u-bc-primary u-c-xffffff">
                    <i class="dripicons-plus"></i> {{ __('db.Add Return') }}
                </a>
            @endif
        </div>
    </div>

    @include('backend.partials.document-draft-tabs', ['draftLabel' => 'sales return', 'draftKey' => 'Shift+F2'])

    <!-- KPI Summary Strip -->
    <div class="kpi-summary-strip">
        <div class="kpi-card">
            <div>
                <div class="kpi-label">TOTAL SALES RETURNS</div>
                <div class="kpi-number">{{ number_format($totalSalesReturnsCount) }}</div>
            </div>
            <div class="z-icon-tile z-tone-primary">
                <i class="dripicons-return u-fs-18px"></i>
            </div>
        </div>
        <div class="kpi-card">
            <div>
                <div class="kpi-label">TOTAL REFUND VALUE</div>
                <div class="kpi-number">{{ $currencyCode }} {{ number_format($totalSalesReturnsSum, 2) }}</div>
            </div>
            <div class="z-icon-tile z-tone-success">
                <i class="dripicons-wallet u-fs-18px"></i>
            </div>
        </div>
        <div class="kpi-card">
            <div>
                <div class="kpi-label">ACTIVE WAREHOUSES</div>
                <div class="kpi-number">{{ $activeWarehousesCount }}</div>
            </div>
            <div class="z-icon-tile z-tone-info">
                <i class="dripicons-store u-fs-18px"></i>
            </div>
        </div>
        <div class="kpi-card">
            <div>
                <div class="kpi-label">DATE WINDOW</div>
                <div class="kpi-number u-fs-13px u-fw-600 u-c-text-2">
                    {{ date('d M Y', strtotime($starting_date)) }} — {{ date('d M Y', strtotime($ending_date)) }}
                </div>
            </div>
            <div class="z-icon-tile z-tone-warning">
                <i class="dripicons-calendar u-fs-18px"></i>
            </div>
        </div>
    </div>

    <!-- Filter & Date Range Bar -->
    <div class="desk-filter-card">
        {!! Form::open(['route' => 'return-sale.index', 'method' => 'get', 'class' => 'form-inline d-flex align-items-center flex-wrap gap-2']) !!}
            <div class="form-group mr-2 mb-1">
                <label class="mr-2 font-weight-bold text-muted small"><i class="dripicons-calendar"></i> {{__('db.date')}}</label>
                <div class="input-group">
                    <input type="text" class="daterangepicker-field form-control form-control-sm u-h-30px u-fs-12px u-w-210px" value="{{$starting_date}} To {{$ending_date}}" required />
                    <input type="hidden" name="starting_date" value="{{$starting_date}}" />
                    <input type="hidden" name="ending_date" value="{{$ending_date}}" />
                </div>
            </div>
            <div class="form-group mr-2 mb-1 @if(\Auth::user()->role_id > 2){{'d-none'}}@endif">
                <label class="mr-2 font-weight-bold text-muted small"><i class="dripicons-store"></i> {{__('db.Warehouse')}}</label>
                <select id="warehouse_id" name="warehouse_id" class="form-control form-control-sm u-h-30px u-fs-12px u-minw-160px">
                    <option value="0">{{__('db.All Warehouse')}}</option>
                    @foreach($lims_warehouse_list as $warehouse)
                        @if($warehouse->id == $warehouse_id)
                            <option selected value="{{$warehouse->id}}">{{$warehouse->name}}</option>
                        @else
                            <option value="{{$warehouse->id}}">{{$warehouse->name}}</option>
                        @endif
                    @endforeach
                </select>
            </div>
            <div class="form-group mb-1">
                <button class="btn btn-primary btn-sm btn-action-pill u-bg-ink u-bc-ink" id="filter-btn" type="submit">
                    <i class="fa fa-filter"></i> {{__('db.submit')}}
                </button>
            </div>
        {!! Form::close() !!}
    </div>

    <!-- Main Table Container -->
    <div class="desk-card-wrapper">
        <div class="table-responsive">
            <table id="return-table" class="table return-list u-w-100pct">
                <thead>
                    <tr>
                        <th class="not-exported"></th>
                        <th>{{__('db.date')}}</th>
                        <th>{{__('db.reference')}}</th>
                        <th>{{__('db.Sale Reference')}}</th>
                        <th>{{__('db.Warehouse')}}</th>
                        <th>{{__('db.Biller')}}</th>
                        <th>{{__('db.customer')}}</th>
                        <th>{{__('db.grand total')}}</th>
                        <th class="not-exported">{{__('db.action')}}</th>
                    </tr>
                </thead>

                <tfoot class="tfoot active">
                    <th></th>
                    <th>{{__('db.Total')}}</th>
                    <th></th>
                    <th></th>
                    <th></th>
                    <th></th>
                    <th></th>
                    <th></th>
                    <th></th>
                </tfoot>
            </table>
        </div>
    </div>
</section>

<div id="return-details" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true" class="modal fade text-left">
    <div role="document" class="modal-dialog modal-lg">
      <div class="modal-content u-r-8px u-b-1px-solid-border-strong u-ov-hidden">
        <div class="container mt-3 pb-2 border-bottom">
            <div class="row align-items-center">
                <div class="col-md-6 d-print-none d-flex align-items-center gap-2">
                    <button id="print-btn" type="button" class="btn btn-outline-secondary btn-sm mr-2"><i class="dripicons-print"></i> {{__('db.Print')}}</button>
                    {{ Form::open(['route' => 'return-sale.sendmail', 'method' => 'post', 'class' => 'sendmail-form d-inline'] ) }}
                        <input type="hidden" name="return_id">
                        <button class="btn btn-outline-secondary btn-sm"><i class="dripicons-mail"></i> {{__('db.Email')}}</button>
                    {{ Form::close() }}
                </div>
                <div class="col-md-6 text-right d-print-none">
                    <button type="button" id="close-btn" data-dismiss="modal" aria-label="Close" class="close"><span aria-hidden="true"><i class="dripicons-cross"></i></span></button>
                </div>
                <div class="col-md-12 text-center mt-2">
                    <h4 id="exampleModalLabel" class="modal-title font-weight-bold u-c-text">{{$general_setting->site_title}}</h4>
                    <span class="badge badge-secondary u-fs-11px u-tt-uppercase">{{__('db.Return Details')}}</span>
                </div>
            </div>
        </div>
        <div id="return-content" class="modal-body">
        </div>
        <div class="px-3">
            <table class="table table-bordered product-return-list u-fs-12px">
                <thead class="u-bg-ink u-c-xffffff">
                    <th>#</th>
                    <th>{{__('db.product')}}</th>
                    <th>{{__('db.Batch No')}}</th>
                    <th>{{__('db.Qty')}}</th>
                    <th>{{__('db.Unit Price')}}</th>
                    <th>{{__('db.Tax')}}</th>
                    <th>{{__('db.Discount')}}</th>
                    <th>{{__('db.Subtotal')}}</th>
                </thead>
                <tbody>
                </tbody>
            </table>
        </div>
        <div id="return-footer" class="modal-body border-top pt-2 u-fs-12px"></div>
      </div>
    </div>
</div>

@endsection

@push('scripts')
<script type="text/javascript">
    $("ul#return").siblings('a').attr('aria-expanded','true');
    $("ul#return").addClass("show");
    $("ul#return #sale-return-menu").addClass("active");

    $(".daterangepicker-field").daterangepicker({
      callback: function(startDate, endDate, period){
        var starting_date = startDate.format('YYYY-MM-DD');
        var ending_date = endDate.format('YYYY-MM-DD');
        var title = starting_date + ' To ' + ending_date;
        $(this).val(title);
        $('input[name="starting_date"]').val(starting_date);
        $('input[name="ending_date"]').val(ending_date);
      }
    });

    var all_permission = <?php echo json_encode($all_permission) ?>;
    var return_id = [];
    var user_verified = <?php echo json_encode(config('app.user_verified')) ?>;

    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

    function confirmDelete() {
        if (confirm("Are you sure want to delete?")) {
            return true;
        }
        return false;
    }

    $(document).on("click", "tr.return-link td:not(:first-child, :last-child)", function() {
        var returns = $(this).parent().data('return');
        returnDetails(returns);
    });

    $(document).on("click", ".view", function() {
        var returns = $(this).parent().parent().parent().parent().parent().data('return');
        returnDetails(returns);
    });

    $("#print-btn").on("click", function(){
        var divContents = document.getElementById("return-details").innerHTML;
        var a = window.open('');
        a.document.write('<html>');
        a.document.write('<body>');
        a.document.write('<link rel="stylesheet" href="{{ versioned_asset('css/pages/shared-quotation-index-2.css') }}"><link rel="stylesheet" href="{{ versioned_asset('css/pages/shared-customer-index-2.css') }}">');
        a.document.write(divContents);
        a.document.write('</body></html>');
        a.document.close();
        setTimeout(function(){a.close();},10);
        a.print();
    });

    var starting_date = $("input[name=starting_date]").val();
    var ending_date = $("input[name=ending_date]").val();
    var warehouse_id = $("#warehouse_id").val();

    $('#return-table').DataTable( {
        "processing": true,
        "serverSide": true,
        "ajax":{
            url:"return-sale/return-data",
            data:{
                all_permission: all_permission,
                starting_date: starting_date,
                ending_date: ending_date,
                warehouse_id: warehouse_id
            },
            dataType: "json",
            type:"post"
        },
        "createdRow": function( row, data, dataIndex ) {
            $(row).addClass('return-link');
            $(row).attr('data-return', data['return']);
        },
        "columns": [
            {"data": "key"},
            {"data": "date"},
            {"data": "reference_no"},
            {"data": "sale_reference"},
            {"data": "warehouse"},
            {"data": "biller"},
            {"data": "customer"},
            {"data": "grand_total"},
            {"data": "options"},
        ],
        'language': {
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
                'targets': [0, 3, 4, 5, 6, 7, 8]
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
        dom: '<"row align-items-center mb-2"<"col-md-4"l><"col-md-4 text-center"B><"col-md-4"f>>rt<"row align-items-center mt-2"<"col-md-6"i><"col-md-6"p>>',
        rowId: 'ObjectID',
        buttons: [
            {
                extend: 'pdf',
                text: '<i title="export to pdf" class="fa fa-file-pdf-o text-danger"></i>',
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
                text: '<i title="export to excel" class="fa fa-file-excel-o text-success"></i>',
                exportOptions: {
                    columns: ':visible:Not(.not-exported)',
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
                text: '<i title="export to csv" class="fa fa-file-text-o text-info"></i>',
                exportOptions: {
                    columns: ':visible:Not(.not-exported)',
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
                text: '<i title="print" class="fa fa-print text-primary"></i>',
                exportOptions: {
                    columns: ':visible:Not(.not-exported)',
                    rows: ':visible'
                },
                action: function(e, dt, button, config) {
                    datatable_sum(dt, true);
                    $.fn.dataTable.ext.buttons.print.action.call(this, e, dt, button, config);
                    datatable_sum(dt, false);
                },
                footer:true
            },
            {
                text: '<i title="delete" class="dripicons-cross text-danger"></i>',
                className: 'buttons-delete',
                action: function ( e, dt, node, config ) {
                    if(user_verified == '1') {
                        return_id.length = 0;
                        $(':checkbox:checked').each(function(i){
                            if(i){
                                var returns = $(this).closest('tr').data('return');
                                return_id[i-1] = returns[13];
                            }
                        });
                        if(return_id.length && confirm("Are you sure want to delete?")) {
                            $.ajax({
                                type:'POST',
                                url:'returns/deletebyselection',
                                data:{
                                    returnIdArray: return_id
                                },
                                success:function(data){
                                    alert(data);
                                    dt.rows({ page: 'current', selected: true }).remove().draw(false);
                                }
                            });
                        }
                        else if(!return_id.length)
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
        ],
        drawCallback: function () {
            var api = this.api();
            datatable_sum(api, false);
        }
    } );

    function datatable_sum(dt_selector, is_calling_first) {
        if (dt_selector.rows( '.selected' ).any() && is_calling_first) {
            var rows = dt_selector.rows( '.selected' ).indexes();
            $( dt_selector.column( 7 ).footer() ).html(dt_selector.cells( rows, 7, { page: 'current' } ).data().sum().toFixed({{$general_setting->decimal}}));
        }
        else {
            $( dt_selector.column( 7 ).footer() ).html(dt_selector.cells( rows, 7, { page: 'current' } ).data().sum().toFixed({{$general_setting->decimal}}));
        }
    }

    function returnDetails(returns){
        $('input[name="return_id"]').val(returns[13]);
        var htmltext = '<strong>{{__("db.date")}}: </strong>'+returns[0]+'<br><strong>{{__("db.reference")}}: </strong>'+returns[1]+'<br><strong>{{__("db.Sale Reference")}}: </strong>'+returns[24]+'<br><strong>{{__("db.Warehouse")}}: </strong>'+returns[2]+'<br><strong>{{__("db.Currency")}}: </strong>'+returns[26];
        if(returns[27])
            htmltext += '<br><strong>{{__("db.Exchange Rate")}}: </strong>'+returns[27]+'<br>';
        else
            htmltext += '<br><strong>{{__("db.Exchange Rate")}}: </strong>N/A<br>';
        if(returns[25])
            htmltext += '<strong>{{__("db.Attach Document")}}: </strong><a href="{{ url('secure-documents/sale_return') }}/'+returns[25]+'">Download</a><br>';
        htmltext += '<br><div class="row"><div class="col-md-6"><strong>{{__("db.From")}}:</strong><br>'+returns[3]+'<br>'+returns[4]+'<br>'+returns[5]+'<br>'+returns[6]+'<br>'+returns[7]+'<br>'+returns[8]+'</div><div class="col-md-6"><div class="float-right"><strong>{{__("db.To")}}:</strong><br>'+returns[9]+'<br>'+returns[10]+'<br>'+returns[11]+'<br>'+returns[12]+'</div></div></div>';
        $.get('return-sale/product_return/' + returns[13], function(data){
            $(".product-return-list tbody").remove();
            var name_code = data[0];
            var qty = data[1];
            var unit_code = data[2];
            var tax = data[3];
            var tax_rate = data[4];
            var discount = data[5];
            var subtotal = data[6];
            var batch_no = data[7];
            var newBody = $("<tbody>");
            $.each(name_code, function(index){
                var newRow = $("<tr>");
                var cols = '';
                cols += '<td><strong>' + (index+1) + '</strong></td>';
                cols += '<td>' + name_code[index] + '</td>';
                cols += '<td>' + batch_no[index] + '</td>';
                cols += '<td>' + qty[index] + ' ' + unit_code[index] + '</td>';
                cols += '<td>' + (subtotal[index] / qty[index]) + '</td>';
                cols += '<td>' + tax[index] + '(' + tax_rate[index] + '%)' + '</td>';
                cols += '<td>' + discount[index] + '</td>';
                cols += '<td>' + subtotal[index] + '</td>';
                newRow.append(cols);
                newBody.append(newRow);
            });

            var newRow = $("<tr>");
            cols = '';
            cols += '<td colspan=5><strong>{{__("db.Total")}}:</strong></td>';
            cols += '<td>' + returns[14] + '</td>';
            cols += '<td>' + returns[15] + '</td>';
            cols += '<td>' + returns[16] + '</td>';
            newRow.append(cols);
            newBody.append(newRow);

            var newRow = $("<tr>");
            cols = '';
            cols += '<td colspan=7><strong>{{__("db.Order Tax")}}:</strong></td>';
            cols += '<td>' + returns[17] + '(' + returns[18] + '%)' + '</td>';
            newRow.append(cols);
            newBody.append(newRow);

            var newRow = $("<tr>");
            cols = '';
            cols += '<td colspan=7><strong>{{__("db.grand total")}}:</strong></td>';
            cols += '<td>' + returns[19] + '</td>';
            newRow.append(cols);
            newBody.append(newRow);

            $("table.product-return-list").append(newBody);
        });
        var htmlfooter = '<p><strong>{{__("db.Return Note")}}:</strong> '+returns[20]+'</p><p><strong>{{__("db.Staff Note")}}:</strong> '+returns[21]+'</p><strong>{{__("db.Created By")}}:</strong><br>'+returns[22]+'<br>'+returns[23];
        $('#return-content').html(htmltext);
        $('#return-footer').html(htmlfooter);
        $('#return-details').modal('show');
    }

    if(all_permission.indexOf("returns-delete") == -1)
        $('.buttons-delete').addClass('d-none');

</script>
<script type="text/javascript" src="https://js.stripe.com/v3/"></script>
@php
    $formDraftConfig = [
        'kind' => 'sreturn', 'label' => 'Sales Return', 'baseUrl' => url('documents/sreturn'), 'shortcut' => 'sale-return',
        'createUrl' => route('return-sale.create'), 'newUrl' => route('return-sale.index', ['new' => 1]),
    ];
@endphp
<script type="application/json" id="form-draft-data">@json($formDraftConfig)</script>
<script src="{{ asset('js/document-drafts.js') }}?v={{ filemtime(public_path('js/document-drafts.js')) }}"></script>
<script src="{{ asset('js/form-drafts.js') }}?v={{ filemtime(public_path('js/form-drafts.js')) }}"></script>
@endpush

@push('css')
    <link rel="stylesheet" href="<?php echo asset('css/commercial-entry.css') . '?v=' . (file_exists(public_path('css/commercial-entry.css')) ? filemtime(public_path('css/commercial-entry.css')) : time()) ?>" type="text/css">
    <link rel="stylesheet" href="<?php echo asset('css/commercial-workspace.css') . '?v=' . (file_exists(public_path('css/commercial-workspace.css')) ? filemtime(public_path('css/commercial-workspace.css')) : time()) ?>" type="text/css">
    <link rel="stylesheet" href="<?php echo asset('css/zolo-erp-neo.css') . '?v=' . (file_exists(public_path('css/zolo-erp-neo.css')) ? filemtime(public_path('css/zolo-erp-neo.css')) : time()) ?>" type="text/css">
    <link rel="stylesheet" href="{{ versioned_asset('css/pages/shared-return-index.css') }}">
@endpush
