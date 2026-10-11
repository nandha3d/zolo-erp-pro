@extends('backend.layout.main') @section('content')

<link rel="stylesheet" href="<?php echo asset('css/commercial-entry.css') . '?v=' . (file_exists(public_path('css/commercial-entry.css')) ? filemtime(public_path('css/commercial-entry.css')) : time()) ?>" type="text/css">
<link rel="stylesheet" href="<?php echo asset('css/commercial-workspace.css') . '?v=' . (file_exists(public_path('css/commercial-workspace.css')) ? filemtime(public_path('css/commercial-workspace.css')) : time()) ?>" type="text/css">
<link rel="stylesheet" href="<?php echo asset('css/zolo-erp-neo.css') . '?v=' . (file_exists(public_path('css/zolo-erp-neo.css')) ? filemtime(public_path('css/zolo-erp-neo.css')) : time()) ?>" type="text/css">

<style>
    .account-workspace-view {
        padding: 12px 18px 24px;
        background: #f8fafc;
        min-height: calc(100vh - 40px);
    }
    body.dark-mode .account-workspace-view {
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
    #account-table {
        border-collapse: collapse !important;
        width: 100% !important;
    }
    #account-table thead th {
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
    body.dark-mode #account-table thead th {
        background: #090d16 !important;
        color: #f8fafc !important;
    }
    #account-table tbody tr:nth-child(even) {
        background: #f8fafc;
    }
    #account-table tbody tr:nth-child(odd) {
        background: #ffffff;
    }
    body.dark-mode #account-table tbody tr:nth-child(even) {
        background: #141c2c;
    }
    body.dark-mode #account-table tbody tr:nth-child(odd) {
        background: #1e293b;
    }
    #account-table tbody tr:hover {
        background: #e2e8f0 !important;
    }
    body.dark-mode #account-table tbody tr:hover {
        background: #27354f !important;
    }
    #account-table td {
        padding: 9px 12px !important;
        border-bottom: 1px solid #e2e8f0 !important;
        vertical-align: middle !important;
        font-size: 12.5px;
    }
    body.dark-mode #account-table td {
        border-color: #334155 !important;
        color: #e2e8f0;
    }
    #account-table tfoot th {
        background: #f1f5f9 !important;
        color: #0f172a !important;
        font-weight: 700 !important;
        padding: 8px 12px !important;
        border-top: 2px solid #cbd5e1 !important;
    }
    body.dark-mode #account-table tfoot th {
        background: #1e293b !important;
        color: #f8fafc !important;
        border-top-color: #475569 !important;
    }
    .btn-action-pill {
        height: 28px !important;
        font-size: 11px !important;
        padding: 0 12px !important;
        border-radius: 4px !important;
        font-weight: 600 !important;
        display: inline-flex !important;
        align-items: center !important;
        gap: 5px !important;
    }
</style>

<x-success-message key="message" />
<x-error-message key="not_permitted" />
<x-error-message key="account_no" />

@php
    $totalAccountsCount = count($lims_account_all);
    $totalBalanceSum = $lims_account_all->sum('balance');
    $defaultAccount = $lims_account_all->firstWhere('is_default', 1);
@endphp

<section class="account-workspace-view">
    <!-- Top Command Bar -->
    <div class="comm-command-bar">
        <div class="comm-title-group">
            <h1><i class="dripicons-wallet text-primary"></i> {{ __('db.Account List') }} Command Center</h1>
            <span class="comm-title-badge"><i class="dripicons-briefcase"></i> Cash &amp; Bank Registers</span>
        </div>
        <ul class="comm-nav-pills">
            <li><a class="nav-link active" href="{{ route('accounts.index') }}"><i class="dripicons-wallet"></i> Accounts</a></li>
            <li><a class="nav-link" href="{{ route('money-transfers.index') }}"><i class="dripicons-export"></i> Money Transfer</a></li>
            <li><a class="nav-link" href="{{ route('accounting.voucher.entry') }}"><i class="dripicons-pencil"></i> Voucher Entry</a></li>
            <li><a class="nav-link" href="{{ route('accounting.coa') }}"><i class="dripicons-view-list"></i> COA</a></li>
            <li><a class="nav-link" href="{{ route('accounting.general-ledger') }}"><i class="dripicons-document"></i> General Ledger</a></li>
        </ul>
        <div class="comm-actions">
            <button class="btn btn-primary btn-action-pill" data-toggle="modal" data-target="#account-modal" style="background:#7c3aed; border-color:#7c3aed; color:#fff;">
                <i class="dripicons-plus"></i> {{ __('db.Add Account') }}
            </button>
        </div>
    </div>

    <!-- KPI Summary Strip -->
    <div class="kpi-summary-strip">
        <div class="kpi-card">
            <div>
                <div class="kpi-label">TOTAL ACCOUNTS</div>
                <div class="kpi-number">{{ $totalAccountsCount }}</div>
            </div>
            <div style="width:36px;height:36px;border-radius:6px;background:#ede9fe;display:flex;align-items:center;justify-content:center;color:#7c3aed;">
                <i class="dripicons-wallet" style="font-size:18px;"></i>
            </div>
        </div>
        <div class="kpi-card">
            <div>
                <div class="kpi-label">TOTAL AVAILABLE BALANCE</div>
                <div class="kpi-number" style="color:#16a34a;">₹ {{ number_format($totalBalanceSum, 2) }}</div>
            </div>
            <div style="width:36px;height:36px;border-radius:6px;background:#dcfce7;display:flex;align-items:center;justify-content:center;color:#16a34a;">
                <i class="dripicons-coin" style="font-size:18px;"></i>
            </div>
        </div>
        <div class="kpi-card">
            <div>
                <div class="kpi-label">PRIMARY DEFAULT ACCOUNT</div>
                <div class="kpi-number" style="font-size:14px; font-weight:700; color:#0f172a;">
                    {{ $defaultAccount ? $defaultAccount->name . ' (' . $defaultAccount->account_no . ')' : 'None Configured' }}
                </div>
            </div>
            <div style="width:36px;height:36px;border-radius:6px;background:#e0f2fe;display:flex;align-items:center;justify-content:center;color:#0284c7;">
                <i class="dripicons-checkmark" style="font-size:18px;"></i>
            </div>
        </div>
    </div>

    <!-- Main Table Container -->
    <div class="desk-card-wrapper">
        <div class="table-responsive">
            <table id="account-table" class="table" style="width: 100%">
                <thead>
                    <tr>
                        <th class="not-exported"></th>
                        <th>{{__('db.Account')}} No</th>
                        <th>{{__('db.name')}}</th>
                        <th>{{__('db.Initial Balance')}}</th>
                        <th>{{__('db.Available Balance')}}</th>
                        <th>{{__('db.Default')}}</th>
                        <th>{{__('db.Note')}}</th>
                        <th class="not-exported">{{__('db.action')}}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($lims_account_all as $key=>$account)
                    <tr>
                        <td>{{$key}}</td>
                        <td class="font-weight-bold font-monospace text-primary">{{ $account->account_no }}</td>
                        <td><span class="font-weight-bold" style="color:#0f172a;">{{ $account->name }}</span></td>
                        @if($account->initial_balance)
                            <td>{{ number_format((float)$account->initial_balance, $general_setting->decimal, '.', '')}}</td>
                        @else
                            <td>{{number_format(0, $general_setting->decimal, '.', '')}}</td>
                        @endif

                        @if($account->balance)
                            <td class="font-weight-bold text-success">{{ number_format((float)$account->balance, $general_setting->decimal, '.', '')}}</td>
                        @else
                            <td>{{number_format(0, $general_setting->decimal, '.', '')}}</td>
                        @endif
                        <td>
                            @if($account->is_default)
                            <input type="checkbox" checked class="default" data-id="{{$account->id}}" data-toggle="toggle" data-onstyle="success" data-offstyle="danger">
                            @else
                            <input type="checkbox" class="default" data-id="{{$account->id}}" data-toggle="toggle"  data-onstyle="success" data-offstyle="danger">
                            @endif
                        </td>
                        <td><small class="text-muted">{{ $account->note }}</small></td>
                        <td>
                            <div class="btn-group">
                                <button type="button" class="btn btn-default btn-sm dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">{{__('db.action')}}
                                    <span class="caret"></span>
                                    <span class="sr-only">Toggle Dropdown</span>
                                </button>
                                <ul class="dropdown-menu edit-options dropdown-menu-right dropdown-default" user="menu">
                                    <li><button type="button" data-id="{{$account->id}}" data-account_no="{{$account->account_no}}" data-name="{{$account->name}}"  data-initial_balance="{{$account->initial_balance}}" data-note="{{$account->note}}" class="edit-btn btn btn-link" data-toggle="modal" data-target="#editModal"><i class="dripicons-document-edit"></i> {{__('db.edit')}}</button></li>
                                    <li id="account-statement-menu"><a id="account-statement" href="{{ route('accounts.statement', $account->id) }}" class="btn btn-link"><i class="dripicons-document"></i> {{__('db.Statement')}}</a></li>
                                    <li class="divider"></li>
                                    {{ Form::open(['route' => ['accounts.destroy', $account->id], 'method' => 'DELETE'] ) }}
                                    <li>
                                        <button type="submit" class="btn btn-link" onclick="return confirmDelete()"><i class="dripicons-trash"></i> {{__('db.delete')}}</button>
                                    </li>
                                    {{ Form::close() }}
                                </ul>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
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
</section>

<div id="editModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true" class="modal fade text-left">
    <div role="document" class="modal-dialog">
        <div class="modal-content" style="border-radius:8px; border:1px solid #cbd5e1; overflow:hidden;">
            <div class="modal-header" style="background:#0f172a; color:#fff;">
                <h5 id="exampleModalLabel" class="modal-title font-weight-bold text-white">{{__('db.Update Account')}}</h5>
                <button type="button" data-dismiss="modal" aria-label="Close" class="close text-white"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
              <p class="italic text-muted"><small>{{__('db.The field labels marked with * are required input fields')}}.</small></p>
                {!! Form::open(['route' => ['accounts.update', 1], 'method' => 'put']) !!}
                    <div class="form-group">
                        <label class="font-weight-bold">{{__('db.Account')}} No *</label>
                        <input type="text" name="account_no" required class="form-control">
                        <input type="hidden" name="account_id">
                    </div>
                    <div class="form-group">
                        <label class="font-weight-bold">{{__('db.name')}} *</label>
                        <input type="text" name="name" required class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="font-weight-bold">{{__('db.Initial Balance')}}</label>
                        <input type="number" name="initial_balance" step="any" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="font-weight-bold">{{__('db.Note')}}</label>
                        <textarea name="note" rows="3" class="form-control"></textarea>
                    </div>
                    <div class="form-group text-right">
                        <button type="submit" class="btn btn-primary btn-action-pill" style="background:#0f172a; border-color:#0f172a;">{{__('db.update')}}</button>
                    </div>
                {{ Form::close() }}
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script type="text/javascript">

    $("ul#account").siblings('a').attr('aria-expanded','true');
    $("ul#account").addClass("show");
    $("ul#account #account-list-menu").addClass("active");

    $('.edit-btn').on('click', function() {
        $("#editModal input[name='account_no']").val( $(this).data('account_no') );
        $("#editModal input[name='name']").val( $(this).data('name') );
        $("#editModal input[name='initial_balance']").val( $(this).data('initial_balance') );
        $("#editModal input[name='account_id']").val( $(this).data('id') );
        $("#editModal textarea[name='note']").val( $(this).data('note') );
    });

    $('.default').on('change', function() {
        if ($(this).parent().hasClass("btn-success")) {
            var id = $(this).data('id');
            $('.default').not($(this)).parent().removeClass('btn-success');
            $('.default').not($(this)).parent().addClass('btn-danger off');
            $('.default').not($(this)).prop('checked', false);
            $(this).prop('checked', true);
            $.get('accounts/make-default/' + id, function(data) {
                alert(data);
            });
        }
        else {
            $(this).parent().removeClass('btn-danger off');
            $(this).parent().addClass('btn-success');
            $(this).prop('checked', true);
            alert('Please make another account default first!');
        }
    });

    function confirmDelete() {
        if (confirm("Are you sure want to delete?")) {
            return true;
        }
        return false;
    }

    var table = $('#account-table').DataTable( {
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
                'targets': [0, 7]
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
            $( dt_selector.column( 4 ).footer() ).html(dt_selector.cells( rows, 4, { page: 'current' } ).data().sum().toFixed({{$general_setting->decimal}}));
        }
        else {
            $( dt_selector.column( 4 ).footer() ).html(dt_selector.cells( rows, 4, { page: 'current' } ).data().sum().toFixed({{$general_setting->decimal}}));
        }
    }

</script>
@endpush
