@extends('backend.layout.main')
@section('title', 'GST Settings')

@section('content')
<style>
    .gst-wrap { padding: 12px 18px 28px; }
    .gst-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 12px; flex-wrap: wrap; }
    .gst-head h1 { font-size: 18px; font-weight: 800; margin: 0; color: #0f172a; }
    .gst-head p { margin: 2px 0 0; font-size: 12.5px; color: #64748b; }
    .gst-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 14px 16px; margin-bottom: 12px; }
    .gst-card h2 { font-size: 14px; font-weight: 700; margin: 0 0 10px; color: #0f172a; }
    .gst-steps { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 10px; margin-bottom: 12px; }
    .gst-step { border: 1px solid #e2e8f0; border-radius: 10px; padding: 10px 12px; background: #fff; font-size: 12.5px; }
    .gst-step strong { display: block; font-size: 13px; color: #0f172a; }
    .gst-step.ok { border-color: #a7f3d0; background: #f0fdf4; }
    .gst-step.todo { border-color: #fde68a; background: #fffbeb; }
    .gst-tabs .nav-link { font-size: 13px; font-weight: 600; color: #475569; border: none; border-bottom: 2px solid transparent; padding: 8px 14px; }
    .gst-tabs .nav-link.active { color: #4f46e5; border-bottom-color: #4f46e5; background: transparent; }
    .gst-table { width: 100%; font-size: 13px; }
    .gst-table th { font-size: 11px; text-transform: uppercase; letter-spacing: .04em; color: #64748b; padding: 8px 10px; border-bottom: 1px solid #e2e8f0; background: #f8fafc; position: sticky; top: 0; }
    .gst-table td { padding: 7px 10px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
    .gst-table input, .gst-table select, .gst-form input, .gst-form select, .gst-form textarea { font-size: 13px; height: 32px; padding: 4px 8px; border: 1px solid #cbd5e1; border-radius: 6px; background: #fff; color: #0f172a; width: 100%; }
    .gst-form textarea { height: 60px; }
    .gst-form label { font-size: 12px; font-weight: 600; color: #334155; margin-bottom: 3px; display: block; }
    .gst-form .row > div { margin-bottom: 8px; }
    .gst-scroll { max-height: 60vh; overflow: auto; border: 1px solid #e2e8f0; border-radius: 8px; }
    .gst-pill { display: inline-block; font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 999px; background: #eef2ff; color: #4338ca; }
    .gst-pill.warn { background: #fef3c7; color: #92400e; }
    .gst-pill.muted { background: #f1f5f9; color: #475569; }
    .gst-muted { color: #64748b; font-size: 12px; }
    body.dark-mode .gst-card, body.dark-mode .gst-step { background: #0f172a; border-color: #1e293b; }
    body.dark-mode .gst-step.ok { background: #052e1a; border-color: #14532d; }
    body.dark-mode .gst-step.todo { background: #2a1f05; border-color: #713f12; }
    body.dark-mode .gst-head h1, body.dark-mode .gst-card h2, body.dark-mode .gst-step strong { color: #f8fafc; }
    body.dark-mode .gst-table th { background: #111827; border-color: #1e293b; color: #94a3b8; }
    body.dark-mode .gst-table td { border-color: #1e293b; color: #e2e8f0; }
    body.dark-mode .gst-table input, body.dark-mode .gst-table select, body.dark-mode .gst-form input, body.dark-mode .gst-form select, body.dark-mode .gst-form textarea { background: #111827; border-color: #334155; color: #f8fafc; }
    body.dark-mode .gst-form label { color: #94a3b8; }
    body.dark-mode .gst-scroll { border-color: #1e293b; }
</style>

<div class="gst-wrap">
    <div class="gst-head">
        <div>
            <h1>GST Settings</h1>
            <p>Products carry a GST rate and HSN/SAC. The app splits CGST + SGST or IGST from your state and the party's state.</p>
        </div>
        @if($canManage)
        <form method="POST" action="{{ url('/settings/gst/install') }}">@csrf<input type="hidden" name="tab" value="rates">
            <button class="btn btn-primary btn-sm" type="submit"><i class="fa fa-magic mr-1"></i> Install standard GST setup</button>
        </form>
        @endif
    </div>

    @if(session('message'))<div class="alert alert-success py-2">{{ session('message') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger py-2"><ul class="mb-0 pl-3">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    @php $taxable = $categories->where('supply_type', 'taxable')->count(); @endphp
    <div class="gst-steps">
        <div class="gst-step {{ $registration ? 'ok' : 'todo' }}"><strong>1. Company GSTIN</strong>{{ $registration ? $registration->gstin.' · '.($states[$registration->state_code] ?? $registration->state_code) : 'Not registered yet' }}</div>
        <div class="gst-step {{ $taxable ? 'ok' : 'todo' }}"><strong>2. GST rates</strong>{{ $taxable ? $categories->count().' rates set up' : 'Click "Install standard GST setup"' }}</div>
        <div class="gst-step {{ $missing ? 'todo' : 'ok' }}"><strong>3. Products</strong>{{ $missing ? $missing.' products need a GST rate or HSN' : 'All products have GST rate + HSN' }}</div>
        <div class="gst-step {{ $ledgers->every(fn ($l) => $l->account) ? 'ok' : 'todo' }}"><strong>4. GST ledgers</strong>{{ $ledgers->filter(fn ($l) => $l->account)->count() }}/8 mapped</div>
        <div class="gst-step {{ $gstMode ? 'ok' : 'todo' }}"><strong>Billing mode</strong>{{ $gstMode ? 'GST billing is ON for this branch' : 'GST billing starts after step 1' }}</div>
    </div>

    <ul class="nav gst-tabs mb-2" role="tablist">
        @foreach(['registration' => 'Registration', 'rates' => 'GST Rates', 'products' => 'Products', 'codes' => 'HSN / SAC', 'parties' => 'Parties', 'ledgers' => 'Ledgers'] as $tab => $label)
            <li class="nav-item"><a class="nav-link {{ $tab === 'rates' ? 'active' : '' }}" data-toggle="tab" href="#{{ $tab }}" role="tab">{{ $label }}</a></li>
        @endforeach
    </ul>

    <div class="tab-content">
        {{-- Registration --}}
        <div class="tab-pane fade" id="registration" role="tabpanel">
            <div class="gst-card">
                <h2>Company GST registration (this branch)</h2>
                @forelse($registrations as $row)
                    <p class="mb-1">{{ $row->gstin }} · {{ $row->legal_name }} · {{ $states[$row->state_code] ?? $row->state_code }} · {{ ucfirst($row->registration_type) }}
                        <span class="gst-muted">({{ $row->effective_from }} — {{ $row->effective_to ?: 'current' }})</span></p>
                @empty
                    <p class="gst-muted">No GSTIN yet. Until you add one, bills use the old tax list without GST.</p>
                @endforelse
                @if($canManage)
                <form method="POST" action="{{ url('/settings/gst/registration') }}" class="gst-form mt-2" id="gst-registration-form">@csrf<input type="hidden" name="tab" value="registration">
                    <div class="row">
                        <div class="col-md-3"><label>GSTIN *</label><input name="gstin" id="reg-gstin" maxlength="15" required value="{{ old('gstin', $registration->gstin ?? '') }}"></div>
                        <div class="col-md-3"><label>Legal name *</label><input name="legal_name" id="reg-legal" maxlength="191" required value="{{ old('legal_name', $registration->legal_name ?? '') }}"></div>
                        <div class="col-md-2"><label>Trade name</label><input name="trade_name" id="reg-trade" maxlength="191" value="{{ old('trade_name', $registration->trade_name ?? '') }}"></div>
                        <div class="col-md-2"><label>Type *</label><select name="registration_type">@foreach(['regular' => 'Regular', 'composition' => 'Composition'] as $value => $label)<option value="{{ $value }}" @selected(old('registration_type', $registration->registration_type ?? 'regular') === $value)>{{ $label }}</option>@endforeach</select></div>
                        <div class="col-md-2"><label>Effective from *</label><input type="date" name="effective_from" required value="{{ old('effective_from', $registration->effective_from ?? now()->startOfYear()->toDateString()) }}"></div>
                        <div class="col-md-10"><label>Registered address *</label><textarea name="address" id="reg-address" maxlength="1000" required>{{ old('address', $registration->address ?? '') }}</textarea></div>
                        <div class="col-md-2 d-flex align-items-end flex-column" style="gap:6px;">
                            <button type="button" class="btn btn-outline-secondary btn-sm w-100" id="reg-lookup">Fetch from GSTIN</button>
                            <button type="submit" class="btn btn-primary btn-sm w-100">Save registration</button>
                        </div>
                    </div>
                    <p class="gst-muted mb-0" id="reg-lookup-status">Same GSTIN updates details. A new GSTIN closes the old one the day before its start date.</p>
                </form>
                @endif
            </div>
        </div>

        {{-- Rates --}}
        <div class="tab-pane fade show active" id="rates" role="tabpanel">
            <div class="gst-card">
                <h2>GST rates</h2>
                <div class="gst-scroll">
                <table class="gst-table">
                    <thead><tr><th>Name</th><th>Type</th><th>GST %</th><th>Cess %</th><th>Since</th><th>Input credit</th><th>Products</th>@if($canManage)<th>Change rate from a date</th>@endif</tr></thead>
                    <tbody>
                    @forelse($categories as $category)
                        <tr>
                            <td><strong>{{ $category->name }}</strong>
                                @if($category->history->count() > 1)<div class="gst-muted">@foreach($category->history as $period){{ (float) $period->rate }}% {{ $period->effective_from }}→{{ $period->effective_to ?: 'now' }}@if(!$loop->last); @endif @endforeach</div>@endif</td>
                            <td><span class="gst-pill {{ $category->supply_type === 'taxable' ? '' : 'muted' }}">{{ ucwords(str_replace('_', ' ', $category->supply_type)) }}</span></td>
                            <td>{{ $category->current ? (float) $category->current->rate.'%' : '—' }}</td>
                            <td>{{ $category->current ? (float) $category->current->cess_rate.'%' : '—' }}</td>
                            <td>{{ $category->current->effective_from ?? '—' }}</td>
                            <td>{{ $category->input_credit_allowed ? 'Yes' : 'No' }}</td>
                            <td>{{ $category->products }}</td>
                            @if($canManage)
                            <td>
                                @if($category->supply_type === 'taxable')
                                <form method="POST" action="{{ url('/settings/gst/rate') }}" class="d-flex" style="gap:4px;">@csrf<input type="hidden" name="tab" value="rates"><input type="hidden" name="id" value="{{ $category->id }}">
                                    <input name="rate" type="number" step="0.01" min="0" max="100" placeholder="GST %" required style="width:72px;">
                                    <input name="cess_rate" type="number" step="0.01" min="0" max="100" value="0" title="Cess %" style="width:60px;">
                                    <input name="effective_from" type="date" required style="width:140px;">
                                    <button class="btn btn-outline-primary btn-sm" type="submit">Save</button>
                                </form>
                                @else<span class="gst-muted">No tax charged</span>@endif
                            </td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center gst-muted py-4">No GST rates yet. Click "Install standard GST setup" for 0%, 5%, 12%, 18%, 28%, 40%, exempt and nil-rated.</td></tr>
                    @endforelse
                    </tbody>
                </table>
                </div>
                @if($canManage)
                <form method="POST" action="{{ url('/settings/gst/category') }}" class="gst-form mt-3">@csrf<input type="hidden" name="tab" value="rates">
                    <div class="row">
                        <div class="col-md-3"><label>New rate name *</label><input name="name" required maxlength="191" placeholder="e.g. GST 18% + Cess 12%"></div>
                        <div class="col-md-2"><label>Type *</label><select name="supply_type"><option value="taxable">Taxable</option><option value="exempt">Exempt</option><option value="nil_rated">Nil rated</option><option value="non_gst">Non-GST</option></select></div>
                        <div class="col-md-2"><label>GST % *</label><input name="rate" type="number" step="0.01" min="0" max="100" value="0" required></div>
                        <div class="col-md-1"><label>Cess %</label><input name="cess_rate" type="number" step="0.01" min="0" max="100" value="0"></div>
                        <div class="col-md-2"><label>Effective from</label><input name="effective_from" type="date" value="{{ \App\Services\Tax\GstSetupService::EFFECTIVE_FROM }}"></div>
                        <div class="col-md-2 d-flex align-items-end"><button type="submit" class="btn btn-primary btn-sm w-100">Add GST rate</button></div>
                    </div>
                    <p class="gst-muted mb-0">Change a rate with a date instead of editing it, so older bills keep the rate they were posted with.</p>
                </form>
                @endif
                @if($legacyTaxes)
                    <p class="gst-muted mt-2 mb-0">The old "Tax" list ({{ $legacyTaxes }} entries, e.g. VAT) is only used by companies without GST registration.</p>
                @endif
            </div>
        </div>

        {{-- Products --}}
        <div class="tab-pane fade" id="products" role="tabpanel">
            <div class="gst-card">
                <div class="d-flex align-items-center justify-content-between mb-2" style="gap:8px;flex-wrap:wrap;">
                    <h2 class="mb-0">Product GST rate &amp; HSN/SAC</h2>
                    <form method="GET" action="{{ url('/settings/gst') }}" class="d-flex gst-form" style="gap:6px;">
                        <select name="products" style="width:170px;"><option value="missing" @selected($productFilter === 'missing')>Needs GST details</option><option value="all" @selected($productFilter === 'all')>All products</option></select>
                        <input name="q" value="{{ request('q') }}" placeholder="Search name or code" style="width:200px;">
                        <input type="hidden" name="tab" value="products">
                        <button class="btn btn-outline-secondary btn-sm">Filter</button>
                    </form>
                </div>
                <form method="POST" action="{{ url('/settings/gst/products') }}">@csrf<input type="hidden" name="tab" value="products">
                    <div class="gst-scroll">
                    <table class="gst-table">
                        <thead><tr><th>Product</th><th>Type</th><th style="width:240px;">GST rate</th><th style="width:150px;">HSN / SAC</th></tr></thead>
                        <tbody>
                        @forelse($products as $product)
                            <tr>
                                <td><strong>{{ $product->name }}</strong> <span class="gst-muted">{{ $product->code }}</span></td>
                                <td>{{ in_array($product->type, ['service', 'digital'], true) ? 'Service (SAC)' : 'Goods (HSN)' }}</td>
                                <td><select name="products[{{ $product->id }}][tax_category_id]">
                                    <option value="">— Not set —</option>
                                    @foreach($categories as $category)<option value="{{ $category->id }}" @selected((int) $product->tax_category_id === (int) $category->id)>{{ $category->name }}</option>@endforeach
                                </select></td>
                                <td><input name="products[{{ $product->id }}][hsn_code]" value="{{ $product->hsn_code }}" maxlength="8" inputmode="numeric" placeholder="{{ in_array($product->type, ['service', 'digital'], true) ? '99xxxx' : '4/6/8 digits' }}"></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center gst-muted py-4">{{ $productFilter === 'missing' ? 'Every product has a GST rate and HSN/SAC.' : 'No products found.' }}</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                    </div>
                    @if($products->isNotEmpty())<div class="text-right mt-2"><button type="submit" class="btn btn-primary btn-sm">Save products</button></div>@endif
                </form>
            </div>
        </div>

        {{-- Codes --}}
        <div class="tab-pane fade" id="codes" role="tabpanel">
            <div class="gst-card">
                <h2>HSN / SAC codes</h2>
                <p class="gst-muted">Codes typed on a product are added here automatically. Add descriptions for your invoices.</p>
                @if($canManage)
                <form method="POST" action="{{ url('/settings/gst/code') }}" class="gst-form mb-2">@csrf<input type="hidden" name="tab" value="codes">
                    <div class="row">
                        <div class="col-md-2"><label>Code *</label><input name="code" maxlength="8" required inputmode="numeric"></div>
                        <div class="col-md-2"><label>Kind *</label><select name="kind"><option value="hsn">Goods (HSN)</option><option value="sac">Service (SAC)</option></select></div>
                        <div class="col-md-6"><label>Description *</label><input name="description" maxlength="191" required></div>
                        <div class="col-md-2 d-flex align-items-end"><button class="btn btn-primary btn-sm w-100" type="submit">Save code</button></div>
                    </div>
                </form>
                @endif
                <div class="gst-scroll">
                <table class="gst-table"><thead><tr><th>Code</th><th>Kind</th><th>Description</th></tr></thead><tbody>
                    @forelse($codes as $code)<tr><td>{{ $code->code }}</td><td>{{ strtoupper($code->kind) }}</td><td>{{ $code->description }}</td></tr>
                    @empty<tr><td colspan="3" class="text-center gst-muted py-4">No codes yet.</td></tr>@endforelse
                </tbody></table>
                </div>
            </div>
        </div>

        {{-- Parties --}}
        <div class="tab-pane fade" id="parties" role="tabpanel">
            <div class="gst-card">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <h2 class="mb-0">Party GST profiles</h2>
                    @if($canManage)
                    <form method="POST" action="{{ url('/settings/gst/parties') }}">@csrf<input type="hidden" name="tab" value="parties"><button class="btn btn-outline-primary btn-sm">Sync from party masters</button></form>
                    @endif
                </div>
                <p class="gst-muted">Filled from the GSTIN and state on each supplier/customer. Edit the party to change them; set Composition here.</p>
                <div class="gst-scroll">
                <table class="gst-table"><thead><tr><th>Party</th><th>GSTIN on master</th><th>GST profile</th>@if($canManage)<th style="width:330px;">Override</th>@endif</tr></thead><tbody>
                    @forelse($parties as $party)
                        <tr>
                            <td><strong>{{ $party->name }}</strong> <span class="gst-muted">{{ ucfirst($party->type) }}</span></td>
                            <td>{{ $party->gstin ?: '—' }}</td>
                            <td>@if($party->profile)<span class="gst-pill {{ $party->profile->registration_type === 'unregistered' ? 'muted' : '' }}">{{ ucfirst($party->profile->registration_type) }}</span> {{ $states[$party->profile->state_code] ?? $party->profile->state_code }}@else<span class="gst-pill warn">Missing — add GSTIN or state</span>@endif</td>
                            @if($canManage)
                            <td><form method="POST" action="{{ url('/settings/gst/party') }}" class="d-flex" style="gap:4px;">@csrf<input type="hidden" name="tab" value="parties">
                                <input type="hidden" name="party_type" value="{{ $party->type }}"><input type="hidden" name="party_id" value="{{ $party->id }}">
                                <select name="registration_type" style="width:120px;">@foreach(['regular' => 'Regular', 'composition' => 'Composition', 'unregistered' => 'Unregistered'] as $value => $label)<option value="{{ $value }}" @selected(($party->profile->registration_type ?? '') === $value)>{{ $label }}</option>@endforeach</select>
                                <input type="hidden" name="gstin" value="{{ $party->profile->gstin ?? '' }}">
                                <select name="state_code" style="width:130px;">@foreach($states as $code => $name)<option value="{{ $code }}" @selected(($party->profile->state_code ?? '') === (string) $code)>{{ $name }}</option>@endforeach</select>
                                <button class="btn btn-outline-secondary btn-sm">Save</button></form></td>
                            @endif
                        </tr>
                    @empty<tr><td colspan="4" class="text-center gst-muted py-4">No parties yet.</td></tr>@endforelse
                </tbody></table>
                </div>
            </div>
        </div>

        {{-- Ledgers --}}
        <div class="tab-pane fade" id="ledgers" role="tabpanel">
            <div class="gst-card">
                <h2>GST ledgers</h2>
                <p class="gst-muted">Input GST is claimed on purchases; output GST is collected on sales. "Install standard GST setup" creates and maps any missing ledger.</p>
                <table class="gst-table"><thead><tr><th>Posting role</th><th>Ledger</th></tr></thead><tbody>
                    @foreach($ledgers as $ledger)<tr><td>{{ $ledger->name }}</td><td>@if($ledger->account){{ $ledger->account->code }} · {{ $ledger->account->name }}@else<span class="gst-pill warn">Not mapped</span>@endif</td></tr>@endforeach
                </tbody></table>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    'use strict';
    var hash = (location.hash || '').replace('#', '') || new URLSearchParams(location.search).get('tab');
    if (hash && $('.gst-tabs a[href="#' + hash + '"]').length) $('.gst-tabs a[href="#' + hash + '"]').tab('show');
    $('.gst-tabs a').on('shown.bs.tab', function () { history.replaceState(null, '', '#' + this.getAttribute('href').slice(1)); });

    $('#reg-lookup').on('click', function () {
        var gstin = ($('#reg-gstin').val() || '').trim().toUpperCase();
        var status = $('#reg-lookup-status');
        if (gstin.length !== 15) { status.text('Enter the 15-character GSTIN first.'); return; }
        status.text('Fetching GSTIN details…');
        $.ajax({ url: '{{ route('parties.gst-lookup') }}', method: 'POST', data: { gstin: gstin, _token: '{{ csrf_token() }}' }, headers: { Accept: 'application/json' } })
            .done(function (res) {
                if (res && res.is_valid === false) { status.text(res.error || 'GSTIN is not valid.'); return; }
                var p = (res && res.party) || {};
                if (p.name && !$('#reg-legal').val()) $('#reg-legal').val(p.name);
                if (p.company_name && !$('#reg-trade').val()) $('#reg-trade').val(p.company_name);
                var address = [p.address, p.city, p.state, p.postal_code].filter(Boolean).join(', ');
                if (address && !$('#reg-address').val()) $('#reg-address').val(address);
                status.text(p.name ? 'Details filled. Check them before saving.' : 'GSTIN format OK (' + (res.state_name || '') + '). Enter name and address.');
            })
            .fail(function (xhr) { status.text((xhr.responseJSON && xhr.responseJSON.message) || 'Lookup unavailable. Enter the details manually.'); });
    });
})();
</script>
@endpush
