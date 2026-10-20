@extends('backend.layout.main')
@section('title', 'Company profile')

@section('content')
<style>
    .cpf-wrap { padding: 12px 18px 28px; max-width: 1100px; }
    .cpf-head h1 { font-size: 18px; font-weight: 800; margin: 0; color: #0f172a; }
    .cpf-head p { margin: 2px 0 12px; font-size: 12.5px; color: #64748b; }
    .cpf-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 14px 16px; margin-bottom: 12px; }
    .cpf-card h2 { font-size: 14px; font-weight: 700; margin: 0 0 10px; color: #0f172a; }
    .cpf-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 10px 14px; }
    .cpf-span { grid-column: 1 / -1; }
    .cpf-card label { display: block; font-size: 12px; font-weight: 600; color: #334155; margin-bottom: 3px; }
    .cpf-card input:not([type=checkbox]):not([type=file]), .cpf-card select, .cpf-card textarea { width: 100%; font-size: 13px; height: 32px; padding: 4px 8px; border: 1px solid #cbd5e1; border-radius: 6px; background: #fff; color: #0f172a; }
    .cpf-card textarea { height: 60px; }
    .cpf-muted { color: #64748b; font-size: 12px; margin: 4px 0 0; }
    .cpf-logo { display: flex; align-items: center; gap: 14px; }
    .cpf-logo-box { width: 120px; height: 64px; border: 1px dashed #cbd5e1; border-radius: 8px; display: flex; align-items: center; justify-content: center; overflow: hidden; background: #f8fafc; font-size: 11px; color: #94a3b8; }
    .cpf-logo-box img { max-width: 100%; max-height: 100%; object-fit: contain; }
    .cpf-check { display: flex !important; align-items: center; gap: 6px; font-weight: 400 !important; }
    .cpf-actions { display: flex; justify-content: flex-end; gap: 8px; }
    /* Wide screens: sections side by side so the whole profile fits one screen. */
    @media (min-width: 1200px) {
        .cpf-wrap { max-width: 1440px; }
        #company-profile-form { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 0 12px; align-items: start; }
        #company-profile-form > .cpf-actions { grid-column: 1 / -1; }
        #company-profile-form .cpf-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }
    .cpf-card .bootstrap-select { width: 100% !important; min-width: 0; }
    .cpf-logo-box { flex: none; }
    .cpf-logo > :not(.cpf-logo-box) { min-width: 0; }
    .cpf-logo input[type=file] { max-width: 100%; font-size: 12px; }
    body.dark-mode .cpf-card { background: #0f172a; border-color: #1e293b; }
    body.dark-mode .cpf-head h1, body.dark-mode .cpf-card h2 { color: #f8fafc; }
    body.dark-mode .cpf-card label { color: #94a3b8; }
    body.dark-mode .cpf-card input:not([type=checkbox]):not([type=file]), body.dark-mode .cpf-card select, body.dark-mode .cpf-card textarea { background: #111827; border-color: #334155; color: #e2e8f0; }
    body.dark-mode .cpf-logo-box { background: #111827; border-color: #334155; }
</style>

<div class="cpf-wrap">
    <div class="cpf-head">
        <h1>Company profile</h1>
        <p>These details print on this company's invoices, bills and notes. Bank details and terms are under <a href="{{ route('settings.gst') }}#registration">GST &amp; Tax › Registration</a>.</p>
    </div>
    @if(session('message'))<div class="alert alert-success py-2">{{ session('message') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger py-2"><ul class="mb-0 pl-3">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <form method="POST" action="{{ route('settings.company.update') }}" enctype="multipart/form-data" id="company-profile-form">
        @csrf
        <div class="cpf-card">
            <h2>Business</h2>
            <div class="cpf-grid">
                <div><label for="cpf-trade">Display name (printed large)</label><input id="cpf-trade" name="trade_name" maxlength="191" value="{{ old('trade_name', $company->trade_name) }}"></div>
                <div><label for="cpf-legal">Legal name *</label><input id="cpf-legal" name="legal_name" maxlength="191" required value="{{ old('legal_name', $company->legal_name) }}"></div>
                <div class="cpf-span cpf-logo">
                    <div class="cpf-logo-box">@if($company->logo && is_file(public_path($company->logo)))<img src="{{ asset($company->logo) }}" alt="Current logo">@else No logo @endif</div>
                    <div><label for="cpf-logo">Logo (PNG or JPG, up to 1 MB)</label><input id="cpf-logo" type="file" name="logo" accept="image/png,image/jpeg">
                        <p class="cpf-muted">Printed at the top left of the A4 invoice. Bills already posted keep the logo they were posted with.</p></div>
                </div>
            </div>
        </div>

        <div class="cpf-card">
            <h2>Address &amp; contact</h2>
            <div class="cpf-grid">
                <div class="cpf-span"><label for="cpf-address">Address</label><textarea id="cpf-address" name="address" maxlength="1000" placeholder="Door no., street, area">{{ old('address', $company->address) }}</textarea></div>
                <div><label for="cpf-city">City</label><input id="cpf-city" name="city" maxlength="100" value="{{ old('city', $company->city) }}"></div>
                <div><label for="cpf-state">State</label><select id="cpf-state" name="state_code">
                    <option value="">— Select —</option>
                    @foreach($states as $code => $name)<option value="{{ $code }}" @selected((string) old('state_code', $company->state_code) === (string) $code)>{{ $name }} ({{ $code }})</option>@endforeach
                </select></div>
                <div><label for="cpf-pin">PIN code</label><input id="cpf-pin" name="postal_code" maxlength="6" inputmode="numeric" value="{{ old('postal_code', $company->postal_code) }}"></div>
                <div><label for="cpf-phone">Phone</label><input id="cpf-phone" name="phone" maxlength="100" value="{{ old('phone', $company->phone) }}"></div>
                <div><label for="cpf-email">Email</label><input id="cpf-email" type="email" name="email" maxlength="191" value="{{ old('email', $company->email) }}"></div>
                <div><label for="cpf-website">Website</label><input id="cpf-website" name="website" maxlength="191" value="{{ old('website', $company->website) }}"></div>
            </div>
        </div>

        <div class="cpf-card">
            <h2>Tax</h2>
            <div class="cpf-grid">
                <div><label for="cpf-gstin">GSTIN</label><input id="cpf-gstin" name="gstin" maxlength="15" style="text-transform:uppercase" value="{{ old('gstin', $registration->gstin ?? '') }}"></div>
                <div><label for="cpf-pan">PAN</label><input id="cpf-pan" name="pan" maxlength="10" style="text-transform:uppercase" value="{{ old('pan', $company->pan) }}"></div>
                <div><label for="cpf-regtype">GST type</label><select id="cpf-regtype" name="registration_type">
                    @foreach(['regular' => 'Regular', 'composition' => 'Composition'] as $value => $label)<option value="{{ $value }}" @selected(old('registration_type', $registration->registration_type ?? 'regular') === $value)>{{ $label }}</option>@endforeach
                </select></div>
                <div><label for="cpf-gstfrom">New GSTIN effective from</label><input id="cpf-gstfrom" type="date" name="gst_effective_from" value="{{ old('gst_effective_from') }}">
                    <p class="cpf-muted">Only for a new or changed GSTIN; blank means today.</p></div>
            </div>
            <p class="cpf-muted">With a GSTIN, the state comes from it, PAN defaults to characters 3–12, and the GST registration takes this legal name, display name and address, so bills always carry the company's name.</p>
        </div>

        <div class="cpf-card">
            <h2>Currency</h2>
            <div class="cpf-grid">
                <div><label for="cpf-currency">Base currency</label><select id="cpf-currency" name="base_currency_id">
                    @foreach($currencies as $currency)<option value="{{ $currency->id }}" @selected((int) old('base_currency_id', $company->base_currency_id) === (int) $currency->id)>{{ $currency->code }} · {{ $currency->name }}</option>@endforeach
                </select></div>
                <div style="align-self:end"><label class="cpf-check"><input type="checkbox" name="app_currency" value="1" @checked($appCurrencyId === (int) $company->base_currency_id)> Also the app's default currency (all screens and new companies)</label></div>
            </div>
            <p class="cpf-muted">Invoices print the amount in words (Rupees … Only) when the currency is INR. Add other currencies under Settings › Currency.</p>
        </div>

        @if($industry)
        <div class="cpf-card">
            <h2>Industry</h2>
            <div class="cpf-grid">
                <div><label for="cpf-industry">Industry</label><select id="cpf-industry" name="industry">
                    @foreach($industries as $key => $label)<option value="{{ $key }}" @selected($industry['profile'] === $key)>{{ $label }}</option>@endforeach
                </select></div>
                <div><label for="cpf-subtype">Business type</label><select id="cpf-subtype" name="subtype" data-current="{{ $industry['subtype'] }}"></select></div>
            </div>
            <p class="cpf-muted">Changing the industry applies its preset (units, product attributes, entry aids). Existing products, stock and bills are not changed.</p>
        </div>
        @endif

        <div class="cpf-actions"><button type="submit" class="btn btn-primary">Save company profile</button></div>
    </form>
</div>
<script>
(function () {
    var subtypes = @json($subtypes);
    var industry = document.getElementById('cpf-industry'), subtype = document.getElementById('cpf-subtype');
    if (!industry) return;
    function fill() {
        var current = subtype.dataset.current; subtype.innerHTML = '';
        Object.keys(subtypes[industry.value] || {}).forEach(function (key) {
            var option = new Option(key.replace(/_/g, ' ').replace(/\b\w/g, function (c) { return c.toUpperCase(); }), key);
            option.selected = key === current; subtype.appendChild(option);
        });
    }
    industry.addEventListener('change', function () { subtype.dataset.current = ''; fill(); });
    fill();
})();
</script>
@endsection
