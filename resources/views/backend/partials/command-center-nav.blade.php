@php
    $salesCenter = $kind === 'sale';
    $centerRoute = $salesCenter ? 'sales.index' : 'purchases.index';
    $centerContext = (isset($context) && $context instanceof \App\Services\Platform\CompanyContext) ? $context : request()->attributes->get(\App\Services\Platform\CompanyContext::class);
    $centerPermission = app(\App\Services\Commercial\CommercialPermission::class);
    $centerCan = fn ($permission) => $centerContext && $centerPermission->allows($permission, $centerContext, Auth::id());
    $centerCurrent = $current ?? null; // 'returns' when included by the returns register
    $centerOrders = !$centerCurrent && request('view') === 'orders';
@endphp
@once
    @push('css')
        <link rel="stylesheet" href="{{ asset('css/command-center.css') }}">
    @endpush
@endonce
<nav class="command-center-nav" aria-label="{{ $salesCenter ? 'Sales' : 'Purchase' }} Command Center">
    @if($centerCan($salesCenter ? 'sales-index' : 'purchases-index'))
        <a href="{{ route($centerRoute) }}" @if(!$centerOrders && !$centerCurrent) aria-current="page" @endif>Bills</a>
        @if(config('commercial.enabled'))
            <a href="{{ route($centerRoute, ['orders' => 1]) }}" data-order-register>{{ $salesCenter ? 'Sales Orders' : 'Purchase Orders' }}</a>
            @if($salesCenter)<a href="{{ route($centerRoute, ['view' => 'orders', 'sale_status' => 2]) }}" @if($centerOrders) aria-current="page" @endif>Pending Sales</a>@endif
        @else
            <a href="{{ route($centerRoute, ['view' => 'orders', $salesCenter ? 'sale_status' : 'purchase_status' => $salesCenter ? 2 : 4]) }}" @if($centerOrders) aria-current="page" @endif>Orders</a>
        @endif
    @endif
    @if($centerCan($salesCenter ? 'returns-index' : 'purchase-return-index'))
        <a href="{{ route($salesCenter ? 'return-sale.index' : 'return-purchase.index') }}" @if($centerCurrent === 'returns') aria-current="page" @endif>Returns</a>
    @endif
    @if($salesCenter && $centerCan('quotes-index'))
        <a href="{{ route('quotations.index') }}">Quotations</a>
    @elseif(!$salesCenter && $centerCan('suppliers-index'))
        <a href="{{ route('supplier.index') }}">Suppliers</a>
    @endif
</nav>
@if($withDrafts ?? true)
@include('backend.partials.document-draft-tabs', ['draftLabel' => $salesCenter ? 'sales' : 'purchase', 'draftKey' => $salesCenter ? 'F2' : 'F12'])
@endif
