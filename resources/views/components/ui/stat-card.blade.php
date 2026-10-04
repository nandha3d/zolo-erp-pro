@props([
    'color' => 'blue', // purple, blue, cyan, emerald, orange, rose, amber, teal
    'label' => '',
    'value' => '0',
    'currency' => null,
    'icon' => 'dripicons-graph-bar',
    'badge' => null,
    'subtext' => null,
])

<div {{ $attributes->merge(['class' => "zolo-kpi-card kpi-$color"]) }}>
    <div class="zolo-kpi-header">
        <div class="zolo-kpi-icon">
            <i class="{{ $icon }}"></i>
        </div>
        @if($badge)
            <span class="zolo-kpi-badge font-weight-bold">{{ $badge }}</span>
        @endif
    </div>

    <div class="zolo-kpi-body">
        <div class="zolo-kpi-label">{{ $label }}</div>
        <div class="zolo-kpi-value">
            @if($currency)
                <span class="zolo-kpi-currency">{{ $currency }}</span>
            @endif
            <span>{{ $value }}</span>
        </div>
        @if($subtext)
            <div class="zolo-kpi-subtext">
                <span>{{ $subtext }}</span>
            </div>
        @endif
    </div>
</div>
