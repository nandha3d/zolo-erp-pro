@props([
    'name',
    'label' => null,
    'options' => [],
    'selected' => null,
    'placeholder' => null,
    'required' => false,
    'help' => null,
    'compact' => false,
])

<div class="form-group zolo-form-group mb-2">
    @if($label)
        <label for="{{ $name }}" class="form-label font-weight-semibold u-c-neo-text-primary u-mb-4px u-d-block {{ $compact ? 'u-fs-11px' : 'u-fs-12_5px' }}">
            {{ $label }}
            @if($required)
                <span class="text-danger">*</span>
            @endif
        </label>
    @endif

    <select name="{{ $name }}" 
            id="{{ $name }}" 
            @if($required) required @endif
            {{ $attributes->merge(['class' => 'form-control zolo-dropdown ' . ($compact ? 'dropdown-compact' : '')]) }}>
        @if($placeholder)
            <option value="">{{ $placeholder }}</option>
        @endif
        @foreach($options as $val => $text)
            <option value="{{ $val }}" @selected((string)old($name, $selected) === (string)$val)>{{ $text }}</option>
        @endforeach
        {{ $slot }}
    </select>

    @if($help)
        <small class="form-text text-muted u-fs-11px u-mt-3px">{{ $help }}</small>
    @endif

    @error($name)
        <div class="text-danger mt-1 font-weight-medium u-fs-11_5px">
            <i class="dripicons-warning mr-1"></i>{{ $message }}
        </div>
    @enderror
</div>
