@extends('backend.layout.main')

@section('content')

@push('css')
<link rel="stylesheet" href="{{ versioned_asset('css/product-workspace.css') }}" type="text/css">
<link rel="stylesheet" href="{{ versioned_asset('css/pages/parties-workspace.css') }}" type="text/css">
@endpush

<x-error-message key="not_permitted" />

<x-party-command-bar active="suppliers" title="{{ __('db.Supplier') }} Command Center" badge="{{ __('db.Add Supplier') }}">
    <a href="{{ route('supplier.index') }}" class="btn btn-outline-secondary">
        <i class="dripicons-arrow-left"></i> {{ __('db.Back') }}
    </a>
</x-party-command-bar>

<div class="supplier-workspace-view">
    <div class="party-single-card">
        <div class="party-card-header">
            <div class="party-card-title-group">
                <h4><i class="dripicons-user-plus text-primary"></i> {{ __('db.Add Supplier') }}</h4>
                <div class="party-hint">{{ __('db.The field labels marked with * are required input fields') }}.</div>
            </div>
            <div class="party-card-actions">
                <a href="{{ route('supplier.index') }}" class="btn btn-outline-secondary">
                    <i class="dripicons-cross"></i> {{ __('db.Cancel') }}
                </a>
                <button type="submit" form="supplier-form" class="btn btn-primary">
                    <i class="dripicons-checkmark"></i> {{ __('db.submit') }}
                </button>
            </div>
        </div>

        <div class="party-card-body">
            @if($errors->any())
                <div class="alert alert-danger py-2 mb-3">{{ $errors->first() }}</div>
            @endif

            {!! Form::open(['route' => 'supplier.store', 'method' => 'post', 'files' => true, 'id' => 'supplier-form', 'data-enter-flow' => 'true']) !!}
                @include('backend.supplier.partials.form', ['supplier' => null])
            {!! Form::close() !!}
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script type="text/javascript">
    $("ul#parties").siblings('a').attr('aria-expanded','true');
    $("ul#parties").addClass("show");
    $("ul#parties #supplier-list-menu").addClass("active");
</script>
@endpush
