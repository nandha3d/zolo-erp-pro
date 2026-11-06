@php
    $billUsage = $billUsage ?? null;
    $selectedUsage = $billUsage ?? old('product_usage', $productUsage ?? 'composite');
@endphp
<fieldset class="product-usage col-12 mb-2">
    <legend class="mb-1">{{ __('Product Usage') }}</legend>
    <div class="d-flex flex-wrap">
        @foreach (['purchase' => __('Purchase'), 'sale' => __('Sales'), 'composite' => __('Composite')] as $value => $label)
            <label class="mr-1 mb-1">
                <input type="radio" name="product_usage" value="{{ $value }}" required
                    @checked($selectedUsage === $value)
                    @disabled($billUsage && $value !== $billUsage && $value !== 'composite')>
                <span class="btn btn-sm btn-outline-primary">{{ $label }}</span>
            </label>
        @endforeach
    </div>
    <small class="text-muted">{{ __('Composite uses the same product for purchases and sales.') }}</small>
</fieldset>

@once
    @push('css')
        <link rel="stylesheet" href="{{ versioned_asset('css/product-usage.css') }}">
    @endpush
    @push('scripts')
        <script defer src="{{ versioned_asset('js/product-usage.js') }}"></script>
    @endpush
@endonce
