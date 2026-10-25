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
        <label for="{{ $name }}" class="form-label font-weight-semibold u-fs-13px u-c-neo-text-primary u-mb-6px u-d-block">
            {{ $label }}
            @if($required)
                <span class="text-danger">*</span>
            @endif
        </label>
    @endif

    <div class="input-group @error($name) has-error @enderror u-r-neo-radius-sm">
        @if($icon)
            <div class="input-group-prepend">
                <span class="input-group-text zolo-input-addon">
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
               >
    </div>

    @if($help)
        <small class="form-text text-muted u-fs-11px u-mt-4px">{{ $help }}</small>
    @endif

    @error($name)
        <div class="text-danger mt-1 font-weight-medium u-fs-12px">
            <i class="dripicons-warning mr-1"></i>{{ $message }}
        </div>
    @enderror
</div>
