{{-- Product & Inventory Command Center bar: same dark bar as the Purchase/Sales/Supplier centers.
     Usage: <x-product-command-bar active="category"> page action buttons </x-product-command-bar> --}}
@props(['active' => null])
@php
    $user = Auth::user();
    $capabilities = app(\App\Services\Platform\CapabilityService::class)->forNavigation();
    $admin = $user && in_array((int) $user->role_id, [1, 2], true);
    $allow = function (string $permission) use ($user, $admin) {
        if ($admin) return true;
        try { return $user && $user->can($permission); } catch (\Throwable $e) { return false; }
    };
    $links = [
        ['key' => 'products', 'permission' => 'products-index', 'route' => 'products.index', 'icon' => 'dripicons-list', 'label' => __('db.product_list')],
        ['key' => 'aliases', 'permission' => 'products-index', 'route' => 'product-aliases.index', 'icon' => 'dripicons-tag', 'label' => 'Alternate names'],
        ['key' => 'import', 'permission' => 'products-import', 'route' => 'products.import.index', 'icon' => 'dripicons-upload', 'label' => 'Reviewed import'],
        ['key' => 'category', 'permission' => 'categories-index', 'route' => 'category.index', 'icon' => 'dripicons-folder', 'label' => __('db.category')],
        ['key' => 'brand', 'permission' => 'brand', 'route' => 'brand.index', 'icon' => 'dripicons-tag', 'label' => __('db.Brand')],
        ['key' => 'unit', 'permission' => 'unit', 'route' => 'unit.index', 'icon' => 'dripicons-scale', 'label' => __('db.Unit')],
        ['key' => 'stock-by-lot', 'permission' => 'products-index', 'route' => 'inventory-reports.stock-by-lot', 'icon' => 'dripicons-stack', 'label' => 'Stock by lot'],
        ['key' => 'adjustment', 'permission' => 'adjustment', 'route' => 'qty_adjustment.index', 'icon' => 'dripicons-swap', 'label' => 'Adjustments'],
        ['key' => 'stock-count', 'permission' => 'stock_count', 'route' => 'stock-count.index', 'icon' => 'dripicons-checklist', 'label' => __('db.Stock Count')],
        ['key' => 'barcode', 'permission' => 'print_barcode', 'route' => 'product.printBarcode', 'icon' => 'dripicons-print', 'label' => __('db.print_barcode')],
    ];
    if (in_array('inventory.damage_stock', $capabilities, true)) {
        $links[] = ['key' => 'damage', 'permission' => 'adjustment', 'route' => 'damage-stock.index', 'icon' => 'dripicons-warning', 'label' => 'Damage'];
    }
@endphp
@once
    @push('css')
        <link rel="stylesheet" href="{{ versioned_asset('css/product-workspace.css') }}" type="text/css">
    @endpush
@endonce
<div class="comm-command-bar product-command-bar">
    <div class="comm-title-group">
        <h1><i class="dripicons-list text-success"></i> Product &amp; Inventory</h1>
        <span class="comm-title-badge"><i class="dripicons-box"></i> Item master, stock &amp; adjustments</span>
    </div>
    <ul class="comm-nav-pills" aria-label="Product and inventory screens">
        @foreach($links as $link)
            @if(Route::has($link['route']) && $allow($link['permission']))
                <li><a class="nav-link {{ $active === $link['key'] ? 'active' : '' }}" href="{{ route($link['route']) }}" @if($active === $link['key']) aria-current="page" @endif><i class="{{ $link['icon'] }}"></i> {{ $link['label'] }}</a></li>
            @endif
        @endforeach
        @if(config('operations.enabled') && in_array('operations.job_work', $capabilities, true))
            <li><a class="nav-link {{ $active === 'job-work' ? 'active' : '' }}" href="{{ url('/operations/job-work') }}"><i class="dripicons-network-3"></i> Job Work</a></li>
        @endif
    </ul>
    <div class="comm-actions">{{ $slot }}</div>
</div>
