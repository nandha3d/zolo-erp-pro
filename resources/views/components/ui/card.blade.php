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

<div {{ $attributes->merge(['class' => 'card zolo-card']) }}>
    @if($title || $actions)
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between p-3 u-bb-1px-solid-neo-border u-bg-transparent">
            @if($title)
                <div class="d-flex align-items-center gap-2">
                    @if($icon)
                        <span class="zolo-header-icon mr-2 u-w-34px u-h-34px u-r-8px u-d-inline-flex u-items-center u-justify-center u-bg-neo-primary-light u-c-neo-primary">
                            <i class="{{ $icon }} u-fs-15px"></i>
                        </span>
                    @endif
                    <div>
                        <h4 class="m-0 font-weight-bold u-fs-1_05rem u-c-neo-text-primary">{{ $title }}</h4>
                        @if($subtitle)
                            <small class="text-muted d-block u-fs-0_8rem u-mt-2px">{{ $subtitle }}</small>
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

    <div class="card-body {{ $bodyPadding }} u-ov-visible">
        {{ $slot }}
    </div>

    @if($footer)
        <div class="card-footer p-3 zolo-card-footer">
            {{ $footer }}
        </div>
    @endif
</div>
