@props([
    'name',
    'label' => null,
    'type' => 'text',
    'value' => null,
    'placeholder' => null,
    'icon' => null,
    'required' => false,
    'help' => null,
])

<div class="form-group zolo-form-group mb-3">
    @if($label)
        <label for="{{ $name }}" class="form-label font-weight-semibold" style="font-size: 13px; color: var(--neo-text-primary); margin-bottom: 6px; display: block;">
            {{ $label }}
            @if($required)
                <span class="text-danger">*</span>
            @endif
        </label>
    @endif

    <div class="input-group @error($name) has-error @enderror" style="border-radius: var(--neo-radius-sm);">
        @if($icon)
            <div class="input-group-prepend">
                <span class="input-group-text" style="background: var(--neo-bg); border-color: var(--neo-border); color: var(--neo-text-secondary); border-top-left-radius: var(--neo-radius-sm); border-bottom-left-radius: var(--neo-radius-sm);">
                    <i class="{{ $icon }}"></i>
                </span>
            </div>
        @endif

        <input type="{{ $type }}" 
               name="{{ $name }}" 
               id="{{ $name }}" 
               value="{{ old($name, $value) }}" 
               placeholder="{{ $placeholder }}" 
               @if($required) required @endif
               {{ $attributes->merge(['class' => 'form-control zolo-input']) }}
               style="border-color: var(--neo-border); background: var(--neo-surface); color: var(--neo-text-primary); font-size: 13.5px; border-radius: var(--neo-radius-sm);">
    </div>

    @if($help)
        <small class="form-text text-muted" style="font-size: 11px; margin-top: 4px;">{{ $help }}</small>
    @endif

    @error($name)
        <div class="text-danger mt-1 font-weight-medium" style="font-size: 12px;">
            <i class="dripicons-warning mr-1"></i>{{ $message }}
        </div>
    @enderror
</div>
