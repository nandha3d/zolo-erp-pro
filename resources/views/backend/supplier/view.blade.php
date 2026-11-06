@extends('backend.layout.main')

@section('content')

@push('css')
<link rel="stylesheet" href="{{ versioned_asset('css/product-workspace.css') }}" type="text/css">
<link rel="stylesheet" href="{{ versioned_asset('css/pages/parties-workspace.css') }}" type="text/css">
@endpush

<x-success-message key="message" />
<x-error-message key="not_permitted" />

<x-party-command-bar active="suppliers" title="{{ __('db.Supplier') }} Profile" badge="{{ $lims_supplier_data->name }}">
    @can('suppliers-edit')
        <a href="{{ route('supplier.edit', $lims_supplier_data->id) }}" class="btn btn-primary">
            <i class="dripicons-document-edit"></i> {{ __('db.edit') }}
        </a>
    @endcan
    <a href="{{ route('supplier.index') }}" class="btn btn-outline-secondary">
        <i class="dripicons-arrow-left"></i> {{ __('db.Back') }}
    </a>
</x-party-command-bar>

<div class="supplier-workspace-view">
    <!-- Supplier Profile Header Card -->
    <div class="party-profile-card">
        <div class="party-profile-left">
            <div class="party-avatar">
                {{ strtoupper(substr($lims_supplier_data->name ?? 'S', 0, 1)) }}
            </div>
            <div>
                <h4 class="party-profile-name">{{ $lims_supplier_data->name ?? '-' }}</h4>
                <div class="party-profile-meta">
                    @if(!empty($lims_supplier_data->company_name))
                        <span><i class="dripicons-store"></i> {{ $lims_supplier_data->company_name }}</span>
                    @endif
                    @if(!empty($lims_supplier_data->vat_number))
                        <span class="badge badge-info"><i class="dripicons-card"></i> GSTIN: {{ $lims_supplier_data->vat_number }}</span>
                    @endif
                    @if(!empty($lims_supplier_data->phone_number))
                        <span><i class="dripicons-phone"></i> {{ $lims_supplier_data->phone_number }}</span>
                    @endif
                    @if(!empty($lims_supplier_data->email))
                        <span><i class="dripicons-mail"></i> {{ $lims_supplier_data->email }}</span>
                    @endif
                </div>
            </div>
        </div>

        <div class="text-right text-muted small">
            <div><strong>Address:</strong> {{ $lims_supplier_data->address ?? '-' }}</div>
            <div>{{ $lims_supplier_data->city ?? '' }}{{ !empty($lims_supplier_data->city) && !empty($lims_supplier_data->state) ? ', ' : '' }}{{ $lims_supplier_data->state ?? '' }} {{ $lims_supplier_data->postal_code ?? '' }}</div>
            <div>{{ $lims_supplier_data->country ?? 'India' }}</div>
        </div>
    </div>

    <!-- Financial KPI Summary Strip -->
    <div id="ledger-summery" class="party-kpi-grid d-none">
        <div class="party-kpi-box">
            <div class="party-kpi-label">Opening Balance</div>
            <h5 class="party-kpi-val">₹ {{ number_format($opening_balance, 2) }}</h5>
        </div>
        <div class="party-kpi-box">
            <div class="party-kpi-label">Total Purchase</div>
            <h5 class="party-kpi-val u-c-primary">₹ {{ number_format($total_purchase, 2) }}</h5>
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
            <a class="nav-link" href="#purchase-latest" role="tab" data-toggle="tab">
                <i class="dripicons-card mr-1"></i> {{ __('db.Purchase') }}
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="#payment-latest" role="tab" data-toggle="tab">
                <i class="fa fa-money mr-1"></i> {{ __('db.Payment') }}
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

        <!-- PURCHASE TAB -->
        <div role="tabpanel" class="tab-pane fade" id="purchase-latest">
            <div class="desk-card-wrapper">
                <div class="table-responsive">
                    <table id="recent-purchase" class="table w-100">
                        <thead>
                            <tr>
                                <th>{{ __('db.date') }}</th>
                                <th>{{ __('db.reference') }}</th>
                                <th>{{ __('db.Warehouse') }}</th>
                                <th>{{ __('db.Purchase Status') }}</th>
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

        <!-- PAYMENT TAB -->
        <div role="tabpanel" class="tab-pane fade" id="payment-latest">
            <div class="desk-card-wrapper">
                <div class="table-responsive">
                    <table id="recent-payment" class="table w-100">
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

    <!-- PURCHASE DETAILS MODAL -->
    <div id="purchase-details" class="modal fade text-left" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header party-modal-header d-flex justify-content-between align-items-center">
                    <h5 class="modal-title"><i class="dripicons-preview text-primary mr-2"></i> {{ __('db.Purchase Details') }}</h5>
                    <div class="d-flex align-items-center gap-2">
                        <button id="print-btn" type="button" class="btn btn-outline-secondary btn-sm">
                            <i class="dripicons-print"></i> {{__('db.Print')}}
                        </button>
                        <button type="button" class="close" data-dismiss="modal">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                </div>

                <div id="purchase-content" class="modal-body"></div>

                <div class="p-3">
                    <table class="table table-bordered product-purchase-list mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>{{__('db.Product')}}</th>
                                <th>{{__('db.Batch No')}}</th>
                                <th>Qty</th>
                                <th>{{__('db.Returned')}}</th>
                                <th>{{__('db.Unit Cost')}}</th>
                                <th>{{__('db.Tax')}}</th>
                                <th>{{__('db.Discount')}}</th>
                                <th>{{__('db.Subtotal')}}</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>

                <div id="purchase-footer" class="modal-body border-top"></div>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
    $(document).ready(function () {
        $("ul#parties").siblings('a').attr('aria-expanded','true');
        $("ul#parties").addClass("show");
        $("ul#parties #supplier-list-menu").addClass("active");

        // SUPPLIER LEDGER
        $('#recent-ledger').DataTable({
            ajax: "{{ route('suppliers.ledger', $lims_supplier_data->id) }}",
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
        
        $('#recent-purchase').DataTable({
            ajax: "{{ route('purchase.supplier', $lims_supplier_data->id) }}",
            columns: [
                { data: 'date' },
                { data: 'reference' },
                { data: 'warehouse' },
                { data: 'purchase_status' },
                { data: 'payment_status' },
                { data: 'grand_total' },
                { data: 'paid_amount' },
                { data: 'payment_due' },
                {
                    data: 'id',
                    render: function (data, type, row) {
                        return `
                            <a href="javascript:void(0)" class="btn btn-sm btn-info view-purchase"
                            data-id="${data}" title="View">
                                <i class="dripicons-preview"></i>
                            </a>
                            <a href="/purchases/${data}/edit" class="btn btn-sm btn-warning" title="Edit">
                                <i class="dripicons-document-edit"></i>
                            </a>
                        `;
                    },
                    orderable: false,
                    searchable: false
                }
            ],
            order: [[0, 'desc']],
            autoWidth: false,
            responsive: true,
        });

        $('#recent-payment').DataTable({
            ajax: "{{ route('suppliers.payments', $lims_supplier_data->id) }}",
            columns: [
                { data: 'created_at' },
                { data: 'payment_reference' },
                { data: 'amount' },
                { data: 'paying_method' },
                { data: 'payment_at' }
            ],
            order: [[0, 'desc']],
            autoWidth: false,
            responsive: true,
        });

        $("#print-btn").on("click", function(){
            var divContents = document.getElementById("purchase-details").innerHTML;
            var a = window.open('');
            a.document.write('<html>');
            a.document.write('<body><link rel="stylesheet" href="{{ versioned_asset('css/pages/supplier-view.css') }}"><link rel="stylesheet" href="{{ versioned_asset('css/pages/shared-customer-index-2.css') }}">');
            a.document.write(divContents);
            a.document.write('<\/body><\/html>');
            a.document.close();
            setTimeout(function(){a.close();},10);
            a.print();
        });

        $(document).on("click", ".view-purchase", function(){
            var table = $('#recent-purchase').DataTable();
            var rowData = table.row($(this).parents('tr')).data();
            purchaseDetails(rowData);
        });
    });

    function purchaseDetails(purchase) {
        var currencyText = purchase.currency ? (purchase.currency.code || purchase.currency.name) : 'N/A';
        var noteText = purchase.note ?? '';

        var htmltext = `
            {{__("db.date")}}: ${purchase.date}<br>
            {{__("db.reference")}}: ${purchase.reference}<br>
            {{__("db.Purchase Status")}}: ${purchase.purchase_status}<br>
            {{__("db.Currency")}}: ${currencyText}<br>
        `;

        if (purchase.document) {
            htmltext += '{{__("db.Attach Document")}}: <a href="{{ url('secure-documents/purchase') }}/' + purchase.document + '" target="_blank">Download</a><br>';
        }

        htmltext += `
            <br>
            <div class="row">
                <div class="col-md-6">
                    {{__("db.From")}}:<br>
                    ${purchase.supplier_name}<br>
                    ${purchase.supplier_company}<br>
                    ${purchase.supplier_address}
                </div>
                <div class="col-md-6">
                    <div class="float-right">
                        {{__("db.To")}}:<br>
                        ${purchase.warehouse}
                    </div>
                </div>
            </div>
        `;

        // Clear previous table content
        var $table = $("table.product-purchase-list");
        $table.find("tbody").remove();

        // Fetch product purchase items
        $.get("{{url('purchases/product_purchase')}}/" + purchase.id, function(data) {
            var $newBody = $("<tbody>");

            if (data && data[0] && data[0].length > 0) {
                for (var i = 0; i < data[0].length; i++) {
                    var $newRow = $(`
                        <tr>
                            <td>${i + 1}</td>
                            <td>${data[0][i]}</td> <!-- name + code -->
                            <td>${data[7][i]}</td> <!-- batch_no -->
                            <td>${data[1][i]} ${data[2][i]}</td> <!-- qty + unit -->
                            <td>${data[8][i]}</td> <!-- returned qty -->
                            <td>${data[6][i]}</td> <!-- unit cost / total -->
                            <td>${data[3][i]} (${data[4][i]}%)</td> <!-- tax -->
                            <td>${data[5][i]}</td> <!-- discount -->
                            <td>${data[6][i]}</td> <!-- subtotal -->
                        </tr>
                    `);
                    $newBody.append($newRow);
                }
            } else {
                $newBody.append('<tr><td colspan="9" class="text-center">No products found</td></tr>');
            }

            $table.append($newBody);
        }).fail(function() {
            $table.append('<tbody><tr><td colspan="9" class="text-center text-danger">Failed to load products</td></tr></tbody>');
        });

        $('#purchase-content').html(htmltext);
        $('#purchase-footer').html(`<p>{{__("db.Note")}}: ${noteText}</p>`);
        $('#purchase-details').modal('show');
    }

    $('#ledger-summery').removeClass('d-none');

    $('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
        if ($(e.target).attr('href') === '#ledger-latest') {
            $('#ledger-summery').removeClass('d-none');
        } else {
            $('#ledger-summery').addClass('d-none');
        }
    });

</script>
@endpush
