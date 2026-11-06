@extends('backend.layout.main')

@section('content')

@push('css')
<link rel="stylesheet" href="{{ versioned_asset('css/product-workspace.css') }}" type="text/css">
<link rel="stylesheet" href="{{ versioned_asset('css/pages/parties-workspace.css') }}" type="text/css">
@endpush

<x-success-message key="message" />
<x-error-message key="not_permitted" />

<x-party-command-bar active="customers" title="{{ __('db.customer') }} Profile" badge="{{ $lims_customer_data->name }}">
    @can('customers-edit')
        <a href="{{ route('customer.edit', $lims_customer_data->id) }}" class="btn btn-primary">
            <i class="dripicons-document-edit"></i> {{ __('db.edit') }}
        </a>
    @endcan
    <a href="{{ route('customer.index') }}" class="btn btn-outline-secondary">
        <i class="dripicons-arrow-left"></i> {{ __('db.Back') }}
    </a>
</x-party-command-bar>

<div class="customer-workspace-view">
    <!-- Customer Profile Header Card -->
    <div class="party-profile-card">
        <div class="party-profile-left">
            <div class="party-avatar">
                {{ strtoupper(substr($lims_customer_data->name ?? 'C', 0, 1)) }}
            </div>
            <div>
                <h4 class="party-profile-name">{{ $lims_customer_data->name ?? '-' }}</h4>
                <div class="party-profile-meta">
                    @if(!empty($lims_customer_data->company_name))
                        <span><i class="dripicons-store"></i> {{ $lims_customer_data->company_name }}</span>
                    @endif
                    @if(!empty($lims_customer_data->tax_no))
                        <span class="badge badge-info"><i class="dripicons-card"></i> GSTIN: {{ $lims_customer_data->tax_no }}</span>
                    @endif
                    @if(!empty($lims_customer_data->phone_number))
                        <span><i class="dripicons-phone"></i> {{ $lims_customer_data->phone_number }}</span>
                    @endif
                    @if(!empty($lims_customer_data->email))
                        <span><i class="dripicons-mail"></i> {{ $lims_customer_data->email }}</span>
                    @endif
                </div>
            </div>
        </div>

        <div class="text-right text-muted small">
            <div><strong>Address:</strong> {{ $lims_customer_data->address ?? '-' }}</div>
            <div>{{ $lims_customer_data->city ?? '' }}{{ !empty($lims_customer_data->city) && !empty($lims_customer_data->state) ? ', ' : '' }}{{ $lims_customer_data->state ?? '' }} {{ $lims_customer_data->postal_code ?? '' }}</div>
            <div>{{ $lims_customer_data->country ?? 'India' }}</div>
        </div>
    </div>

    <!-- Financial KPI Summary Strip -->
    <div id="ledger-summery" class="party-kpi-grid d-none">
        <div class="party-kpi-box">
            <div class="party-kpi-label">Opening Balance</div>
            <h5 class="party-kpi-val">₹ {{ number_format($opening_balance, 2) }}</h5>
        </div>
        <div class="party-kpi-box">
            <div class="party-kpi-label">Total Sales</div>
            <h5 class="party-kpi-val u-c-primary">₹ {{ number_format($total_sales, 2) }}</h5>
        </div>
        <div class="party-kpi-box">
            <div class="party-kpi-label">Total Paid</div>
            <h5 class="party-kpi-val u-c-success">₹ {{ number_format($total_paid, 2) }}</h5>
        </div>
        <div class="party-kpi-box">
            <div class="party-kpi-label">Balance Due</div>
            <h5 class="party-kpi-val u-c-danger">₹ {{ number_format($balance_due, 2) }}</h5>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <ul class="nav nav-tabs mt-2" role="tablist">
        <li class="nav-item">
            <a class="nav-link active" href="#ledger-latest" role="tab" data-toggle="tab">
                <i class="dripicons-document mr-1"></i> Ledger
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="#sales-latest" role="tab" data-toggle="tab">
                <i class="dripicons-cart mr-1"></i> {{ __('db.Sale') }}
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="#sales-payment-latest" role="tab" data-toggle="tab">
                <i class="fa fa-money mr-1"></i> {{ __('db.Sale Payment') }}
            </a>
        </li>
    </ul>

    <div class="tab-content mb-4 pt-3">
        <!-- LEDGER TAB -->
        <div role="tabpanel" class="tab-pane fade show active" id="ledger-latest">
            <div class="desk-card-wrapper">
                <div class="table-responsive">
                    <table id="recent-ledger" class="table w-100">
                        <thead>
                            <tr>
                                <th>{{ __('db.date') }}</th>
                                <th>Type</th>
                                <th>{{ __('db.reference') }}</th>
                                <th>Debit</th>
                                <th>Credit</th>
                                <th>Balance</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- SALES TAB -->
        <div role="tabpanel" class="tab-pane fade" id="sales-latest">
            <div class="desk-card-wrapper">
                <div class="table-responsive">
                    <table id="recent-sales" class="table w-100">
                        <thead>
                            <tr>
                                <th>{{ __('db.date') }}</th>
                                <th>{{ __('db.reference') }}</th>
                                <th>{{ __('db.Warehouse') }}</th>
                                <th>{{ __('db.Sale Status') }}</th>
                                <th>{{ __('db.Payment Status') }}</th>
                                <th>{{ __('db.grand total') }}</th>
                                <th>{{ __('db.Paid Amount') }}</th>
                                <th>{{ __('db.Due') }}</th>
                                <th class="not-exported">{{ __('db.action') }}</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- SALES PAYMENT TAB -->
        <div role="tabpanel" class="tab-pane fade" id="sales-payment-latest">
            <div class="desk-card-wrapper">
                <div class="table-responsive">
                    <table id="recent-sales-payment" class="table w-100">
                        <thead>
                            <tr>
                                <th>{{ __('db.date') }}</th>
                                <th>{{ __('db.reference') }}</th>
                                <th>{{ __('db.Amount') }}</th>
                                <th>{{ __('db.Payment Method') }}</th>
                                <th>{{ __('db.Payment At') }}</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- SALE DETAILS MODAL -->
    <div id="sale-details" class="modal fade text-left" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header party-modal-header d-flex justify-content-between align-items-center">
                    <h5 class="modal-title"><i class="dripicons-preview text-primary mr-2"></i> {{ __('db.Sale Details') }}</h5>
                    <div class="d-flex align-items-center gap-2">
                        <button id="print-btn" type="button" class="btn btn-outline-secondary btn-sm">
                            <i class="dripicons-print"></i> {{__('db.Print')}}
                        </button>
                        <button type="button" class="close" data-dismiss="modal">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                </div>

                <div id="sale-content" class="modal-body"></div>

                <div class="p-3">
                    <table class="table table-bordered product-sale-list mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>{{ __('db.Product') }}</th>
                                <th>{{ __('db.qty') }}</th>
                                <th>{{ __('db.Price') }}</th>
                                <th>{{ __('db.Tax') }}</th>
                                <th>{{ __('db.Discount') }}</th>
                                <th>{{ __('db.Subtotal') }}</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>

                <div id="sale-footer" class="modal-body border-top"></div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(function () {
    $("ul#parties").siblings('a').attr('aria-expanded','true');
    $("ul#parties").addClass("show");
    $("ul#parties #customer-list-menu").addClass("active");

    // LEDGER TABLE
    $('#recent-ledger').DataTable({
        ajax: "{{ route('customers.ledger', $lims_customer_data->id) }}",
        columns: [
            { data: 'date' },
            { data: 'type' },
            { data: 'reference' },
            { data: 'debit' },
            { data: 'credit' },
            { data: 'balance' }
        ],
        order: [[0, 'desc']],
        responsive: true,
    });

    // SALES TABLE
    $('#recent-sales').DataTable({
        ajax: "{{ route('sales.customer', $lims_customer_data->id) }}",
        columns: [
            { data: 'date' },
            { data: 'reference' },
            { data: 'warehouse' },
            { data: 'sale_status' },
            { data: 'payment_status' },
            { data: 'grand_total' },
            { data: 'paid_amount' },
            { data: 'payment_due' },
            {
                data: 'id',
                render: function(data){
                    return `
                        <a href="javascript:void(0)" class="btn btn-sm btn-info view-sale" data-id="${data}">
                            <i class="dripicons-preview"></i>
                        </a>
                        <a href="/sales/${data}/edit" class="btn btn-sm btn-warning">
                            <i class="dripicons-document-edit"></i>
                        </a>
                    `;
                },
                orderable: false,
                searchable: false
            }
        ],
        order: [[0, 'desc']],
        responsive: true,
    });

    // SALES PAYMENT TABLE
    $('#recent-sales-payment').DataTable({
        ajax: "{{ route('customers.payments', $lims_customer_data->id) }}",
        columns: [
            { data: 'date' },
            { data: 'reference' },
            { data: 'amount' },
            { data: 'paying_method' },
            { data: 'created_at' }
        ],
        order: [[0, 'desc']],
        responsive: true,
    });

    // Handle Ledger Tab Show
    $('a[href="#ledger-latest"]').on('shown.bs.tab', function () {
        $('#ledger-summery').removeClass('d-none');
    });

    // Handle Other Tabs Show
    $('a[href="#sales-latest"], a[href="#sales-payment-latest"]').on('shown.bs.tab', function () {
        $('#ledger-summery').addClass('d-none');
    });

    // Show on page load since Ledger is active
    $('#ledger-summery').removeClass('d-none');

    // VIEW SALE MODAL HANDLER
    $(document).on('click', '.view-sale', function () {
        var saleId = $(this).data('id');
        $.get('/sales/' + saleId, function (data) {
            var sale = data.sale || data[0] || {};
            var saleDetails = data.items || data[1] || [];
            var customer = sale.customer || data[2] || {};
            var biller = sale.biller || data[3] || {};

            var saleDate = (data.business_date || sale.created_at || '').substring(0, 10);
            var html = `
                <div class="row">
                    <div class="col-md-6">
                        <strong>Date:</strong> ${saleDate}<br>
                        <strong>Reference:</strong> ${sale.reference_no || '-'}<br>
                        <strong>Warehouse:</strong> ${sale.warehouse ? sale.warehouse.name : '-'}<br>
                    </div>
                    <div class="col-md-6 text-right">
                        <strong>Customer:</strong> ${customer.name || '-'}<br>
                        <strong>Phone:</strong> ${customer.phone_number || '-'}<br>
                        <strong>Biller:</strong> ${biller ? biller.name : '-'}<br>
                    </div>
                </div>
            `;
            $('#sale-content').html(html);

            var rows = '';
            var totalQty = 0;
            var subtotal = 0;

            $.each(saleDetails, function (index, item) {
                var pQty = parseFloat(item.qty || 0);
                var pTotal = parseFloat(item.total || 0);
                var pPrice = parseFloat(item.net_unit_price || (pQty > 0 ? (pTotal / pQty) : 0));
                var pName = item.product_name || item.name || '';
                var pCode = item.product_code || item.code || '';
                totalQty += pQty;
                subtotal += pTotal;

                rows += `
                    <tr>
                        <td>${index + 1}</td>
                        <td>${pCode ? pCode + ' - ' : ''}${pName}</td>
                        <td>${pQty}</td>
                        <td>₹${pPrice.toFixed(2)}</td>
                        <td>₹${parseFloat(item.tax || 0).toFixed(2)}</td>
                        <td>₹${parseFloat(item.discount || 0).toFixed(2)}</td>
                        <td>₹${pTotal.toFixed(2)}</td>
                    </tr>
                `;
            });

            $('.product-sale-list tbody').html(rows);

            var footerHtml = `
                <div class="row">
                    <div class="col-md-6">
                        <strong>Note:</strong> ${sale.sale_note ?? '-'}<br>
                    </div>
                    <div class="col-md-6 text-right">
                        <strong>Total Qty:</strong> ${totalQty}<br>
                        <strong>Grand Total:</strong> ₹ ${parseFloat(sale.grand_total).toFixed(2)}<br>
                        <strong>Paid Amount:</strong> ₹ ${parseFloat(sale.paid_amount).toFixed(2)}<br>
                        <strong>Due:</strong> <span class="text-danger">₹ ${(parseFloat(sale.grand_total) - parseFloat(sale.paid_amount)).toFixed(2)}</span>
                    </div>
                </div>
            `;
            $('#sale-footer').html(footerHtml);

            $('#sale-details').modal('show');
        });
    });

    // PRINT SALE DETAILS
    $('#print-btn').on('click', function () {
        var printContents = document.getElementById('sale-details').innerHTML;
        var originalContents = document.body.innerHTML;

        document.body.innerHTML = printContents;
        window.print();
        document.body.innerHTML = originalContents;
        location.reload();
    });
});
</script>
@endpush
