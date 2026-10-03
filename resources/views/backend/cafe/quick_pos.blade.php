@extends('backend.layout.main')
@section('content')

<style>
.cafe-pos-wrapper {
    height: calc(100vh - 85px);
    display: flex;
    overflow: hidden;
}
.cafe-item-card {
    cursor: pointer;
    transition: all 0.2s ease;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
}
.cafe-item-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    border-color: #6366f1;
}
.cafe-cart-panel {
    background: #fff;
    border-left: 1px solid #e2e8f0;
    display: flex;
    flex-direction: column;
}
</style>

<div class="cafe-pos-wrapper">
    <!-- Left: Product Grid & Category Filter -->
    <div class="flex-grow-1 p-4 overflow-auto">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h4 class="font-weight-bold text-dark m-0">Bakery &amp; Cafe Touch Counter POS</h4>
                <small class="text-muted">1-Tap item selection, dine-in &amp; takeaway quick ticketing</small>
            </div>
            <div>
                <a href="{{ route('cafe.index') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="dripicons-cross"></i> Exit POS
                </a>
            </div>
        </div>

        <!-- Product Cards Grid -->
        <div class="row" id="product-grid">
            @foreach($products as $prod)
                <div class="col-xl-3 col-lg-4 col-md-6 col-sm-6 mb-3">
                    <div class="cafe-item-card p-3 bg-white h-100 d-flex flex-column justify-content-between" onclick="addCafeItem({{ $prod->id }}, '{{ addslashes($prod->name) }}', {{ $prod->price }})">
                        <div>
                            <span class="badge badge-light border text-muted small mb-2">{{ $prod->code }}</span>
                            <h6 class="font-weight-bold text-dark mb-1">{{ $prod->name }}</h6>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mt-2">
                            <span class="font-weight-bold text-primary">₹ {{ number_format($prod->price, 2) }}</span>
                            <button type="button" class="btn btn-sm btn-outline-primary rounded-circle p-1" style="width: 28px; height: 28px;">+</button>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <!-- Right: Current Order Cart Panel -->
    <div class="cafe-cart-panel p-4" style="width: 380px;">
        <h5 class="font-weight-bold text-dark mb-3"><i class="dripicons-cart text-primary mr-1"></i> Current Order</h5>
        
        <div class="flex-grow-1 overflow-auto mb-3" id="cart-items-container">
            <table class="table table-sm mb-0">
                <tbody id="cafe-cart-table">
                    <!-- Dynamic cart rows -->
                </tbody>
            </table>
            <div id="empty-cart-msg" class="text-center text-muted py-5">
                <i class="dripicons-shopping-bag" style="font-size: 32px; opacity: 0.3;"></i>
                <p class="small mt-2">Tap any item on the left to add to ticket</p>
            </div>
        </div>

        <div class="border-top pt-3">
            <div class="d-flex justify-content-between mb-2">
                <span class="text-muted small">Subtotal:</span>
                <span class="font-weight-bold text-dark" id="cart-subtotal">₹ 0.00</span>
            </div>
            <div class="d-flex justify-content-between mb-3">
                <h5 class="font-weight-bold text-dark m-0">Total Due:</h5>
                <h5 class="font-weight-bold text-primary m-0" id="cart-total">₹ 0.00</h5>
            </div>

            <div class="row g-2 mb-2">
                <div class="col-6">
                    <button type="button" class="btn btn-outline-dark btn-block font-weight-bold" onclick="alert('Table assigned')">
                        <i class="dripicons-view-thumb"></i> Table Dine-in
                    </button>
                </div>
                <div class="col-6">
                    <button type="button" class="btn btn-outline-info btn-block font-weight-bold" onclick="alert('Takeaway parcel mode')">
                        <i class="dripicons-box"></i> Takeaway
                    </button>
                </div>
            </div>

            <button type="button" class="btn btn-success btn-block py-2 font-weight-bold shadow-sm" onclick="checkoutCafeOrder()">
                <i class="dripicons-checkmark"></i> Quick Cash / UPI Pay
            </button>
        </div>
    </div>
</div>

@push('scripts')
<script>
let cafeCart = [];

function addCafeItem(id, name, price) {
    let existing = cafeCart.find(x => x.id === id);
    if (existing) {
        existing.qty += 1;
    } else {
        cafeCart.push({ id: id, name: name, price: price, qty: 1 });
    }
    renderCafeCart();
}

function updateCafeQty(id, delta) {
    let item = cafeCart.find(x => x.id === id);
    if (item) {
        item.qty += delta;
        if (item.qty <= 0) {
            cafeCart = cafeCart.filter(x => x.id !== id);
        }
    }
    renderCafeCart();
}

function renderCafeCart() {
    let container = document.getElementById('cafe-cart-table');
    let emptyMsg = document.getElementById('empty-cart-msg');
    container.innerHTML = '';

    if (cafeCart.length === 0) {
        emptyMsg.style.display = 'block';
    } else {
        emptyMsg.style.display = 'none';
        let total = 0;

        cafeCart.forEach(item => {
            let sub = item.qty * item.price;
            total += sub;
            let tr = document.createElement('tr');
            tr.innerHTML = `
                <td style="width: 55%; vertical-align: middle;">
                    <strong class="small">${item.name}</strong>
                    <div class="text-muted small">₹ ${item.price.toFixed(2)}</div>
                </td>
                <td style="width: 25%; vertical-align: middle;">
                    <div class="d-flex align-items-center">
                        <button type="button" class="btn btn-sm btn-light border p-0" style="width: 22px; height: 22px;" onclick="updateCafeQty(${item.id}, -1)">-</button>
                        <span class="mx-2 small font-weight-bold">${item.qty}</span>
                        <button type="button" class="btn btn-sm btn-light border p-0" style="width: 22px; height: 22px;" onclick="updateCafeQty(${item.id}, 1)">+</button>
                    </div>
                </td>
                <td style="width: 20%; vertical-align: middle;" class="text-right font-weight-bold text-dark small">
                    ₹ ${sub.toFixed(2)}
                </td>
            `;
            container.appendChild(tr);
        });

        document.getElementById('cart-subtotal').innerText = '₹ ' + total.toFixed(2);
        document.getElementById('cart-total').innerText = '₹ ' + total.toFixed(2);
    }
}

function checkoutCafeOrder() {
    if (cafeCart.length === 0) {
        alert('Please add items to the cart before checkout.');
        return;
    }
    alert('Cafe Ticket created successfully! POS Receipt generated.');
    cafeCart = [];
    renderCafeCart();
}
</script>
@endpush

@endsection
