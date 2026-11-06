@extends('backend.layout.main')
@section('content')

<x-error-message key="not_permitted" />

@php
    $general_setting = \Illuminate\Support\Facades\DB::table('general_settings')->latest()->first();
    $currency = \App\Models\Currency::find($general_setting->currency ?? 1);
    $currencySymbol = $currency->symbol ?? '₹';
    $customerName = 'All Customers';
    $customer = $ledgerData['party'] ?? null;
    if ($customer) {
        $customerName = $customer->name . ($customer->company_name ? ' (' . $customer->company_name . ')' : '');
    }
    $openingBal = (float)($ledgerData['opening_balance'] ?? 0);
    $totalDebits = (float)($ledgerData['total_debit'] ?? 0);
    $totalCredits = (float)($ledgerData['total_credit'] ?? 0);
    $closingBal = (float)($ledgerData['closing_balance'] ?? 0);
@endphp


<section class="report-workspace-view">
    <!-- Top Command Bar -->
    <div class="comm-command-bar d-print-none">
        <div class="comm-title-group">
            <h1><i class="dripicons-user-group text-primary"></i> {{ __('db.Customer Report') }} Command Center</h1>
            <span class="comm-title-badge"><i class="dripicons-tag"></i> 360° Account Receivable &amp; Single-View Ledger</span>
        </div>
        <ul class="comm-nav-pills">
            <li><a class="nav-link active" href="{{ url('report/customer_report') }}"><i class="dripicons-user-group"></i> Customer Ledger</a></li>
            <li><a class="nav-link" href="{{ route('report.customerDueByDate') }}"><i class="dripicons-warning"></i> Customer Due</a></li>
            <li><a class="nav-link" href="{{ url('report/customer_group') }}"><i class="dripicons-network"></i> Customer Group</a></li>
            <li><a class="nav-link" href="{{ route('customer.index') }}"><i class="dripicons-user"></i> Customer Master</a></li>
            <li><a class="nav-link" href="{{ route('report.outstandingDues') }}"><i class="dripicons-pulse"></i> Outstanding Dues</a></li>
        </ul>
        <div class="comm-actions">
            <div class="btn-group">
                <button type="button" class="btn btn-secondary btn-action-pill" onclick="printReport('all')">
                    <i class="dripicons-print"></i> Print Complete Ledger
                </button>
                <button type="button" class="btn btn-secondary btn-action-pill dropdown-toggle dropdown-toggle-split" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    <span class="sr-only">Toggle Dropdown</span>
                </button>
                <div class="dropdown-menu dropdown-menu-right">
                    <a class="dropdown-item" href="javascript:void(0)" onclick="printReport('all')"><i class="dripicons-document mr-1"></i> Print Complete Ledger</a>
                    <a class="dropdown-item" href="javascript:void(0)" onclick="printReport('current')"><i class="dripicons-view-list mr-1"></i> Print Current Filtered View</a>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item" href="javascript:void(0)" onclick="printReport('sale')"><i class="dripicons-cart mr-1"></i> Print Invoices Only</a>
                    <a class="dropdown-item" href="javascript:void(0)" onclick="printReport('payment')"><i class="dripicons-wallet mr-1"></i> Print Payments Only</a>
                    <a class="dropdown-item" href="javascript:void(0)" onclick="printReport('return')"><i class="dripicons-return mr-1"></i> Print Returns Only</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Print Party Details Card (Only Visible When Printing) -->
    @if($customer)
    <div class="erp-print-party-card">
        <div>
            <div class="ledger-print-label">Customer / Debtor Details:</div>
            <div class="ledger-party-name">{{ $customer->name }}</div>
            @if(!empty($customer->company_name))
                <div class="ledger-party-company">{{ $customer->company_name }}</div>
            @endif
            <div class="ledger-print-detail ledger-print-contact">
                Phone: {{ $customer->phone_number }} | Email: {{ $customer->email ?? '—' }}
            </div>
            <div class="ledger-print-detail">
                GSTIN: <strong>{{ $customer->tax_no ?? 'Unregistered' }}</strong>
            </div>
        </div>
        <div class="ledger-print-balances">
            <div class="ledger-print-label">Opening Balance:</div>
            <div class="ledger-opening-balance">
                {{ $currencySymbol }} {{ number_format(abs($openingBal), 2) }} {{ $openingBal >= 0 ? 'Dr' : 'Cr' }}
            </div>
            <div class="ledger-print-label ledger-closing-label">Current Outstanding:</div>
            <div class="ledger-closing-balance {{ $closingBal > 0 ? 'ledger-balance-due' : 'ledger-balance-settled' }}">
                {{ $currencySymbol }} {{ number_format(abs($closingBal), 2) }} {{ $closingBal >= 0 ? 'Dr' : 'Cr' }}
            </div>
        </div>
    </div>
    @endif

    <!-- KPI Summary Strip -->
    <div class="kpi-summary-strip d-print-none">
        <div class="kpi-card">
            <div>
                <div class="kpi-label">REPORT TIMEFRAME</div>
                <div class="kpi-number u-fs-13px u-fw-600 u-c-text-2">
                    {{ date('d M Y', strtotime($start_date)) }} — {{ date('d M Y', strtotime($end_date)) }}
                </div>
            </div>
            <div class="z-icon-tile z-tone-primary">
                <i class="dripicons-calendar u-fs-18px"></i>
            </div>
        </div>
        <div class="kpi-card">
            <div>
                <div class="kpi-label">OPENING BALANCE</div>
                <div class="kpi-number u-fs-15px u-fw-700">
                    {{ $currencySymbol }} {{ number_format(abs($openingBal), 2) }}
                    <span class="badge badge-sm {{ $openingBal >= 0 ? 'badge-secondary' : 'badge-success' }}">{{ $openingBal >= 0 ? 'Dr' : 'Cr' }}</span>
                </div>
            </div>
            <div class="z-icon-tile z-tone-info">
                <i class="dripicons-wallet u-fs-18px"></i>
            </div>
        </div>
        <div class="kpi-card">
            <div>
                <div class="kpi-label">TOTAL INVOICED (DEBIT)</div>
                <div class="kpi-number u-fs-15px u-fw-700 text-primary">
                    {{ $currencySymbol }} {{ number_format($totalDebits, 2) }}
                </div>
            </div>
            <div class="z-icon-tile z-tone-warning">
                <i class="dripicons-cart u-fs-18px"></i>
            </div>
        </div>
        <div class="kpi-card">
            <div>
                <div class="kpi-label">TOTAL CREDITS (PAID/RET)</div>
                <div class="kpi-number u-fs-15px u-fw-700 text-success">
                    {{ $currencySymbol }} {{ number_format($totalCredits, 2) }}
                </div>
            </div>
            <div class="z-icon-tile z-tone-success">
                <i class="dripicons-checkmark u-fs-18px"></i>
            </div>
        </div>
        <div class="kpi-card {{ $closingBal > 0 ? 'ledger-kpi-due' : 'ledger-kpi-settled' }}">
            <div>
                <div class="kpi-label">NET CLOSING BALANCE</div>
                <div class="kpi-number u-fs-16px u-fw-800 {{ $closingBal > 0 ? 'ledger-balance-due' : 'ledger-balance-settled' }}">
                    {{ $currencySymbol }} {{ number_format(abs($closingBal), 2) }}
                    <span class="badge badge-sm {{ $closingBal >= 0 ? 'badge-danger' : 'badge-success' }}">{{ $closingBal >= 0 ? 'Dr' : 'Cr' }}</span>
                </div>
            </div>
            <div class="z-icon-tile {{ $closingBal > 0 ? 'z-tone-danger' : 'z-tone-success' }}">
                <i class="dripicons-scale u-fs-18px"></i>
            </div>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="desk-filter-card d-print-none">
        {!! Form::open(['route' => 'report.customer', 'method' => 'post', 'id' => 'ledgerFilterForm', 'class' => 'form-inline d-flex align-items-center flex-wrap gap-2']) !!}
            <div class="form-group mr-2 mb-1">
                <label class="mr-2 font-weight-bold text-muted small"><i class="dripicons-calendar"></i> {{ __('db.Choose Your Date') }}</label>
                <div class="input-group">
                    <input type="text" class="daterangepicker-field form-control form-control-sm u-h-30px u-fs-12px u-w-210px"
                        value="{{ $start_date }} To {{ $end_date }}" required />
                    <input type="hidden" name="start_date" value="{{ $start_date }}" />
                    <input type="hidden" name="end_date" value="{{ $end_date }}" />
                </div>
            </div>
            <div class="form-group mr-2 mb-1">
                <label class="mr-2 font-weight-bold text-muted small"><i class="dripicons-user"></i> {{ __('db.Choose Customer') }}</label>
                <select id="customer_id" name="customer_id" class="form-control form-control-sm u-h-30px u-fs-12px u-minw-220px selectpicker" data-live-search="true">
                    @foreach ($lims_customer_list as $c)
                        @if ($c->type != 'walkin')
                            <option value="{{ $c->id }}" @selected($c->id == $customer_id)>
                                {{ $c->name }} ({{ $c->phone_number }})
                            </option>
                        @endif
                    @endforeach
                </select>
            </div>
            <div class="form-group mr-2 mb-1">
                <label class="mr-2 font-weight-bold text-muted small"><i class="dripicons-filter"></i> Transaction Type</label>
                <select id="transaction_type" name="transaction_type" class="form-control form-control-sm u-h-30px u-fs-12px">
                    <option value="all" @selected(($transaction_type ?? 'all') == 'all')>All Transactions (Complete Ledger)</option>
                    <option value="sale" @selected(($transaction_type ?? '') == 'sale')>Sales Invoices Only</option>
                    <option value="payment" @selected(($transaction_type ?? '') == 'payment')>Payments Only</option>
                    <option value="return" @selected(($transaction_type ?? '') == 'return')>Sales Returns Only</option>
                    <option value="quotation" @selected(($transaction_type ?? '') == 'quotation')>Quotations Only</option>
                </select>
            </div>
            <div class="form-group mb-1">
                <button class="btn btn-primary btn-sm btn-action-pill u-bg-ink u-bc-ink mr-1" type="submit">
                    <i class="fa fa-filter"></i> {{ __('db.submit') }}
                </button>
                <a href="{{ route('report.customer', ['customer_id' => $customer_id]) }}" class="btn btn-outline-secondary btn-sm btn-action-pill">
                    <i class="dripicons-clockwise"></i> Reset
                </a>
            </div>
        {!! Form::close() !!}
    </div>

    <!-- Quick In-Table Filter Pills -->
    <div class="d-flex justify-content-between align-items-center mb-2 px-1 quick-filter-bar d-print-none">
        <div class="quick-filter-pills">
            <span class="small font-weight-bold text-muted mr-1">Quick View:</span>
            <button type="button" class="btn-filter-pill active" data-filter="all">
                All ({{ $ledgerData['items']->count() }})
            </button>
            <button type="button" class="btn-filter-pill" data-filter="sale">
                <i class="dripicons-cart mr-1"></i> Invoices ({{ $ledgerData['items']->where('type', 'sale')->count() }})
            </button>
            <button type="button" class="btn-filter-pill" data-filter="payment">
                <i class="dripicons-wallet mr-1"></i> Payments ({{ $ledgerData['items']->where('type', 'payment')->count() + $ledgerData['items']->where('type', 'deposit')->count() }})
            </button>
            <button type="button" class="btn-filter-pill" data-filter="return">
                <i class="dripicons-return mr-1"></i> Returns ({{ $ledgerData['items']->where('type', 'return')->count() }})
            </button>
            <button type="button" class="btn-filter-pill" data-filter="quotation">
                <i class="dripicons-document mr-1"></i> Quotations ({{ $ledgerData['items']->where('type', 'quotation')->count() }})
            </button>
        </div>
        <div class="text-muted small">
            <span>Showing statement for: <strong>{{ $customerName }}</strong></span>
        </div>
    </div>

    <!-- Unified Single-View Ledger Table -->
    <div class="desk-card-wrapper">
        <div class="table-responsive">
            <table id="customer-ledger-table" class="table ledger-table u-w-100pct" data-print-all-columns>
                <thead>
                    <tr>
                        <th class="ledger-number-column not-exported-col">#</th>
                        <th class="u-w-95px">{{ __('db.date') }}</th>
                        <th class="u-w-120px">{{ __('db.Type') }}</th>
                        <th class="u-w-130px">{{ __('db.reference') }} No</th>
                        <th>{{ __('db.Warehouse') }}</th>
                        <th>{{ __('db.Description') }} / Particulars</th>
                        <th class="u-w-110px text-right">Debit ({{ $currencySymbol }})</th>
                        <th class="u-w-110px text-right">Credit ({{ $currencySymbol }})</th>
                        <th class="u-w-130px text-right">Running Balance ({{ $currencySymbol }})</th>
                        <th class="ledger-status-column not-exported-col">{{ __('db.status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Opening Balance Row -->
                    <tr class="ledger-opening-row" data-type="opening">
                        <td class="text-muted font-italic not-exported-col">—</td>
                        <td>{{ date('d/m/Y', strtotime($start_date)) }}</td>
                        <td><span class="badge-type-pill badge-type-opening"><i class="dripicons-wallet"></i> Opening Balance</span></td>
                        <td class="font-italic text-muted">—</td>
                        <td class="text-muted">—</td>
                        <td><strong>Opening Balance Brought Forward</strong></td>
                        <td class="text-right font-weight-bold">{{ $openingBal > 0 ? number_format($openingBal, 2) : '0.00' }}</td>
                        <td class="text-right font-weight-bold">{{ $openingBal < 0 ? number_format(abs($openingBal), 2) : '0.00' }}</td>
                        <td class="text-right font-weight-bold">
                            {{ number_format(abs($openingBal), 2) }}
                            <span class="badge badge-sm {{ $openingBal >= 0 ? 'badge-secondary' : 'badge-success' }}">{{ $openingBal >= 0 ? 'Dr' : 'Cr' }}</span>
                        </td>
                        <td class="not-exported-col"><span class="badge badge-light border">B/F</span></td>
                    </tr>

                    @php
                        $calcRunning = $openingBal;
                        $calcTotalDebit = 0.0;
                        $calcTotalCredit = 0.0;
                    @endphp

                    @foreach($ledgerData['items'] as $index => $item)
                        @php
                            $calcTotalDebit += $item->debit;
                            $calcTotalCredit += $item->credit;
                            $calcRunning += ($item->debit - $item->credit);
                        @endphp
                        <tr data-type="{{ $item->type }}">
                            <td class="not-exported-col">{{ $index + 1 }}</td>
                            <td>{{ date('d/m/Y', strtotime($item->date)) }}</td>
                            <td>
                                @if($item->type === 'sale')
                                    <span class="badge-type-pill badge-type-sale"><i class="dripicons-cart"></i> Sale Invoice</span>
                                @elseif($item->type === 'payment')
                                    <span class="badge-type-pill badge-type-payment"><i class="dripicons-checkmark"></i> Payment Recv</span>
                                @elseif($item->type === 'return')
                                    <span class="badge-type-pill badge-type-return"><i class="dripicons-return"></i> Sales Return</span>
                                @elseif($item->type === 'deposit')
                                    <span class="badge-type-pill badge-type-deposit"><i class="dripicons-wallet"></i> Adv Deposit</span>
                                @elseif($item->type === 'quotation')
                                    <span class="badge-type-pill badge-type-quotation"><i class="dripicons-document"></i> Quotation</span>
                                @else
                                    <span class="badge-type-pill badge-type-opening">{{ $item->type_label }}</span>
                                @endif
                            </td>
                            <td class="font-weight-bold font-monospace text-primary">
                                @if($item->type === 'sale')
                                    <a href="{{ route('sales.index') }}?reference={{ $item->reference_no }}" target="_blank">{{ $item->reference_no }}</a>
                                @else
                                    {{ $item->reference_no }}
                                @endif
                            </td>
                            <td>{{ $item->warehouse }}</td>
                            <td>{{ $item->description }}</td>
                            <td class="text-right font-weight-bold {{ $item->debit > 0 ? 'text-dark' : 'text-muted' }}">
                                {{ $item->debit > 0 ? number_format($item->debit, 2) : '—' }}
                            </td>
                            <td class="text-right font-weight-bold {{ $item->credit > 0 ? 'text-success' : 'text-muted' }}">
                                {{ $item->credit > 0 ? number_format($item->credit, 2) : '—' }}
                            </td>
                            <td class="text-right font-weight-bold">
                                {{ number_format(abs($calcRunning), 2) }}
                                <span class="badge badge-sm {{ $calcRunning >= 0 ? 'badge-danger' : 'badge-success' }}">{{ $calcRunning >= 0 ? 'Dr' : 'Cr' }}</span>
                            </td>
                            <td class="not-exported-col">
                                <span class="badge badge-light border text-capitalize">{{ $item->status }}</span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <th class="not-exported-col"></th>
                        <th colspan="5" class="text-right">Total Activity &amp; Closing Balance:</th>
                        <th class="text-right text-primary" id="footerTotalDebit">{{ number_format($totalDebits, 2) }}</th>
                        <th class="text-right text-success" id="footerTotalCredit">{{ number_format($totalCredits, 2) }}</th>
                        <th class="text-right" id="footerClosingBal">
                            {{ number_format(abs($closingBal), 2) }}
                            <span class="badge badge-sm {{ $closingBal >= 0 ? 'badge-danger' : 'badge-success' }}">{{ $closingBal >= 0 ? 'Dr' : 'Cr' }}</span>
                        </th>
                        <th class="not-exported-col"></th>
                    </tr>
                </tfoot>
            </table>
            @if($ledgerData['items']->isEmpty())
                <p class="text-center py-4 mb-0 text-muted">
                    <i class="dripicons-information mr-1"></i> No transactions found for the selected timeframe.
                </p>
            @endif
        </div>
    </div>

    <!-- Print Footer Signatures (Only Visible When Printing) -->
    <div class="erp-print-signatures">
        <div class="erp-print-sig-box">
            Customer Confirmation Signature
        </div>
        <div class="erp-print-sig-box">
            For {{ $activeCompany->trade_name ?? $general_setting->company_name ?? 'zoloERP Pro' }}<br>
            <strong>Authorized Signatory</strong>
        </div>
    </div>
</section>

@endsection

@push('scripts')
<script type="text/javascript">
    $("ul#report").siblings('a').attr('aria-expanded','true');
    $("ul#report").addClass("show");
    $("ul#report #customer-report-menu").addClass("active");

    var table = null;

    $(document).ready(function() {
        $(".daterangepicker-field").daterangepicker({
            parentElement: '.daterangepicker-container',
            @if( Config::get('app.locale') == 'ar' || ($general_setting->is_rtl ?? false))
            orientation: 'left',
            @endif
            callback: function(startDate, endDate, period){
                var start_date = startDate.format('YYYY-MM-DD');
                var end_date = endDate.format('YYYY-MM-DD');
                var title = start_date + ' To ' + end_date;
                $(this).val(title);
                $('input[name="start_date"]').val(start_date);
                $('input[name="end_date"]').val(end_date);
            }
        });

        // Initialize DataTable on the unified ledger table
        table = $('#customer-ledger-table').DataTable({
            "order": [],
            "paging": true,
            "pageLength": 25,
            "lengthMenu": [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
            "language": {
                "lengthMenu": '_MENU_ {{__("db.records per page")}}',
                "info": '<small>{{__("db.Showing")}} _START_ - _END_ (_TOTAL_)</small>',
                "search": '{{__("db.Search")}}:',
                "paginate": {
                    "previous": '<i class="dripicons-chevron-left"></i>',
                    "next": '<i class="dripicons-chevron-right"></i>'
                }
            },
            "columnDefs": [
                { "orderable": false, "targets": [0, 9] }
            ]
        });

        // Quick filter pill handling
        $('.btn-filter-pill').on('click', function() {
            var filter = $(this).data('filter');
            $('.btn-filter-pill').removeClass('active');
            $(this).addClass('active');

            applyFilter(filter);
        });

        // Trigger transaction_type from backend if specified
        var initialType = "{{ $transaction_type ?? 'all' }}";
        if(initialType !== 'all') {
            $('.btn-filter-pill[data-filter="' + initialType + '"]').trigger('click');
        }
    });

    function applyFilter(filter) {
        if (!table) return;

        // Cleanly remove any previously registered ledger filter
        $.fn.dataTable.ext.search = $.fn.dataTable.ext.search.filter(function(fn) {
            return !fn._isCustomerLedgerFilter;
        });

        if (filter !== 'all') {
            var filterFn = function(settings, data, dataIndex) {
                if (settings.nTable.id !== 'customer-ledger-table') return true;
                var row = table.row(dataIndex).node();
                if (!row) return true;
                var type = $(row).data('type');
                if (type === 'opening') return true; // Opening balance always stays in statement
                if (filter === 'payment') {
                    return type === 'payment' || type === 'deposit';
                }
                return type === filter;
            };
            filterFn._isCustomerLedgerFilter = true;
            $.fn.dataTable.ext.search.push(filterFn);
        }

        table.draw();
    }

    function printReport(mode) {
        var reportTitle = 'Customer Account Statement / Ledger';

        if (mode === 'all') {
            reportTitle = 'Complete Customer Account Statement / Ledger';
            applyFilter('all');
            $('.btn-filter-pill').removeClass('active');
            $('.btn-filter-pill[data-filter="all"]').addClass('active');
        } else if (mode === 'current') {
            var activeFilter = $('.btn-filter-pill.active').data('filter') || 'all';
            reportTitle = activeFilter === 'all'
                ? 'Complete Customer Account Statement / Ledger'
                : 'Customer ' + activeFilter.charAt(0).toUpperCase() + activeFilter.slice(1) + ' Statement';
        } else {
            applyFilter(mode);
            $('.btn-filter-pill').removeClass('active');
            $('.btn-filter-pill[data-filter="' + mode + '"]').addClass('active');
            reportTitle = 'Customer ' + mode.charAt(0).toUpperCase() + mode.slice(1) + ' Statement';
        }

        $('[data-erp-print-title]').text(reportTitle);

        if (table) {
            var origLen = table.page.len();
            table.page.len(-1).draw();

            setTimeout(function() {
                window.print();
                setTimeout(function() {
                    table.page.len(origLen).draw();
                }, 500);
            }, 300);
        } else {
            window.print();
        }
    }
</script>
@endpush

@push('css')
    <link rel="stylesheet" href="<?php echo asset('css/commercial-entry.css') . '?v=' . (file_exists(public_path('css/commercial-entry.css')) ? filemtime(public_path('css/commercial-entry.css')) : time()) ?>" type="text/css">
    <link rel="stylesheet" href="<?php echo asset('css/commercial-workspace.css') . '?v=' . (file_exists(public_path('css/commercial-workspace.css')) ? filemtime(public_path('css/commercial-workspace.css')) : time()) ?>" type="text/css">
    <link rel="stylesheet" href="<?php echo asset('css/zolo-erp-neo.css') . '?v=' . (file_exists(public_path('css/zolo-erp-neo.css')) ? filemtime(public_path('css/zolo-erp-neo.css')) : time()) ?>" type="text/css">
    <link rel="stylesheet" href="{{ versioned_asset('css/pages/shared-report-biller-report.css') }}">
    <link rel="stylesheet" href="{{ versioned_asset('css/pages/party-ledger.css') }}">
@endpush
