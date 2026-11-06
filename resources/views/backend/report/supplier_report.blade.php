@extends('backend.layout.main')
@section('content')

<x-error-message key="not_permitted" />

@php
    $general_setting = \Illuminate\Support\Facades\DB::table('general_settings')->latest()->first();
    $currency = \App\Models\Currency::find($general_setting->currency ?? 1);
    $currencySymbol = $currency->symbol ?? '₹';
    $supplierName = 'All Suppliers';
    $supplier = $ledgerData['party'] ?? null;
    if ($supplier) {
        $supplierName = $supplier->name . ($supplier->company_name ? ' (' . $supplier->company_name . ')' : '');
    }
    $openingBal = (float)($ledgerData['opening_balance'] ?? 0);
    $totalDebits = (float)($ledgerData['total_debit'] ?? 0);
    $totalCredits = (float)($ledgerData['total_credit'] ?? 0);
    $closingBal = (float)($ledgerData['closing_balance'] ?? 0);
@endphp

@push('css')
<link rel="stylesheet" href="{{ versioned_asset('css/pages/party-ledger.css') }}">
@endpush

<section class="report-workspace-view">
    <!-- Top Command Bar -->
    <div class="comm-command-bar section-command-bar d-print-none">
        <div class="comm-title-group">
            <h1><i class="dripicons-user-id text-primary"></i> {{ __('db.Supplier Report') }} Command Center</h1>
            <span class="comm-title-badge"><i class="dripicons-tag"></i> 360° Vendor Payable &amp; Single-View Ledger</span>
        </div>
        <ul class="comm-nav-pills">
            <li><a class="nav-link active" href="{{ url('report/supplier') }}"><i class="dripicons-user-id"></i> Supplier Ledger</a></li>
            <li><a class="nav-link" href="{{ route('report.supplierDueByDate') }}"><i class="dripicons-warning"></i> Supplier Due</a></li>
            <li><a class="nav-link" href="{{ route('supplier.index') }}"><i class="dripicons-user"></i> Supplier Master</a></li>
            <li><a class="nav-link" href="{{ route('report.outstandingDues') }}"><i class="dripicons-pulse"></i> Outstanding Dues</a></li>
        </ul>
        <div class="comm-actions">
            <div class="btn-group">
                <button type="button" class="btn btn-primary btn-action-pill u-bg-primary u-bc-primary" onclick="printReport('all')">
                    <i class="dripicons-print"></i> Print Complete Ledger
                </button>
                <button type="button" class="btn btn-primary btn-action-pill dropdown-toggle dropdown-toggle-split u-bg-primary u-bc-primary" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    <span class="sr-only">Toggle Dropdown</span>
                </button>
                <div class="dropdown-menu dropdown-menu-right">
                    <a class="dropdown-item" href="javascript:void(0)" onclick="printReport('all')"><i class="dripicons-document mr-1"></i> Print Complete Ledger</a>
                    <a class="dropdown-item" href="javascript:void(0)" onclick="printReport('current')"><i class="dripicons-view-list mr-1"></i> Print Current Filtered View</a>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item" href="javascript:void(0)" onclick="printReport('purchase')"><i class="dripicons-basket mr-1"></i> Print Bills Only</a>
                    <a class="dropdown-item" href="javascript:void(0)" onclick="printReport('payment')"><i class="dripicons-wallet mr-1"></i> Print Payments Only</a>
                    <a class="dropdown-item" href="javascript:void(0)" onclick="printReport('return')"><i class="dripicons-return mr-1"></i> Print Returns Only</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Print Letterhead & Party Header (Only Visible When Printing) -->
    <div class="print-only-header">
        <div class="ledger-letterhead">
            <div>
                <h2 class="ledger-company-name">{{ $general_setting->company_name ?? 'zoloERP Pro' }}</h2>
                <div class="ledger-company-address">
                    GSTIN: <strong>{{ $general_setting->vat_registration_number ?? '—' }}</strong> | Phone: {{ $general_setting->phone ?? '—' }}
                </div>
                <div class="ledger-print-detail">
                    {{ $general_setting->address ?? '' }}
                </div>
            </div>
            <div class="text-right">
                <h3 class="ledger-print-title" id="printDocTitle">Supplier Account Statement / Ledger</h3>
                <div class="ledger-print-period">
                    Period: <strong>{{ date('d-m-Y', strtotime($start_date)) }}</strong> To <strong>{{ date('d-m-Y', strtotime($end_date)) }}</strong>
                </div>
                <div class="ledger-print-date">Printed on: {{ date('d-m-Y H:i') }}</div>
            </div>
        </div>

        @if($supplier)
        <div class="ledger-print-party">
            <div>
                <div class="ledger-print-label">Supplier / Creditor Details:</div>
                <div class="ledger-party-name">{{ $supplier->name }}</div>
                <div class="ledger-party-company">{{ $supplier->company_name ?? '' }}</div>
                <div class="ledger-print-detail">Phone: {{ $supplier->phone_number }} | Email: {{ $supplier->email ?? '—' }}</div>
                <div class="ledger-print-detail">GSTIN: <strong>{{ $supplier->vat_number ?? 'Unregistered' }}</strong></div>
            </div>
            <div class="ledger-print-balances">
                <div class="ledger-print-label">Opening Balance:</div>
                <div class="ledger-opening-balance">{{ $currencySymbol }} {{ number_format(abs($openingBal), 2) }} {{ $openingBal >= 0 ? 'Cr' : 'Dr' }}</div>
                <div class="ledger-print-label ledger-closing-label">Current Outstanding Payable:</div>
                <div style="font-size: 12pt; font-weight: 800; color: {{ $closingBal > 0 ? '#b91c1c' : '#15803d' }};">
                    {{ $currencySymbol }} {{ number_format(abs($closingBal), 2) }} {{ $closingBal >= 0 ? 'Cr' : 'Dr' }}
                </div>
            </div>
        </div>
        @endif
    </div>

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
                    <span class="badge badge-sm {{ $openingBal >= 0 ? 'badge-secondary' : 'badge-success' }}">{{ $openingBal >= 0 ? 'Cr' : 'Dr' }}</span>
                </div>
            </div>
            <div class="z-icon-tile z-tone-info">
                <i class="dripicons-wallet u-fs-18px"></i>
            </div>
        </div>
        <div class="kpi-card">
            <div>
                <div class="kpi-label">TOTAL BILLED (CREDIT)</div>
                <div class="kpi-number u-fs-15px u-fw-700 text-primary">
                    {{ $currencySymbol }} {{ number_format($totalCredits, 2) }}
                </div>
            </div>
            <div class="z-icon-tile z-tone-warning">
                <i class="dripicons-basket u-fs-18px"></i>
            </div>
        </div>
        <div class="kpi-card">
            <div>
                <div class="kpi-label">TOTAL PAID (DEBIT)</div>
                <div class="kpi-number u-fs-15px u-fw-700 text-success">
                    {{ $currencySymbol }} {{ number_format($totalDebits, 2) }}
                </div>
            </div>
            <div class="z-icon-tile z-tone-success">
                <i class="dripicons-checkmark u-fs-18px"></i>
            </div>
        </div>
        <div class="kpi-card" style="border-left: 3px solid {{ $closingBal > 0 ? '#ef4444' : '#10b981' }};">
            <div>
                <div class="kpi-label">NET CLOSING PAYABLE</div>
                <div class="kpi-number u-fs-16px u-fw-800" style="color: {{ $closingBal > 0 ? '#b91c1c' : '#15803d' }};">
                    {{ $currencySymbol }} {{ number_format(abs($closingBal), 2) }}
                    <span class="badge badge-sm {{ $closingBal >= 0 ? 'badge-danger' : 'badge-success' }}">{{ $closingBal >= 0 ? 'Cr' : 'Dr' }}</span>
                </div>
            </div>
            <div class="z-icon-tile {{ $closingBal > 0 ? 'z-tone-danger' : 'z-tone-success' }}">
                <i class="dripicons-scale u-fs-18px"></i>
            </div>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="desk-filter-card d-print-none">
        {!! Form::open(['route' => 'report.supplier', 'method' => 'post', 'id' => 'ledgerFilterForm', 'class' => 'form-inline d-flex align-items-center flex-wrap gap-2']) !!}
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
                <label class="mr-2 font-weight-bold text-muted small"><i class="dripicons-user-id"></i> {{ __('db.Choose Supplier') }}</label>
                <select id="supplier_id" name="supplier_id" class="form-control form-control-sm u-h-30px u-fs-12px u-minw-220px selectpicker" data-live-search="true">
                    @foreach ($lims_supplier_list as $s)
                        <option value="{{ $s->id }}" @selected($s->id == $supplier_id)>
                            {{ $s->name }} ({{ $s->phone_number }})
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="form-group mr-2 mb-1">
                <label class="mr-2 font-weight-bold text-muted small"><i class="dripicons-filter"></i> Transaction Type</label>
                <select id="transaction_type" name="transaction_type" class="form-control form-control-sm u-h-30px u-fs-12px">
                    <option value="all" @selected(($transaction_type ?? 'all') == 'all')>All Transactions (Complete Ledger)</option>
                    <option value="purchase" @selected(($transaction_type ?? '') == 'purchase')>Purchase Bills Only</option>
                    <option value="payment" @selected(($transaction_type ?? '') == 'payment')>Payments Only</option>
                    <option value="return" @selected(($transaction_type ?? '') == 'return')>Purchase Returns Only</option>
                    <option value="quotation" @selected(($transaction_type ?? '') == 'quotation')>Quotations / POs Only</option>
                </select>
            </div>
            <div class="form-group mb-1">
                <button class="btn btn-primary btn-sm btn-action-pill u-bg-ink u-bc-ink mr-1" type="submit">
                    <i class="fa fa-filter"></i> {{ __('db.submit') }}
                </button>
                <a href="{{ route('report.supplier', ['supplier_id' => $supplier_id]) }}" class="btn btn-outline-secondary btn-sm btn-action-pill">
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
            <button type="button" class="btn-filter-pill" data-filter="purchase">
                <i class="dripicons-basket mr-1"></i> Bills ({{ $ledgerData['items']->where('type', 'purchase')->count() }})
            </button>
            <button type="button" class="btn-filter-pill" data-filter="payment">
                <i class="dripicons-wallet mr-1"></i> Payments ({{ $ledgerData['items']->where('type', 'payment')->count() }})
            </button>
            <button type="button" class="btn-filter-pill" data-filter="return">
                <i class="dripicons-return mr-1"></i> Returns ({{ $ledgerData['items']->where('type', 'return')->count() }})
            </button>
            <button type="button" class="btn-filter-pill" data-filter="quotation">
                <i class="dripicons-document mr-1"></i> POs / Quotes ({{ $ledgerData['items']->where('type', 'quotation')->count() }})
            </button>
        </div>
        <div class="text-muted small">
            <span>Showing statement for: <strong>{{ $supplierName }}</strong></span>
        </div>
    </div>

    <!-- Unified Single-View Ledger Table -->
    <div class="desk-card-wrapper">
        <div class="table-responsive">
            <table id="supplier-ledger-table" class="table ledger-table u-w-100pct">
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
                        <td class="text-right font-weight-bold">{{ $openingBal < 0 ? number_format(abs($openingBal), 2) : '0.00' }}</td>
                        <td class="text-right font-weight-bold">{{ $openingBal > 0 ? number_format($openingBal, 2) : '0.00' }}</td>
                        <td class="text-right font-weight-bold">
                            {{ number_format(abs($openingBal), 2) }}
                            <span class="badge badge-sm {{ $openingBal >= 0 ? 'badge-secondary' : 'badge-success' }}">{{ $openingBal >= 0 ? 'Cr' : 'Dr' }}</span>
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
                            $calcRunning += ($item->credit - $item->debit);
                        @endphp
                        <tr data-type="{{ $item->type }}">
                            <td class="not-exported-col">{{ $index + 1 }}</td>
                            <td>{{ date('d/m/Y', strtotime($item->date)) }}</td>
                            <td>
                                @if($item->type === 'purchase')
                                    <span class="badge-type-pill badge-type-purchase"><i class="dripicons-basket"></i> Purchase Bill</span>
                                @elseif($item->type === 'payment')
                                    <span class="badge-type-pill badge-type-payment"><i class="dripicons-checkmark"></i> Payment Paid</span>
                                @elseif($item->type === 'return')
                                    <span class="badge-type-pill badge-type-return"><i class="dripicons-return"></i> Purchase Ret</span>
                                @elseif($item->type === 'quotation')
                                    <span class="badge-type-pill badge-type-quotation"><i class="dripicons-document"></i> PO / Quote</span>
                                @else
                                    <span class="badge-type-pill badge-type-opening">{{ $item->type_label }}</span>
                                @endif
                            </td>
                            <td class="font-weight-bold font-monospace text-primary">
                                @if($item->type === 'purchase')
                                    <a href="{{ route('purchases.index') }}?reference={{ $item->reference_no }}" target="_blank">{{ $item->reference_no }}</a>
                                @else
                                    {{ $item->reference_no }}
                                @endif
                            </td>
                            <td>{{ $item->warehouse }}</td>
                            <td>{{ $item->description }}</td>
                            <td class="text-right font-weight-bold {{ $item->debit > 0 ? 'text-success' : 'text-muted' }}">
                                {{ $item->debit > 0 ? number_format($item->debit, 2) : '—' }}
                            </td>
                            <td class="text-right font-weight-bold {{ $item->credit > 0 ? 'text-dark' : 'text-muted' }}">
                                {{ $item->credit > 0 ? number_format($item->credit, 2) : '—' }}
                            </td>
                            <td class="text-right font-weight-bold">
                                {{ number_format(abs($calcRunning), 2) }}
                                <span class="badge badge-sm {{ $calcRunning >= 0 ? 'badge-danger' : 'badge-success' }}">{{ $calcRunning >= 0 ? 'Cr' : 'Dr' }}</span>
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
                        <th colspan="5" class="text-right">Total Activity &amp; Closing Payable:</th>
                        <th class="text-right text-success" id="footerTotalDebit">{{ number_format($totalDebits, 2) }}</th>
                        <th class="text-right text-primary" id="footerTotalCredit">{{ number_format($totalCredits, 2) }}</th>
                        <th class="text-right" id="footerClosingBal">
                            {{ number_format(abs($closingBal), 2) }}
                            <span class="badge badge-sm {{ $closingBal >= 0 ? 'badge-danger' : 'badge-success' }}">{{ $closingBal >= 0 ? 'Cr' : 'Dr' }}</span>
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
    <div class="ledger-signatures print-only-header">
        <div class="ledger-signature-row">
            <div class="ledger-signature">
                Supplier Confirmation / Verification
            </div>
            <div class="ledger-signature">
                For {{ $general_setting->company_name ?? 'zoloERP Pro' }}<br>
                <strong>Authorized Signatory</strong>
            </div>
        </div>
    </div>
</section>

@endsection

@push('scripts')
<script type="text/javascript">
    $("ul#report").siblings('a').attr('aria-expanded','true');
    $("ul#report").addClass("show");
    $("ul#report #supplier-report-menu").addClass("active");

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

        table = $('#supplier-ledger-table').DataTable({
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

        $('.btn-filter-pill').on('click', function() {
            var filter = $(this).data('filter');
            $('.btn-filter-pill').removeClass('active');
            $(this).addClass('active');

            applyFilter(filter);
        });

        var initialType = "{{ $transaction_type ?? 'all' }}";
        if(initialType !== 'all') {
            $('.btn-filter-pill[data-filter="' + initialType + '"]').trigger('click');
        }
    });

    function applyFilter(filter) {
        if (!table) return;

        $.fn.dataTable.ext.search.pop();

        if (filter !== 'all') {
            $.fn.dataTable.ext.search.push(
                function(settings, data, dataIndex) {
                    if (settings.nTable.id !== 'supplier-ledger-table') return true;
                    var row = table.row(dataIndex).node();
                    var type = $(row).data('type');
                    return type === filter;
                }
            );
        }

        table.draw();
    }

    function printReport(mode) {
        var prevFilter = $('.btn-filter-pill.active').data('filter');

        if (mode === 'all') {
            $('#printDocTitle').text('Complete Supplier Account Statement / Ledger');
            $('.btn-filter-pill[data-filter="all"]').trigger('click');
        } else if (mode === 'current') {
            var filterName = prevFilter.charAt(0).toUpperCase() + prevFilter.slice(1);
            $('#printDocTitle').text(filterName === 'All' ? 'Complete Supplier Account Statement / Ledger' : 'Supplier ' + filterName + ' Statement');
        } else {
            $('.btn-filter-pill[data-filter="' + mode + '"]').trigger('click');
            var filterName = mode.charAt(0).toUpperCase() + mode.slice(1);
            $('#printDocTitle').text('Supplier ' + filterName + ' Statement');
        }

        if (table) {
            var origLen = table.page.len();
            table.page.len(-1).draw();

            setTimeout(function() {
                window.print();
                setTimeout(function() {
                    table.page.len(origLen).draw();
                    if (mode === 'all' && prevFilter && prevFilter !== 'all') {
                        $('.btn-filter-pill[data-filter="' + prevFilter + '"]').trigger('click');
                    }
                }, 300);
            }, 300);
        } else {
            window.print();
        }
    }
</script>
@endpush
