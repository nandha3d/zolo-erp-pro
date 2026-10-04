@props([
    'title' => null,
    'subtitle' => null,
    'icon' => null,
    'card' => true,
    'actions' => null,
])

<div {{ $attributes->merge(['class' => $card ? 'card zolo-table-card' : 'zolo-table-wrapper']) }} style="{{ $card ? 'border: 1px solid var(--neo-border); border-radius: var(--neo-radius-lg); background: var(--neo-surface-card); box-shadow: var(--neo-shadow-xs); margin-bottom: 24px; overflow: visible;' : 'overflow: visible;' }}">
    @if($title || $actions)
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between p-3" style="border-bottom: 1px solid var(--neo-border); background: transparent;">
            @if($title)
                <div class="d-flex align-items-center gap-2">
                    @if($icon)
                        <span class="zolo-header-icon mr-2" style="width: 32px; height: 32px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; background: var(--neo-primary-light); color: var(--neo-primary);">
                            <i class="{{ $icon }}" style="font-size: 15px;"></i>
                        </span>
                    @endif
                    <div>
                        <h4 class="m-0 font-weight-bold" style="font-size: 1rem; color: var(--neo-text-primary);">{{ $title }}</h4>
                        @if($subtitle)
                            <small class="text-muted" style="font-size: 0.8rem;">{{ $subtitle }}</small>
                        @endif
                    </div>
                </div>
            @endif

            @if($actions)
                <div class="d-flex align-items-center gap-2 ml-auto zolo-table-actions">
                    {{ $actions }}
                </div>
            @endif
        </div>
    @endif

    <div class="card-body p-0 zolo-table-body" style="overflow: visible;">
        <div class="table-responsive zolo-table-responsive" style="min-height: 280px; position: relative;">
            {{ $slot }}
        </div>
    </div>
</div>
