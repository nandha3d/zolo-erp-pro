@props([
    'title' => null,
    'subtitle' => null,
    'icon' => null,
    'actions' => null,
    'footer' => null,
    'padding' => 'md', // none, sm, md, lg
])

@php
    $bodyPadding = match($padding) {
        'none' => 'p-0',
        'sm' => 'p-3',
        'lg' => 'p-4',
        default => 'p-3 p-md-4',
    };
@endphp

<div {{ $attributes->merge(['class' => 'card zolo-card']) }} style="border: 1px solid var(--neo-border); border-radius: var(--neo-radius-lg); background: var(--neo-surface-card); box-shadow: var(--neo-shadow-xs); margin-bottom: 24px; overflow: visible;">
    @if($title || $actions)
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between p-3" style="border-bottom: 1px solid var(--neo-border); background: transparent;">
            @if($title)
                <div class="d-flex align-items-center gap-2">
                    @if($icon)
                        <span class="zolo-header-icon mr-2" style="width: 34px; height: 34px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; background: var(--neo-primary-light); color: var(--neo-primary);">
                            <i class="{{ $icon }}" style="font-size: 15px;"></i>
                        </span>
                    @endif
                    <div>
                        <h4 class="m-0 font-weight-bold" style="font-size: 1.05rem; color: var(--neo-text-primary);">{{ $title }}</h4>
                        @if($subtitle)
                            <small class="text-muted d-block" style="font-size: 0.8rem; margin-top: 2px;">{{ $subtitle }}</small>
                        @endif
                    </div>
                </div>
            @endif

            @if($actions)
                <div class="d-flex align-items-center gap-2 ml-auto">
                    {{ $actions }}
                </div>
            @endif
        </div>
    @endif

    <div class="card-body {{ $bodyPadding }}" style="overflow: visible;">
        {{ $slot }}
    </div>

    @if($footer)
        <div class="card-footer p-3" style="border-top: 1px solid var(--neo-border); background: var(--neo-bg); border-bottom-left-radius: var(--neo-radius-lg); border-bottom-right-radius: var(--neo-radius-lg);">
            {{ $footer }}
        </div>
    @endif
</div>
