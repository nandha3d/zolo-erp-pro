@props([
    'title' => 'Info',
    'type' => 'primary',
    'size' => 'regular' // options: 'small', 'regular'
])

@php
    $iconClass = $size === 'small' ? 'info-icon-sm' : 'info-icon';
@endphp

<span class="dripicons-information u-bg-xcce5ff u-r-50pct u-c-x004085 u-d-inline-block u-fs-15px u-h-16px u-lh-1px u-cur-help" data-toggle="tooltip" title="{{ $title }}"></span>
