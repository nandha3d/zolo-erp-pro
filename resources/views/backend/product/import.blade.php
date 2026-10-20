@extends('backend.layout.main')
@section('content')
<section class="p-3">
    <x-product-command-bar active="import" />
    <x-success-message key="message" />
    @if($errors->any())<div class="alert alert-danger" role="alert">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif
    <div class="card p-3 mb-3">
        <h5>Reviewed item import</h5>
        <p>Create up to 500 new standard, service or digital items from a UTF-8 CSV (maximum 1 MB). Review every row before confirming the whole file. Existing items are never updated. Opening stock, variants and images use the normal item form.</p>
        <a href="{{ route('products.import.template') }}" class="mb-2">Download CSV template</a>
        <form action="{{ route('product.import') }}" method="post" enctype="multipart/form-data" class="d-flex align-items-center flex-wrap">
            @csrf
            <label for="product-import-file" class="mr-2">CSV file</label><input id="product-import-file" type="file" name="file" accept=".csv,text/csv" required class="mr-3">
            <button class="btn btn-primary" type="submit">Preview rows</button>
        </form>
        <details class="mt-2"><summary>Company master IDs for the CSV</summary>
            @foreach($masters as $table => $records)
                <h6 class="mt-2">{{ str_replace('_', ' ', ucfirst($table)) }}</h6>
                <div class="small">@foreach($records as $record)<span class="mr-3">{{ $record->id }}: {{ $record->name ?? $record->unit_name ?? '' }}</span>@endforeach</div>
            @endforeach
        </details>
    </div>
    @if($preview)
        <div class="card p-3">
            <h5>Preview #{{ $preview->id }} — {{ ucfirst($preview->status) }}</h5>
            <p>{{ count($preview->rows) }} rows. All rows must pass validation. Preview expires {{ $preview->expires_at }}.</p>
            <div class="table-responsive"><table class="table table-sm" id="product-import-preview">
                <thead><tr><th>CSV row</th><th>Name</th><th>Code</th><th>Type</th><th>Group</th><th>Base / sale / purchase unit</th><th>Cost / price</th><th>HSN / SAC</th><th>Brand / tax / stock policy</th><th>Validation</th></tr></thead>
                <tbody>@foreach($preview->rows as $row)<tr>
                    <td>{{ $row['row'] }}</td><td>{{ $row['data']['name'] ?? '' }}</td><td>{{ $row['data']['code'] ?? '' }}</td><td>{{ $row['data']['type'] ?? '' }}</td>
                    <td>{{ $row['data']['category_id'] ?? '' }}</td><td>{{ $row['data']['unit_id'] ?? '' }} / {{ $row['data']['sale_unit_id'] ?? '' }} / {{ $row['data']['purchase_unit_id'] ?? '' }}</td>
                    <td>{{ $row['data']['cost'] ?? 0 }} / {{ $row['data']['price'] ?? 0 }}</td><td>{{ $row['data']['hsn_code'] ?? '' }}</td>
                    <td>Brand: {{ $row['data']['brand_id'] ?? 'None' }}<br>Legacy tax: {{ $row['data']['tax_id'] ?? 'None' }}<br>GST category: {{ $row['data']['tax_category_id'] ?? 'None' }}<br>Negative stock: {{ $row['data']['negative_stock_policy'] ?? 'Inherit' }}</td>
                    <td>@forelse($row['errors'] as $field => $messages)<div class="text-danger">{{ $field }}: {{ implode(' ', $messages) }}</div>@empty Valid @endforelse</td>
                </tr>@endforeach</tbody>
            </table></div>
            @if($preview->can_commit)
                <form action="{{ route('products.import.commit', $preview->id) }}" method="post">@csrf
                    <label><input type="checkbox" name="confirm" value="1" required> I reviewed every row. Create all {{ count($preview->rows) }} items.</label>
                    <button class="btn btn-primary ml-2" type="submit">Create reviewed items</button>
                </form>
            @elseif($preview->status === 'committed')
                <p class="text-success">Created {{ count($preview->results) }} items. Repeating confirmation returns the same result.</p>
            @else <p class="text-danger">No items created. Correct the errors or refresh an expired preview.</p> @endif
        </div>
    @endif
</section>
@endsection
