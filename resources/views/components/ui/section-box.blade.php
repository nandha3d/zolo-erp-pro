@props([
    'title' => null,
    'subtitle' => null,
    'icon' => null,
    'actions' => null,
])

<div {{ $attributes->merge(['class' => 'zolo-section-box']) }}>
    @if($title || $icon || $actions)
        <div class="zolo-section-box-header d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-2">
                @if($icon)
                    <span class="zolo-section-box-icon"><i class="{{ $icon }}"></i></span>
                @endif
                <span class="zolo-section-box-title">{{ $title }}</span>
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
