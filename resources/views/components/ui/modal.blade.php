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
        <div class="modal-content" style="border: 1px solid var(--neo-border); border-radius: var(--neo-radius-lg); box-shadow: var(--neo-shadow-lg); overflow: hidden; background: var(--neo-surface);">
            @if($formAction)
                <form action="{{ $formAction }}" method="{{ strtoupper($formMethod) === 'GET' ? 'GET' : 'POST' }}" @if($formEnctype) enctype="{{ $formEnctype }}" @endif>
                    @if(strtoupper($formMethod) !== 'GET')
                        @csrf
                        @if(!in_array(strtoupper($formMethod), ['POST', 'GET']))
                            @method($formMethod)
                        @endif
                    @endif
            @endif

            <div class="modal-header d-flex align-items-center justify-content-between p-3" style="border-bottom: 1px solid var(--neo-border); background: var(--neo-surface-card);">
                <div class="d-flex align-items-center gap-2">
                    @if($icon)
                        <span class="zolo-brand-mark mr-2" style="width: 32px; height: 32px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; background: var(--neo-primary-gradient); color: #fff; box-shadow: 0 2px 8px rgba(99,102,241,0.3);">
                            <i class="{{ $icon }}" style="font-size: 14px;"></i>
                        </span>
                    @endif
                    <h5 id="{{ $id }}Label" class="modal-title font-weight-bold m-0" style="font-size: 1.05rem; color: var(--neo-text-primary);">
                        {{ $title }}
                    </h5>
                </div>
                <button type="button" data-dismiss="modal" aria-label="Close" class="close" style="outline: none; opacity: 0.7; font-size: 1.25rem;">
                    <span aria-hidden="true"><i class="dripicons-cross"></i></span>
                </button>
            </div>

            <div class="modal-body p-3 p-md-4" style="background: var(--neo-surface);">
                {{ $slot }}
            </div>

            @if($footer || $formAction)
                <div class="modal-footer p-3 d-flex align-items-center justify-content-end gap-2" style="border-top: 1px solid var(--neo-border); background: var(--neo-bg);">
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
