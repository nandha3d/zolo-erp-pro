@extends('backend.layout.main') @section('content')


<x-success-message key="message" />
<x-error-message key="not_permitted" />

@php
    $totalIncomesCount = \App\Models\Income::count();
    $totalIncomesSum = \App\Models\Income::sum('amount');
    $incomeCategoriesCount = \Illuminate\Support\Facades\DB::table('income_categories')->where('is_active', true)->count();
@endphp

<section class="income-workspace-view">
    <!-- Top Command Bar -->
    <div class="comm-command-bar">
        <div class="comm-title-group">
            <h1><i class="dripicons-rocket text-success"></i> {{ __('db.Income List') }} Command Center</h1>
            <span class="comm-title-badge"><i class="dripicons-tag"></i> Operating &amp; General Incomes</span>
        </div>
        <ul class="comm-nav-pills">
            <li><a class="nav-link active" href="{{ route('incomes.index') }}"><i class="dripicons-rocket"></i> Incomes</a></li>
            <li><a class="nav-link" href="{{ route('income_categories.index') }}"><i class="dripicons-list"></i> Income Category</a></li>
            <li><a class="nav-link" href="{{ route('expenses.index') }}"><i class="dripicons-wallet"></i> Expenses</a></li>
            <li><a class="nav-link" href="{{ route('accounts.index') }}"><i class="dripicons-briefcase"></i> Accounts</a></li>
            <li><a class="nav-link" href="{{ route('accounting.voucher.entry') }}"><i class="dripicons-pencil"></i> Voucher Entry</a></li>
        </ul>
        <div class="comm-actions">
            @if (in_array('incomes-add', $all_permission))
                <button class="btn btn-primary btn-action-pill u-bg-success u-bc-success-border u-c-xffffff" data-toggle="modal" data-target="#income-modal">
                    <i class="dripicons-plus"></i> {{ __('db.Add Income') }}
                </button>
            @endif
        </div>
    </div>

    <!-- KPI Summary Strip -->
    <div class="kpi-summary-strip">
        <div class="kpi-card">
            <div>
                <div class="kpi-label">TOTAL INCOME RECORDS</div>
                <div class="kpi-number">{{ number_format($totalIncomesCount) }}</div>
            </div>
            <div class="z-icon-tile z-tone-success">
                <i class="dripicons-rocket u-fs-18px"></i>
            </div>
        </div>
        <div class="kpi-card">
            <div>
                <div class="kpi-label">TOTAL INCOME VALUE</div>
                <div class="kpi-number u-c-success">₹ {{ number_format($totalIncomesSum, 2) }}</div>
            </div>
            <div class="z-icon-tile z-tone-success">
                <i class="dripicons-arrow-up u-fs-18px"></i>
            </div>
        </div>
        <div class="kpi-card">
            <div>
                <div class="kpi-label">ACTIVE CATEGORIES</div>
                <div class="kpi-number">{{ $incomeCategoriesCount }}</div>
            </div>
            <div class="z-icon-tile z-tone-info">
                <i class="dripicons-tags u-fs-18px"></i>
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

    <!-- Desk Single-Box Filter Card -->
    <div class="desk-filter-card">
        {!! Form::open(['route' => 'incomes.index', 'method' => 'get', 'class' => 'm-0']) !!}
        <div class="row align-items-center u-m-0-6px">
            <div class="col-md-5 u-p-0-6px">
                <div class="form-group mb-0">
                    <label class="font-weight-600 mb-1 u-fs-11px u-tt-uppercase u-c-text-muted">
                        {{ __('db.Choose Your Date') }}
                    </label>
                    <div class="input-group">
                        <input type="text" class="daterangepicker-field form-control form-control-sm u-h-32px u-fs-12px" value="{{ $starting_date }} To {{ $ending_date }}" required />
                        <input type="hidden" name="starting_date" value="{{ $starting_date }}" />
                        <input type="hidden" name="ending_date" value="{{ $ending_date }}" />
                    </div>
                </div>
            </div>
            <div class="col-md-4 @if(\Auth::user()->role_id > 2) d-none @endif u-p-0-6px">
                <div class="form-group mb-0">
                    <label class="font-weight-600 mb-1 u-fs-11px u-tt-uppercase u-c-text-muted">
                        {{ __('db.Choose Warehouse') }}
                    </label>
                    <select id="warehouse_id" name="warehouse_id" class="form-control form-control-sm u-h-32px u-fs-12px">
                        <option value="0">{{ __('db.All Warehouse') }}</option>
                        @foreach ($lims_warehouse_list as $warehouse)
                            @if ($warehouse->id == $warehouse_id)
                                <option selected value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                            @else
                                <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                            @endif
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="col-md-3 d-flex align-items-end u-p-0-6px u-mt-18px">
                <button class="btn btn-primary btn-sm w-100 u-h-32px u-fs-12px u-fw-600" type="submit">
                    <i class="dripicons-search"></i> {{ __('db.submit') }}
                </button>
            </div>
        </div>
        {!! Form::close() !!}
    </div>

    <!-- Data Table Container -->
    <div class="desk-card-wrapper">
        <div class="table-responsive">
            <table id="income-table" class="table income-list u-w-100pct">
                <thead>
                    <tr>
                        <th class="not-exported"></th>
                        <th>{{ __('db.date') }}</th>
                        <th>{{ __('db.reference') }} No</th>
                        <th>{{ __('db.Warehouse') }}</th>
                        <th>{{ __('db.category') }}</th>
                        <th>{{ __('db.Amount') }}</th>
                        <th>{{ __('db.Note') }}</th>
                        <th class="not-exported">{{ __('db.action') }}</th>
                    </tr>
                </thead>
                <tfoot class="tfoot active">
                    <th></th>
                    <th>{{ __('db.Total') }}</th>
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

<!-- Edit Income Modal -->
<div id="editModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true" class="modal fade text-left">
    <div role="document" class="modal-dialog modal-dialog-centered">
        <div class="modal-content u-r-8px u-ov-hidden u-b-1px-solid-border-strong">
            <div class="modal-header u-bg-ink u-c-xffffff u-p-12px-18px">
                <h5 id="exampleModalLabel" class="modal-title font-weight-bold u-fs-15px u-c-xffffff">
                    <i class="dripicons-document-edit mr-1"></i> {{ __('db.Update Income') }}
                </h5>
                <button type="button" data-dismiss="modal" aria-label="Close" class="close u-c-xffffff u-op-0_8"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body u-p-16px-20px">
                <p class="italic text-muted mb-3 u-fs-11px"><small>{{ __('db.The field labels marked with * are required input fields') }}.</small></p>
                {!! Form::open(['route' => ['incomes.update', 1], 'method' => 'put']) !!}
                <?php
                    $lims_income_category_list = DB::table('income_categories')->where('is_active', true)->get();
                    if (Auth::user()->role_id > 2)
                        $lims_warehouse_list = DB::table('warehouses')->where([
                            ['is_active', true],
                            ['id', Auth::user()->warehouse_id]
                        ])->get();
                    else
                        $lims_warehouse_list = DB::table('warehouses')->where('is_active', true)->get();
                ?>
                <div class="form-group mb-2">
                    <input type="hidden" name="income_id">
                    <label class="font-weight-600 mb-0 u-fs-11_5px u-c-text-2">{{ __('db.reference') }}</label>
                    <p id="reference" class="font-weight-bold text-primary font-monospace mb-0 u-fs-13px">{{ 'er-' . date('Ymd') . '-' . date('his') }}</p>
                </div>
                <div class="row">
                    <div class="col-md-6 form-group mb-2">
                        <label class="font-weight-600 mb-1 u-fs-11_5px u-c-text-2">{{ __('db.date') }}</label>
                        <input type="text" name="created_at" class="form-control form-control-sm date u-h-32px u-fs-12_5px" placeholder="{{ __('db.Choose date') }}" />
                    </div>
                    <div class="col-md-6 form-group mb-2">
                        <label class="font-weight-600 mb-1 u-fs-11_5px u-c-text-2">{{ __('db.Income Category') }} *</label>
                        <select name="income_category_id" class="form-control form-control-sm u-h-32px u-fs-12_5px" required>
                            @foreach ($lims_income_category_list as $income_category)
                                <option value="{{ $income_category->id }}">{{ $income_category->name . ' (' . $income_category->code . ')' }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6 form-group mb-2">
                        <label class="font-weight-600 mb-1 u-fs-11_5px u-c-text-2">{{ __('db.Warehouse') }} *</label>
                        <select name="warehouse_id" class="form-control form-control-sm u-h-32px u-fs-12_5px" required>
                            @foreach ($lims_warehouse_list as $warehouse)
                                <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6 form-group mb-2">
                        <label class="font-weight-600 mb-1 u-fs-11_5px u-c-text-2">{{ __('db.Amount') }} *</label>
                        <input type="number" name="amount" step="any" required class="form-control form-control-sm u-h-32px u-fs-12_5px">
                    </div>
                    <div class="col-md-12 form-group mb-2">
                        <label class="font-weight-600 mb-1 u-fs-11_5px u-c-text-2">{{ __('db.Account') }}</label>
                        <select class="form-control form-control-sm u-h-32px u-fs-12_5px" name="account_id">
                            @foreach ($lims_account_list as $account)
                                @if ($account->is_default)
                                    <option selected value="{{ $account->id }}">{{ $account->name }} [{{ $account->account_no }}]</option>
                                @else
                                    <option value="{{ $account->id }}">{{ $account->name }} [{{ $account->account_no }}]</option>
                                @endif
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="form-group mb-3">
                    <label class="font-weight-600 mb-1 u-fs-11_5px u-c-text-2">{{ __('db.Note') }}</label>
                    <textarea name="note" rows="2" class="form-control form-control-sm u-fs-12_5px"></textarea>
                </div>
                <div class="form-group mb-0 text-right">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">{{ __('db.Cancel') }}</button>
                    <button type="submit" class="btn btn-primary btn-sm font-weight-bold u-bg-success u-bc-success-border">{{ __('db.submit') }}</button>
                </div>
                {{ Form::close() }}
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script type="text/javascript">
    $("ul#income").siblings('a').attr('aria-expanded','true');
    $("ul#income").addClass("show");
    $("ul#income #income-list-menu").addClass("active");

    var income_id = [];
    var user_verified = <?php echo json_encode(config('app.user_verified')) ?>;
    var all_permission = <?php echo json_encode($all_permission) ?>;

    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

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

    $(document).ready(function() {
        $(document).on('click', 'button.open-Editincome_categoryDialog', function() {
            var url = "incomes/";
            var id = $(this).data('id').toString();
            url = url.concat(id).concat("/edit");
            $.get(url, function(data) {
                $('#editModal #reference').text(data['reference_no']);
                $("#editModal input[name='created_at']").val(data['date']);
                $("#editModal select[name='warehouse_id']").val(data['warehouse_id']);
                $("#editModal select[name='income_category_id']").val(data['income_category_id']);
                $("#editModal select[name='account_id']").val(data['account_id']);
                $("#editModal input[name='amount']").val(data['amount']);
                $("#editModal input[name='income_id']").val(data['id']);
                $("#editModal textarea[name='note']").val(data['note']);
            });
        });
    });

    function confirmDelete() {
        if (confirm("Are you sure want to delete?")) {
            return true;
        }
        return false;
    }

    var starting_date = $("input[name=starting_date]").val();
    var ending_date = $("input[name=ending_date]").val();
    var warehouse_id = $("#warehouse_id").val();
    $('#income-table').DataTable( {
        "processing": true,
        "serverSide": true,
        "ajax":{
            url:"incomes/income-data",
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
            $(row).attr('data-income_id', data['id']);
        },
        "columns": [
            {"data": "key"},
            {"data": "date"},
            {"data": "reference_no"},
            {"data": "warehouse"},
            {"data": "incomeCategory"},
            {"data": "amount"},
            {"data": "note"},
            {"data": "options"}
        ],
        'language': {
            'lengthMenu': '_MENU_ {{__("db.records per page")}}',
            "info": '<small>{{__("db.Showing")}} _START_ - _END_ (_TOTAL_)</small>',
            "search": '{{__("db.Search")}}',
            'paginate': {
                'previous': '<i class="dripicons-chevron-left"></i>',
                'next': '<i class="dripicons-chevron-right"></i>'
            }
        },
        order:[['1', 'desc']],
        'columnDefs': [
            {
                "orderable": false,
                'targets': [0, 3, 4, 6, 7]
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
        'select': { style: 'multi', selector: 'td:first-child'},
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
                text: '<i title="export to excel" class="fa fa-file-excel-o"></i>',
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
                        income_id.length = 0;
                        $(':checkbox:checked').each(function(i){
                            if(i){
                                income_id[i-1] = $(this).closest('tr').data('income_id');
                            }
                        });
                        if(income_id.length && confirm("Are you sure want to delete?")) {
                            $.ajax({
                                type:'POST',
                                url:'incomes/deletebyselection',
                                data:{
                                    incomeIdArray: income_id
                                },
                                success:function(data){
                                    $(':checkbox:checked').each(function(i) {
                                        if (i) {
                                            dt.row($(this).closest('tr')).remove().draw(false);
                                        }
                                    });
                                    alert(data);
                                }
                            });
                        }
                        else if(!income_id.length)
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
            $( dt_selector.column( 5 ).footer() ).html(dt_selector.cells( rows, 5, { page: 'current' } ).data().sum().toFixed({{$general_setting->decimal}}));
        }
        else {
            $( dt_selector.column( 5 ).footer() ).html(dt_selector.cells( rows, 5, { page: 'current' } ).data().sum().toFixed({{$general_setting->decimal}}));
        }
    }

    if(all_permission.indexOf("incomes-delete") == -1)
        $('.buttons-delete').addClass('d-none');

</script>
@endpush

@push('css')
    <link rel="stylesheet" href="<?php echo asset('css/commercial-entry.css') . '?v=' . (file_exists(public_path('css/commercial-entry.css')) ? filemtime(public_path('css/commercial-entry.css')) : time()) ?>" type="text/css">
    <link rel="stylesheet" href="<?php echo asset('css/commercial-workspace.css') . '?v=' . (file_exists(public_path('css/commercial-workspace.css')) ? filemtime(public_path('css/commercial-workspace.css')) : time()) ?>" type="text/css">
    <link rel="stylesheet" href="<?php echo asset('css/zolo-erp-neo.css') . '?v=' . (file_exists(public_path('css/zolo-erp-neo.css')) ? filemtime(public_path('css/zolo-erp-neo.css')) : time()) ?>" type="text/css">
    <link rel="stylesheet" href="{{ versioned_asset('css/pages/income-index.css') }}">
@endpush
