@extends('backend.layout.main') @section('content')


<x-error-message key="code" />
<x-success-message key="message" />
<x-error-message key="not_permitted" />

@php
    $totalCatCount = count($lims_expense_category_all);
    $totalExpenseCount = \App\Models\Expense::count();
@endphp

<section class="expense-cat-workspace-view">
    <!-- Top Command Bar -->
    <div class="comm-command-bar">
        <div class="comm-title-group">
            <h1><i class="dripicons-list text-primary"></i> Expense Categories Command Center</h1>
            <span class="comm-title-badge"><i class="dripicons-tag"></i> Expense Master Classification</span>
        </div>
        <ul class="comm-nav-pills">
            <li><a class="nav-link" href="{{ route('expenses.index') }}"><i class="dripicons-wallet"></i> Expenses</a></li>
            <li><a class="nav-link active" href="{{ route('expense_categories.index') }}"><i class="dripicons-list"></i> Expense Category</a></li>
            <li><a class="nav-link" href="{{ route('incomes.index') }}"><i class="dripicons-rocket"></i> Incomes</a></li>
            <li><a class="nav-link" href="{{ route('income_categories.index') }}"><i class="dripicons-view-list"></i> Income Category</a></li>
            <li><a class="nav-link" href="{{ route('accounts.index') }}"><i class="dripicons-briefcase"></i> Accounts</a></li>
        </ul>
        <div class="comm-actions">
            <button class="btn btn-primary btn-action-pill u-bg-primary u-bc-primary u-c-xffffff" data-toggle="modal" data-target="#createModal">
                <i class="dripicons-plus"></i> {{ __('db.Add Expense Category') }}
            </button>
            <button class="btn btn-outline-secondary btn-action-pill" data-toggle="modal" data-target="#importExpenseCategory">
                <i class="dripicons-copy"></i> {{ __('db.Import Expense Category') }}
            </button>
        </div>
    </div>

    <!-- KPI Summary Strip -->
    <div class="kpi-summary-strip">
        <div class="kpi-card">
            <div>
                <div class="kpi-label">TOTAL CATEGORIES</div>
                <div class="kpi-number">{{ number_format($totalCatCount) }}</div>
            </div>
            <div class="z-icon-tile z-tone-primary">
                <i class="dripicons-list u-fs-18px"></i>
            </div>
        </div>
        <div class="kpi-card">
            <div>
                <div class="kpi-label">TOTAL EXPENSES LOGGED</div>
                <div class="kpi-number u-c-danger">{{ number_format($totalExpenseCount) }}</div>
            </div>
            <div class="z-icon-tile z-tone-danger">
                <i class="dripicons-wallet u-fs-18px"></i>
            </div>
        </div>
        <div class="kpi-card">
            <div>
                <div class="kpi-label">STATUS</div>
                <div class="kpi-number u-fs-14px u-fw-600 u-c-success">Active Master</div>
            </div>
            <div class="z-icon-tile z-tone-info">
                <i class="dripicons-checkmark u-fs-18px"></i>
            </div>
        </div>
    </div>

    <!-- Data Table Container -->
    <div class="desk-card-wrapper">
        <div class="table-responsive">
            <table id="expense_category-table" class="table u-w-100pct">
                <thead>
                    <tr>
                        <th class="not-exported"></th>
                        <th>{{ __('db.Code') }}</th>
                        <th>{{ __('db.name') }}</th>
                        <th class="not-exported">{{ __('db.action') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($lims_expense_category_all as $key => $expense_category)
                    <tr data-id="{{ $expense_category->id }}">
                        <td>{{ $key }}</td>
                        <td><span class="font-weight-bold font-monospace text-primary">{{ $expense_category->code }}</span></td>
                        <td><span class="font-weight-600">{{ $expense_category->name }}</span></td>
                        <td>
                            <div class="btn-group">
                                <button type="button" class="btn btn-default btn-sm dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">{{ __('db.action') }}
                                    <span class="caret"></span>
                                    <span class="sr-only">Toggle Dropdown</span>
                                </button>
                                <ul class="dropdown-menu edit-options dropdown-menu-right dropdown-default" user="menu">
                                    <li><button type="button" data-id="{{ $expense_category->id }}" class="open-Editexpense_categoryDialog btn btn-link" data-toggle="modal" data-target="#editModal"><i class="dripicons-document-edit"></i> {{ __('db.edit') }}</button></li>
                                    <li class="divider"></li>
                                    {{ Form::open(['route' => ['expense_categories.destroy', $expense_category->id], 'method' => 'DELETE']) }}
                                    <li>
                                        <button type="submit" class="btn btn-link" onclick="return confirmDelete()"><i class="dripicons-trash"></i> {{ __('db.delete') }}</button>
                                    </li>
                                    {{ Form::close() }}
                                </ul>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</section>

<!-- Create Expense Category Modal -->
<div id="createModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true" class="modal fade text-left">
    <div role="document" class="modal-dialog modal-dialog-centered">
      <div class="modal-content u-r-8px u-ov-hidden u-b-1px-solid-border-strong">
        {!! Form::open(['route' => 'expense_categories.store', 'method' => 'post', 'files' => true]) !!}
        <div class="modal-header u-bg-ink u-c-xffffff u-p-12px-18px">
          <h5 id="exampleModalLabel" class="modal-title font-weight-bold u-fs-15px u-c-xffffff">
              <i class="dripicons-plus mr-1"></i> {{ __('db.Add Expense Category') }}
          </h5>
          <button type="button" data-dismiss="modal" aria-label="Close" class="close u-c-xffffff u-op-0_8"><span aria-hidden="true">&times;</span></button>
        </div>
        <div class="modal-body u-p-16px-20px">
          <p class="italic text-muted mb-3 u-fs-11px"><small>{{ __('db.The field labels marked with * are required input fields') }}.</small></p>
            <div class="form-group mb-3">
                <label class="font-weight-600 mb-1 u-fs-11_5px u-c-text-2">{{ __('db.Code') }} *</label>
                <div class="input-group">
                    {{ Form::text('code', null, ['required' => 'required', 'class' => 'form-control form-control-sm', 'placeholder' => __('db.Type expense category code'), 'style' => 'height:32px; font-size:12.5px;']) }}
                    <div class="input-group-append">
                        <button id="genbutton" type="button" class="btn btn-secondary btn-sm u-fs-11px">{{ __('db.Generate') }}</button>
                    </div>
                </div>
            </div>
            <div class="form-group mb-3">
                <label class="font-weight-600 mb-1 u-fs-11_5px u-c-text-2">{{ __('db.name') }} *</label>
                {{ Form::text('name', null, ['required' => 'required', 'class' => 'form-control form-control-sm', 'placeholder' => __('db.Type expense category name'), 'style' => 'height:32px; font-size:12.5px;']) }}
            </div>
            <input type="hidden" name="is_active" value="1">
            <div class="form-group mb-0 text-right">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">{{ __('db.Cancel') }}</button>
                <button type="submit" class="btn btn-primary btn-sm font-weight-bold u-bg-primary u-bc-primary">{{ __('db.submit') }}</button>
            </div>
        </div>
        {{ Form::close() }}
      </div>
    </div>
</div>

<!-- Edit Expense Category Modal -->
<div id="editModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true" class="modal fade text-left">
  <div role="document" class="modal-dialog modal-dialog-centered">
    <div class="modal-content u-r-8px u-ov-hidden u-b-1px-solid-border-strong">
        {{ Form::open(['route' => ['expense_categories.update', 1], 'method' => 'PUT', 'files' => true]) }}
      <div class="modal-header u-bg-ink u-c-xffffff u-p-12px-18px">
        <h5 id="exampleModalLabel" class="modal-title font-weight-bold u-fs-15px u-c-xffffff">
            <i class="dripicons-document-edit mr-1"></i> {{ __('db.Update Expense Category') }}
        </h5>
        <button type="button" data-dismiss="modal" aria-label="Close" class="close u-c-xffffff u-op-0_8"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body u-p-16px-20px">
        <p class="italic text-muted mb-3 u-fs-11px"><small>{{ __('db.The field labels marked with * are required input fields') }}.</small></p>
            <div class="form-group mb-3">
                <label class="font-weight-600 mb-1 u-fs-11_5px u-c-text-2">{{ __('db.Code') }} *</label>
                {{ Form::text('code', null, ['required' => 'required', 'class' => 'form-control form-control-sm', 'placeholder' => __('db.Type expense category code'), 'style' => 'height:32px; font-size:12.5px;']) }}
            </div>
            <div class="form-group mb-3">
                <label class="font-weight-600 mb-1 u-fs-11_5px u-c-text-2">{{ __('db.name') }} *</label>
                {{ Form::text('name', null, ['required' => 'required', 'class' => 'form-control form-control-sm', 'placeholder' => __('db.Type expense category name'), 'style' => 'height:32px; font-size:12.5px;']) }}
            </div>
        <input type="hidden" name="expense_category_id">
        <div class="form-group mb-0 text-right">
            <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">{{ __('db.Cancel') }}</button>
            <button type="submit" class="btn btn-primary btn-sm font-weight-bold u-bg-primary u-bc-primary">{{ __('db.submit') }}</button>
        </div>
      </div>
      {{ Form::close() }}
    </div>
  </div>
</div>

<!-- Import Expense Category Modal -->
<div id="importExpenseCategory" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true" class="modal fade text-left">
    <div role="document" class="modal-dialog modal-dialog-centered">
      <div class="modal-content u-r-8px u-ov-hidden u-b-1px-solid-border-strong">
        {!! Form::open(['route' => 'expense_category.import', 'method' => 'post', 'files' => true]) !!}
        <div class="modal-header u-bg-ink u-c-xffffff u-p-12px-18px">
          <h5 id="exampleModalLabel" class="modal-title font-weight-bold u-fs-15px u-c-xffffff">
              <i class="dripicons-copy mr-1"></i> {{ __('db.Import Expense Category') }}
          </h5>
          <button type="button" data-dismiss="modal" aria-label="Close" class="close u-c-xffffff u-op-0_8"><span aria-hidden="true">&times;</span></button>
        </div>
        <div class="modal-body u-p-16px-20px">
            <p class="italic text-muted mb-2 u-fs-11px"><small>{{ __('db.The field labels marked with * are required input fields') }}.</small></p>
            <p class="text-muted mb-3 u-fs-11_5px">{{ __('db.The correct column order is') }} (code*, name*) {{ __('db.and you must follow this') }}.</p>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <div class="form-group mb-0">
                        <label class="font-weight-600 mb-1 u-fs-11_5px u-c-text-2">{{ __('db.Upload CSV File') }} *</label>
                        {{ Form::file('file', ['class' => 'form-control form-control-sm', 'required' => true, 'style' => 'height:32px; font-size:11px; padding:3px 6px;']) }}
                    </div>
                </div>
                <div class="col-md-6 mb-3">
                    <div class="form-group mb-0">
                        <label class="font-weight-600 mb-1 u-fs-11_5px u-c-text-2">{{ __('db.Sample File') }}</label>
                        <a href="sample_file/sample_expense_category.csv" class="btn btn-info btn-block btn-sm u-h-32px u-fs-12px u-d-flex u-items-center u-justify-center u-gap-5px"><i class="dripicons-download"></i> {{ __('db.Download') }}</a>
                    </div>
                </div>
            </div>
            <div class="form-group mb-0 text-right">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">{{ __('db.Cancel') }}</button>
                <button type="submit" class="btn btn-primary btn-sm font-weight-bold u-bg-primary u-bc-primary">{{ __('db.submit') }}</button>
            </div>
        </div>
        {{ Form::close() }}
      </div>
    </div>
</div>

@endsection

@push('scripts')
<script type="text/javascript">
    $("ul#expense").siblings('a').attr('aria-expanded','true');
    $("ul#expense").addClass("show");
    $("ul#expense #exp-cat-menu").addClass("active");

    var expense_category_id = [];
    var user_verified = <?php echo json_encode(config('app.user_verified')) ?>;

    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

    $('#genbutton').on("click", function(){
      $.get('expense_categories/gencode', function(data){
        $("input[name='code']").val(data);
      });
    });

    $(document).ready(function() {
        $(document).on('click', '.open-Editexpense_categoryDialog', function() {
            var url = "expense_categories/"
            var id = $(this).data('id').toString();
            url = url.concat(id).concat("/edit");
            $.get(url, function(data) {
                $("input[name='code']").val(data['code']);
                $("input[name='name']").val(data['name']);
                $("input[name='expense_category_id']").val(data['id']);
            });
        });
    });

    function confirmDelete() {
        if (confirm("Are you sure want to delete?")) {
            return true;
        }
        return false;
    }

    $('#expense_category-table').DataTable( {
        "order": [],
        'language': {
            'lengthMenu': '_MENU_ {{__("db.records per page")}}',
             "info": '<small>{{__("db.Showing")}} _START_ - _END_ (_TOTAL_)</small>',
            "search": '{{__("db.Search")}}',
            'paginate': {
                'previous': '<i class="dripicons-chevron-left"></i>',
                'next': '<i class="dripicons-chevron-right"></i>'
            }
        },
        'columnDefs': [
            {
                "orderable": false,
                'targets': [0, 3]
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
        buttons: [
            {
                extend: 'pdf',
                text: '<i title="export to pdf" class="fa fa-file-pdf-o"></i>',
                exportOptions: {
                    columns: ':visible:Not(.not-exported)',
                    rows: ':visible',
                    stripHtml: false
                },
            },
            {
                extend: 'excel',
                text: '<i title="export to excel" class="fa fa-file-excel-o"></i>',
                exportOptions: {
                    columns: ':visible:Not(.not-exported)',
                    rows: ':visible',
                },
            },
            {
                extend: 'csv',
                text: '<i title="export to csv" class="fa fa-file-text-o"></i>',
                exportOptions: {
                    columns: ':visible:Not(.not-exported)',
                    rows: ':visible',
                },
            },
            {
                extend: 'print',
                text: '<i title="print" class="fa fa-print"></i>',
                exportOptions: {
                    columns: ':visible:Not(.not-exported)',
                    rows: ':visible',
                    stripHtml: false
                },
            },
            {
                text: '<i title="delete" class="dripicons-cross"></i>',
                className: 'buttons-delete',
                action: function ( e, dt, node, config ) {
                    if(user_verified == '1') {
                        expense_category_id.length = 0;
                        $(':checkbox:checked').each(function(i){
                            if(i){
                                expense_category_id[i-1] = $(this).closest('tr').data('id');
                            }
                        });
                        if(expense_category_id.length && confirm("Are you sure want to delete?")) {
                            $.ajax({
                                type:'POST',
                                url:'expense_categories/deletebyselection',
                                data:{
                                    expense_categoryIdArray: expense_category_id
                                },
                                success: function(data) {
                                    $(':checkbox:checked').each(function(i) {
                                        if (i) {
                                            dt.row($(this).closest('tr')).remove().draw(false);
                                        }
                                    });
                                    alert(data);
                                }
                            });
                        }
                        else if(!expense_category_id.length)
                            alert('No expense category is selected!');
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
    } );
</script>
@endpush

@push('css')
    <link rel="stylesheet" href="<?php echo asset('css/commercial-entry.css') . '?v=' . (file_exists(public_path('css/commercial-entry.css')) ? filemtime(public_path('css/commercial-entry.css')) : time()) ?>" type="text/css">
    <link rel="stylesheet" href="<?php echo asset('css/commercial-workspace.css') . '?v=' . (file_exists(public_path('css/commercial-workspace.css')) ? filemtime(public_path('css/commercial-workspace.css')) : time()) ?>" type="text/css">
    <link rel="stylesheet" href="<?php echo asset('css/zolo-erp-neo.css') . '?v=' . (file_exists(public_path('css/zolo-erp-neo.css')) ? filemtime(public_path('css/zolo-erp-neo.css')) : time()) ?>" type="text/css">
    <link rel="stylesheet" href="{{ versioned_asset('css/pages/expense-category-index.css') }}">
@endpush
