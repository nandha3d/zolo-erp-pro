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
        <label for="{{ $name }}" class="form-label font-weight-semibold" style="font-size: {{ $compact ? '11px' : '12.5px' }}; color: var(--neo-text-primary); margin-bottom: 4px; display: block;">
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
        <small class="form-text text-muted" style="font-size: 11px; margin-top: 3px;">{{ $help }}</small>
    @endif

    @error($name)
        <div class="text-danger mt-1 font-weight-medium" style="font-size: 11.5px;">
            <i class="dripicons-warning mr-1"></i>{{ $message }}
        </div>
    @enderror
</div>
