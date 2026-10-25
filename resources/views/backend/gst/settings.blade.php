@extends('backend.layout.main')
@section('title', 'GST Settings')

@section('content')
<x-settings-command-bar active="gst">
    @if($canManage)
    <form method="POST" action="{{ url('/settings/gst/install') }}" class="u-d-inline-block u-m-0">@csrf<input type="hidden" name="tab" value="rates">
        <button class="btn btn-primary" type="submit"><i class="fa fa-magic mr-1"></i> Install standard GST setup</button>
    </form>
    @endif
</x-settings-command-bar>

<section class="settings-workspace-view">
    <div class="gst-wrap u-p-4px-6px u-w-100pct u-flex-1-1-0 u-ovy-auto">
        <div class="gst-head u-mb-6px">
            <div>
                <p class="u-m-0 u-fs-12px u-c-text-muted">Products carry a GST rate and HSN/SAC. The app splits CGST + SGST or IGST from your state and the party's state.</p>
            </div>
        </div>

    @if(session('message'))<div class="alert alert-success py-2">{{ session('message') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger py-2"><ul class="mb-0 pl-3">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    @php $taxable = $categories->where('supply_type', 'taxable')->count(); @endphp
    <div class="gst-steps">
        <div class="gst-step {{ $registration ? 'ok' : 'todo' }}"><strong>1. Company GSTIN</strong>{{ $registration ? ($registration->gstin ?: 'Unregistered').' · '.($states[$registration->state_code] ?? $registration->state_code) : 'Not registered yet' }}</div>
        <div class="gst-step {{ $taxable ? 'ok' : 'todo' }}"><strong>2. GST rates</strong>{{ $taxable ? $categories->count().' rates set up' : 'Click "Install standard GST setup"' }}</div>
        <div class="gst-step {{ $missing ? 'todo' : 'ok' }}"><strong>3. Products</strong>{{ $missing ? $missing.' products need a GST rate or HSN' : 'All products have GST rate + HSN' }}</div>
        <div class="gst-step {{ $ledgers->every(fn ($l) => $l->account) ? 'ok' : 'todo' }}"><strong>4. GST ledgers</strong>{{ $ledgers->filter(fn ($l) => $l->account)->count() }}/{{ $ledgers->count() }} mapped</div>
        <div class="gst-step {{ $gstMode ? 'ok' : 'todo' }}"><strong>Billing mode</strong>{{ !$gstMode ? 'GST billing starts after step 1' : (($registration->registration_type ?? '') === 'unregistered' ? 'Unregistered: bills carry no GST' : 'GST billing is ON for this branch') }}</div>
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
                    <p class="mb-1">{{ $row->gstin ?: 'No GSTIN' }} · {{ $row->legal_name }} · {{ $states[$row->state_code] ?? $row->state_code }} · {{ ucfirst($row->registration_type) }}
                        <span class="gst-muted">({{ $row->effective_from }} — {{ $row->effective_to ?: 'current' }})</span></p>
                @empty
                    <p class="gst-muted">Not set up yet. Enter your GSTIN, or choose type Unregistered if the business has no GSTIN.</p>
                @endforelse
                @if($canManage)
                <form method="POST" action="{{ url('/settings/gst/registration') }}" class="gst-form mt-2" id="gst-registration-form">@csrf<input type="hidden" name="tab" value="registration">
                    <div class="row">
                        @php $regType = old('registration_type', $registration->registration_type ?? 'regular'); @endphp
                        <div class="col-md-3"><label for="reg-gstin">GSTIN <span id="reg-gstin-star">*</span></label><input name="gstin" id="reg-gstin" maxlength="15" @required($regType !== 'unregistered') @disabled($regType === 'unregistered') value="{{ old('gstin', $registration->gstin ?? '') }}"></div>
                        <div class="col-md-3"><label>Legal name *</label><input name="legal_name" id="reg-legal" maxlength="191" required value="{{ old('legal_name', $registration->legal_name ?? $company->legal_name) }}"></div>
                        <div class="col-md-2"><label>Trade name</label><input name="trade_name" id="reg-trade" maxlength="191" value="{{ old('trade_name', $registration->trade_name ?? $company->trade_name) }}"></div>
                        <div class="col-md-2"><label for="reg-type">Type *</label><select name="registration_type" id="reg-type">@foreach(['regular' => 'Regular', 'composition' => 'Composition', 'unregistered' => 'Unregistered (no GSTIN)'] as $value => $label)<option value="{{ $value }}" @selected($regType === $value)>{{ $label }}</option>@endforeach</select></div>
                        <div class="col-md-2"><label>Effective from *</label><input type="date" name="effective_from" required value="{{ old('effective_from', $registration->effective_from ?? now()->startOfYear()->toDateString()) }}"></div>
                        <div class="col-md-8"><label for="reg-address">Registered address *</label><textarea name="address" id="reg-address" maxlength="1000" required>{{ old('address', $registration->address ?? $companyAddress) }}</textarea></div>
                        <div class="col-md-2"><label for="reg-state">State *</label><select name="state_code" id="reg-state" @disabled($regType !== 'unregistered')><option value="">Choose…</option>@foreach($states as $code => $name)<option value="{{ $code }}" @selected(old('state_code', $registration->state_code ?? $company->state_code) == $code)>{{ $name }}</option>@endforeach</select></div>
                        <div class="col-md-2 d-flex align-items-end flex-column u-gap-6px">
                            <button type="button" class="btn btn-outline-secondary btn-sm w-100" id="reg-lookup">Fetch from GSTIN</button>
                            <button type="submit" class="btn btn-primary btn-sm w-100">Save registration</button>
                        </div>
                    </div>
                    <p class="gst-muted mb-0" id="reg-lookup-status">Same GSTIN updates details. A new GSTIN closes the old one the day before its start date. Unregistered: bills charge no GST and purchase GST goes into cost (no input credit). The state comes from the GSTIN, or from State when unregistered.</p>
                </form>
                @endif
            </div>
            <div class="gst-card">
                <h2>Invoice print details</h2>
                <p class="gst-muted">Printed on the A4 tax invoice under your name and address. Each bill keeps the details it was posted with. Name, address, phone, email, website and logo come from <a href="{{ route('settings.company') }}">Company profile</a>.</p>
                @php $print = old() ? old() : $invoicePrint; @endphp
                <form method="POST" action="{{ url('/settings/gst/print') }}" class="gst-form">@csrf<input type="hidden" name="tab" value="registration">
                    <fieldset @disabled(!$canManage)>
                    <div class="row">
                        <div class="col-md-6"><label for="print-licences">Licence line (IEC, FSSAI, Drug licence…)</label><input id="print-licences" name="licences" maxlength="300" placeholder="IEC: … · FSSAI No: …" value="{{ $print['licences'] ?? '' }}"></div>
                        <div class="col-md-6"><label for="print-dispatch">Dispatch from (if not the registered address)</label><input id="print-dispatch" name="dispatch_from" maxlength="300" value="{{ $print['dispatch_from'] ?? '' }}"></div>
                        <div class="col-md-3"><label for="print-bank">Bank name</label><input id="print-bank" name="bank_name" maxlength="100" value="{{ $print['bank_name'] ?? '' }}"></div>
                        <div class="col-md-3"><label for="print-holder">Account name</label><input id="print-holder" name="bank_account_name" maxlength="150" value="{{ $print['bank_account_name'] ?? '' }}"></div>
                        <div class="col-md-2"><label for="print-account">Account no.</label><input id="print-account" name="bank_account_no" maxlength="40" value="{{ $print['bank_account_no'] ?? '' }}"></div>
                        <div class="col-md-2"><label for="print-ifsc">IFSC</label><input id="print-ifsc" name="bank_ifsc" maxlength="20" value="{{ $print['bank_ifsc'] ?? '' }}"></div>
                        <div class="col-md-2"><label for="print-branch">Branch</label><input id="print-branch" name="bank_branch" maxlength="100" value="{{ $print['bank_branch'] ?? '' }}"></div>
                        <div class="col-md-3"><label for="print-upi">UPI ID</label><input id="print-upi" name="upi_id" maxlength="100" value="{{ $print['upi_id'] ?? '' }}"></div>
                        <div class="col-md-3"><label for="print-signatory">Signature caption</label><input id="print-signatory" name="signatory" maxlength="100" placeholder="Authorised Signatory" value="{{ $print['signatory'] ?? '' }}"></div>
                        <div class="col-md-6"><label for="print-declaration">Declaration</label><input id="print-declaration" name="declaration" maxlength="500" placeholder="We declare that this invoice shows the actual price of the goods described and that all particulars are true and correct." value="{{ $print['declaration'] ?? '' }}"></div>
                        <div class="col-md-10"><label for="print-terms">Terms &amp; conditions (one per line)</label><textarea id="print-terms" name="terms" maxlength="2000" class="u-h-72px">{{ $print['terms'] ?? '' }}</textarea></div>
                        <div class="col-md-2 d-flex align-items-end"><button type="submit" class="btn btn-primary btn-sm w-100">Save print details</button></div>
                    </div>
                    </fieldset>
                </form>
            </div>
            <div class="gst-card">
                <h2>Exports and TDS</h2>
                <p class="gst-muted">The Letter of Undertaking (LUT, form RFD-11) lets you export or supply to SEZ without paying IGST. Turn on 194Q if your turnover last year was over ₹10 crore: you then deduct 0.1% TDS on purchases above ₹50 lakh a year from one supplier.</p>
                @php $trade = \App\Services\Tax\GstSetupService::tradeSettings($company->id); @endphp
                <form method="POST" action="{{ url('/settings/gst/trade') }}" class="gst-form">@csrf<input type="hidden" name="tab" value="registration">
                    <fieldset @disabled(!$canManage)>
                    <div class="row">
                        <div class="col-md-3"><label for="trade-lut">LUT ARN / number</label><input id="trade-lut" name="lut_number" maxlength="50" value="{{ $trade['lut_number'] }}" placeholder="AD330326000123X"></div>
                        <div class="col-md-2"><label for="trade-lut-from">Valid from</label><input id="trade-lut-from" name="lut_valid_from" type="date" value="{{ $trade['lut_valid_from'] }}"></div>
                        <div class="col-md-2"><label for="trade-lut-to">Valid to</label><input id="trade-lut-to" name="lut_valid_to" type="date" value="{{ $trade['lut_valid_to'] }}"></div>
                        <div class="col-md-3 d-flex align-items-end"><label class="mb-2"><input type="hidden" name="tds_194q" value="0"><input type="checkbox" name="tds_194q" value="1" @checked($trade['tds_194q'])> Deduct TDS under 194Q</label></div>
                        <div class="col-md-2 d-flex align-items-end"><button type="submit" class="btn btn-primary btn-sm w-100">Save</button></div>
                    </div>
                    </fieldset>
                </form>
            </div>
        </div>

        {{-- Rates --}}
        <div class="tab-pane fade show active" id="rates" role="tabpanel">
            <div class="gst-card">
                <h2>GST rates</h2>
                <div class="gst-scroll">
                <table class="gst-table">
                    <thead><tr><th>Name</th><th>Type</th><th>GST %</th><th>Cess %</th><th>Since</th><th>Input credit</th><th>Products</th>@if($canManage)<th>Change rate from a date</th><th class="u-w-130px">Actions</th>@endif</tr></thead>
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
                                <form method="POST" action="{{ url('/settings/gst/rate') }}" class="d-flex u-gap-4px">@csrf<input type="hidden" name="tab" value="rates"><input type="hidden" name="id" value="{{ $category->id }}">
                                    <input name="rate" type="number" step="0.01" min="0" max="100" placeholder="GST %" required class="u-w-72px">
                                    <input name="cess_rate" type="number" step="0.01" min="0" max="100" value="0" title="Cess %" class="u-w-60px">
                                    <input name="effective_from" type="date" required class="u-w-140px">
                                    <button class="btn btn-outline-primary btn-sm" type="submit">Save</button>
                                </form>
                                @else<span class="gst-muted">No tax charged</span>@endif
                            </td>
                            <td class="text-nowrap">
                                <button type="button" class="btn btn-outline-secondary btn-sm js-edit-rate" data-id="{{ $category->id }}" data-name="{{ $category->name }}"
                                    data-credit="{{ $category->input_credit_allowed ? 1 : 0 }}" data-taxable="{{ $category->supply_type === 'taxable' ? 1 : 0 }}">Edit</button>
                                <form method="POST" action="{{ url('/settings/gst/category-delete') }}" class="d-inline" data-confirm="Delete GST rate {{ $category->name }}?" onsubmit="return confirm(this.dataset.confirm);">@csrf
                                    <input type="hidden" name="tab" value="rates"><input type="hidden" name="id" value="{{ $category->id }}">
                                    <button class="btn btn-outline-danger btn-sm" type="submit" @disabled($category->products) title="{{ $category->products ? 'Set on '.$category->products.' products. Move them to another rate first.' : 'Delete' }}">Delete</button>
                                </form>
                            </td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="9" class="text-center gst-muted py-4">No GST rates yet. Click "Install standard GST setup" for 0%, 5%, 12%, 18%, 28%, 40%, exempt and nil-rated.</td></tr>
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
                <form method="POST" action="{{ url('/settings/gst/garment-band') }}" class="mt-2">@csrf<input type="hidden" name="tab" value="rates">
                    <button type="submit" class="btn btn-outline-primary btn-sm">Add garment rate by sale value</button>
                    <span class="gst-muted">One rate for apparel and made-ups: up to ₹2,500 a piece 5%, above 18% (GST 2.0, from 22-09-2025). The invoice picks the band from each line's price.</span>
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
                <div class="d-flex align-items-center justify-content-between mb-2 u-gap-8px u-wrap-wrap">
                    <h2 class="mb-0">Product GST rate &amp; HSN/SAC</h2>
                    <form method="GET" action="{{ url('/settings/gst') }}" class="d-flex gst-form u-gap-6px">
                        <select name="products" onchange="this.form.submit()" class="u-w-170px"><option value="all" @selected($productFilter === 'all')>All products</option><option value="missing" @selected($productFilter === 'missing')>Needs GST details</option></select>
                        <input name="q" value="{{ request('q') }}" placeholder="Search name or code" class="u-w-200px">
                        <input type="hidden" name="tab" value="products">
                        <button class="btn btn-outline-secondary btn-sm">Filter</button>
                        @if($products->isNotEmpty())<button type="submit" form="gst-products-form" class="btn btn-primary btn-sm text-nowrap">Save products</button>@endif
                    </form>
                </div>
                <form method="POST" action="{{ url('/settings/gst/products') }}" id="gst-products-form">@csrf<input type="hidden" name="tab" value="products">
                    <div class="gst-scroll">
                    <table class="gst-table">
                        <thead><tr><th>Product</th><th>Type</th><th class="u-w-240px">GST rate</th><th class="u-w-150px">HSN / SAC</th></tr></thead>
                        <tbody>
                        @forelse($products as $product)
                            <tr>
                                <td><strong>{{ $product->name }}</strong> <span class="gst-muted">{{ $product->code }}</span>
                                    @if(!$product->tax_category_id || !$product->hsn_code)<span class="gst-pill warn">Needs GST details</span>@endif</td>
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
                </form>
            </div>
        </div>

        {{-- Codes --}}
        <div class="tab-pane fade" id="codes" role="tabpanel">
            <div class="gst-card">
                <h2>HSN / SAC codes</h2>
                <p class="gst-muted">Codes typed on a product are added here automatically. Add descriptions for your invoices.</p>
                @if($canManage)
                <form method="POST" action="{{ url('/settings/gst/code') }}" class="gst-form mb-2" id="code-form">@csrf<input type="hidden" name="tab" value="codes">
                    <div class="row">
                        <div class="col-md-2"><label>Code *</label><input name="code" id="code-code" maxlength="8" required inputmode="numeric"></div>
                        <div class="col-md-2"><label>Kind *</label><select name="kind" id="code-kind"><option value="hsn">Goods (HSN)</option><option value="sac">Service (SAC)</option></select></div>
                        <div class="col-md-5"><label>Description *</label><input name="description" id="code-description" maxlength="191" required></div>
                        <div class="col-md-3 d-flex align-items-end u-gap-6px"><button class="btn btn-primary btn-sm w-100" type="submit" id="code-save">Save code</button>
                            <button class="btn btn-outline-secondary btn-sm" type="button" id="code-cancel" style="display:none">Cancel</button></div>
                    </div>
                </form>
                @endif
                <div class="gst-scroll">
                <table class="gst-table"><thead><tr><th>Code</th><th>Kind</th><th>Description</th><th>Products</th>@if($canManage)<th class="u-w-130px">Actions</th>@endif</tr></thead><tbody>
                    @forelse($codes as $code)
                        @php $used = (int) ($codeUsage[$code->code] ?? 0); @endphp
                        <tr><td>{{ $code->code }}</td><td>{{ strtoupper($code->kind) }}</td><td>{{ $code->description }}</td><td>{{ $used }}</td>
                            @if($canManage)
                            <td class="text-nowrap">
                                <button type="button" class="btn btn-outline-secondary btn-sm js-edit-code" data-code="{{ $code->code }}" data-kind="{{ $code->kind }}" data-description="{{ $code->description }}">Edit</button>
                                <form method="POST" action="{{ url('/settings/gst/code-delete') }}" class="d-inline" data-confirm="Delete code {{ $code->code }}?" onsubmit="return confirm(this.dataset.confirm);">@csrf
                                    <input type="hidden" name="tab" value="codes"><input type="hidden" name="code" value="{{ $code->code }}">
                                    <button class="btn btn-outline-danger btn-sm" type="submit" @disabled($used) title="{{ $used ? 'Set on '.$used.' products. Change them first.' : 'Delete' }}">Delete</button>
                                </form>
                            </td>
                            @endif
                        </tr>
                    @empty<tr><td colspan="5" class="text-center gst-muted py-4">No codes yet.</td></tr>@endforelse
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
                <table class="gst-table"><thead><tr><th>Party</th><th>GSTIN on master</th><th>GST profile</th>@if($canManage)<th class="u-w-330px">Override</th>@endif</tr></thead><tbody>
                    @forelse($parties as $party)
                        <tr>
                            <td><strong>{{ $party->name }}</strong> <span class="gst-muted">{{ ucfirst($party->type) }}</span></td>
                            <td>{{ $party->gstin ?: '—' }}</td>
                            <td>@if($party->profile)<span class="gst-pill {{ $party->profile->registration_type === 'unregistered' ? 'muted' : '' }}">{{ ucfirst($party->profile->registration_type) }}</span> {{ $states[$party->profile->state_code] ?? $party->profile->state_code }}@else<span class="gst-pill warn">Missing — add GSTIN or state</span>@endif</td>
                            @if($canManage)
                            <td><form method="POST" action="{{ url('/settings/gst/party') }}" class="d-flex u-gap-4px">@csrf<input type="hidden" name="tab" value="parties">
                                <input type="hidden" name="party_type" value="{{ $party->type }}"><input type="hidden" name="party_id" value="{{ $party->id }}">
                                <select name="registration_type" class="u-w-120px">@foreach(['regular' => 'Regular', 'composition' => 'Composition', 'unregistered' => 'Unregistered'] as $value => $label)<option value="{{ $value }}" @selected(($party->profile->registration_type ?? '') === $value)>{{ $label }}</option>@endforeach</select>
                                <input type="hidden" name="gstin" value="{{ $party->profile->gstin ?? '' }}">
                                <select name="state_code" class="u-w-130px">@foreach($states as $code => $name)<option value="{{ $code }}" @selected(($party->profile->state_code ?? '') === (string) $code)>{{ $name }}</option>@endforeach</select>
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
                <h2>GST and round-off ledgers</h2>
                <p class="gst-muted">Input GST is claimed on purchases; output GST is collected on sales. "Install standard GST setup" creates and maps any missing ledger.</p>
                <table class="gst-table"><thead><tr><th>Posting role</th><th>Ledger</th></tr></thead><tbody>
                    @foreach($ledgers as $ledger)<tr><td>{{ $ledger->name }}</td><td>@if($ledger->account){{ $ledger->account->code }} · {{ $ledger->account->name }}@else<span class="gst-pill warn">Not mapped</span>@endif</td></tr>@endforeach
                </tbody></table>
            </div>
        </div>
    </div>
</div>
@if($canManage)
<div class="modal fade" id="rate-edit-modal" tabindex="-1" role="dialog" aria-labelledby="rate-edit-title">
    <div class="modal-dialog modal-sm" role="document">
        <form method="POST" action="{{ url('/settings/gst/rename') }}" class="modal-content gst-form">@csrf<input type="hidden" name="tab" value="rates"><input type="hidden" name="id" id="rate-edit-id">
            <div class="modal-header py-2"><h5 class="modal-title" id="rate-edit-title">Edit GST rate</h5><button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button></div>
            <div class="modal-body">
                <label for="rate-edit-name">Name *</label><input id="rate-edit-name" name="name" maxlength="191" required>
                <div id="rate-edit-credit-wrap"><label class="d-flex align-items-center mt-2 mb-0 u-gap-6px"><input type="checkbox" name="input_credit_allowed" value="1" id="rate-edit-credit" class="u-w-auto u-h-auto"> Input credit allowed</label></div>
                <p class="gst-muted mt-2 mb-0">To change the percentage, use "Change rate from a date" so older bills keep their rate.</p>
            </div>
            <div class="modal-footer py-2"><button type="button" class="btn btn-outline-secondary btn-sm" data-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary btn-sm">Save</button></div>
        </form>
    </div>
</div>
@endif
</section>
@endsection

@push('scripts')
<script>
(function () {
    'use strict';
    var hash = (location.hash || '').replace('#', '') || new URLSearchParams(location.search).get('tab');
    if (hash && $('.gst-tabs a[href="#' + hash + '"]').length) $('.gst-tabs a[href="#' + hash + '"]').tab('show');
    $('.gst-tabs a').on('shown.bs.tab', function () { history.replaceState(null, '', '#' + this.getAttribute('href').slice(1)); });

    $('.js-edit-rate').on('click', function () {
        var d = this.dataset;
        $('#rate-edit-id').val(d.id);
        $('#rate-edit-name').val(d.name);
        $('#rate-edit-credit').prop('checked', d.credit === '1');
        $('#rate-edit-credit-wrap').toggle(d.taxable === '1');
        $('#rate-edit-modal').modal('show').one('shown.bs.modal', function () { $('#rate-edit-name').trigger('focus').select(); });
    });

    // Editing a code keeps the code fixed: products point at it by its number.
    function resetCodeForm() {
        $('#code-form')[0].reset();
        $('#code-code').prop('readonly', false);
        $('#code-save').text('Save code');
        $('#code-cancel').hide();
    }
    $('.js-edit-code').on('click', function () {
        var d = this.dataset;
        $('#code-code').val(d.code).prop('readonly', true);
        $('#code-kind').val(d.kind).trigger('change');
        if ($.fn.selectpicker) $('#code-kind').selectpicker('refresh');
        $('#code-description').val(d.description).trigger('focus').select();
        $('#code-save').text('Update code');
        $('#code-cancel').show();
    });
    $('#code-cancel').on('click', function () {
        resetCodeForm();
        if ($.fn.selectpicker) $('#code-kind').selectpicker('refresh');
    });

    // Unregistered has no GSTIN; its state is chosen. Other types take the state from the GSTIN.
    $('#reg-type').on('change', function () {
        var unregistered = this.value === 'unregistered';
        $('#reg-gstin').prop({ required: !unregistered, disabled: unregistered });
        if (unregistered) $('#reg-gstin').val('');
        $('#reg-gstin-star').toggle(!unregistered);
        $('#reg-lookup').prop('disabled', unregistered);
        $('#reg-state').prop({ disabled: !unregistered, required: unregistered });
        if ($.fn.selectpicker) $('#reg-state').selectpicker('refresh');
    }).trigger('change');

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
                if (res && res.provider_message) {
                    status.text('Live provider note: ' + res.provider_message);
                } else {
                    status.text(p.name ? 'Details filled. Check them before saving.' : 'GSTIN format OK (' + (res.state_name || '') + '). Enter name and address.');
                }
            })
            .fail(function (xhr) { status.text((xhr.responseJSON && xhr.responseJSON.message) || 'Lookup unavailable. Enter the details manually.'); });
    });
})();
</script>
@endpush

@push('css')
    <link rel="stylesheet" href="{{ versioned_asset('css/pages/gst-settings.css') }}">
@endpush
