@extends('backend.layout.main') @section('content')

<x-success-message key="message" />
<x-error-message key="not_permitted" />

<x-inventory-command-bar active="transfers" view="register">
    @if((new \App\Services\Platform\NavigationAccess($role_has_permissions_list ?? null))->allows('transfers-add'))<a href="{{ route('transfers.create') }}" class="btn btn-primary"><i class="dripicons-plus"></i> New transfer</a>@endif
</x-inventory-command-bar>
<section class="product-workspace-view inventory-transfer-workspace">
    <form id="transfer-filters" action="{{ route('transfers.index') }}" method="get" class="inventory-transfer-filters desk-filter-card">
        <div class="form-group"><label for="transfer-from-date">From date</label><input id="transfer-from-date" type="date" class="form-control" name="starting_date" value="{{ $starting_date }}" required></div>
        <div class="form-group"><label for="transfer-to-date">To date</label><input id="transfer-to-date" type="date" class="form-control" name="ending_date" value="{{ $ending_date }}" min="{{ $starting_date }}" required></div>
        @if(\Auth::user()->role_id <= 2)
        <div class="form-group"><label for="from_warehouse_id">From warehouse</label><select id="from_warehouse_id" name="from_warehouse_id" class="selectpicker form-control" data-live-search="true"><option value="0">All warehouses</option>@foreach($lims_warehouse_list as $warehouse)<option value="{{ $warehouse->id }}" @selected($warehouse->id == $from_warehouse_id)>{{ $warehouse->name }}</option>@endforeach</select></div>
        <div class="form-group"><label for="to_warehouse_id">To warehouse</label><select id="to_warehouse_id" name="to_warehouse_id" class="selectpicker form-control" data-live-search="true"><option value="0">All warehouses</option>@foreach($lims_warehouse_list as $warehouse)<option value="{{ $warehouse->id }}" @selected($warehouse->id == $to_warehouse_id)>{{ $warehouse->name }}</option>@endforeach</select></div>
        @else
        <input type="hidden" id="from_warehouse_id" name="from_warehouse_id" value="{{ $from_warehouse_id }}"><input type="hidden" id="to_warehouse_id" name="to_warehouse_id" value="{{ $to_warehouse_id }}">
        @endif
        <button class="btn btn-primary" type="submit">Apply filters</button>
        <p id="filter-loading" role="status" hidden>Loading transfers…</p>
    </form>
    <div class="desk-card-wrapper">
        <div class="table-responsive">
            <table id="transfer-table" class="table transfer-list u-w-100pct">
            <thead>
                <tr>
                    <th class="not-exported"></th>
                    <th>{{__('db.date')}}</th>
                    <th>{{__('db.reference')}} No</th>
                    <th>{{__('db.Warehouse')}}<br>({{__('db.From')}})</th>
                    <th>{{__('db.Warehouse')}}<br>({{__('db.To')}})</th>
                    <th>{{__('db.Shipping Cost')}}</th>
                    <th>{{__('db.grand total')}}</th>
                    <th>{{__("db.status")}}</th>
                    <th>{{__('db.Email Sent')}}</th>
                    <th class="not-exported">{{__('db.action')}}</th>
                </tr>
            </thead>
            <tfoot><tr><th></th><th>{{ __('db.Total') }}</th><th></th><th></th><th></th><th></th><th></th><th></th><th></th><th></th></tr></tfoot>
        </table>
    </div>
    </div>
</section>

<div id="transfer-details" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true" class="modal fade text-left">
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
                    <i class="u-fs-15px">{{__('db.Transfer Details')}}</i>
                </div>
            </div>
        </div>
            <div id="transfer-content" class="modal-body">
            </div>
            <br>
            <table class="table table-bordered product-transfer-list">
                <thead>
                    <th>#</th>
                    <th>{{__('db.product')}}</th>
                    <th>{{__('db.Batch No')}}</th>
                    <th>Qty</th>
                    <th>{{__('db.Unit Cost')}}</th>
                    <th>{{__('db.Tax')}}</th>
                    <th>{{__('db.Subtotal')}}</th>
                </thead>
                <tbody>
                </tbody>
            </table>
            <div id="transfer-footer" class="modal-body"></div>
      </div>
    </div>
</div>

@endsection

@push('scripts')
<script type="text/javascript">
    $("ul#inventory").siblings('a').attr('aria-expanded','true');
    $("ul#inventory").addClass("show");
    $("#transfer-list-menu").addClass("active");

    var all_permission = <?php echo json_encode($all_permission) ?>;
    var transfer_id = [];
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
    function confirmApprove() {
        if (confirm("Are you sure want to Approve?")) {
            return true;
        }
        return false;
    }

     $(document).on("click", "tr.transfer-link td:not(:first-child, :last-child)", function() {
        var transfers = $(this).parent().data('transfer');
        transferDetails(transfers);
    });

    $(document).on("click", ".view", function() {
        var transfers = $(this).parent().parent().parent().parent().parent().data('transfer');
        transferDetails(transfers);
    });

    $("#print-btn").on("click", function(){
        var divContents = document.getElementById("transfer-details").innerHTML;
        var a = window.open('');
        a.document.write('<html>');
        a.document.write('<body>');
        a.document.write('<link rel="stylesheet" href="{{ versioned_asset('css/pages/shared-quotation-index-2.css') }}"><link rel="stylesheet" href="{{ versioned_asset('css/pages/shared-customer-index-2.css') }}">');
        a.document.write(divContents);
        a.document.write('<\\/body><\\/html>');
        a.document.close();
        setTimeout(function(){a.close();},10);
        a.print();
    });

    var starting_date = $("input[name=starting_date]").val();
    var ending_date = $("input[name=ending_date]").val();
    var from_warehouse_id = $("#from_warehouse_id").val();
    var to_warehouse_id = $("#to_warehouse_id").val();

    var transferTable = $('#transfer-table').DataTable({
        "processing": true,
        "serverSide": true,
        "ajax":{
            url: @json(route("transfers.data")),
            data:function(d){
                d.all_permission    = all_permission;
                d.starting_date     = $("input[name=starting_date]").val();
                d.ending_date       = $("input[name=ending_date]").val();
                d.from_warehouse_id = $("#from_warehouse_id").val();
                d.to_warehouse_id   = $("#to_warehouse_id").val();
            },
            dataType: "json",
            type:"post"
        },
        "createdRow": function( row, data, dataIndex ) {
            $(row).addClass('transfer-link');
            $(row).attr('data-transfer', data['transfer']);
        },
        "columns": [
            {"data": "key"},
            {"data": "date"},
            {"data": "reference_no"},
            {"data": "from_warehouse"},
            {"data": "to_warehouse"},
            {"data": "shipping_cost"},
            {"data": "grand_total"},
            {"data": "status"},
            {"data": "is_sent"},
            {"data": "options"}
        ],
        'language': {

            'emptyTable': '<span class="inventory-empty-text">No transfers found for these dates and warehouses.</span>',
            'zeroRecords': '<span class="inventory-empty-text">No transfers match your search.</span>',
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
                'targets': [0, 2, 3, 4, 5, 6, 7, 8, 9]
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
        rowId: 'ObjectID',
        buttons: [
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
                text: '<i title="export to csv" class="fa fa-file-text-o"></i>',
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
                text: '<i title="print" class="fa fa-print"></i>',
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
                text: '<i title="delete" class="dripicons-cross"></i>',
                className: 'buttons-delete',
                action: function ( e, dt, node, config ) {
                    if(user_verified == '1') {
                        transfer_id.length = 0;
                        $(':checkbox:checked').each(function(i){
                            if(i){
                                var transfer = $(this).closest('tr').data('transfer');
                                if(transfer)
                                    transfer_id[i-1] = transfer[3];
                            }
                        });
                        if(transfer_id.length && confirm("Are you sure want to delete?")) {
                            $.ajax({
                                type:'POST',
                                url:'transfers/deletebyselection',
                                data:{
                                    transferIdArray: transfer_id
                                },
                                success:function(data){
                                    alert(data);
                                    //dt.rows({ page: 'current', selected: true }).deselect();
                                    dt.rows({ page: 'current', selected: true }).remove().draw(false);
                                }
                            });
                        }
                        else if(!transfer_id.length)
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
    });

    $('#transfer-from-date').on('change', function () {
        $('#transfer-to-date').attr('min', this.value);
    });
    $('#transfer-filters').on('submit', function (event) {
        event.preventDefault();
        if (!this.reportValidity()) return;
        var target = new URL(this.action, window.location.href);
        target.search = new URLSearchParams(new FormData(this)).toString();
        window.history.replaceState(null, '', target);
        transferTable.ajax.reload();
    });

    function datatable_sum(dt_selector, is_calling_first) {
        if (dt_selector.rows( '.selected' ).any() && is_calling_first) {
            var rows = dt_selector.rows( '.selected' ).indexes();

            $( dt_selector.column( 5 ).footer() ).html(dt_selector.cells( rows, 5, { page: 'current' } ).data().sum().toFixed({{$general_setting->decimal}}));
            $( dt_selector.column( 6 ).footer() ).html(dt_selector.cells( rows, 6, { page: 'current' } ).data().sum().toFixed({{$general_setting->decimal}}));
            // $( dt_selector.column( 7 ).footer() ).html(dt_selector.cells( rows, 7, { page: 'current' } ).data().sum().toFixed({{$general_setting->decimal}}));
        }
        else {
            $( dt_selector.column( 5 ).footer() ).html(dt_selector.cells( rows, 5, { page: 'current' } ).data().sum().toFixed({{$general_setting->decimal}}));
            $( dt_selector.column( 6 ).footer() ).html(dt_selector.cells( rows, 6, { page: 'current' } ).data().sum().toFixed({{$general_setting->decimal}}));
            // $( dt_selector.column( 7 ).footer() ).html(dt_selector.cells( rows, 7, { page: 'current' } ).data().sum().toFixed({{$general_setting->decimal}}));
        }
    }

    // Show loader on request
    transferTable.on('preXhr.dt', function () {
        $('#filter-loading').prop('hidden', false);
    });

    // Hide loader after draw
    transferTable.on('xhr.dt', function () {
        $('#filter-loading').prop('hidden', true);
    });

    function transferDetails(transfer) {
        var htmltext = '<strong>{{__("db.date")}}: </strong>'+transfer[0]+'<br><strong>{{__("db.reference")}}: </strong>'+transfer[1]+'<br><strong> {{__("db.Transfer")}} {{__("db.status")}}: </strong>'+transfer[2]+'<br><br><div class="row"><div class="col-md-6"><strong>{{__("db.From")}}:</strong><br>'+transfer[4]+'<br>'+transfer[5]+'<br>'+transfer[6]+'</div><div class="col-md-6"><div class="float-right"><strong>{{__("db.To")}}:</strong><br>'+transfer[7]+'<br>'+transfer[8]+'<br>'+transfer[9]+'</div></div></div>';

        $.get('transfers/product_transfer/' + transfer[3], function(data) {
            $(".product-transfer-list tbody").remove();
            var name_code = data[0];
            var qty = data[1];
            var unit_code = data[2];
            var tax = data[3];
            var tax_rate = data[4];
            var subtotal = data[5];
            var batch_no = data[6];
            var newBody = $("<tbody>");
            $.each(name_code, function(index) {
                var newRow = $("<tr>");
                var cols = '';
                cols += '<td><strong>' + (index+1) + '</strong></td>';
                cols += '<td>' + name_code[index] + '</td>';
                cols += '<td>' + batch_no[index] + '</td>';
                cols += '<td>' + qty[index] + ' ' + unit_code[index] + '</td>';
                cols += '<td>' + (subtotal[index] / qty[index]) + '</td>';
                cols += '<td>' + tax[index] + '(' + tax_rate[index] + '%)' + '</td>';
                cols += '<td>' + subtotal[index] + '</td>';
                newRow.append(cols);
                newBody.append(newRow);
            });

            var newRow = $("<tr>");
            cols = '';
            cols += '<td colspan=5><strong>{{__("db.Total")}}:</strong></td>';
            cols += '<td>' + transfer[10] + '</td>';
            cols += '<td>' + transfer[11] + '</td>';
            newRow.append(cols);
            newBody.append(newRow);

            var newRow = $("<tr>");
            cols = '';
            cols += '<td colspan=6><strong>{{__("db.Shipping Cost")}}:</strong></td>';
            cols += '<td>' + transfer[12] + '</td>';
            newRow.append(cols);
            newBody.append(newRow);

            var newRow = $("<tr>");
            cols = '';
            cols += '<td colspan=6><strong>{{__("db.grand total")}}:</strong></td>';
            cols += '<td>' + transfer[13] + '</td>';
            newRow.append(cols);
            newBody.append(newRow);

             $("table.product-transfer-list").append(newBody);
        });

        var htmlfooter = '<p><strong>{{__("db.Note")}}:</strong> '+transfer[14]+'</p><strong>{{__("db.Created By")}}:</strong><br>'+transfer[15]+'<br>'+transfer[16];

        $('#transfer-content').html(htmltext);
        $('#transfer-footer').html(htmlfooter);
        $('#transfer-details').modal('show');
    }

    if(all_permission.indexOf("transfers-delete") == -1)
        $('.buttons-delete').addClass('d-none');

</script>

@endpush
