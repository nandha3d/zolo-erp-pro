@php
    $salesCenter = $kind === 'sale';
    $centerRoute = $salesCenter ? 'sales.index' : 'purchases.index';
    $centerContext = request()->attributes->get(\App\Services\Platform\CompanyContext::class);
    $centerPermission = app(\App\Services\Commercial\CommercialPermission::class);
    $centerCan = fn ($permission) => $centerContext && $centerPermission->allows($permission, $centerContext, Auth::id());
    $centerOrders = request('view') === 'orders';
@endphp
@once
    @push('css')
        <link rel="stylesheet" href="{{ asset('css/command-center.css') }}">
    @endpush
@endonce
<nav class="command-center-nav" aria-label="{{ $salesCenter ? 'Sales' : 'Purchase' }} Command Center">
    @if($centerCan($salesCenter ? 'sales-index' : 'purchases-index'))
        <a href="{{ route($centerRoute) }}" @if(!$centerOrders) aria-current="page" @endif>Bills</a>
        <a href="{{ route($centerRoute, ['view' => 'orders', $salesCenter ? 'sale_status' : 'purchase_status' => $salesCenter ? 2 : 4]) }}" @if($centerOrders) aria-current="page" @endif>Orders</a>
    @endif
    @if($centerCan($salesCenter ? 'returns-index' : 'purchase-return-index'))
        <a href="{{ route($salesCenter ? 'return-sale.index' : 'return-purchase.index') }}">Returns</a>
    @endif
    @if($salesCenter && $centerCan('quotes-index'))
        <a href="{{ route('quotations.index') }}">Quotations</a>
    @elseif(!$salesCenter && $centerCan('suppliers-index'))
        <a href="{{ route('supplier.index') }}">Suppliers</a>
    @endif
</nav>
@include('backend.partials.document-draft-tabs', ['draftLabel' => $salesCenter ? 'sales' : 'purchase', 'draftKey' => $salesCenter ? 'F2' : 'F12'])
