@extends('backend.layout.main')

@section('content')
<div class="container-fluid">

    <x-success-message key="message" />
    <x-error-message key="not_permitted" />

    <!-- Customer Header -->
    <div class="card mt-5">
        <div class="card-header d-flex justify-content-between align-items-start">

            <!-- Left: name, phone, email -->
            <div>
                <span><h4 class="mb-1">{{ $lims_customer_data->name ?? '-' }}</h4>Customer</span>
                <p class="mb-0"><strong>Email:</strong> {{ $lims_customer_data->email ?? '-' }}</p>
                <p class="mb-0"><strong>Phone:</strong> {{ $lims_customer_data->phone_number ?? '-' }}</p>
            </div>

            <!-- Right: address -->
            <div class="text-end">
                <p class="mb-0">
                    <strong>Address:</strong> {{ $lims_customer_data->address ?? '-' }}<br>
                    <strong>City:</strong> {{ $lims_customer_data->city ?? '-' }}<br>
                    <strong>Country:</strong> {{ $lims_customer_data->country ?? '-' }}
                </p>
            </div>

        </div>
    </div>

    <!-- Tabs -->
    <ul class="nav nav-tabs mt-4" role="tablist">
        <li class="nav-item">
            <a class="nav-link active" href="#ledger-latest" role="tab" data-toggle="tab">
                Ledger
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="#sales-latest" role="tab" data-toggle="tab">
                {{ __('db.Sale') }}
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="#sales-payment-latest" role="tab" data-toggle="tab">
                {{ __('db.Sale Payment') }}
            </a>
        </li>
    </ul>

    <div id="ledger-summery" class="row mt-3 text-center d-none">

        <div class="col-md-3">
            <div class="p-2 border rounded bg-light">
                <strong>Opening Balance</strong><br>
                <h5>{{ number_format($opening_balance, 2) }}</h5>
            </div>
        </div>

        <div class="col-md-3">
            <div class="p-2 border rounded bg-light">
                <strong>Total Sales</strong><br>
                <h5>{{ number_format($total_sales, 2) }}</h5>
            </div>
        </div>

        <div class="col-md-3">
            <div class="p-2 border rounded bg-light">
                <strong>Total Paid</strong><br>
                <h5>{{ number_format($total_paid, 2) }}</h5>
            </div>
        </div>

        <div class="col-md-3">
            <div class="p-2 border rounded bg-light">
                <strong>Balance Due</strong><br>
                <h5 class="text-danger">{{ number_format($balance_due, 2) }}</h5>
            </div>
        </div>

    </div>

    <div class="tab-content mb-5">

        <!-- LEDGER TAB -->
        <div role="tabpanel" class="tab-pane fade show active" id="ledger-latest">
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

        <!-- SALES TAB -->
        <div role="tabpanel" class="tab-pane fade" id="sales-latest">
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
                            <th>{{ __('db.action') }}</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>

        <!-- SALES PAYMENT TAB -->
        <div role="tabpanel" class="tab-pane fade" id="sales-payment-latest">
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

    <!-- SALE DETAILS MODAL -->
    <div id="sale-details" class="modal fade text-left" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">

                <div class="container mt-3 pb-2 border-bottom">
                    <div class="row">
                        <div class="col-md-6 d-print-none">
                            <button id="print-btn" type="button" class="btn btn-default btn-sm">
                                <i class="dripicons-print"></i> {{__('db.Print')}}
                            </button>
                        </div>

                        <div class="col-md-6 d-print-none">
                            <button type="button" class="close" data-dismiss="modal">
                                <span aria-hidden="true"><i class="dripicons-cross"></i></span>
                            </button>
                        </div>

                        <div class="col-md-12 text-center">
                            <h3 class="modal-title">{{ $general_setting->site_title }}</h3>
                            <i style="font-size: 15px;">{{ __('db.Sale Details') }}</i>
                        </div>
                    </div>
                </div>

                <div id="sale-content" class="modal-body"></div>

                <table class="table table-bordered product-sale-list">
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

                <div id="sale-footer" class="modal-body"></div>
            </div>
        </div>
    </div>

</div>
@endsection


@push('scripts')
<script>
$(function () {

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
            { data: 'created_at' },
            { data: 'payment_reference' },
            { data: 'amount' },
            { data: 'paying_method' },
            { data: 'payment_at' },
        ],
        order: [[0, 'desc']],
        responsive: true,
    });

    // VIEW SALE MODAL
    $(document).on("click", ".view-sale", function () {
        let table = $('#recent-sales').DataTable();
        let sale = table.row($(this).parents('tr')).data();
        saleDetails(sale);
    });

    $('#ledger-summery').removeClass('d-none'); // default open

    $('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
        let target = $(e.target).attr("href");
        if (target === '#ledger-latest') {
            $('#ledger-summery').removeClass('d-none');
        } else {
            $('#ledger-summery').addClass('d-none');
        }
    });

});

// SALE DETAILS MODAL FUNCTION
function saleDetails(sale) {
    let html = `
        {{__('db.date')}}: ${sale.date}<br>
        {{__('db.reference')}}: ${sale.reference}<br>
        {{__('db.Sale Status')}}: ${sale.sale_status}<br><br>

        <div style="display:flex; justify-content:space-between; gap:20px; width:100%">
            <div style="flex:1">
                <strong>{{__('db.customer')}}</strong><br>
                ${sale.customer_name}<br>
                ${sale.customer_phone}<br>
                ${sale.customer_address}<br><br>
            </div>

            <div style="flex:1; text-align:right;">
                <strong>{{__('db.Warehouse')}}</strong><br>
                ${sale.warehouse}
            </div>
        </div>
    `;

    $("#sale-content").html(html);

    let $table = $("table.product-sale-list");
    $table.find("tbody").remove();

    // sales/product_sale/
    $.get("{{url('sales/product_sale')}}/" + sale.id, function(data){
        console.log(data);
        var $newBody = $("<tbody>");

        if (data && data[0] && data[0].length > 0) {
            for (var i = 0; i < data[0].length; i++) {
                var $newRow = $(`
                    <tr>
                        <td>${i + 1}</td>
                        <td>${data[0][i]}</td> <!-- name + code -->
                        <td>${data[1][i]} ${data[2][i]}</td> <!-- qty + unit -->
                        <td>${data[6][i]}</td> <!-- price -->
                        <td>${data[3][i]} (${data[4][i]}%)</td> <!-- tax -->
                        <td>${data[5][i]}</td> <!-- discount -->
                        <td>${data[6][i]}</td> <!-- sub total -->
                    </tr>
                `);
                $newBody.append($newRow);
            }
        } else {
            $newBody.append('<tr><td colspan="9" class="text-center">No products found</td></tr>');
        }

        $table.append($newBody);
    });

    $("#sale-footer").html(`<p>{{__('db.Note')}}: ${sale.note ?? ''}</p>`);

    $("#sale-details").modal('show');
}

</script>
@endpush
