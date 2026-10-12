{{-- GST rate and HSN/SAC for companies billing under GST; replaces the legacy "Product Tax" list. --}}
@php
    $gstRates = app(\App\Services\Tax\GstSetupService::class)->currentRates();
    $selectedRate = old('tax_category_id', $product->tax_category_id ?? '');
@endphp
<div class="col-md-4">
    <div class="form-group">
        <label>GST rate <a href="{{ route('settings.gst') }}" target="_blank" class="small ml-1">Manage</a></label>
        <select name="tax_category_id" class="form-control selectpicker">
            <option value="">Not set</option>
            @foreach($gstRates as $id => $rate)
                <option value="{{ $id }}" @selected((string) $selectedRate === (string) $id)>{{ $rate['name'] }}</option>
            @endforeach
        </select>
        @if(empty($gstRates))<small class="text-warning">No GST rates yet. Open Settings › GST › Install standard GST setup.</small>@endif
    </div>
</div>
<div class="col-md-4">
    <div class="form-group">
        <label>HSN / SAC code</label>
        <input type="text" name="hsn_code" class="form-control" maxlength="8" inputmode="numeric"
               value="{{ old('hsn_code', $product->hsn_code ?? '') }}" placeholder="Goods: 4/6/8 digits · Services: 99xxxx">
    </div>
</div>
