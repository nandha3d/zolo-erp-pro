@props([
    'title' => null,
    'subtitle' => null,
    'icon' => null,
    'card' => true,
    'actions' => null,
])

<div {{ $attributes->merge(['class' => $card ? 'card zolo-table-card' : 'zolo-table-wrapper']) }}>
    @if($title || $actions)
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between p-3 u-bb-1px-solid-neo-border u-bg-transparent">
            @if($title)
                <div class="d-flex align-items-center gap-2">
                    @if($icon)
                        <span class="zolo-header-icon mr-2 u-w-32px u-h-32px u-r-8px u-d-inline-flex u-items-center u-justify-center u-bg-neo-primary-light u-c-neo-primary">
                            <i class="{{ $icon }} u-fs-15px"></i>
                        </span>
                    @endif
                    <div>
                        <h4 class="m-0 font-weight-bold u-fs-1rem u-c-neo-text-primary">{{ $title }}</h4>
                        @if($subtitle)
                            <small class="text-muted u-fs-0_8rem">{{ $subtitle }}</small>
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

    <div class="card-body p-0 zolo-table-body u-ov-visible">
        <div class="table-responsive zolo-table-responsive u-minh-280px u-pos-relative">
            {{ $slot }}
        </div>
    </div>
</div>
