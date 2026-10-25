@props([
    'variant' => 'neutral', // primary, success, danger, warning, info, purple, neutral
    'pill' => false,
    'dot' => false,
    'icon' => null,
])

@php
    $variantClass = in_array($variant, ['primary', 'success', 'danger', 'warning', 'info', 'purple', 'neutral'], true)
        ? 'badge-' . $variant
        : 'badge-neutral';
@endphp

<span {{ $attributes->merge(['class' => "badge zolo-badge $variantClass" . ($pill ? ' zolo-badge-pill' : '') . ' d-inline-flex align-items-center font-weight-semibold']) }}>
    @if($dot)
        <span class="zolo-badge-dot"></span>
    @elseif($icon)
        <i class="{{ $icon }} mr-1"></i>
    @endif
    <span>{{ $slot }}</span>
</span>
