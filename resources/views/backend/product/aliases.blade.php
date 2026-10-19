@extends('backend.layout.main')
@section('content')
<section class="container-fluid py-3">
    <x-product-command-bar active="aliases" />
    <h1 class="h4">Alternate product names</h1>
    <p>Names select the existing product. Party names apply only when that customer or supplier is selected. Shared names require explicit product selection.</p>
    @if(session('message'))<div class="alert alert-success">{{ session('message') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
    @if($canEdit)
    <form method="post" action="{{ route('product-aliases.store') }}" class="card card-body mb-3" data-alias-form>
        @csrf
        <div class="row">
            <label class="col-md-4">Product<select name="product_id" class="form-control selectpicker" data-live-search="true" required><option value="">Select product</option>@foreach($products as $product)<option value="{{ $product->id }}">{{ $product->code }} · {{ $product->name }}</option>@endforeach</select></label>
            <label class="col-md-3">Alternate name<input name="name" class="form-control" required maxlength="150" value="{{ old('name') }}"></label>
            <label class="col-md-2">Scope<select name="party_type" class="form-control"><option value="company">Company</option><option value="customer">Customer</option><option value="supplier">Supplier</option></select></label>
            <label class="col-md-3" data-alias-party hidden>Party<select class="form-control" name="party_id" disabled><option value="">Select party</option>@foreach($customers as $party)<option data-kind="customer" value="{{ $party->id }}">{{ $party->name }}</option>@endforeach @foreach($suppliers as $party)<option data-kind="supplier" value="{{ $party->id }}">{{ $party->name }}</option>@endforeach</select></label>
        </div><button type="submit" class="btn btn-primary align-self-start">Save alternate name</button>
    </form>
    @endif
    <form method="get" class="mb-2"><label>Find alternate name<input name="q" class="form-control" value="{{ request('q') }}" maxlength="150"></label><button class="btn btn-secondary">Search</button></form>
    <div class="table-responsive"><table class="table table-sm"><thead><tr><th>Product</th><th>Alternate name</th><th>Scope</th><th>Created</th><th></th></tr></thead><tbody>
        @foreach($aliases as $alias)<tr><td>{{ $alias->code }} · {{ $alias->product_name }}</td><td>{{ $alias->name }}</td><td>{{ ucfirst($alias->party_type) }}@if($alias->party_id) · {{ ($alias->party_type === 'customer' ? $customers : $suppliers)->firstWhere('id', $alias->party_id)?->name ?? '#'.$alias->party_id }}@endif</td><td>{{ $alias->created_at }}</td><td>@if($canEdit)<form method="post" action="{{ route('product-aliases.destroy', $alias->id) }}">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Remove</button></form>@endif</td></tr>@endforeach
    </tbody></table></div>{{ $aliases->links() }}
</section>
@endsection
@push('scripts')<script src="{{ asset('js/product-aliases.js') }}?v={{ filemtime(public_path('js/product-aliases.js')) }}"></script>@endpush
