@extends('backend.layout.main')

@push('css')
<style>
.product-history-nav-pills {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    padding: 10px 18px;
    border-bottom: 2px solid rgba(0, 0, 0, 0.05);
    background: #fff;
    border-radius: 12px;
    margin: 15px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
}
.product-history-nav-pills .nav-link {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 8px 18px;
    border-radius: 9999px;
    font-weight: 600;
    font-size: 0.88rem;
    text-decoration: none;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    border: 1px solid transparent;
}
/* Sales: Theme Purple */
.product-history-nav-pills .pill-sales {
    background: #fdf4ff;
    color: #7c3aed;
    border-color: #f0abfc;
}
.product-history-nav-pills .pill-sales.active,
.product-history-nav-pills .pill-sales:hover {
    background: linear-gradient(135deg, #7c3aed 0%, #6d28d9 100%) !important;
    color: #ffffff !important;
    box-shadow: 0 4px 12px rgba(124, 58, 237, 0.3);
    border-color: transparent;
}
/* Purchases: Theme Emerald */
.product-history-nav-pills .pill-purchases {
    background: #ecfdf5;
    color: #059669;
    border-color: #a7f3d0;
}
.product-history-nav-pills .pill-purchases.active,
.product-history-nav-pills .pill-purchases:hover {
    background: linear-gradient(135deg, #059669 0%, #047857 100%) !important;
    color: #ffffff !important;
    box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3);
    border-color: transparent;
}
/* Composition: Theme Amber */
.product-history-nav-pills .pill-composition {
    background: #fffbeb;
    color: #d97706;
    border-color: #fde68a;
}
.product-history-nav-pills .pill-composition.active,
.product-history-nav-pills .pill-composition:hover {
    background: linear-gradient(135deg, #d97706 0%, #b45309 100%) !important;
    color: #ffffff !important;
    box-shadow: 0 4px 12px rgba(217, 119, 6, 0.3);
    border-color: transparent;
}
/* Returns: Rose & Orange */
.product-history-nav-pills .pill-sale-return {
    background: #fff1f2;
    color: #e11d48;
    border-color: #fecdd3;
}
.product-history-nav-pills .pill-sale-return.active,
.product-history-nav-pills .pill-sale-return:hover {
    background: linear-gradient(135deg, #e11d48 0%, #be123c 100%) !important;
    color: #ffffff !important;
    box-shadow: 0 4px 12px rgba(225, 29, 72, 0.3);
    border-color: transparent;
}
.product-history-nav-pills .pill-purchase-return {
    background: #fff7ed;
    color: #ea580c;
    border-color: #fed7aa;
}
.product-history-nav-pills .pill-purchase-return.active,
.product-history-nav-pills .pill-purchase-return:hover {
    background: linear-gradient(135deg, #ea580c 0%, #c2410c 100%) !important;
    color: #ffffff !important;
    box-shadow: 0 4px 12px rgba(234, 88, 12, 0.3);
    border-color: transparent;
}

body.dark-mode .product-history-nav-pills {
    background: #111827;
    border-color: #374151;
}
body.dark-mode .product-history-nav-pills .pill-sales { background: #2e1065; color: #ddd6fe; border-color: #581c87; }
body.dark-mode .product-history-nav-pills .pill-purchases { background: #064e3b; color: #a7f3d0; border-color: #065f46; }
body.dark-mode .product-history-nav-pills .pill-composition { background: #451a03; color: #fde68a; border-color: #78350f; }
body.dark-mode .product-history-nav-pills .pill-sale-return { background: #4c0519; color: #fecdd3; border-color: #881337; }
body.dark-mode .product-history-nav-pills .pill-purchase-return { background: #431407; color: #fed7aa; border-color: #7c2d12; }
</style>
@endpush

@section('content')

<x-error-message key="not_permitted" />

<section>
    <div class="container-fluid">
        <div class="card">
            <div class="card-header mt-2">
                <h3 class="text-center">{{__('db.Product History')}}</h3>
            </div>
            {!! Form::open(['route' => 'products.history', 'method' => 'get']) !!}
            <div class="row ml-1">
                <input type="hidden" name="product_id" value="{{$product_id}}">
                <div class="col-md-4">
                    <div class="form-group">
                        <h4 class="mt-4">{{$product_data->name.' ['.$product_data->code.']'}}</h4>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label><strong>{{__('db.date')}}</strong></label>
                        <input type="text" class="daterangepicker-field form-control" value="{{$starting_date}} To {{$ending_date}}" required />
                        <input type="hidden" name="starting_date" value="{{$starting_date}}" />
                        <input type="hidden" name="ending_date" value="{{$ending_date}}" />
                    </div>
                </div>
                <div class="col-md-3 @if(\Auth::user()->role_id > 2){{'d-none'}}@endif">
                    <div class="form-group">
                        <label><strong>{{__('db.Warehouse')}}</strong></label>
                        <select id="warehouse_id" name="warehouse_id" class="selectpicker form-control" data-live-search="true" data-live-search-style="begins" >
                            <option value="0">{{__('db.All Warehouse')}}</option>
                            @foreach($lims_warehouse_list as $warehouse)
                                <option value="{{$warehouse->id}}">{{$warehouse->name}}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-2 mt-4">
                    <div class="form-group">
                        <button class="btn btn-primary" id="filter-btn" type="submit">{{__('db.submit')}}</button>
                    </div>
                </div>
            </div>
            {!! Form::close() !!}
        </div>
    </div>
    <ul class="nav product-history-nav-pills" role="tablist">
        <li class="nav-item">
            <a class="nav-link pill-sales active" href="#product-sale" role="tab" data-toggle="tab"><i class="fa fa-shopping-bag mr-1"></i> {{__('db.Sale')}}</a>
        </li>
        <li class="nav-item">
            <a class="nav-link pill-purchases" href="#product-purchase" role="tab" data-toggle="tab"><i class="fa fa-truck mr-1"></i> {{__('db.Purchase')}} (GRN)</a>
        </li>
        <li class="nav-item">
            <a class="nav-link pill-composition" href="#product-composition" role="tab" data-toggle="tab"><i class="fa fa-cubes mr-1"></i> Composition & Recipe</a>
        </li>
        <li class="nav-item">
            <a class="nav-link pill-sale-return" href="#product-sale-return" role="tab" data-toggle="tab"><i class="fa fa-reply mr-1"></i> {{__('db.Sale Return')}}</a>
        </li>
        <li class="nav-item">
            <a class="nav-link pill-purchase-return" href="#product-purchase-return" role="tab" data-toggle="tab"><i class="fa fa-undo mr-1"></i> {{__('db.Purchase Return')}}</a>
        </li>
    </ul>
    <div class="tab-content">
        <!-- sale table -->
        <div role="tabpanel" class="tab-pane fade show active" id="product-sale">
            <div class="table-responsive mb-4">
                <table id="sale-table" class="table table-hover u-w-100pct">
                    <thead>
                        <tr>
                            <th class="not-exported-sale"></th>
                            <th>{{__('db.date')}}</th>
                            <th>{{__('db.reference')}}</th>
                            <th>{{__('db.Warehouse')}}</th>
                            <th>{{__('db.customer')}}</th>
                            <th>{{__('db.qty')}}</th>
                            <th>{{__('db.Unit Price')}}</th>
                            <th>{{__('db.Subtotal')}}</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
        <!-- purchase table -->
        <div role="tabpanel" class="tab-pane fade" id="product-purchase">
            <div class="table-responsive mb-4">
                <table id="purchase-table" class="table table-hover u-w-100pct">
                    <thead>
                        <tr>
                            <th class="not-exported-purchase"></th>
                            <th>{{__('db.date')}}</th>
                            <th>{{__('db.reference')}}</th>
                            <th>{{__('db.Warehouse')}}</th>
                            <th>{{__('db.Supplier')}}</th>
                            <th>{{__('db.qty')}}</th>
                            <th>{{__('db.Unit Price')}}</th>
                            <th>{{__('db.Subtotal')}}</th>
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
                    </tfoot>
                </table>
            </div>
        </div>
        <!-- sale return table -->
        <div role="tabpanel" class="tab-pane fade" id="product-sale-return">
            <div class="table-responsive mb-4">
                <table id="sale-return-table" class="table table-hover u-w-100pct">
                    <thead>
                        <tr>
                            <th class="not-exported-sale-return"></th>
                            <th>{{__('db.date')}}</th>
                            <th>{{__('db.reference')}}</th>
                            <th>{{__('db.Warehouse')}}</th>
                            <th>{{__('db.customer')}}</th>
                            <th>{{__('db.qty')}}</th>
                            <th>{{__('db.Unit Price')}}</th>
                            <th>{{__('db.Subtotal')}}</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
        <!-- purchase return table -->
        <div role="tabpanel" class="tab-pane fade" id="product-purchase-return">
            <div class="table-responsive mb-4">
                <table id="purchase-return-table" class="table table-hover u-w-100pct">
                    <thead>
                        <tr>
                            <th class="not-exported-purchase-return"></th>
                            <th>{{__('db.date')}}</th>
                            <th>{{__('db.reference')}}</th>
                            <th>{{__('db.Warehouse')}}</th>
                            <th>{{__('db.Supplier')}}</th>
                            <th>{{__('db.qty')}}</th>
                            <th>{{__('db.Unit Price')}}</th>
                            <th>{{__('db.Subtotal')}}</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>

        <!-- composition table -->
        <div role="tabpanel" class="tab-pane fade" id="product-composition">
            <div class="card p-4 mx-3 my-2 shadow-sm rounded">
                <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom">
                    <div>
                        <h4 class="mb-0 text-dark font-weight-bold"><i class="fa fa-cubes text-warning mr-2"></i>Product Composition & Bill of Materials (BOM)</h4>
                        <small class="text-muted">Component recipe breakdown, wastage allowance, and unit cost structure for {{$product_data->name}}</small>
                    </div>
                    @can('products-edit')
                    <a href="{{ route('products.edit', $product_id) }}" class="btn btn-sm btn-outline-primary"><i class="fa fa-edit mr-1"></i> Edit Composition</a>
                    @endcan
                </div>

                @if(!empty($combo_items))
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead class="thead-light">
                            <tr>
                                <th>#</th>
                                <th>Component Product</th>
                                <th>Item Code</th>
                                <th>Required Qty</th>
                                <th>Wastage %</th>
                                <th>Unit Cost</th>
                                <th>Unit Price</th>
                                <th>Line Cost</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php $totalCompCost = 0; @endphp
                            @foreach($combo_items as $cIdx => $cItem)
                            @php
                                $lineCost = (float)$cItem['cost'] * (float)$cItem['qty'] * (1 + ((float)$cItem['wastage'] / 100));
                                $totalCompCost += $lineCost;
                            @endphp
                            <tr>
                                <td>{{ $cIdx + 1 }}</td>
                                <td><strong>{{ $cItem['name'] }}</strong></td>
                                <td><code>{{ $cItem['code'] }}</code></td>
                                <td><span class="badge badge-info">{{ $cItem['qty'] }} {{ $cItem['unit'] }}</span></td>
                                <td>{{ $cItem['wastage'] > 0 ? $cItem['wastage'] . '%' : '0%' }}</td>
                                <td>₹{{ number_format($cItem['cost'], 2) }}</td>
                                <td>₹{{ number_format($cItem['price'], 2) }}</td>
                                <td class="font-weight-bold text-success">₹{{ number_format($lineCost, 2) }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-light font-weight-bold">
                            <tr>
                                <td colspan="7" class="text-right">Estimated Recipe Production Cost:</td>
                                <td class="text-success">₹{{ number_format($totalCompCost, 2) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                @else
                <div class="alert alert-info py-4 text-center">
                    <i class="fa fa-info-circle fa-2x mb-2 text-primary d-block"></i>
                    <h5 class="font-weight-bold">Single / Standard Product Master</h5>
                    <p class="text-muted mb-3">This product is currently configured as a <strong>{{ ucfirst($product_data->type ?? 'Standard') }}</strong> item.<br>To define sub-components, raw materials, wastage percentages, and recipe composition, change its type to <strong>Combo / Recipe</strong> in the product editor.</p>
                    @can('products-edit')
                    <a href="{{ route('products.edit', $product_id) }}" class="btn btn-primary"><i class="fa fa-sliders mr-1"></i> Configure Recipe in Edit Product</a>
                    @endcan
                </div>
                @endif
            </div>
        </div>
    </div>
</section>

@endsection

@push('scripts')
<script type="text/javascript">

    $("ul#product").siblings('a').attr('aria-expanded','true');
    $("ul#product").addClass("show");

    var starting_date = <?php echo json_encode($starting_date); ?>;
    var ending_date = <?php echo json_encode($ending_date); ?>;
    var warehouse_id = <?php echo json_encode($warehouse_id); ?>;
    var product_id = <?php echo json_encode($product_id); ?>;

    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

    $("#warehouse_id").val(warehouse_id);

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
    //retreiving sale table data
    $('#sale-table').DataTable({
        "processing": true,
        "serverSide": true,
        "ajax":{
            url:"sale-history-data",
            data:{
                product_id: product_id,
                starting_date: starting_date,
                ending_date: ending_date,
                warehouse_id: warehouse_id
            },
            dataType: "json",
            type:"post"
        },
        "columns": [
            {"data": "key"},
            {"data": "date"},
            {"data": "reference_no"},
            {"data": "warehouse"},
            {"data": "customer"},
            {"data": "qty"},
            {"data": "unit_price"},
            {"data": "sub_total"}
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
                'targets': [0, 3, 4, 5, 6, 7]
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
                    columns: ':visible:Not(.not-exported-sale)',
                    rows: ':visible'
                }
            },
            {
                extend: 'csv',
                text: '<i title="export to csv" class="fa fa-file-text-o"></i>',
                exportOptions: {
                    columns: ':visible:Not(.not-exported-sale)',
                    rows: ':visible'
                }
            },
            {
                extend: 'print',
                text: '<i title="print" class="fa fa-print"></i>',
                exportOptions: {
                    columns: ':visible:Not(.not-exported-sale)',
                    rows: ':visible'
                }
            },
            {
                extend: 'colvis',
                text: '<i title="column visibility" class="fa fa-eye"></i>',
                columns: ':gt(0)'
            },
        ]
    });
    //retreiving purchase table data
    $('#purchase-table').DataTable({
        "processing": true,
        "serverSide": true,
        "ajax":{
            url:"purchase-history-data",
            data:{
                product_id: product_id,
                starting_date: starting_date,
                ending_date: ending_date,
                warehouse_id: warehouse_id
            },
            dataType: "json",
            type:"post"
        },
        "columns": [
            {"data": "key"},
            {"data": "date"},
            {"data": "reference_no"},
            {"data": "warehouse"},
            {"data": "supplier"},
            {"data": "qty"},
            {"data": "unit_cost"},
            {"data": "sub_total"}
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
                'targets': [0, 3, 4, 5, 6, 7]
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
                    columns: ':visible:Not(.not-exported-purchase)',
                    rows: ':visible'
                },
                action: function(e, dt, button, config) {
                    datatable_sum_purchase(dt, true);
                    $.fn.dataTable.ext.buttons.pdfHtml5.action.call(this, e, dt, button, config);
                    datatable_sum_purchase(dt, false);
                },
                footer:true
            },
            {
                extend: 'csv',
                text: '<i title="export to csv" class="fa fa-file-text-o"></i>',
                exportOptions: {
                    columns: ':visible:Not(.not-exported-purchase)',
                    rows: ':visible'
                },
                action: function(e, dt, button, config) {
                    datatable_sum_purchase(dt, true);
                    $.fn.dataTable.ext.buttons.csvHtml5.action.call(this, e, dt, button, config);
                    datatable_sum_purchase(dt, false);
                },
                footer:true
            },
            {
                extend: 'print',
                text: '<i title="print" class="fa fa-print"></i>',
                exportOptions: {
                    columns: ':visible:Not(.not-exported-purchase)',
                    rows: ':visible'
                },
                action: function(e, dt, button, config) {
                    datatable_sum_purchase(dt, true);
                    $.fn.dataTable.ext.buttons.print.action.call(this, e, dt, button, config);
                    datatable_sum_purchase(dt, false);
                },
                footer:true
            },
            {
                extend: 'colvis',
                text: '<i title="column visibility" class="fa fa-eye"></i>',
                columns: ':gt(0)'
            },
        ],
        drawCallback: function () {
            var api = this.api();
            datatable_sum_purchase(api, false);
        }
    });
    
    function datatable_sum_purchase(dt_selector, is_calling_first) {
        if (dt_selector.rows( '.selected' ).any() && is_calling_first) {
            var rows = dt_selector.rows( '.selected' ).indexes();

            $( dt_selector.column( 7 ).footer() ).html(dt_selector.cells( rows, 7, { page: 'current' } ).data().sum().toFixed({{$general_setting->decimal}}));
        }
        else {
            $( dt_selector.column( 7 ).footer() ).html(dt_selector.cells( rows, 7, { page: 'current' } ).data().sum().toFixed({{$general_setting->decimal}}));
        }
    }

    //retreiving sale return table data
    $('#sale-return-table').DataTable({
        "processing": true,
        "serverSide": true,
        "ajax":{
            url:"sale-return-history-data",
            data:{
                product_id: product_id,
                starting_date: starting_date,
                ending_date: ending_date,
                warehouse_id: warehouse_id
            },
            dataType: "json",
            type:"post"
        },
        "columns": [
            {"data": "key"},
            {"data": "date"},
            {"data": "reference_no"},
            {"data": "warehouse"},
            {"data": "customer"},
            {"data": "qty"},
            {"data": "unit_price"},
            {"data": "sub_total"}
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
                'targets': [0, 3, 4, 5, 6, 7]
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
                    columns: ':visible:Not(.not-exported-sale-return)',
                    rows: ':visible'
                }
            },
            {
                extend: 'csv',
                text: '<i title="export to csv" class="fa fa-file-text-o"></i>',
                exportOptions: {
                    columns: ':visible:Not(.not-exported-sale-return)',
                    rows: ':visible'
                }
            },
            {
                extend: 'print',
                text: '<i title="print" class="fa fa-print"></i>',
                exportOptions: {
                    columns: ':visible:Not(.not-exported-sale-return)',
                    rows: ':visible'
                }
            },
            {
                extend: 'colvis',
                text: '<i title="column visibility" class="fa fa-eye"></i>',
                columns: ':gt(0)'
            },
        ]
    });
    //retreiving purchase return table data
    $('#purchase-return-table').DataTable({
        "processing": true,
        "serverSide": true,
        "ajax":{
            url:"purchase-return-history-data",
            data:{
                product_id: product_id,
                starting_date: starting_date,
                ending_date: ending_date,
                warehouse_id: warehouse_id
            },
            dataType: "json",
            type:"post"
        },
        "columns": [
            {"data": "key"},
            {"data": "date"},
            {"data": "reference_no"},
            {"data": "warehouse"},
            {"data": "supplier"},
            {"data": "qty"},
            {"data": "unit_cost"},
            {"data": "sub_total"}
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
                'targets': [0, 3, 4, 5, 6, 7]
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
                    columns: ':visible:Not(.not-exported-purchase-return)',
                    rows: ':visible'
                }
            },
            {
                extend: 'csv',
                text: '<i title="export to csv" class="fa fa-file-text-o"></i>',
                exportOptions: {
                    columns: ':visible:Not(.not-exported-purchase-return)',
                    rows: ':visible'
                }
            },
            {
                extend: 'print',
                text: '<i title="print" class="fa fa-print"></i>',
                exportOptions: {
                    columns: ':visible:Not(.not-exported-purchase-return)',
                    rows: ':visible'
                }
            },
            {
                extend: 'colvis',
                text: '<i title="column visibility" class="fa fa-eye"></i>',
                columns: ':gt(0)'
            },
        ]
    });

    // Auto-switch tab based on URL query parameter
    var urlParams = new URLSearchParams(window.location.search);
    var targetTab = urlParams.get('tab');
    if (targetTab === 'purchases' || targetTab === 'purchase') {
        $('.product-history-nav-pills a[href="#product-purchase"]').tab('show');
    } else if (targetTab === 'composition' || targetTab === 'recipe') {
        $('.product-history-nav-pills a[href="#product-composition"]').tab('show');
    } else if (targetTab === 'sales' || targetTab === 'sale') {
        $('.product-history-nav-pills a[href="#product-sale"]').tab('show');
    }
</script>
<script type="text/javascript" src="https://js.stripe.com/v3/"></script>
@endpush
