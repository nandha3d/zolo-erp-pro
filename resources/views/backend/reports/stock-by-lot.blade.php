@extends('backend.layout.main')
@section('content')
@php
    // Quantities print without padding zeros (1,200 · 9.1 · 0.125); values keep two decimals.
    $qty = fn ($value) => rtrim(rtrim(number_format((float) $value, 4), '0'), '.') ?: '0';
    $page = collect($rows->items());
    $quantities = ['opening_qty' => 'Opening', 'inward_qty' => 'Inward', 'outward_qty' => 'Outward', 'closing_qty' => 'Closing',
        'current_qty' => 'Warehouse now', 'custody_qty' => 'Custody now', 'custody_value' => 'GDNI value', 'owned_current_qty' => 'Owned now'];
    // Columns that are zero on every row shown carry no information; they are named in the caption instead.
    $optional = ['custody_qty', 'custody_value'];
    $zero = $page->isNotEmpty() ? array_values(array_filter($optional, fn ($column) => $page->every(fn ($row) => (float) $row->$column == 0.0))) : [];
    $shown = array_diff_key($quantities, array_flip($zero));
    $oneGodown = $page->pluck('warehouse')->unique()->count() === 1 ? $page->first()->warehouse : null;
@endphp
<style>
#stock-lot-report { padding: 8px 14px 0; }
#stock-lot-report .lot-head { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-bottom: 6px; }
#stock-lot-report .lot-head h1 { font-size: 16px; font-weight: 700; margin: 0; white-space: nowrap; }
#stock-lot-report .lot-filters { display: flex; align-items: flex-end; gap: 8px; flex-wrap: wrap; margin: 0 0 6px; }
#stock-lot-report .lot-filters label { display: block; font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: #64748b; margin: 0 0 2px; }
#stock-lot-report .lot-filters .form-control { height: 32px; font-size: 13px; padding: 3px 8px; }
#stock-lot-report .lot-filters .f-date { width: 128px; }
#stock-lot-report .lot-filters .f-pick { width: 160px; }
#stock-lot-report .lot-filters .btn { height: 32px; padding: 0 14px; font-size: 13px; display: inline-flex; align-items: center; }
#stock-lot-report .lot-note { font-size: 11.5px; color: #64748b; margin: 0 0 6px; }
#stock-lot-report .table { width: 100%; margin: 0; font-size: 13px; }
#stock-lot-report .table th { white-space: nowrap; font-size: 10.5px; text-transform: uppercase; letter-spacing: .04em; color: #475569; vertical-align: middle; position: sticky; top: 0; background: #f8fafc; z-index: 1; }
#stock-lot-report .table td { white-space: nowrap; vertical-align: middle; }
#stock-lot-report .table td.lot-product { max-width: 260px; overflow: hidden; text-overflow: ellipsis; }
#stock-lot-report .table td.lot-product small { color: #64748b; margin-left: 4px; }
#stock-lot-report caption { caption-side: top; padding: 0 0 4px; font-size: 11.5px; color: #64748b; }
body.dark-mode #stock-lot-report .table th { background: #1e293b; color: #cbd5e1; }
</style>
<x-product-command-bar active="stock-by-lot"></x-product-command-bar>
<section id="stock-lot-report" class="container-fluid">
    <form class="lot-filters" method="get">
        <div class="lot-head"><h1>Stock by lot</h1></div>
        <div><label for="lot-from">From</label><input id="lot-from" type="date" name="from" class="form-control f-date" value="{{ $filters['from'] }}" required></div>
        <div><label for="lot-to">To</label><input id="lot-to" type="date" name="to" class="form-control f-date" value="{{ $filters['to'] }}" required></div>
        <div><label for="lot-product">Product</label><select id="lot-product" name="product_id" class="form-control f-pick"><option value="">All products</option>@foreach($products as $product)<option value="{{ $product->id }}" @selected(($filters['product_id'] ?? '') == $product->id)>{{ $product->name }}</option>@endforeach</select></div>
        <div><label for="lot-warehouse">Warehouse / Godown</label><select id="lot-warehouse" name="warehouse_id" class="form-control f-pick"><option value="">All branch warehouses</option>@foreach($warehouses as $warehouse)<option value="{{ $warehouse->id }}" @selected(($filters['warehouse_id'] ?? '') == $warehouse->id)>{{ $warehouse->name }}</option>@endforeach</select></div>
        <div><label for="lot-number">Lot (exact)</label><input id="lot-number" name="lot" maxlength="100" class="form-control f-pick" value="{{ $filters['lot'] ?? '' }}" placeholder="__unbatched__ for no lot"></div>
        <div class="d-flex" style="gap:6px;"><button class="btn btn-primary">Apply</button><button name="export" value="csv" class="btn btn-outline-primary">Export all</button></div>
    </form>
    @if($errors->any())<div class="alert alert-danger py-1 mb-2" role="alert">{{ $errors->first() }}</div>@endif
    <p class="lot-note" title="Historical owned totals are unavailable. Owned now = warehouse now + customer custody now. GDNI is carried accrual value, not independent lot costing; inventory uses item weighted-average valuation.">
        <i class="dripicons-information"></i> Custody, GDNI and owned columns are <strong>current balances only</strong> (no history). Quantities are in each product's base unit.
    </p>
    <div class="table-responsive"><table class="table table-striped table-sm">
        <caption>
            {{ $rows->total() }} rows
            @if($oneGodown) · Godown: <strong>{{ $oneGodown }}</strong>@endif
            @if($zero) · {{ implode(' and ', array_map(fn ($column) => $quantities[$column], $zero)) }} hidden (zero on every row)@endif
        </caption>
        <thead><tr><th>Product</th><th>Lot</th>@unless($oneGodown)<th>Warehouse / Godown</th>@endunless<th>Unit</th>@foreach($shown as $label)<th class="text-right">{{ $label }}</th>@endforeach</tr></thead>
        <tbody>@forelse($rows as $row)<tr>
            <td class="lot-product" title="{{ $row->product }} {{ $row->product_code }}">{{ $row->product }}<small>{{ $row->product_code }}</small></td>
            <td>{{ $row->lot ?: 'Unbatched' }}</td>
            @unless($oneGodown)<td>{{ $row->warehouse }}</td>@endunless
            <td>{{ $row->base_unit }}</td>
            @foreach(array_keys($shown) as $column)<td class="text-right">{{ $column === 'custody_value' ? number_format((float) $row->$column, 2) : $qty($row->$column) }}</td>@endforeach
        </tr>@empty<tr><td colspan="{{ 4 + count($shown) }}">No stock movements or current customer custody match these filters.</td></tr>@endforelse</tbody>
    </table></div>
    {{ $rows->links() }}
</section>
@endsection
