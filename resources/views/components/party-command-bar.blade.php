@props(['active' => null, 'title' => null, 'badge' => null])
@php
    $user = Auth::user();
    $admin = $user && in_array((int) $user->role_id, [1, 2], true);
    $allow = function (string $permission) use ($user, $admin) {
        if ($admin) return true;
        try { return $user && $user->can($permission); } catch (\Throwable $e) { return false; }
    };
    $canMasters = $admin || $allow('sidebar_product');
    $switches = app(\App\Services\Platform\MenuSwitches::class);

    $links = [
        ['key' => 'customers', 'allowed' => $allow('customers-index'), 'route' => 'customer.index', 'icon' => 'dripicons-user-group', 'label' => 'Customers'],
        ['key' => 'customer_groups', 'allowed' => $canMasters || $allow('customer_group'), 'route' => 'customer_group.index', 'icon' => 'dripicons-folder', 'label' => 'Customer Groups'],
        ['key' => 'suppliers', 'allowed' => $allow('suppliers-index'), 'route' => 'supplier.index', 'icon' => 'dripicons-user', 'label' => 'Suppliers'],
        ['key' => 'agents', 'allowed' => $canMasters, 'route' => 'agent.index', 'icon' => 'dripicons-briefcase', 'label' => 'Agents / Brokers'],
        ['key' => 'areas', 'allowed' => $canMasters, 'route' => 'area.index', 'icon' => 'dripicons-location', 'label' => 'Areas / Routes'],
    ];
@endphp
@once
    @push('css')
        <link rel="stylesheet" href="{{ versioned_asset('css/product-workspace.css') }}" type="text/css">
        <link rel="stylesheet" href="{{ versioned_asset('css/pages/parties-workspace.css') }}" type="text/css">
    @endpush
@endonce
<div class="comm-command-bar party-command-bar section-command-bar">
    <div class="comm-title-group">
        <h1><i class="dripicons-user text-primary"></i> {{ $title ?? 'Parties Command Center' }}</h1>
        <span class="comm-title-badge">{{ $badge ?? 'Customer, Supplier, Broker & Area Master Directory' }}</span>
    </div>
    <ul class="comm-nav-pills" aria-label="Parties screens">
        @foreach($links as $link)
            @if(Route::has($link['route']) && $link['allowed'] && $switches->routeAllowed($link['route']))
                <li>
                    <a class="nav-link {{ $active === $link['key'] ? 'active' : '' }}" href="{{ route($link['route']) }}" @if($active === $link['key']) aria-current="page" @endif>
                        <i class="{{ $link['icon'] }}"></i> {{ $link['label'] }}
                    </a>
                </li>
            @endif
        @endforeach
    </ul>
    <div class="comm-actions">{{ $slot }}</div>
</div>
