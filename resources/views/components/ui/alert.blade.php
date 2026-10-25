@props([
    'variant' => 'info', // success, danger, warning, info
    'dismissible' => true,
    'icon' => null,
    'title' => null,
])

@php
    $icons = [
        'success' => 'dripicons-checkmark',
        'danger'  => 'dripicons-cross',
        'warning' => 'dripicons-warning',
        'info'    => 'dripicons-information',
    ];
    $variant = array_key_exists($variant, $icons) ? $variant : 'info';
    $iconClass = $icon ?: $icons[$variant];
@endphp

<div {{ $attributes->merge(['class' => 'alert alert-' . $variant . ($dismissible ? ' alert-dismissible' : '') . ' fade show zolo-alert d-flex align-items-center gap-2']) }} role="alert">
    @if($iconClass)
        <i class="{{ $iconClass }} zolo-alert-icon"></i>
    @endif

    <div class="flex-grow-1 zolo-alert-body">
        @if($title)
            <strong class="d-block mb-1 zolo-alert-title">{{ $title }}</strong>
        @endif
        <div>{{ $slot }}</div>
    </div>

    @if($dismissible)
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    @endif
</div>
