@props([
    'variant' => 'info', // success, danger, warning, info
    'dismissible' => true,
    'icon' => null,
    'title' => null,
])

@php
    $styleMap = [
        'success' => [
            'class' => 'alert-success',
            'icon'  => 'dripicons-checkmark',
            'style' => 'background: rgba(16, 185, 129, 0.1); border-color: rgba(16, 185, 129, 0.25); color: #065f46;',
        ],
        'danger' => [
            'class' => 'alert-danger',
            'icon'  => 'dripicons-cross',
            'style' => 'background: rgba(239, 68, 68, 0.1); border-color: rgba(239, 68, 68, 0.25); color: #991b1b;',
        ],
        'warning' => [
            'class' => 'alert-warning',
            'icon'  => 'dripicons-warning',
            'style' => 'background: rgba(245, 158, 11, 0.1); border-color: rgba(245, 158, 11, 0.25); color: #92400e;',
        ],
        'info' => [
            'class' => 'alert-info',
            'icon'  => 'dripicons-information',
            'style' => 'background: rgba(14, 165, 233, 0.1); border-color: rgba(14, 165, 233, 0.25); color: #075985;',
        ],
    ];

    $cfg = $styleMap[$variant] ?? $styleMap['info'];
    $iconClass = $icon ?: $cfg['icon'];
@endphp

<div {{ $attributes->merge(['class' => 'alert ' . $cfg['class'] . ($dismissible ? ' alert-dismissible' : '') . ' fade show zolo-alert d-flex align-items-center gap-2']) }} 
     style="{{ $cfg['style'] }} border-radius: var(--neo-radius-md); padding: 12px 16px; margin-bottom: 16px;" 
     role="alert">
    @if($iconClass)
        <i class="{{ $iconClass }}" style="font-size: 16px; flex-shrink: 0; margin-right: 6px;"></i>
    @endif

    <div class="flex-grow-1" style="font-size: 13.5px; line-height: 1.4;">
        @if($title)
            <strong class="d-block mb-1" style="font-size: 14px;">{{ $title }}</strong>
        @endif
        <div>{{ $slot }}</div>
    </div>

    @if($dismissible)
        <button type="button" class="close" data-dismiss="alert" aria-label="Close" style="padding: 10px 12px; outline: none; opacity: 0.6;">
            <span aria-hidden="true">&times;</span>
        </button>
    @endif
</div>
