@extends('backend.compliance.layout')
@section('title','Tax & document settings')
@section('content')
<p>Use reviewed registration and rate data. GSTIN format checks do not establish active registration.</p>
<section class="panel"><h2>GSTIN lookup</h2><form id="gst-lookup-form" class="fields">
<label for="lookup-gstin">GSTIN to verify<input id="lookup-gstin" name="gstin" required minlength="15" maxlength="15"></label><button type="submit">Check registration</button></form>
<p id="gst-lookup-result" role="status" aria-live="polite">Lookup does not change saved party data. If unavailable, enter reviewed details manually.</p></section>
@push('scripts')<script defer src="{{ asset('js/compliance.js') }}"></script>@endpush
<section class="panel"><h2>Current registration</h2>@forelse($registrations as $registration)<p>Period ID {{ $registration->id }} · {{ $registration->legal_name }} · {{ $registration->gstin }} · State {{ $registration->state_code }} · {{ $registration->effective_from }} — {{ $registration->effective_to ?: 'Open-ended' }}<br><small>{{ $registration->verified_at ? 'Provider verified '.$registration->verified_at : 'Manual registration; not provider verified' }}</small></p>@empty<p>No GST registration configured for this branch.</p>@endforelse</section>
@if($canManage)
<div class="settings-grid">
<details class="panel"><summary>Close an effective period</summary><p>Close the current open period before adding a replacement. Posted snapshots remain unchanged.</p><form method="POST" action="{{ url('/compliance/setup/close-period') }}" class="fields">@csrf
<label for="close-type">Period type<select id="close-type" name="period_type"><option value="registration">Registration</option><option value="rate">Rate</option></select></label>
<label for="close-id">Period ID<input id="close-id" name="id" type="number" min="1" required></label><label for="close-date">Final effective date<input id="close-date" name="effective_to" type="date" required></label><button type="submit">Close period</button></form></details>
<details class="panel"><summary>Company GST registration</summary><form method="POST" action="{{ url('/compliance/setup/registration') }}" class="fields">@csrf
<label for="setup-gstin">GSTIN<input id="setup-gstin" name="gstin" required maxlength="15"></label>
<label for="setup-legal">Legal name<input id="setup-legal" name="legal_name" required maxlength="191"></label>
<label for="setup-address">Registered address<textarea id="setup-address" name="address" required maxlength="1000"></textarea></label>
<label for="setup-reg-type">Registration type<select id="setup-reg-type" name="registration_type"><option value="regular">Regular</option><option value="composition">Composition</option></select></label>
<label for="setup-reg-from">Effective from<input id="setup-reg-from" name="effective_from" type="date" required></label><label for="setup-reg-to">Effective to<input id="setup-reg-to" name="effective_to" type="date"></label><button type="submit">Add registration period</button></form></details>
<details class="panel"><summary>Party registration & state</summary><form method="POST" action="{{ url('/compliance/setup/party') }}" class="fields">@csrf
<label for="setup-party-type">Party type<select id="setup-party-type" name="party_type"><option value="customer">Customer</option><option value="supplier">Supplier</option></select></label>
<label for="setup-party-id">Party ID<input id="setup-party-id" name="party_id" type="number" min="1" required></label>
<label for="setup-party-reg">Registration<select id="setup-party-reg" name="registration_type"><option value="regular">Regular</option><option value="unregistered">Unregistered</option><option value="composition">Composition</option></select></label>
<label for="setup-party-gstin">GSTIN<input id="setup-party-gstin" name="gstin" maxlength="15"></label><label for="setup-party-state">State code<input id="setup-party-state" name="state_code" required minlength="2" maxlength="2" placeholder="33"></label>
<button type="submit">Save party tax profile</button></form></details>
<details class="panel"><summary>Tax categories</summary><form method="POST" action="{{ url('/compliance/setup/category') }}" class="fields">@csrf
<label for="category-name">Name<input id="category-name" name="name" required maxlength="191"></label><label for="category-supply">Supply classification<select id="category-supply" name="supply_type">@foreach(['taxable','exempt','nil_rated','non_gst'] as $value)<option value="{{ $value }}">{{ ucfirst(str_replace('_',' ',$value)) }}</option>@endforeach</select></label>
<label for="category-credit"><input id="category-credit" type="checkbox" name="input_credit_allowed" value="1"> Input credit allowed for reviewed purchases</label><button type="submit">Add category</button></form></details>
<details class="panel"><summary>Effective tax rates</summary><form method="POST" action="{{ url('/compliance/setup/rate') }}" class="fields">@csrf
<label for="rate-category">Category<select id="rate-category" name="tax_category_id" required>@foreach($categories as $category)<option value="{{ $category->id }}">{{ $category->name }} · {{ $category->id }}</option>@endforeach</select></label>
<label for="rate-gst">GST rate (%)<input id="rate-gst" name="rate" type="number" min="0" max="100" step="0.0001" required></label>
<label for="rate-cess">Cess rate (%)<input id="rate-cess" name="cess_rate" type="number" min="0" max="100" step="0.0001" value="0" required></label>
<label for="rate-from">Effective from<input id="rate-from" name="effective_from" type="date" required></label><label for="rate-to">Effective to<input id="rate-to" name="effective_to" type="date"></label><button type="submit">Add rate period</button></form></details>
<details class="panel"><summary>HSN / SAC reference</summary><form method="POST" action="{{ url('/compliance/setup/hsn') }}" class="fields">@csrf
<label for="hsn-code">Code<input id="hsn-code" name="code" required maxlength="8"></label><label for="hsn-kind">Kind<select id="hsn-kind" name="kind"><option value="hsn">Goods HSN</option><option value="sac">Service SAC</option></select></label>
<label for="hsn-description">Description<input id="hsn-description" name="description" required maxlength="191"></label><button type="submit">Save reference</button></form></details>
<details class="panel"><summary>Assign product tax</summary><form method="POST" action="{{ url('/compliance/setup/product') }}" class="fields">@csrf
<label for="assign-product">Product ID<input id="assign-product" name="product_id" type="number" min="1" required></label>
<label for="assign-category">Tax category<select id="assign-category" name="tax_category_id" required>@foreach($categories as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach</select></label>
<label for="assign-hsn">HSN / SAC code<input id="assign-hsn" name="hsn_code" required maxlength="8"></label><button type="submit">Assign tax category</button></form></details>
<details class="panel"><summary>Return approval policy</summary><p>{{ $policy ? 'Notes above either threshold require approval.' : 'Every return currently requires approval.' }}</p><form method="POST" action="{{ url('/compliance/setup/return-policy') }}" class="fields">@csrf
<label for="policy-amount">Approval above amount<input id="policy-amount" name="amount" type="number" min="0" step="0.0001" value="{{ $policy['amount'] ?? '' }}" required></label>
<label for="policy-days">Approval after days<input id="policy-days" name="days" type="number" min="0" max="3650" value="{{ $policy['days'] ?? '' }}" required></label><button type="submit">Save approval policy</button></form></details>
<details class="panel"><summary>Quarantine warehouse</summary><p>Use an empty warehouse for expired, damaged and uninspected returns.</p><form method="POST" action="{{ url('/compliance/setup/quarantine') }}" class="fields">@csrf
<label for="quarantine-warehouse">Warehouse<select id="quarantine-warehouse" name="warehouse_id">@foreach($warehouses as $warehouse)<option value="{{ $warehouse->id }}">{{ $warehouse->name }}{{ $warehouse->is_quarantine ? ' · Quarantine' : '' }}</option>@endforeach</select></label><button type="submit">Mark as quarantine</button></form></details>
<details class="panel"><summary>Print profile</summary><form method="POST" action="{{ url('/compliance/setup/profile') }}" class="fields">@csrf
<label for="profile-name">Profile name<input id="profile-name" name="name" maxlength="191" required></label>
<label for="profile-document">Document<select id="profile-document" name="document_type">@foreach(['sale','purchase','sale_note','purchase_note'] as $kind)<option value="{{ $kind }}">{{ ucfirst(str_replace('_',' ',$kind)) }}</option>@endforeach</select></label>
<label for="profile-format">Format<select id="profile-format" name="format"><option value="a4">A4</option><option value="thermal">Thermal</option><option value="dot_matrix">Dot matrix</option></select></label>
<label for="profile-width">Paper width (mm)<input id="profile-width" name="paper_width" type="number" min="58" max="300" value="210" required></label>
<label for="profile-height">Lines per page<input id="profile-height" name="height_lines" type="number" min="20" max="200" value="68" required></label>
<label for="profile-columns">Text columns<input id="profile-columns" name="columns" type="number" min="60" max="200" value="80" required></label>
<label for="profile-copy">Copy label<input id="profile-copy" name="copy_label" value="Original" maxlength="60" required></label><button type="submit">Add print profile</button></form></details>
<details class="panel"><summary>Delivery channel</summary><p>Email uses the configured mail transport. SMS and WhatsApp use company Twilio credentials. Saved secrets are encrypted.</p><form method="POST" action="{{ url('/compliance/setup/channel') }}" class="fields" autocomplete="off">@csrf
<label for="channel-type">Channel<select id="channel-type" name="channel"><option value="email">Email</option><option value="sms">SMS</option><option value="whatsapp">WhatsApp</option></select></label>
<label for="channel-from">Sender address / phone<input id="channel-from" name="from" maxlength="191" required></label>
<label for="channel-account">Twilio Account SID<input id="channel-account" name="account_sid" maxlength="34" autocomplete="off"></label>
<label for="channel-token">Twilio auth token<input id="channel-token" name="auth_token" type="password" maxlength="200" autocomplete="new-password"></label>
<label for="channel-content">WhatsApp approved Content SID<input id="channel-content" name="content_sid" maxlength="34"></label>
<p>WhatsApp template variables: 1 invoice number, 2 currency and total, 3 company name.</p><button type="submit">Save and enable channel</button></form></details>
</div>
<section class="panel"><h2>Stock loss / disposal</h2><form method="POST" action="{{ url('/compliance/stock-loss') }}" class="fields">@csrf
<input type="hidden" name="idempotency_key" value="{{ (string) Illuminate\Support\Str::uuid() }}">
<label for="loss-date">Date<input id="loss-date" name="business_date" type="date" required></label><label for="loss-product">Product ID<input id="loss-product" name="product_id" type="number" min="1" required></label>
<label for="loss-warehouse">Warehouse<select id="loss-warehouse" name="warehouse_id">@foreach($warehouses as $warehouse)<option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>@endforeach</select></label>
<label for="loss-qty">Quantity<input id="loss-qty" name="qty" type="number" min="0.0001" step="0.0001" required></label>
<label for="loss-batch">Batch ID, if tracked<input id="loss-batch" name="product_batch_id" type="number" min="1"></label>
<label for="loss-identity">Stock identity ID, if tracked<input id="loss-identity" name="stock_identity_id" type="number" min="1"></label>
<label for="loss-disposition">Disposition<select id="loss-disposition" name="disposition">@foreach(['damaged','expired','spillage','theft','quality_reject','sample','internal_consumption'] as $value)<option value="{{ $value }}">{{ ucfirst(str_replace('_',' ',$value)) }}</option>@endforeach</select></label>
<label for="loss-reason">Reason<input id="loss-reason" name="reason" required minlength="3" maxlength="500"></label><button type="submit">Post stock loss</button></form></section>
@endif
<section class="panel"><h2>Effective rate catalog</h2><div class="table-scroll"><table><thead><tr><th scope="col">Period ID</th><th scope="col">Category ID</th><th scope="col">GST %</th><th scope="col">Cess %</th><th scope="col">From</th><th scope="col">To</th></tr></thead><tbody>
@forelse($rates as $rate)<tr><td>{{ $rate->id }}</td><td>{{ $rate->tax_category_id }}</td><td>{{ $rate->rate }}</td><td>{{ $rate->cess_rate }}</td><td>{{ $rate->effective_from }}</td><td>{{ $rate->effective_to ?: 'Open-ended' }}</td></tr>@empty<tr><td colspan="6">No effective rates configured.</td></tr>@endforelse</tbody></table></div></section>
@endsection
