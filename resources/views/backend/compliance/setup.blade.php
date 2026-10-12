@extends('backend.compliance.layout')
@section('title','Tax & document settings')
@section('compliance_body')
<p>Use reviewed registration and rate data. GSTIN format checks do not establish active registration.</p>
<section class="panel"><h2>GSTIN lookup</h2><form id="gst-lookup-form" class="fields">
<label for="lookup-gstin">GSTIN to verify<input id="lookup-gstin" name="gstin" required minlength="15" maxlength="15"></label><button type="submit">Check registration</button></form>
<p id="gst-lookup-result" role="status" aria-live="polite">Lookup does not change saved party data. If unavailable, enter reviewed details manually.</p></section>
@push('scripts')<script defer src="{{ asset('js/compliance.js') }}"></script>@endpush
<section class="panel"><h2>GST registration, rates, HSN and product tax</h2><p>These moved to <a href="{{ route('settings.gst') }}">Settings › GST</a>. Registration: {{ $registrations->whereNull('effective_to')->first()?->gstin ?? 'not set' }}.</p></section>
@if($canManage)
<div class="settings-grid">
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
@endsection
