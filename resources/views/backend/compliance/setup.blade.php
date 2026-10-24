@extends('backend.compliance.layout')
@section('title','Tax & document settings')
@section('compliance_body')
@php
    $registration = $registrations->whereNull('effective_to')->first();
    $quarantine = $warehouses->where('is_quarantine', true);
@endphp
<style>
    /* Everything on one screen: three columns of cards over a full-width stock-loss strip. */
    .tax-settings { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px; }
    .tax-settings .cx-col { display: flex; flex-direction: column; gap: 10px; min-width: 0; }
    .tax-settings .cx-wide { grid-column: 1 / -1; }
    .tax-settings .cx-card { padding: 10px 12px; }
    .tax-settings .cx-card h2 { margin-bottom: 6px; }
    .tax-settings .cx-card > p { margin-bottom: 6px; font-size: 11.5px; }
    .tax-settings .cx-form { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 6px 10px; }
    .tax-settings .cx-form input:not([type=checkbox]):not([type=hidden]), .tax-settings .cx-form select { height: 30px; }
    .tax-settings .cx-inline { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 8px; align-items: end; }
    .tax-settings .cx-list { list-style: none; margin: 0 0 6px; padding: 0; display: flex; flex-wrap: wrap; gap: 4px; }
    .tax-settings .cx-kv { grid-template-columns: auto 1fr auto 1fr; font-size: 12px; }
    .tax-settings .loss-form { grid-template-columns: 130px minmax(0, 2fr) minmax(0, 1.2fr) minmax(0, 1.4fr) 100px minmax(0, 1fr) 110px minmax(0, 2fr) auto; }
    @media (max-width: 1199px) {
        .tax-settings { grid-template-columns: 1fr 1fr; }
        .tax-settings .loss-form { grid-template-columns: repeat(3, minmax(0, 1fr)); }
    }
</style>

<div class="tax-settings">
    <div class="cx-col">
        <section class="cx-card">
            <h2><i class="dripicons-document"></i> GST registration</h2>
            @if($registration)
                <dl class="cx-kv">
                    <dt>GSTIN</dt><dd>{{ $registration->gstin ?: 'Unregistered' }}</dd>
                    <dt>Type</dt><dd><span class="cx-pill {{ $registration->registration_type === 'regular' ? 'ok' : 'warn' }}">{{ ucfirst($registration->registration_type) }}</span></dd>
                    <dt>Name</dt><dd>{{ $registration->trade_name ?: $registration->legal_name }}</dd>
                    <dt>State</dt><dd>{{ \App\Services\Tax\Gstin::$states[$registration->state_code] ?? $registration->state_code }} ({{ $registration->state_code }})</dd>
                </dl>
            @else
                <p><span class="cx-pill warn">Not set</span> Bills cannot carry GST until it is entered.</p>
            @endif
            <a class="cx-btn ghost mt-2" href="{{ route('settings.gst') }}"><i class="dripicons-gear"></i> Registration, rates, HSN &amp; item tax</a>
        </section>
        <section class="cx-card">
            <h2><i class="dripicons-search"></i> Verify a GSTIN</h2>
            <form id="gst-lookup-form" class="cx-inline">
                <div class="cx-field"><label for="lookup-gstin">Party GSTIN</label><input id="lookup-gstin" name="gstin" required minlength="15" maxlength="15" class="text-uppercase" placeholder="15-character GSTIN" style="width:100%;height:30px;font-size:13px;padding:4px 8px;border:1px solid #cbd5e1;border-radius:6px"></div>
                <button type="submit" class="cx-btn">Check</button>
            </form>
            <p id="gst-lookup-result" class="cx-help mt-1 mb-0" role="status" aria-live="polite">Shows registration status only; nothing is saved.</p>
        </section>
        @if($canManage)
        <section class="cx-card">
            <h2><i class="dripicons-return"></i> Return approval policy</h2>
            <p>{!! $policy ? 'Notes above <b>₹'.number_format((float) $policy['amount'], 2).'</b> or older than <b>'.(int) $policy['days'].' days</b> need approval.' : '<span class="cx-pill warn">Every return needs approval</span> until limits are set.' !!}</p>
            <form method="POST" action="{{ url('/compliance/setup/return-policy') }}" class="cx-form" data-enter-flow>@csrf
                <div><label for="policy-amount">Approve above ₹</label><input id="policy-amount" name="amount" type="number" min="0" step="0.0001" value="{{ $policy['amount'] ?? '' }}" required></div>
                <div><label for="policy-days">Approve after days</label><input id="policy-days" name="days" type="number" min="0" max="3650" value="{{ $policy['days'] ?? '' }}" required></div>
                <div class="cx-actions cx-span"><button type="submit" class="cx-btn">Save policy</button></div>
            </form>
        </section>
        @endif
    </div>

    @if($canManage)
    <div class="cx-col">
        <section class="cx-card">
            <h2><i class="dripicons-warning"></i> Quarantine warehouse</h2>
            <ul class="cx-list">@forelse($quarantine as $w)<li><span class="cx-pill ok">{{ $w->name }}</span></li>@empty<li><span class="cx-pill warn">None yet</span></li>@endforelse</ul>
            <form method="POST" action="{{ url('/compliance/setup/quarantine') }}" class="cx-inline">@csrf
                <div class="cx-field"><label for="quarantine-warehouse">Expired / damaged returns go to</label>
                    <select id="quarantine-warehouse" name="warehouse_id" style="width:100%;height:30px;font-size:13px;border:1px solid #cbd5e1;border-radius:6px">@foreach($warehouses as $warehouse)<option value="{{ $warehouse->id }}">{{ $warehouse->name }}{{ $warehouse->is_quarantine ? ' · Quarantine' : '' }}</option>@endforeach</select></div>
                <button type="submit" class="cx-btn">Mark</button>
            </form>
        </section>
        <section class="cx-card">
            <h2><i class="dripicons-mail"></i> Send invoices by</h2>
            <ul class="cx-list">@foreach(['email' => 'Email', 'sms' => 'SMS', 'whatsapp' => 'WhatsApp'] as $key => $label)@php $on = $channels->firstWhere('channel', $key)?->is_active; @endphp<li><span class="cx-pill {{ $on ? 'ok' : 'muted' }}">{{ $label }} · {{ $on ? 'On' : 'Off' }}</span></li>@endforeach</ul>
            <p>SMS and WhatsApp use your Twilio account; secrets are stored encrypted. WhatsApp template variables: 1 invoice no., 2 total, 3 company.</p>
            <form method="POST" action="{{ url('/compliance/setup/channel') }}" class="cx-form" autocomplete="off" data-enter-flow>@csrf
                <div><label for="channel-type">Channel</label><select id="channel-type" name="channel"><option value="email">Email</option><option value="sms">SMS</option><option value="whatsapp">WhatsApp</option></select></div>
                <div><label for="channel-from">Sender address / phone</label><input id="channel-from" name="from" maxlength="191" required></div>
                <div><label for="channel-account">Twilio Account SID</label><input id="channel-account" name="account_sid" maxlength="34" autocomplete="off"></div>
                <div><label for="channel-token">Twilio auth token</label><input id="channel-token" name="auth_token" type="password" maxlength="200" autocomplete="new-password"></div>
                <div><label for="channel-content">WhatsApp Content SID</label><input id="channel-content" name="content_sid" maxlength="34"></div>
                <div class="cx-actions"><button type="submit" class="cx-btn">Save &amp; enable</button></div>
            </form>
        </section>
    </div>

    <div class="cx-col">
        <section class="cx-card">
            <h2><i class="dripicons-print"></i> Print profiles</h2>
            <ul class="cx-list">@forelse($profiles as $profile)<li><span class="cx-pill muted">{{ $profile->name }} · {{ str_replace('_', ' ', $profile->document_type ?? '') }} · {{ strtoupper(str_replace('_', ' ', $profile->format ?? '')) }}</span></li>@empty<li><span class="cx-pill muted">Default A4 only</span></li>@endforelse</ul>
            <form method="POST" action="{{ url('/compliance/setup/profile') }}" class="cx-form" data-enter-flow>@csrf
                <div><label for="profile-name">Profile name</label><input id="profile-name" name="name" maxlength="191" required placeholder="e.g. Counter thermal"></div>
                <div><label for="profile-document">Document</label><select id="profile-document" name="document_type">@foreach(['sale' => 'Sales invoice', 'purchase' => 'Purchase bill', 'sale_note' => 'Sales note', 'purchase_note' => 'Purchase note'] as $kind => $label)<option value="{{ $kind }}">{{ $label }}</option>@endforeach</select></div>
                <div><label for="profile-format">Format</label><select id="profile-format" name="format"><option value="a4">A4</option><option value="thermal">Thermal</option><option value="dot_matrix">Dot matrix</option></select></div>
                <div><label for="profile-width">Paper width (mm)</label><input id="profile-width" name="paper_width" type="number" min="58" max="300" value="210" required></div>
                <div><label for="profile-height">Lines per page</label><input id="profile-height" name="height_lines" type="number" min="20" max="200" value="68" required></div>
                <div><label for="profile-columns">Text columns</label><input id="profile-columns" name="columns" type="number" min="60" max="200" value="80" required></div>
                <div><label for="profile-copy">Copy label</label><input id="profile-copy" name="copy_label" value="Original" maxlength="60" required></div>
                <div class="cx-actions"><button type="submit" class="cx-btn">Add profile</button></div>
            </form>
        </section>
    </div>

    <section class="cx-card cx-wide">
        <h2><i class="dripicons-trash"></i> Stock loss / disposal <span class="text-muted font-weight-normal small ml-1">Removes stock and its value. Reverse the ITC on goods lost, destroyed, stolen or given as samples (Sec 17(5)(h)).</span></h2>
        <form method="POST" action="{{ url('/compliance/stock-loss') }}" class="cx-form loss-form" id="stock-loss-form" data-enter-flow>@csrf
            <input type="hidden" name="idempotency_key" value="{{ (string) Illuminate\Support\Str::uuid() }}">
            <div><label for="loss-date">Date</label><input id="loss-date" name="business_date" type="date" value="{{ now()->toDateString() }}" required></div>
            <div><label for="loss-product">Item</label>
                <select id="loss-product" name="product_id" required>
                    <option value="">Select item…</option>
                    @foreach($products as $product)<option value="{{ $product->id }}" data-batch="{{ $product->is_batch ? 1 : 0 }}">{{ $product->name }}{{ $product->code ? ' · '.$product->code : '' }}</option>@endforeach
                </select></div>
            <div><label for="loss-warehouse">Warehouse</label><select id="loss-warehouse" name="warehouse_id">@foreach($warehouses as $warehouse)<option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>@endforeach</select></div>
            <div><label for="loss-batch">Batch</label><select id="loss-batch" name="product_batch_id" disabled><option value="">Not batch-tracked</option></select></div>
            <div><label for="loss-qty">Quantity</label><input id="loss-qty" name="qty" type="number" min="0.0001" step="0.0001" required></div>
            <div><label for="loss-disposition">Reason</label><select id="loss-disposition" name="disposition">@foreach(['damaged','expired','spillage','theft','quality_reject','sample','internal_consumption'] as $value)<option value="{{ $value }}">{{ ucfirst(str_replace('_',' ',$value)) }}</option>@endforeach</select></div>
            <div><label for="loss-identity">Serial / piece ID</label><input id="loss-identity" name="stock_identity_id" type="number" min="1" placeholder="If tracked"></div>
            <div><label for="loss-reason">Details</label><input id="loss-reason" name="reason" required minlength="3" maxlength="500" placeholder="What happened, who checked"></div>
            <div class="cx-actions"><button type="submit" class="cx-btn danger">Post loss</button></div>
        </form>
    </section>
    @endif
</div>

@push('scripts')
<script defer src="{{ asset('js/compliance.js') }}"></script>
<script>
    (function () {
        var $product = $('#loss-product'), $warehouse = $('#loss-warehouse'), $batch = $('#loss-batch');
        if (!$product.length) return;
        // Batch-tracked items list the batches in stock at the chosen warehouse, earliest expiry first.
        function loadBatches() {
            var option = $product.find('option:selected');
            $batch.empty();
            if (!$product.val() || String(option.data('batch')) !== '1') { $batch.append(new Option('Not batch-tracked', '')).prop({ disabled: true, required: false }); return; }
            $batch.prop({ disabled: false, required: true }).append(new Option('Loading…', ''));
            $.getJSON('{{ url('/operations/inventory/lots') }}', { product_id: $product.val(), warehouse_id: $warehouse.val() })
                .done(function (res) {
                    $batch.empty().append(new Option((res.data || []).length ? 'Select batch' : 'No stock here', ''));
                    (res.data || []).forEach(function (lot) {
                        $batch.append(new Option(lot.batch_no + (lot.expired_date ? ' · exp ' + lot.expired_date : '') + ' · ' + (+lot.qty) + ' in stock', lot.id));
                    });
                })
                .fail(function () { $batch.empty().append(new Option('Batches unavailable', '')).prop('required', false); });
        }
        $product.on('change', loadBatches);
        $warehouse.on('change', loadBatches);
    })();
</script>
@endpush
@endsection
