@extends('backend.layout.main')
@section('content')
<style>
/* Fits the screen: headers wrap, product/warehouse text wraps, numbers stay on one line. */
#stock-lot-report .table { width: 100%; }
#stock-lot-report .table td { white-space: nowrap; }
#stock-lot-report .table th { white-space: normal; }
#stock-lot-report .table td:nth-child(3) { white-space: normal; }
#stock-lot-report .table th:first-child, #stock-lot-report .table td:first-child { min-width: 160px; white-space: normal; }
</style>
<x-product-command-bar active="stock-by-lot"></x-product-command-bar>
<section id="stock-lot-report" class="container-fluid py-3">
    <h1 class="h4">Stock by lot</h1>
    <p>Warehouse quantities use applied stock movements, including reversals. Base units apply to each row.</p>
    <form class="row align-items-end mb-3" method="get">
        <div class="col-md-2 form-group"><label for="lot-from">From</label><input id="lot-from" type="date" name="from" class="form-control" value="{{ $filters['from'] }}" required></div>
        <div class="col-md-2 form-group"><label for="lot-to">To</label><input id="lot-to" type="date" name="to" class="form-control" value="{{ $filters['to'] }}" required></div>
        <div class="col-md-2 form-group"><label for="lot-product">Product</label><select id="lot-product" name="product_id" class="form-control"><option value="">All products</option>@foreach($products as $product)<option value="{{ $product->id }}" @selected(($filters['product_id'] ?? '') == $product->id)>{{ $product->name }}</option>@endforeach</select></div>
        <div class="col-md-2 form-group"><label for="lot-warehouse">Warehouse / Godown</label><select id="lot-warehouse" name="warehouse_id" class="form-control"><option value="">All branch warehouses</option>@foreach($warehouses as $warehouse)<option value="{{ $warehouse->id }}" @selected(($filters['warehouse_id'] ?? '') == $warehouse->id)>{{ $warehouse->name }}</option>@endforeach</select></div>
        <div class="col-md-2 form-group"><label for="lot-number">Lot (exact)</label><input id="lot-number" name="lot" maxlength="100" class="form-control" value="{{ $filters['lot'] ?? '' }}" placeholder="__unbatched__ for no lot"></div>
        <div class="col-md-2 form-group"><button class="btn btn-primary">Apply</button> <button name="export" value="csv" class="btn btn-outline-primary">Export all</button></div>
    </form>
    @if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
    <div class="alert alert-info">Customer custody, GDNI and owned totals show <strong>current balances only</strong>. Historical owned totals are unavailable. Owned current = warehouse current + customer custody current. GDNI is carried accrual value, not independent lot costing; inventory uses item weighted-average valuation.</div>
    <div class="table-responsive"><table class="table table-striped table-sm"><caption>{{ $rows->total() }} filtered rows. Quantities in each product's base unit.</caption>
        <thead><tr><th>Product</th><th>Lot</th><th>Warehouse / Godown</th><th>Unit</th><th>Opening</th><th>Inward</th><th>Outward</th><th>Closing</th><th>Warehouse current</th><th>Custody current</th><th>GDNI current value</th><th>Owned current</th></tr></thead>
        <tbody>@forelse($rows as $row)<tr><td>{{ $row->product }} <small>{{ $row->product_code }}</small></td><td>{{ $row->lot ?: 'Unbatched' }}</td><td>{{ $row->warehouse }}</td><td>{{ $row->base_unit }}</td>
        @foreach(['opening_qty','inward_qty','outward_qty','closing_qty','current_qty','custody_qty','custody_value','owned_current_qty'] as $column)<td class="text-right">{{ number_format((float) $row->$column, 4) }}</td>@endforeach
        </tr>@empty<tr><td colspan="12">No stock movements or current customer custody match these filters.</td></tr>@endforelse</tbody>
    </table></div>
    {{ $rows->links() }}
</section>
@endsection
