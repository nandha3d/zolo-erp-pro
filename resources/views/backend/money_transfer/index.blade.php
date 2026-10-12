@extends('backend.layout.main') @section('content')

<link rel="stylesheet" href="<?php echo asset('css/commercial-entry.css') . '?v=' . (file_exists(public_path('css/commercial-entry.css')) ? filemtime(public_path('css/commercial-entry.css')) : time()) ?>" type="text/css">
<link rel="stylesheet" href="<?php echo asset('css/commercial-workspace.css') . '?v=' . (file_exists(public_path('css/commercial-workspace.css')) ? filemtime(public_path('css/commercial-workspace.css')) : time()) ?>" type="text/css">
<link rel="stylesheet" href="<?php echo asset('css/zolo-erp-neo.css') . '?v=' . (file_exists(public_path('css/zolo-erp-neo.css')) ? filemtime(public_path('css/zolo-erp-neo.css')) : time()) ?>" type="text/css">

<style>
    .transfer-workspace-view {
        padding: 12px 18px 24px;
        background: #f8fafc;
        min-height: calc(100vh - 40px);
    }
    body.dark-mode .transfer-workspace-view {
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
    #money-transfer-table {
        border-collapse: collapse !important;
        width: 100% !important;
    }
    #money-transfer-table thead th {
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
    body.dark-mode #money-transfer-table thead th {
        background: #090d16 !important;
        color: #f8fafc !important;
    }
    #money-transfer-table tbody tr:nth-child(even) {
        background: #f8fafc;
    }
    #money-transfer-table tbody tr:nth-child(odd) {
        background: #ffffff;
    }
    body.dark-mode #money-transfer-table tbody tr:nth-child(even) {
        background: #141c2c;
    }
    body.dark-mode #money-transfer-table tbody tr:nth-child(odd) {
        background: #1e293b;
    }
    #money-transfer-table tbody tr:hover {
        background: #e2e8f0 !important;
    }
    body.dark-mode #money-transfer-table tbody tr:hover {
        background: #27354f !important;
    }
    #money-transfer-table td {
        padding: 9px 12px !important;
        border-bottom: 1px solid #e2e8f0 !important;
        vertical-align: middle !important;
        font-size: 12.5px;
    }
    body.dark-mode #money-transfer-table td {
        border-color: #334155 !important;
        color: #e2e8f0;
    }
    #money-transfer-table tfoot th {
        background: #f1f5f9 !important;
        color: #0f172a !important;
        font-weight: 700 !important;
        padding: 8px 12px !important;
        border-top: 2px solid #cbd5e1 !important;
    }
    body.dark-mode #money-transfer-table tfoot th {
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

@php
    $totalTransfersCount = count($lims_money_transfer_all);
    $totalTransfersSum = $lims_money_transfer_all->sum('amount');
    $accountsCount = count($lims_account_list);
@endphp

<section class="transfer-workspace-view">
    <!-- Top Command Bar -->
    <div class="comm-command-bar">
        <div class="comm-title-group">
            <h1><i class="dripicons-export text-primary"></i> {{ __('db.Money Transfer') }} Command Center</h1>
            <span class="comm-title-badge"><i class="dripicons-swap"></i> Inter-Account Transfers</span>
        </div>
        <ul class="comm-nav-pills">
            <li><a class="nav-link active" href="{{ route('money-transfers.index') }}"><i class="dripicons-export"></i> Money Transfer</a></li>
            <li><a class="nav-link" href="{{ route('accounts.index') }}"><i class="dripicons-wallet"></i> Accounts</a></li>
            <li><a class="nav-link" href="{{ route('accounting.voucher.entry') }}"><i class="dripicons-pencil"></i> Voucher Entry</a></li>
            <li><a class="nav-link" href="{{ route('accounting.coa') }}"><i class="dripicons-view-list"></i> COA</a></li>
            <li><a class="nav-link" href="{{ route('accounting.general-ledger') }}"><i class="dripicons-document"></i> General Ledger</a></li>
        </ul>
        <div class="comm-actions">
            <button class="btn btn-primary btn-action-pill" data-toggle="modal" data-target="#create-money-transfer-modal" style="background:#7c3aed; border-color:#7c3aed; color:#fff;">
                <i class="dripicons-plus"></i> {{ __('db.Add Money Transfer') }}
            </button>
        </div>
    </div>

    <!-- KPI Summary Strip -->
    <div class="kpi-summary-strip">
        <div class="kpi-card">
            <div>
                <div class="kpi-label">TOTAL TRANSFERS</div>
                <div class="kpi-number">{{ $totalTransfersCount }}</div>
            </div>
            <div style="width:36px;height:36px;border-radius:6px;background:#ede9fe;display:flex;align-items:center;justify-content:center;color:#7c3aed;">
                <i class="dripicons-export" style="font-size:18px;"></i>
            </div>
        </div>
        <div class="kpi-card">
            <div>
                <div class="kpi-label">TOTAL TRANSFERRED AMOUNT</div>
                <div class="kpi-number" style="color:#16a34a;">₹ {{ number_format($totalTransfersSum, 2) }}</div>
            </div>
            <div style="width:36px;height:36px;border-radius:6px;background:#dcfce7;display:flex;align-items:center;justify-content:center;color:#16a34a;">
                <i class="dripicons-wallet" style="font-size:18px;"></i>
            </div>
        </div>
        <div class="kpi-card">
            <div>
                <div class="kpi-label">CONFIGURED ACCOUNTS</div>
                <div class="kpi-number">{{ $accountsCount }}</div>
            </div>
            <div style="width:36px;height:36px;border-radius:6px;background:#e0f2fe;display:flex;align-items:center;justify-content:center;color:#0284c7;">
                <i class="dripicons-card" style="font-size:18px;"></i>
            </div>
        </div>
    </div>

    <!-- Main Table Container -->
    <div class="desk-card-wrapper">
        <div class="table-responsive">
            <table id="money-transfer-table" class="table" style="width: 100%">
                <thead>
                    <tr>
                        <th class="not-exported"></th>
                        <th>{{__('db.date')}}</th>
                        <th>{{__('db.Reference No')}}</th>
                        <th>{{__('db.From Account')}}</th>
                        <th>{{__('db.To Account')}}</th>
                        <th>{{__('db.Amount')}}</th>
                        <th class="not-exported">{{__('db.action')}}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($lims_money_transfer_all as $key=>$money_transfer)
                    <tr data-id="{{$money_transfer->id}}">
                        <td>{{$key}}</td>
                        <td>{{date($general_setting->date_format, strtotime($money_transfer->created_at->toDateString())) . ' '. $money_transfer->created_at->toTimeString() }}</td>
                        <td class="font-weight-bold font-monospace text-primary">{{ $money_transfer->reference_no }}</td>
                        <td><span class="badge badge-light border" style="font-size:11px;">{{ $money_transfer->fromAccount->name ?? 'N/A' }}</span></td>
                        <td><span class="badge badge-light border" style="font-size:11px;">{{ $money_transfer->toAccount->name ?? 'N/A' }}</span></td>
                        <td class="font-weight-bold" style="color:#0f172a;">{{ number_format((float)$money_transfer->amount, $general_setting->decimal, '.', '')}}</td>
                        <td>
                            <div class="btn-group">
                                <button type="button" class="btn btn-default btn-sm dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">{{__('db.action')}}
                                    <span class="caret"></span>
                                    <span class="sr-only">Toggle Dropdown</span>
                                </button>
                                <ul class="dropdown-menu edit-options dropdown-menu-right dropdown-default" user="menu">
                                    <li><button type="button" id="edit-btn" data-id="{{$money_transfer->id}}" data-created_at="{{date(config('date_format'), strtotime($money_transfer->created_at->toDateString()))}}" data-from_id="{{$money_transfer->from_account_id}}" data-to_id="{{$money_transfer->to_account_id}}" data-amount="{{$money_transfer->amount}}"  class=" btn btn-link" data-toggle="modal" data-target="#edit-money-transfer-modal"><i class="dripicons-document-edit"></i> {{__('db.edit')}}</button></li>
                                    <li class="divider"></li>
                                    {{ Form::open(['route' => ['money-transfers.destroy', $money_transfer->id], 'method' => 'DELETE'] ) }}
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
                </tfoot>
            </table>
        </div>
    </div>
</section>

<!-- Create Money Transfer modal -->
<div id="create-money-transfer-modal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true" class="modal fade text-left">
    <div role="document" class="modal-dialog">
        <div class="modal-content" style="border-radius:8px; border:1px solid #cbd5e1; overflow:hidden;">
            <div class="modal-header" style="background:#0f172a; color:#fff;">
                <h5 id="exampleModalLabel" class="modal-title font-weight-bold text-white">{{__('db.Add Money Transfer')}}</h5>
                <button type="button" data-dismiss="modal" aria-label="Close" class="close text-white"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
              <p class="italic text-muted"><small>{{__('db.The field labels marked with * are required input fields')}}.</small></p>
                {!! Form::open(['route' => 'money-transfers.store', 'method' => 'post']) !!}
                  <div class="row">
                      <div class="col-md-6 form-group">
                          <label class="font-weight-bold"> {{__('db.From Account')}} *</label>
                          <select class="form-control" name="from_account_id" required>
                          <option value="">Select source account...</option>
                          @foreach($lims_account_list as $account)
                              <option value="{{$account->id}}">{{$account->name}} [{{$account->account_no}}]</option>
                          @endforeach
                          </select>
                      </div>
                      <div class="col-md-6 form-group">
                          <label class="font-weight-bold"> {{__('db.To Account')}} *</label>
                          <select class="form-control" name="to_account_id" required>
                          <option value="">Select destination account...</option>
                          @foreach($lims_account_list as $account)
                              <option value="{{$account->id}}">{{$account->name}} [{{$account->account_no}}]</option>
                          @endforeach
                          </select>
                      </div>

                      <div class="col-md-12 form-group">
                          <label class="font-weight-bold">{{__('db.Amount')}} *</label>
                          <input type="number" name="amount" class="form-control" step="any" required>
                      </div>
                  </div>
                  <div class="form-group text-right">
                      <button type="submit" class="btn btn-primary btn-action-pill" style="background:#0f172a; border-color:#0f172a;">{{__('db.submit')}}</button>
                  </div>
                {{ Form::close() }}
            </div>
        </div>
    </div>
</div>

<!-- Edit Money Transfer modal -->
<div id="edit-money-transfer-modal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true" class="modal fade text-left">
    <div role="document" class="modal-dialog">
        <div class="modal-content" style="border-radius:8px; border:1px solid #cbd5e1; overflow:hidden;">
            <div class="modal-header" style="background:#0f172a; color:#fff;">
                <h5 id="exampleModalLabel" class="modal-title font-weight-bold text-white">{{__('db.Update Money Transfer')}}</h5>
                <button type="button" data-dismiss="modal" aria-label="Close" class="close text-white"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <p class="italic text-muted"><small>{{__('db.The field labels marked with * are required input fields')}}.</small></p>
                {!! Form::open(['route' => ['money-transfers.update', 1], 'method' => 'put']) !!}
                  <div class="row">
                        <input type="hidden" name="id">
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold">{{__('db.date')}}</label>
                            <input type="text" name="created_at" class="form-control date" placeholder="{{__('db.Choose date')}}"/>
                        </div>

                      <div class="col-md-6 form-group">
                          <label class="font-weight-bold"> {{__('db.From Account')}} *</label>
                          <select class="form-control" name="from_account_id" required>
                          @foreach($lims_account_list as $account)
                              <option value="{{$account->id}}">{{$account->name}} [{{$account->account_no}}]</option>
                          @endforeach
                          </select>
                      </div>
                      <div class="col-md-6 form-group">
                          <label class="font-weight-bold"> {{__('db.To Account')}} *</label>
                          <select class="form-control" name="to_account_id" required>
                          @foreach($lims_account_list as $account)
                              <option value="{{$account->id}}">{{$account->name}} [{{$account->account_no}}]</option>
                          @endforeach
                          </select>
                      </div>

                      <div class="col-md-6 form-group">
                          <label class="font-weight-bold">{{__('db.Amount')}} *</label>
                          <input type="number" name="amount" class="form-control" step="any" required>
                      </div>
                  </div>
                  <div class="form-group text-right">
                      <button type="submit" class="btn btn-primary btn-action-pill" style="background:#0f172a; border-color:#0f172a;">{{__('db.submit')}}</button>
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
    $("ul#account #money-transfer-menu").addClass("active");

    var money_transfer_id = [];
    var user_verified = <?php echo json_encode(env('USER_VERIFIED')) ?>;

    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

    $(document).on('click', '#edit-btn', function() {
        $("#edit-money-transfer-modal input[name='created_at']").val($(this).data('created_at'));
        $("#edit-money-transfer-modal select[name='from_account_id']").val($(this).data('from_id'));
        $("#edit-money-transfer-modal select[name='to_account_id']").val($(this).data('to_id'));
        $("#edit-money-transfer-modal input[name='id']").val($(this).data('id'));
        $("#edit-money-transfer-modal input[name='amount']").val($(this).data('amount'));
    });

    function confirmDelete() {
        if (confirm("Are you sure want to delete?")) {
            return true;
        }
        return false;
    }

    $('#money-transfer-table').DataTable( {
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
                'targets': [0, 6]
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
                text: '<i title="delete" class="dripicons-cross text-danger"></i>',
                className: 'buttons-delete',
                action: function ( e, dt, node, config ) {
                    if(user_verified == '1') {
                        money_transfer_id.length = 0;
                        $(':checkbox:checked').each(function(i){
                            if(i){
                                money_transfer_id[i-1] = $(this).closest('tr').data('id');
                            }
                        });
                        if(money_transfer_id.length && confirm("Are you sure want to delete?")) {
                            $.ajax({
                                type:'POST',
                                url:'money_transfers/deletebyselection',
                                data:{
                                    money_transferIdArray: money_transfer_id
                                },
                                success:function(data){
                                    alert(data);
                                }
                            });
                            dt.rows({ page: 'current', selected: true }).remove().draw(false);
                        }
                        else if(!money_transfer_id.length)
                            alert('No money_transfer is selected!');
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

</script>
@endpush
