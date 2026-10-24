@extends('backend.layout.main') @section('content')

<x-error-message key="not_permitted" />
<x-purchase-command-bar active="suppliers" title="Edit supplier"></x-purchase-command-bar>

<section class="forms">
    <div class="container-fluid">
        <div class="card">
            <div class="card-header d-flex align-items-center">
                <h4>{{__('db.Update Supplier')}}: {{ $lims_supplier_data->name }}</h4>
            </div>
            <div class="card-body">
                @if($errors->any())<div class="alert alert-danger py-2 mb-2">{{ $errors->first() }}</div>@endif
                {!! Form::open(['route' => ['supplier.update', $lims_supplier_data->id], 'method' => 'put', 'files' => true, 'id' => 'supplier-form', 'data-enter-flow' => 'true']) !!}
                    @include('backend.supplier.partials.form', ['supplier' => $lims_supplier_data])
                {!! Form::close() !!}
            </div>
        </div>
    </div>
</section>

@endsection
