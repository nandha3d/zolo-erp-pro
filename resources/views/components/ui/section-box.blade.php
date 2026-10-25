@props([
    'title' => null,
    'subtitle' => null,
    'icon' => null,
    'actions' => null,
    'variant' => 'default', // default, blue/identity, emerald/address, purple/compliance
])

@php
    $variantClass = match($variant) {
        'identity', 'blue' => 'zolo-section-box-blue cp-col-identity',
        'address', 'emerald', 'green' => 'zolo-section-box-emerald cp-col-address',
        'compliance', 'purple', 'violet' => 'zolo-section-box-purple cp-col-compliance',
        'inventory', 'amber', 'orange', 'warning' => 'zolo-section-box-amber cp-col-inventory',
        default => '',
    };
@endphp

<div {{ $attributes->merge(['class' => "zolo-section-box $variantClass"]) }}>
    @if($title || $icon || $actions)
        <div class="zolo-section-box-header d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-2">
                @if($icon)
                    <span class="zolo-section-box-icon"><i class="{{ $icon }}"></i></span>
                @endif
                <span class="zolo-section-box-title">{!! $title !!}</span>
                @if($subtitle)
                    <small class="text-muted ml-2 font-weight-normal" style="font-size: 10px; text-transform: none;">{{ $subtitle }}</small>
                @endif
            </div>
            @if($actions)
                <div class="zolo-section-box-actions">
                    {{ $actions }}
                </div>
            @endif
        </div>
    @endif
    <div class="zolo-section-box-content">
        {{ $slot }}
    </div>
</div>
