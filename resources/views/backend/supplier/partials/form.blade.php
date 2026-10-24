{{-- Supplier master form shared by Add and Edit: every field on one screen, filled top-left to bottom-right with Enter or Tab.
     GSTIN comes first so the lookup can fill name, address and state before the user reaches them.
     Params: $supplier (null on Add), $areas, $agents, $states, $lims_customer_group_all (Add only). --}}
@php
    $s = $supplier;
    $v = fn (string $field, $default = null) => old($field, $s->{$field} ?? $default);
    $state = $v('state');
    $d = fn (string $key) => old('purchase_defaults.'.$key, ($s->purchase_defaults ?? [])[$key] ?? null);
@endphp
@push('css')
<style>
    .supplier-form { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 6px 14px; }
    .supplier-form .sf-head { grid-column: 1 / -1; font-size: 10.5px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: #7c3aed; border-bottom: 1px solid #ede9fe; padding: 4px 0 2px; margin-top: 2px; }
    .supplier-form .sf-wide { grid-column: span 2; }
    .supplier-form label { display: block; font-size: 11.5px; font-weight: 600; color: #334155; margin: 0 0 2px; }
    .supplier-form .form-control { height: 30px; padding: 3px 8px; font-size: 13px; }
    .supplier-form input[type=file].form-control { padding: 2px 6px; font-size: 12px; }
    .supplier-form small { font-size: 11px; line-height: 1.25; }
    .supplier-form .sf-check { display: flex; align-items: center; gap: 6px; height: 30px; margin-top: 18px; }
    .supplier-form .sf-check label { margin: 0; }
    .supplier-form .sf-note { font-size: 11.5px; color: #475569; background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 6px; padding: 5px 8px; }
    body.dark-mode .supplier-form label { color: #94a3b8; }
    body.dark-mode .supplier-form .sf-head { border-color: #2e1065; }
    body.dark-mode .supplier-form .sf-note { background: #111827; border-color: #334155; color: #94a3b8; }
    .supplier-form-actions { display: flex; gap: 8px; align-items: center; margin-top: 12px; }
    .supplier-form-actions .hint { font-size: 11.5px; color: #64748b; margin-left: auto; }
    @media (max-width: 991px) { .supplier-form { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
</style>
@endpush

<div class="supplier-form">
    <div class="sf-head">Party &amp; contact</div>
    <div>
        <label for="sup-gstin">GSTIN / VAT no.</label>
        <input id="sup-gstin" type="text" name="vat_number" value="{{ $v('vat_number') }}" maxlength="20" class="form-control text-uppercase" placeholder="Fills name, address, state" @unless($s) autofocus @endunless>
    </div>
    <div>
        <label for="sup-name">Supplier name *</label>
        <input id="sup-name" type="text" name="name" value="{{ $v('name') }}" required maxlength="191" class="form-control" placeholder="As you call them" @if($s) autofocus @endif>
    </div>
    <div>
        <label for="sup-company">Company / legal name</label>
        <input id="sup-company" type="text" name="company_name" value="{{ $v('company_name') }}" maxlength="191" class="form-control" placeholder="As on their invoice">
        @error('company_name')<small class="text-danger">{{ $message }}</small>@enderror
    </div>
    <div>
        <label for="sup-print">Print name</label>
        <input id="sup-print" type="text" name="print_name" value="{{ $v('print_name') }}" maxlength="191" class="form-control" placeholder="If different on documents">
    </div>
    <div>
        <label for="sup-contact">Contact person</label>
        <input id="sup-contact" type="text" name="contact_person" value="{{ $v('contact_person') }}" maxlength="191" class="form-control">
    </div>
    <div>
        <label for="sup-phone">Phone *</label>
        <input id="sup-phone" type="tel" name="phone_number" value="{{ $v('phone_number') }}" required maxlength="30" class="form-control">
        @error('phone_number')<small class="text-danger">{{ $message }}</small>@enderror
    </div>
    <div>
        <label for="sup-wa">WhatsApp</label>
        <input id="sup-wa" type="tel" name="wa_number" value="{{ $v('wa_number') }}" maxlength="30" class="form-control" placeholder="If different from phone">
    </div>
    <div>
        <label for="sup-email">Email</label>
        <input id="sup-email" type="email" name="email" value="{{ $v('email') }}" maxlength="191" class="form-control" placeholder="accounts@supplier.com">
        @error('email')<small class="text-danger">{{ $message }}</small>@enderror
    </div>

    <div class="sf-head">Address</div>
    <div class="sf-wide">
        <label for="sup-address">Address</label>
        <input id="sup-address" type="text" name="address" value="{{ $v('address') }}" maxlength="500" class="form-control" placeholder="Door no., street, area">
    </div>
    <div>
        <label for="sup-city">City</label>
        <input id="sup-city" type="text" name="city" value="{{ $v('city') }}" maxlength="100" class="form-control">
    </div>
    <div>
        <label for="sup-state">State <span class="text-muted font-weight-normal">(CGST+SGST or IGST)</span></label>
        <select id="sup-state" name="state" class="form-control">
            <option value="">— Select —</option>
            @foreach($states as $code => $name)
                <option value="{{ $name }}" @selected($state === $name || $state === $code)>{{ $name }} ({{ $code }})</option>
            @endforeach
            @if($state && !in_array($state, $states, true) && !array_key_exists($state, $states))<option value="{{ $state }}" selected>{{ $state }}</option>@endif
        </select>
    </div>
    <div>
        <label for="sup-pin">PIN code</label>
        <input id="sup-pin" type="text" name="postal_code" value="{{ $v('postal_code') }}" maxlength="10" inputmode="numeric" class="form-control">
    </div>
    <div>
        <label for="sup-country">Country</label>
        <input id="sup-country" type="text" name="country" value="{{ $v('country', 'India') }}" maxlength="100" class="form-control">
    </div>
    <div>
        <label for="sup-pan">PAN</label>
        <input id="sup-pan" type="text" name="tin_no" value="{{ $v('tin_no') }}" maxlength="20" class="form-control text-uppercase" placeholder="For TDS, if no GSTIN">
    </div>
    <div>
        <label for="sup-image">Logo / photo</label>
        <input id="sup-image" type="file" name="image" accept="image/png,image/jpeg,image/gif" class="form-control">
        @error('image')<small class="text-danger">{{ $message }}</small>@enderror
    </div>

    <div class="sf-head">Credit &amp; accounts</div>
    <div>
        <label for="sup-credit-days">Credit days <span class="text-muted font-weight-normal">(sets bill due date)</span></label>
        <input id="sup-credit-days" type="number" name="credit_days" value="{{ $v('credit_days', 0) }}" min="0" max="3650" class="form-control">
    </div>
    <div>
        <label for="sup-credit-limit">Credit limit ₹ <span class="text-muted font-weight-normal">(0 = none)</span></label>
        <input id="sup-credit-limit" type="number" name="credit_limit" value="{{ $v('credit_limit', 0) }}" min="0" step="any" class="form-control">
    </div>
    <div>
        <label for="sup-cd-days">Cash discount within days</label>
        <input id="sup-cd-days" type="number" name="cd_days" value="{{ $v('cd_days', 0) }}" min="0" max="3650" class="form-control">
    </div>
    <div>
        <label for="sup-cd-percent">Cash discount %</label>
        <input id="sup-cd-percent" type="number" name="cd_percent" value="{{ $v('cd_percent', 0) }}" min="0" max="100" step="0.01" class="form-control">
    </div>
    @if($areas->isNotEmpty())
        <div>
            <label for="sup-area">Area</label>
            <select id="sup-area" name="area_id" class="form-control">
                <option value="">—</option>
                @foreach($areas as $area)<option value="{{ $area->id }}" @selected((string) $v('area_id') === (string) $area->id)>{{ $area->name }}</option>@endforeach
            </select>
        </div>
    @endif
    @if($agents->isNotEmpty())
        <div>
            <label for="sup-agent">Agent / broker</label>
            <select id="sup-agent" name="agent_id" class="form-control">
                <option value="">—</option>
                @foreach($agents as $agent)<option value="{{ $agent->id }}" @selected((string) $v('agent_id') === (string) $agent->id)>{{ $agent->name }}</option>@endforeach
            </select>
        </div>
    @endif
    @if($s)
        <div class="sf-wide">
            <label>Opening balance (you owe)</label>
            <div class="sf-note">₹{{ number_format((float) $s->opening_balance, 2) }}, posted once when the supplier was created. Correct it with a journal voucher.</div>
        </div>
    @else
        <div>
            <label for="sup-opening">Opening balance ₹ <span class="text-muted font-weight-normal">(you owe)</span></label>
            <input id="sup-opening" type="number" name="opening_balance" value="{{ old('opening_balance', 0) }}" min="0" step="any" class="form-control">
        </div>
        <div>
            <div class="sf-check">
                <input id="sup-both" type="checkbox" name="both" value="1" @checked(old('both'))>
                <label for="sup-both">Also a customer</label>
            </div>
        </div>
        <div class="customer-group-section" @unless(old('both')) style="display:none" @endunless>
            <label for="customer-group-id">Customer group *</label>
            <select id="customer-group-id" name="customer_group_id" class="form-control">
                @foreach($lims_customer_group_all as $group)
                    <option value="{{ $group->id }}" @selected((string) old('customer_group_id') === (string) $group->id)>{{ $group->name }}</option>
                @endforeach
            </select>
        </div>
    @endif
    <div class="sf-head">Purchase bill defaults <span class="text-muted font-weight-normal" style="text-transform:none;letter-spacing:0;">— filled in when you pick this supplier on a bill; blank = screen default</span></div>
    <div>
        <label for="sup-def-payment">Payment</label>
        <select id="sup-def-payment" name="purchase_defaults[payment_mode]" class="form-control">
            <option value="">—</option>
            @foreach(['Credit', 'Cash'] as $mode)<option value="{{ $mode }}" @selected($d('payment_mode') === $mode)>{{ $mode }}</option>@endforeach
        </select>
    </div>
    <div>
        <label for="sup-def-nature">Items</label>
        <select id="sup-def-nature" name="purchase_defaults[nature]" class="form-control">
            <option value="">—</option>
            @foreach(['product' => 'Product', 'service' => 'Service', 'mixed' => 'Mixed'] as $nature => $label)<option value="{{ $nature }}" @selected($d('nature') === $nature)>{{ $label }}</option>@endforeach
        </select>
    </div>
    <div>
        <label for="sup-def-pos">GST place of supply</label>
        <select id="sup-def-pos" name="purchase_defaults[place_of_supply]" class="form-control">
            <option value="">Auto (your state)</option>
            @foreach($states as $code => $name)<option value="{{ $code }}" @selected((string) $d('place_of_supply') === (string) $code)>{{ $code }} · {{ $name }}</option>@endforeach
        </select>
    </div>
    <div>
        <div class="sf-check">
            <input type="hidden" name="purchase_defaults[reverse_charge]" value="0">
            <input id="sup-def-rcm" type="checkbox" name="purchase_defaults[reverse_charge]" value="1" @checked($d('reverse_charge'))>
            <label for="sup-def-rcm">Reverse charge (RCM)</label>
        </div>
    </div>
    <div class="sf-wide">
        <label for="sup-def-note">Standard remarks / purchase terms</label>
        <input id="sup-def-note" type="text" name="purchase_defaults[note]" value="{{ $d('note') }}" maxlength="500" class="form-control" placeholder="e.g. Goods at buyer's risk after dispatch">
    </div>
</div>

<div class="supplier-form-actions">
    <button type="submit" class="btn btn-primary"><i class="dripicons-checkmark"></i> {{ $s ? 'Update supplier' : 'Save supplier' }}</button>
    <a href="{{ route('supplier.index') }}" class="btn btn-outline-secondary">Cancel</a>
    <span class="hint">* required · Enter or Tab moves to the next field · Enter on the last field saves</span>
</div>

@push('scripts')
<script type="text/javascript">
    $("ul#people").siblings('a').attr('aria-expanded', 'true');
    $("ul#people").addClass("show");
    $("ul#people #supplier-list-menu").addClass("active");

    $('#sup-both').on('change', function () {
        $('.customer-group-section').toggle(this.checked);
        $('#customer-group-id').prop('required', this.checked);
    });
</script>
@include('backend.partials.gstin-autofill', ['gstinField' => 'vat_number', 'nameField' => 'name'])
@endpush
