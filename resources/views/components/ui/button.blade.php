@props([
    'variant' => 'primary', // primary, secondary, success, danger, warning, info, outline-primary, ghost
    'size' => 'md',         // xs, sm, md, lg
    'type' => 'button',     // button, submit, reset, link
    'href' => '#',
    'icon' => null,
    'iconPosition' => 'left',
    'loading' => false,
])

@php
    $variantClass = match($variant) {
        'primary' => 'btn-primary',
        'secondary' => 'btn-secondary',
        'success' => 'btn-success',
        'danger' => 'btn-danger',
        'warning' => 'btn-warning',
        'info' => 'btn-info',
        'outline-primary' => 'btn-outline-primary',
        'outline-secondary' => 'btn-outline-secondary',
        'ghost' => 'btn-link text-decoration-none',
        default => 'btn-primary',
    };

    $sizeClass = match($size) {
        'xs' => 'btn-xs',
        'sm' => 'btn-sm',
        'lg' => 'btn-lg',
        default => '',
    };
@endphp

@if($type === 'link')
    <a href="{{ $href }}" {{ $attributes->merge(['class' => "btn $variantClass $sizeClass zolo-btn d-inline-flex align-items-center justify-content-center"]) }}>
        @if($icon && $iconPosition === 'left')
            <i class="{{ $icon }} mr-1"></i>
        @endif
        <span>{{ $slot }}</span>
        @if($icon && $iconPosition === 'right')
            <i class="{{ $icon }} ml-1"></i>
        @endif
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => "btn $variantClass $sizeClass zolo-btn d-inline-flex align-items-center justify-content-center"]) }} @if($loading) disabled @endif>
        @if($loading)
            <span class="spinner-border spinner-border-sm mr-1" role="status" aria-hidden="true"></span>
        @elseif($icon && $iconPosition === 'left')
            <i class="{{ $icon }} mr-1"></i>
        @endif
        <span>{{ $slot }}</span>
        @if(!$loading && $icon && $iconPosition === 'right')
            <i class="{{ $icon }} ml-1"></i>
        @endif
    </button>
@endif
