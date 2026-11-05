@extends('backend.layout.main') @section('content')


<x-success-message key="message" />
<x-error-message key="not_permitted" />
<x-error-message key="account_no" />

@php
    $linkedBooks = config('commercial.enabled');
    $context = request()->attributes->get(\App\Services\Platform\CompanyContext::class);
    $canManageAccounts = $context ? app(\App\Services\Platform\CompanyContextResolver::class)->canManageFinancialYears(auth()->id(), $context->companyId) : true;
    $totalAccountsCount = count($lims_account_all);
    $totalBalanceSum = $lims_account_all->sum('balance');
    $defaultAccount = $lims_account_all->firstWhere('is_default', 1);
@endphp

<section class="account-workspace-view">
    <x-accounting-command-bar active="accounts" title="Cash & Bank Accounts" purpose="Choose these accounts when receiving or paying money. Linked balances and statements come from the same accounting ledger.">
        @if($canManageAccounts)
            @if($linkedBooks)
            <a class="btn btn-primary" href="{{ route('accounting.coa', ['create' => 'bank']) }}"><i class="dripicons-plus"></i> Add cash / bank account</a>
            @else
            <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#account-modal"><i class="dripicons-plus"></i> {{ __('db.Add Account') }}</button>
            @endif
        @endif
    </x-accounting-command-bar>
    @if($errors->any())<div class="alert alert-danger" role="alert"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <p class="account-setup-hint">Payment method describes how money moves. Select IOB, SBI or Cash to identify the actual account. Transfer between your accounts using Money Transfer.</p>
    <!-- KPI Summary Strip -->
    <div class="kpi-summary-strip">
        <div class="kpi-card">
            <div>
                <div class="kpi-label">TOTAL ACCOUNTS</div>
                <div class="kpi-number">{{ $totalAccountsCount }}</div>
            </div>
            <div class="z-icon-tile z-tone-primary">
                <i class="dripicons-wallet u-fs-18px"></i>
            </div>
        </div>
        <div class="kpi-card">
            <div>
                <div class="kpi-label">TOTAL LEDGER BALANCE</div>
                <div class="kpi-number u-c-success">₹ {{ number_format($totalBalanceSum, 2) }}</div>
            </div>
            <div class="z-icon-tile z-tone-success">
                <i class="dripicons-coin u-fs-18px"></i>
            </div>
        </div>
        <div class="kpi-card">
            <div>
                <div class="kpi-label">PRIMARY DEFAULT ACCOUNT</div>
                <div class="kpi-number u-fs-14px u-fw-700 u-c-text" id="primary-default-account">
                    {{ $defaultAccount ? $defaultAccount->name : 'None configured' }}
                </div>
            </div>
            <div class="z-icon-tile z-tone-info">
                <i class="dripicons-checkmark u-fs-18px"></i>
            </div>
        </div>
    </div>

    <p id="account-default-status" role="status" aria-live="polite" class="small text-muted"></p>
    <!-- Main Table Container -->
    <div class="desk-card-wrapper">
        <div class="table-responsive">
            <table id="account-table" class="table u-w-100pct">
                <caption class="sr-only">Cash and bank settlement accounts, balances, ledger links and statement actions</caption>
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
                        <td class="font-weight-bold font-monospace text-primary">{{ $account->account_no ?: '—' }}</td>
                        <td><span class="font-weight-bold u-c-text">{{ $account->name }}</span>@if($linkedBooks)<small class="d-block text-muted">{{ $account->ledger ? $account->ledger->code.' · '.ucfirst($account->ledger->control_type) : 'Ledger link required' }}</small>@endif</td>
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
                            <input type="radio" name="default_account" class="default" value="{{ $account->id }}" data-id="{{ $account->id }}" data-name="{{ $account->name }}" aria-label="Use {{ $account->name }} as the default account" @checked($account->is_default) @disabled(!$canManageAccounts)>
                        </td>
                        <td><small class="text-muted">{{ $account->note }}</small></td>
                        <td>
                            @if($linkedBooks)
                                <div class="account-row-actions">
                                @if($account->ledger)
                                    <a href="{{ route('accounting.general-ledger', ['account_id' => $account->chart_of_account_id]) }}" class="btn btn-outline-primary account-text-action" aria-label="Statement for {{ $account->name }}"><i class="dripicons-document"></i> Statement</a>
                                    @if($canManageAccounts)<a href="{{ route('accounting.coa', ['edit' => $account->chart_of_account_id]) }}" class="btn btn-outline-secondary account-text-action" aria-label="Edit {{ $account->name }} in Chart of Accounts"><i class="dripicons-pencil"></i> Edit account</a>@endif
                                @elseif($canManageAccounts)
                                    <a href="{{ route('accounting.vouchers') }}#settlement-account-links" class="btn btn-outline-secondary account-text-action">Link ledger</a>
                                @else
                                    <span class="text-muted small">Ask an administrator to link this account.</span>
                                @endif
                                </div>
                            @else
                            <div class="btn-group">
                                <button type="button" class="btn btn-default btn-sm dropdown-toggle account-text-action" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">{{__('db.action')}} <span class="caret"></span><span class="sr-only">for {{ $account->name }}</span></button>
                                <ul class="dropdown-menu edit-options dropdown-menu-right dropdown-default" role="menu">
                                    <li><button type="button" data-id="{{$account->id}}" data-account_no="{{$account->account_no}}" data-name="{{$account->name}}" data-initial_balance="{{$account->initial_balance}}" data-note="{{$account->note}}" class="edit-btn btn btn-link" data-toggle="modal" data-target="#editModal"><i class="dripicons-document-edit"></i> {{__('db.edit')}}</button></li>
                                    <li><button type="button" class="btn btn-link account-statement-button" data-id="{{ $account->id }}" data-name="{{ $account->name }}" data-toggle="modal" data-target="#accountStatementModal"><i class="dripicons-document"></i> {{__('db.Statement')}}</button></li>
                                    <li class="divider"></li>
                                    {{ Form::open(['route' => ['accounts.destroy', $account->id], 'method' => 'DELETE']) }}
                                    <li><button type="submit" class="btn btn-link" onclick="return confirmDelete()"><i class="dripicons-trash"></i> {{__('db.delete')}}</button></li>
                                    {{ Form::close() }}
                                </ul>
                            </div>
                            @endif
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

<div id="editModal" tabindex="-1" role="dialog" aria-labelledby="account-edit-title" aria-hidden="true" class="modal fade text-left">
    <div role="document" class="modal-dialog">
        <div class="modal-content u-r-8px u-b-1px-solid-border-strong u-ov-hidden">
            <div class="modal-header u-bg-ink u-c-xffffff">
                <h5 id="account-edit-title" class="modal-title font-weight-bold text-white">{{__('db.Update Account')}}</h5>
                <button type="button" data-dismiss="modal" aria-label="Close" class="close text-white"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
              <p class="italic text-muted"><small>{{__('db.The field labels marked with * are required input fields')}}.</small></p>
                {!! Form::open(['route' => ['accounts.update', 1], 'method' => 'put']) !!}
                    <div class="form-group">
                        <label for="legacy-account-account_no" class="font-weight-bold">{{__('db.Account')}} No *</label>
                        <input type="text" id="legacy-account-account_no" name="account_no" required class="form-control">
                        <input type="hidden" name="account_id">
                    </div>
                    <div class="form-group">
                        <label for="legacy-account-name" class="font-weight-bold">{{__('db.name')}} *</label>
                        <input type="text" id="legacy-account-name" name="name" required class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="legacy-account-initial_balance" class="font-weight-bold">{{__('db.Initial Balance')}}</label>
                        <input type="number" id="legacy-account-initial_balance" name="initial_balance" step="any" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="legacy-account-note" class="font-weight-bold">{{__('db.Note')}}</label>
                        <textarea id="legacy-account-note" name="note" rows="3" class="form-control"></textarea>
                    </div>
                    <div class="form-group text-right">
                        <button type="submit" class="btn btn-primary btn-action-pill u-bg-ink u-bc-ink">{{__('db.update')}}</button>
                    </div>
                {{ Form::close() }}
            </div>
        </div>
    </div>
</div>

@unless($linkedBooks)
<div class="modal fade" id="accountStatementModal" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="account-statement-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
        <div class="modal-header"><h5 id="account-statement-title">Account statement</h5><button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button></div>
        <form method="POST" action="{{ route('accounts.statement') }}">@csrf
            <div class="modal-body">
                <input type="hidden" name="account_id" id="statement-account-id">
                <p id="statement-account-name" class="font-weight-bold"></p>
                <div class="form-group"><label for="statement-start">From date</label><input type="date" id="statement-start" name="start_date" class="form-control" value="{{ now()->startOfYear()->toDateString() }}" required></div>
                <div class="form-group"><label for="statement-end">To date</label><input type="date" id="statement-end" name="end_date" class="form-control" value="{{ now()->toDateString() }}" required></div>
                <div class="form-group"><label for="statement-type">Transactions</label><select id="statement-type" name="type" class="form-control"><option value="2">All transactions</option><option value="0">Credits</option><option value="1">Debits</option></select></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">View statement</button></div>
        </form>
    </div></div>
</div>
@endunless
@endsection

@push('scripts')
<script type="text/javascript">

    $("ul#account").siblings('a').attr('aria-expanded','true');
    $("ul#account").addClass("show");
    $("ul#account #account-list-menu").addClass("active");

    $('.account-statement-button').on('click', function () { $('#statement-account-id').val(this.dataset.id); $('#statement-account-name').text(this.dataset.name); });

    $('.edit-btn').on('click', function() {
        $("#editModal input[name='account_no']").val( $(this).data('account_no') );
        $("#editModal input[name='name']").val( $(this).data('name') );
        $("#editModal input[name='initial_balance']").val( $(this).data('initial_balance') );
        $("#editModal input[name='account_id']").val( $(this).data('id') );
        $("#editModal textarea[name='note']").val( $(this).data('note') );
    });

    var savedDefault = $('.default:checked').val();
    $('.default').on('change', function() {
        var radio = this;
        if (!radio.checked) return;
        $('.default').prop('disabled', true);
        $('#account-default-status').text('Saving the default account…');
        $.post(@json(url('make-default')) + '/' + encodeURIComponent(radio.dataset.id), {_token: @json(csrf_token())})
            .done(function () { savedDefault = radio.value; $('#primary-default-account').text(radio.dataset.name); $('#account-default-status').text('Default account saved. Select the actual account on each payment.'); })
            .fail(function () { $('.default').each(function () { this.checked = this.value === savedDefault; }); $('#account-default-status').text('The default account could not be saved. Please try again.'); })
            .always(function () { $('.default').prop('disabled', false); });
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

@push('css')
    <link rel="stylesheet" href="<?php echo asset('css/commercial-entry.css') . '?v=' . (file_exists(public_path('css/commercial-entry.css')) ? filemtime(public_path('css/commercial-entry.css')) : time()) ?>" type="text/css">
    <link rel="stylesheet" href="<?php echo asset('css/commercial-workspace.css') . '?v=' . (file_exists(public_path('css/commercial-workspace.css')) ? filemtime(public_path('css/commercial-workspace.css')) : time()) ?>" type="text/css">
    <link rel="stylesheet" href="<?php echo asset('css/zolo-erp-neo.css') . '?v=' . (file_exists(public_path('css/zolo-erp-neo.css')) ? filemtime(public_path('css/zolo-erp-neo.css')) : time()) ?>" type="text/css">
    <link rel="stylesheet" href="{{ versioned_asset('css/pages/account-index.css') }}">
@endpush
