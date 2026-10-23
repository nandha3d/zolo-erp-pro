{{-- Settings Command Center bar: dark command bar with navigation pills across system configuration & master screens.
     Usage: <x-settings-command-bar active="warehouse"> action buttons </x-settings-command-bar> --}}
@props(['active' => null])
@php
    $user = Auth::user();
    $roleId = $user ? (int) $user->role_id : 0;
    $admin = $user && in_array($roleId, [1, 2], true);
    $allow = function (string $permission) use ($user, $admin) {
        if ($admin) return true;
        try { return $user && $user->can($permission); } catch (\Throwable $e) { return false; }
    };
    $links = [
        ['key' => 'general', 'permission' => 'general_setting', 'route' => 'setting.general', 'icon' => 'dripicons-gear', 'label' => __('db.General Setting')],
        ['key' => 'company', 'permission' => 'general_setting', 'route' => 'settings.company', 'icon' => 'dripicons-store', 'label' => 'Company profile', 'adminOnly' => true],
        ['key' => 'gst', 'permission' => 'tax', 'route' => \App\Services\Tax\GstSetupService::usesGst() ? 'settings.gst' : 'tax.index', 'icon' => 'dripicons-ticket', 'label' => \App\Services\Tax\GstSetupService::usesGst() ? 'GST & Tax' : __('db.Tax')],
        ['key' => 'warehouse', 'permission' => 'warehouse', 'route' => 'warehouse.index', 'icon' => 'dripicons-home', 'label' => __('db.Warehouse')],
        ['key' => 'customer_group', 'permission' => 'customer_group', 'route' => 'customer_group.index', 'icon' => 'dripicons-user-group', 'label' => __('db.Customer Group')],
        ['key' => 'currency', 'permission' => 'currency', 'route' => 'currency.index', 'icon' => 'dripicons-view-thumb', 'label' => __('db.Currency')],
        ['key' => 'role', 'permission' => 'role_permission', 'route' => 'role.index', 'icon' => 'dripicons-lock', 'label' => __('db.Role Permission')],
        ['key' => 'invoice', 'permission' => 'invoice_setting', 'route' => 'settings.invoice.index', 'icon' => 'dripicons-document', 'label' => __('db.Invoice Settings')],
        ['key' => 'pos', 'permission' => 'pos_setting', 'route' => 'setting.pos', 'icon' => 'dripicons-shopping-bag', 'label' => 'POS Settings'],
        ['key' => 'printers', 'permission' => 'general_setting', 'route' => 'printers.index', 'icon' => 'dripicons-print', 'label' => __('db.Receipt Printers'), 'adminOnly' => true],
        ['key' => 'barcode', 'permission' => 'barcode_setting', 'route' => 'barcodes.index', 'icon' => 'dripicons-code', 'label' => __('db.Barcode Settings')],
        ['key' => 'discount_plan', 'permission' => 'discount_plan', 'route' => 'discount-plans.index', 'icon' => 'dripicons-tag', 'label' => __('db.Discount Plan')],
        ['key' => 'discount', 'permission' => 'discount', 'route' => 'discounts.index', 'icon' => 'dripicons-tags', 'label' => __('db.Discount')],
        ['key' => 'custom_field', 'permission' => 'custom_field', 'route' => 'custom-fields.index', 'icon' => 'dripicons-list', 'label' => __('db.Custom Field List')],
        ['key' => 'payment_gateway', 'permission' => 'payment_gateway_setting', 'route' => 'setting.gateway', 'icon' => 'dripicons-card', 'label' => __('db.Payment Gateways')],
        ['key' => 'mail', 'permission' => 'mail_setting', 'route' => 'setting.mail', 'icon' => 'dripicons-mail', 'label' => __('db.Mail Setting')],
        ['key' => 'sms', 'permission' => 'sms_setting', 'route' => 'setting.sms', 'icon' => 'dripicons-message', 'label' => __('db.SMS Setting')],
        ['key' => 'sms_template', 'permission' => 'role_permission', 'route' => 'smstemplates.index', 'icon' => 'dripicons-document-edit', 'label' => __('db.SMS Template')],
        ['key' => 'reward_point', 'permission' => 'reward_point_setting', 'route' => 'setting.rewardPoint', 'icon' => 'dripicons-trophy', 'label' => __('db.Reward Point Setting')],
        ['key' => 'hrm', 'permission' => 'hrm_setting', 'route' => 'setting.hrm', 'icon' => 'dripicons-user', 'label' => __('db.HRM Setting')],
    ];
@endphp
@once
    @push('css')
        <link rel="stylesheet" href="{{ versioned_asset('css/settings-workspace.css') }}" type="text/css">
    @endpush
@endonce
<div class="comm-command-bar settings-command-bar">
    <div class="comm-title-group">
        <h1><i class="dripicons-gear text-primary"></i> Settings</h1>
        <span class="comm-title-badge"><i class="dripicons-toggles"></i> Configuration &amp; Masters</span>
    </div>
    <ul class="comm-nav-pills" aria-label="Settings screens">
        @foreach($links as $link)
            @php
                if (!empty($link['adminOnly']) && !$admin) continue;
            @endphp
            @if(Route::has($link['route']) && $allow($link['permission']))
                <li>
                    <a class="nav-link {{ $active === $link['key'] ? 'active' : '' }}" 
                       href="{{ route($link['route']) }}" 
                       @if($active === $link['key']) aria-current="page" @endif>
                        <i class="{{ $link['icon'] }}"></i> {{ $link['label'] }}
                    </a>
                </li>
            @endif
        @endforeach
    </ul>
    <div class="comm-actions">{{ $slot }}</div>
</div>
