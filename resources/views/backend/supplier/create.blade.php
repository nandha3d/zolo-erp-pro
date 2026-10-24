@extends('backend.layout.main') @section('content')

<x-error-message key="not_permitted" />
<x-purchase-command-bar active="suppliers" title="Add supplier"></x-purchase-command-bar>

<section class="forms">
    <div class="container-fluid">
        <div class="card">
            <div class="card-header d-flex align-items-center">
                <h4>{{__('db.Add Supplier')}}</h4>
            </div>
            <div class="card-body">
                @if($errors->any())<div class="alert alert-danger py-2 mb-2">{{ $errors->first() }}</div>@endif
                {!! Form::open(['route' => 'supplier.store', 'method' => 'post', 'files' => true, 'id' => 'supplier-form', 'data-enter-flow' => 'true']) !!}
                    @include('backend.supplier.partials.form', ['supplier' => null])
                {!! Form::close() !!}
            </div>
        </div>
    </div>
</section>

@endsection
