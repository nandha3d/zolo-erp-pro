@props([
    'variant' => 'neutral', // primary, success, danger, warning, info, purple, neutral
    'pill' => false,
    'dot' => false,
    'icon' => null,
])

@php
    $styleMap = [
        'primary' => 'background: rgba(79, 70, 229, 0.12); color: #4f46e5; border: 1px solid rgba(79, 70, 229, 0.2);',
        'success' => 'background: rgba(16, 185, 129, 0.12); color: #059669; border: 1px solid rgba(16, 185, 129, 0.2);',
        'danger'  => 'background: rgba(239, 68, 68, 0.12); color: #dc2626; border: 1px solid rgba(239, 68, 68, 0.2);',
        'warning' => 'background: rgba(245, 158, 11, 0.12); color: #d97706; border: 1px solid rgba(245, 158, 11, 0.2);',
        'info'    => 'background: rgba(14, 165, 233, 0.12); color: #0284c7; border: 1px solid rgba(14, 165, 233, 0.2);',
        'purple'  => 'background: rgba(139, 92, 246, 0.12); color: #7c3aed; border: 1px solid rgba(139, 92, 246, 0.2);',
        'neutral' => 'background: rgba(100, 116, 139, 0.1); color: #475569; border: 1px solid rgba(100, 116, 139, 0.2);',
    ];

    $badgeStyle = $styleMap[$variant] ?? $styleMap['neutral'];
    $radius = $pill ? '9999px' : '6px';
@endphp

<span {{ $attributes->merge(['class' => 'badge zolo-badge d-inline-flex align-items-center font-weight-semibold']) }} 
      style="{{ $badgeStyle }} border-radius: {{ $radius }}; padding: 4px 8px; font-size: 11px; letter-spacing: 0.02em;">
    @if($dot)
        <span style="width: 6px; height: 6px; border-radius: 50%; background: currentColor; margin-right: 5px; display: inline-block;"></span>
    @elseif($icon)
        <i class="{{ $icon }} mr-1"></i>
    @endif
    <span>{{ $slot }}</span>
</span>
