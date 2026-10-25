@props([
    'id' => 'zoloModal',
    'title' => '',
    'icon' => null,
    'size' => 'md', // sm, md, lg, xl
    'formAction' => null,
    'formMethod' => 'POST',
    'formEnctype' => null,
    'footer' => null,
])

@php
    $dialogSize = match($size) {
        'sm' => 'modal-sm',
        'lg' => 'modal-lg',
        'xl' => 'modal-xl',
        default => '',
    };
@endphp

<div id="{{ $id }}" tabindex="-1" role="dialog" aria-labelledby="{{ $id }}Label" aria-hidden="true" {{ $attributes->merge(['class' => 'modal fade text-left zolo-modal']) }}>
    <div role="document" class="modal-dialog modal-dialog-centered {{ $dialogSize }}">
        <div class="modal-content u-b-1px-solid-neo-border u-r-neo-radius-lg u-sh-neo-shadow-lg u-ov-hidden u-bg-neo-surface">
            @if($formAction)
                <form action="{{ $formAction }}" method="{{ strtoupper($formMethod) === 'GET' ? 'GET' : 'POST' }}" @if($formEnctype) enctype="{{ $formEnctype }}" @endif>
                    @if(strtoupper($formMethod) !== 'GET')
                        @csrf
                        @if(!in_array(strtoupper($formMethod), ['POST', 'GET']))
                            @method($formMethod)
                        @endif
                    @endif
            @endif

            <div class="modal-header d-flex align-items-center justify-content-between p-3 u-bb-1px-solid-neo-border u-bg-neo-surface-card">
                <div class="d-flex align-items-center gap-2">
                    @if($icon)
                        <span class="zolo-brand-mark mr-2 u-w-32px u-h-32px u-r-8px u-d-inline-flex u-items-center u-justify-center u-bg-neo-primary-gradient u-c-xffffff u-sh-0-2px-8px-rgb-primary-rgb-0_3">
                            <i class="{{ $icon }} u-fs-14px"></i>
                        </span>
                    @endif
                    <h5 id="{{ $id }}Label" class="modal-title font-weight-bold m-0 u-fs-1_05rem u-c-neo-text-primary">
                        {{ $title }}
                    </h5>
                </div>
                <button type="button" data-dismiss="modal" aria-label="Close" class="close u-ol-none u-op-0_7 u-fs-1_25rem">
                    <span aria-hidden="true"><i class="dripicons-cross"></i></span>
                </button>
            </div>

            <div class="modal-body p-3 p-md-4 u-bg-neo-surface">
                {{ $slot }}
            </div>

            @if($footer || $formAction)
                <div class="modal-footer p-3 d-flex align-items-center justify-content-end gap-2 u-bt-1px-solid-neo-border u-bg-neo-bg">
                    @if($footer)
                        {{ $footer }}
                    @else
                        <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">{{ __('db.Cancel') ?? 'Cancel' }}</button>
                        <button type="submit" class="btn btn-primary btn-sm"><i class="dripicons-checkmark mr-1"></i> {{ __('db.submit') ?? 'Submit' }}</button>
                    @endif
                </div>
            @endif

            @if($formAction)
                </form>
            @endif
        </div>
    </div>
</div>
