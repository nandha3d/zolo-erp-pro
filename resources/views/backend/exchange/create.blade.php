@extends('backend.layout.main')
@section('content')

<section class="forms">
    <div class="container-fluid">
        <div class="row align-items-center mb-3">
            <div class="col-md-8">
                <h3 class="font-weight-bold text-dark m-0">Sale Exchange & Replacement</h3>
                <p class="text-muted small m-0">Return items from an original sale and issue replacement items with automated differential billing.</p>
            </div>
            <div class="col-md-4 text-right">
                <a href="{{ route('exchange.index') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="dripicons-arrow-thin-left"></i> Back to Exchange List
                </a>
            </div>
        </div>

        <form action="{{ route('exchange.store') }}" method="POST" id="exchange-form">
            @csrf
            @if($sale)
                <input type="hidden" name="sale_id" value="{{ $sale->id }}">
            @endif

            <!-- Top Config Card -->
            <div class="card border-0 shadow-sm rounded-lg mb-4">
                <div class="card-body p-4">
                    <div class="row">
                        <div class="col-md-4 form-group">
                            <label class="font-weight-600 small">Warehouse *</label>
                            <select name="warehouse_id" class="form-control selectpicker" required>
                                @foreach($warehouses as $wh)
                                    <option value="{{ $wh->id }}" {{ ($sale && $sale->warehouse_id == $wh->id) ? 'selected' : '' }}>{{ $wh->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4 form-group">
                            <label class="font-weight-600 small">Customer *</label>
                            <select name="customer_id" class="form-control selectpicker" data-live-search="true" required>
                                @foreach($customers as $c)
                                    <option value="{{ $c->id }}" {{ ($sale && $sale->customer_id == $c->id) ? 'selected' : '' }}>{{ $c->name }} ({{ $c->phone_number }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4 form-group">
                            <label class="font-weight-600 small">Original Sale Reference</label>
                            <input type="text" class="form-control bg-light" value="{{ $sale ? $sale->reference_no : ($referenceNo ?: 'Direct Exchange (No Previous Ref)') }}" readonly>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section 1: Returned Products -->
            <div class="card border-0 shadow-sm rounded-lg mb-4">
                <div class="card-header bg-danger text-white py-3">
                    <h5 class="m-0 font-weight-bold"><i class="dripicons-return"></i> 1. Items Being Returned by Customer</h5>
                </div>
                <div class="card-body p-4">
                    <div class="table-responsive">
                        <table class="table table-bordered align-middle" id="returned-table">
                            <thead class="bg-light small uppercase">
                                <tr>
                                    <th style="width: 45%">Product</th>
                                    <th style="width: 15%">Return Qty</th>
                                    <th style="width: 20%">Unit Price</th>
                                    <th style="width: 20%">Return Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                @if($sale && $sale->productSales)
                                    @foreach($sale->productSales as $idx => $ps)
                                        <tr class="return-row">
                                            <td>
                                                <input type="hidden" name="returned_products[{{ $idx }}][product_id]" value="{{ $ps->product_id }}">
                                                <input type="hidden" name="returned_products[{{ $idx }}][name]" value="{{ $ps->product->name ?? 'Product' }}">
                                                <strong>{{ $ps->product->name ?? 'Product' }}</strong> ({{ $ps->product->code ?? '' }})
                                            </td>
                                            <td>
                                                <input type="number" step="any" min="0" max="{{ $ps->qty }}" name="returned_products[{{ $idx }}][qty]" class="form-control form-control-sm return-qty" value="0" oninput="calcExchangeTotals()">
                                            </td>
                                            <td>
                                                <input type="number" step="any" name="returned_products[{{ $idx }}][unit_price]" class="form-control form-control-sm return-price" value="{{ $ps->net_unit_price }}" readonly>
                                            </td>
                                            <td class="return-subtotal font-weight-bold text-danger text-right">0.00</td>
                                        </tr>
                                    @endforeach
                                @else
                                    <tr class="return-row">
                                        <td>
                                            <select name="returned_products[0][product_id]" class="form-control form-control-sm" onchange="updateProductReturnPrice(this, 0)">
                                                <option value="">Select product to return...</option>
                                                @foreach($products as $p)
                                                    <option value="{{ $p->id }}" data-price="{{ $p->price }}" data-name="{{ $p->name }}">{{ $p->name }} ({{ $p->code }}) — {{ number_format($p->price, 2) }}</option>
                                                @endforeach
                                            </select>
                                            <input type="hidden" name="returned_products[0][name]" id="ret-name-0">
                                        </td>
                                        <td>
                                            <input type="number" step="any" min="0" name="returned_products[0][qty]" class="form-control form-control-sm return-qty" value="1" oninput="calcExchangeTotals()">
                                        </td>
                                        <td>
                                            <input type="number" step="any" name="returned_products[0][unit_price]" id="ret-price-0" class="form-control form-control-sm return-price" value="0.00" oninput="calcExchangeTotals()">
                                        </td>
                                        <td class="return-subtotal font-weight-bold text-danger text-right">0.00</td>
                                    </tr>
                                @endif
                            </tbody>
                            <tfoot>
                                <tr class="bg-light">
                                    <th colspan="3" class="text-right">Total Returned Credit:</th>
                                    <th id="total-returned-display" class="text-right text-danger font-weight-bold">0.00</th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Section 2: Exchanged New Products -->
            <div class="card border-0 shadow-sm rounded-lg mb-4">
                <div class="card-header bg-success text-white py-3">
                    <h5 class="m-0 font-weight-bold"><i class="dripicons-cart"></i> 2. New Replacement Products Issued to Customer</h5>
                </div>
                <div class="card-body p-4">
                    <div class="table-responsive">
                        <table class="table table-bordered align-middle" id="exchanged-table">
                            <thead class="bg-light small uppercase">
                                <tr>
                                    <th style="width: 45%">Select Replacement Product</th>
                                    <th style="width: 15%">Quantity</th>
                                    <th style="width: 20%">Unit Price</th>
                                    <th style="width: 20%">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr class="exchange-row">
                                    <td>
                                        <select name="exchanged_products[0][product_id]" class="form-control form-control-sm" onchange="updateProductExchangePrice(this, 0)" required>
                                            <option value="">Select replacement item...</option>
                                            @foreach($products as $p)
                                                <option value="{{ $p->id }}" data-price="{{ $p->price }}" data-name="{{ $p->name }}">{{ $p->name }} ({{ $p->code }}) — Price: {{ number_format($p->price, 2) }}</option>
                                            @endforeach
                                        </select>
                                        <input type="hidden" name="exchanged_products[0][name]" id="exc-name-0">
                                    </td>
                                    <td>
                                        <input type="number" step="any" min="1" name="exchanged_products[0][qty]" class="form-control form-control-sm exchange-qty" value="1" oninput="calcExchangeTotals()">
                                    </td>
                                    <td>
                                        <input type="number" step="any" name="exchanged_products[0][unit_price]" id="exc-price-0" class="form-control form-control-sm exchange-price" value="0.00" oninput="calcExchangeTotals()">
                                    </td>
                                    <td class="exchange-subtotal font-weight-bold text-success text-right">0.00</td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr class="bg-light">
                                    <th colspan="3" class="text-right">Total Replacement Value:</th>
                                    <th id="total-exchanged-display" class="text-right text-success font-weight-bold">0.00</th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Summary & Payment Diff Card -->
            <div class="card border-0 shadow-lg rounded-lg mb-4 bg-dark text-white">
                <div class="card-body p-4">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <h4 class="font-weight-bold mb-1">Differential Settlement</h4>
                            <p class="text-muted small mb-0">The system balances returned credit against replacement cost.</p>
                        </div>
                        <div class="col-md-6 text-right">
                            <h2 class="font-weight-bold m-0" id="diff-display">₹ 0.00</h2>
                            <span class="badge badge-secondary px-3 py-1 mt-1" id="diff-status">Even Exchange</span>
                        </div>
                    </div>

                    <hr class="border-secondary my-3">

                    <div class="row">
                        <div class="col-md-4 form-group mb-0">
                            <label class="small text-muted font-weight-600">Payment / Settlement Method</label>
                            <select name="payment_method" class="form-control form-control-sm">
                                <option value="Cash">Cash</option>
                                <option value="Card">Credit/Debit Card</option>
                                <option value="UPI">UPI / QR Code</option>
                                <option value="Credit">Customer Account Credit</option>
                            </select>
                        </div>
                        <div class="col-md-8 form-group mb-0">
                            <label class="small text-muted font-weight-600">Exchange Reason / Note</label>
                            <input type="text" name="note" class="form-control form-control-sm" placeholder="e.g. Size exchange, defective replacement">
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-secondary p-3 text-right border-0">
                    <a href="{{ route('exchange.index') }}" class="btn btn-light px-4 mr-2">Cancel</a>
                    <button type="submit" class="btn btn-primary px-5 font-weight-bold">Confirm & Finalize Exchange</button>
                </div>
            </div>
        </form>
    </div>
</section>

@push('scripts')
<script>
function updateProductReturnPrice(elem, idx) {
    let opt = elem.options[elem.selectedIndex];
    let price = opt.getAttribute('data-price') || 0;
    let name = opt.getAttribute('data-name') || '';
    document.getElementById('ret-price-' + idx).value = price;
    document.getElementById('ret-name-' + idx).value = name;
    calcExchangeTotals();
}

function updateProductExchangePrice(elem, idx) {
    let opt = elem.options[elem.selectedIndex];
    let price = opt.getAttribute('data-price') || 0;
    let name = opt.getAttribute('data-name') || '';
    document.getElementById('exc-price-' + idx).value = price;
    document.getElementById('exc-name-' + idx).value = name;
    calcExchangeTotals();
}

function calcExchangeTotals() {
    let retTotal = 0;
    document.querySelectorAll('#returned-table .return-row').forEach(row => {
        let qty = parseFloat(row.querySelector('.return-qty').value) || 0;
        let price = parseFloat(row.querySelector('.return-price').value) || 0;
        let sub = qty * price;
        row.querySelector('.return-subtotal').innerText = sub.toFixed(2);
        retTotal += sub;
    });
    document.getElementById('total-returned-display').innerText = retTotal.toFixed(2);

    let excTotal = 0;
    document.querySelectorAll('#exchanged-table .exchange-row').forEach(row => {
        let qty = parseFloat(row.querySelector('.exchange-qty').value) || 0;
        let price = parseFloat(row.querySelector('.exchange-price').value) || 0;
        let sub = qty * price;
        row.querySelector('.exchange-subtotal').innerText = sub.toFixed(2);
        excTotal += sub;
    });
    document.getElementById('total-exchanged-display').innerText = excTotal.toFixed(2);

    let diff = excTotal - retTotal;
    let diffDisplay = document.getElementById('diff-display');
    let diffStatus = document.getElementById('diff-status');

    if (diff > 0) {
        diffDisplay.innerText = "+ ₹ " + diff.toFixed(2);
        diffDisplay.className = "font-weight-bold text-success m-0";
        diffStatus.innerText = "Customer Pays Difference";
        diffStatus.className = "badge badge-success px-3 py-1 mt-1";
    } else if (diff < 0) {
        diffDisplay.innerText = "- ₹ " + Math.abs(diff).toFixed(2);
        diffDisplay.className = "font-weight-bold text-danger m-0";
        diffStatus.innerText = "Store Issues Refund to Customer";
        diffStatus.className = "badge badge-danger px-3 py-1 mt-1";
    } else {
        diffDisplay.innerText = "₹ 0.00";
        diffDisplay.className = "font-weight-bold text-white m-0";
        diffStatus.innerText = "Even Exchange (No Money Due)";
        diffStatus.className = "badge badge-secondary px-3 py-1 mt-1";
    }
}
window.addEventListener('DOMContentLoaded', calcExchangeTotals);
</script>
@endpush

@endsection
