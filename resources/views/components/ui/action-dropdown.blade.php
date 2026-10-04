@props([
    'label' => __('db.action'),
    'icon' => null,
    'variant' => 'default', // default, primary, outline, ghost
    'size' => 'sm',         // xs, sm, md, lg
    'align' => 'right',     // left, right
    'boundary' => 'window'  // window, viewport, scrollParent
])

@php
    $btnClasses = match($variant) {
        'primary' => 'btn btn-primary',
        'outline' => 'btn btn-outline-primary',
        'ghost' => 'btn btn-link text-dark p-1',
        default => 'btn btn-default',
    };
    
    $sizeClass = match($size) {
        'xs' => 'btn-xs',
        'sm' => 'btn-sm',
        'lg' => 'btn-lg',
        default => '',
    };
    
    $menuAlignClass = $align === 'right' ? 'dropdown-menu-right' : 'dropdown-menu-left';
@endphp

<div {{ $attributes->merge(['class' => 'btn-group zolo-action-dropdown-group']) }}>
    <button type="button" 
            class="{{ $btnClasses }} {{ $sizeClass }} dropdown-toggle zolo-action-btn" 
            data-toggle="dropdown" 
            data-boundary="{{ $boundary }}"
            aria-haspopup="true" 
            aria-expanded="false">
        @if($icon)
            <i class="{{ $icon }} mr-1"></i>
        @endif
        <span>{{ $label }}</span>
        <span class="caret ml-1"></span>
        <span class="sr-only">Toggle Dropdown</span>
    </button>
    <ul class="dropdown-menu edit-options {{ $menuAlignClass }} dropdown-default zolo-action-menu" role="menu">
        {{ $slot }}
    </ul>
</div>
