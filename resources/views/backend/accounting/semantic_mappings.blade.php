@extends('backend.layout.main')
@section('content')


@if(session()->has('message'))
    <div class="alert alert-success alert-dismissible text-center">
        <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        {{ session()->get('message') }}
    </div>
@endif

@php
    $context = app(\App\Services\Platform\CompanyContextResolver::class)->forActor();
    $canManageMappings = app(\App\Services\Platform\CompanyContextResolver::class)->canManageFinancialYears(auth()->id(), $context->companyId);
    $resolver = app(\App\Services\Accounting\SemanticAccountResolver::class);
    $parentIds = \App\Models\Accounting\ChartOfAccount::whereIn('parent_id', $accounts->pluck('id'))->pluck('parent_id')->flip();
    $leafAccounts = $accounts->reject(fn ($account) => $parentIds->has($account->id));
    $roleHelp = [
        'ar' => ['Customer receivables', 'Unpaid customer invoices and their settlement.'],
        'ap' => ['Supplier payables', 'Unpaid supplier bills and their settlement.'],
        'bank' => ['Default bank', 'Default for workflows requesting a bank. A selected IOB or SBI payment account takes precedence.'],
        'cash' => ['Default cash', 'Default cash ledger. Select the actual cash account when recording a payment.'],
        'sales' => ['Sales income', 'Income earned when a sales invoice is posted, including credit sales.'],
        'inventory' => ['Stock value', 'Value of inventory held by the business.'],
        'cogs' => ['Cost of goods sold', 'Cost of inventory charged as an expense when goods are sold.'],
        'production_costs' => ['Production costs', 'Costs charged by production workflows.'],
        'expense' => ['General expenses', 'Default operating expense account for automatic expense postings.'],
        'damage' => ['Damaged stock', 'Expense for inventory damage and write-offs.'],
        'variance' => ['Stock differences', 'Value differences recorded by inventory adjustments.'],
        'discounts' => ['Sales discounts', 'Discounts granted on sales.'],
        'freight' => ['Delivery income', 'Shipping and delivery charges collected from customers.'],
        'sales_returns' => ['Sales returns', 'Reversal of income for returned sales.'],
        'purchase_returns' => ['Purchase returns', 'Account used by purchase return postings.'],
        'rounding' => ['Rounding differences', 'Small differences caused by rounding document totals.'],
        'goods_in_transit' => ['Goods in transit', 'Billed goods that have not yet been received.'],
        'goods_received_not_invoiced' => ['Received, not billed', 'Goods received before the supplier bill is posted.'],
        'goods_delivered_not_invoiced' => ['Delivered, not billed', 'Goods delivered before the sales invoice is posted.'],
        'tax_output' => ['Output tax', 'Default tax liability arising on sales.'],
        'tax_input' => ['Input tax', 'Default recoverable tax arising on purchases.'],
        'retained_earnings' => ['Retained earnings', 'Accumulated profit retained in the business.'],
        'opening_balance' => ['Opening balance offset', 'Balancing account for authorized opening balance migration.'],
    ];
    $mappingRows = $mappings->map(function ($map) use ($resolver, $leafAccounts, $roleHelp) {
        $role = $resolver->role($map->semantic_role);
        $types = isset($resolver::ROLES[$role]) ? (array) $resolver::ROLES[$role][1] : [];
        $choices = $leafAccounts->filter(fn ($account) => (!$types || in_array($account->type, $types, true))
            && (!in_array($role, ['ar', 'ap', 'cash', 'bank'], true) || in_array($account->control_type, ['none', $role], true)));
        $taxLabel = str_replace(['output_tax_', 'input_tax_'], ['Output ', 'Input '], $role);
        [$label, $help] = $roleHelp[$role] ?? [ucwords(str_replace('_', ' ', $taxLabel)), str_starts_with($role, 'output_tax_') ? 'Tax collected on sales and payable to the government.' : (str_starts_with($role, 'input_tax_') ? 'Recoverable tax recorded on purchases.' : $map->description)];
        return ['map' => $map, 'choices' => $choices, 'valid' => $choices->contains('id', $map->account_id), 'label' => $label, 'help' => $help];
    });
    $totalRoles = $mappingRows->count();
    $mappedRoles = $mappingRows->where('valid', true)->count();
    $unmappedRoles = $totalRoles - $mappedRoles;
@endphp

<section class="semantic-workspace-view">
    <x-accounting-command-bar active="mappings" title="Account Mappings" purpose="Choose default ledgers for automatic posting. Configure business roles once; you do not map every account.">
        @if($canManageMappings && $totalRoles)
        <button type="submit" form="mapping-form" class="btn btn-primary"><i class="dripicons-checkmark"></i> Save mappings</button>
        @endif
    </x-accounting-command-bar>
    @if($errors->any())<div class="alert alert-danger" role="alert"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <div class="mapping-explanation">
        <strong>Two banks? Select the actual bank on each receipt.</strong>
        <p>Receive ₹6,000 into IOB and ₹4,000 into SBI by choosing those accounts on the two receipts. Sales income is recorded once. The default bank mapping does not combine or redirect these receipts.</p>
        <details><summary>When should I change a mapping?</summary><p>Change a role when automatic postings should use a different ledger. Only active accounts without sub-accounts and with the correct category are offered. Changing a mapping does not move earlier transactions.</p></details>
    </div>
    <!-- KPI Summary Strip -->
    <div class="kpi-summary-strip">
        <div class="kpi-card">
            <div>
                <div class="kpi-label">POSTING ROLES</div>
                <div class="kpi-number">{{ $totalRoles }}</div>
            </div>
            <div class="z-icon-tile z-tone-info">
                <i class="dripicons-gear u-fs-18px"></i>
            </div>
        </div>
        <div class="kpi-card">
            <div>
                <div class="kpi-label">READY TO USE</div>
                <div class="kpi-number u-c-success">{{ $mappedRoles }}</div>
            </div>
            <div class="z-icon-tile z-tone-success">
                <i class="dripicons-checkmark u-fs-18px"></i>
            </div>
        </div>
        <div class="kpi-card">
            <div>
                <div class="kpi-label">NEEDS REVIEW</div>
                <div class="kpi-number {{ $unmappedRoles > 0 ? 'u-c-danger' : 'u-c-success' }}">{{ $unmappedRoles }}</div>
            </div>
            <div class="z-icon-tile {{ $unmappedRoles > 0 ? 'z-tone-danger' : 'z-tone-success' }}">
                <i class="{{ $unmappedRoles > 0 ? 'dripicons-warning' : 'dripicons-checkmark' }} u-fs-18px"></i>
            </div>
        </div>
    </div>

    @unless($canManageMappings)<p class="alert alert-info">You can review these mappings. Ask a company administrator to change them.</p>@endunless
    <div class="desk-card-wrapper">
        <div class="mapping-toolbar">
            <div><label for="mapping-search">Find a posting role</label><input id="mapping-search" type="search" class="form-control form-control-sm" placeholder="Search role, purpose or account"></div>
            <div><label for="mapping-status">Show roles</label><select id="mapping-status" class="form-control form-control-sm"><option value="all">All roles</option><option value="review">Needs review</option><option value="ready">Ready to use</option></select></div>
            <span id="mapping-results" role="status" aria-live="polite">{{ $totalRoles }} roles</span>
        </div>
        <form action="{{ route('accounting.semantic-mappings.update') }}" method="POST" id="mapping-form">
            @csrf
            <div class="table-responsive">
                <table class="table semantic-table align-middle mb-3" id="mapping-table">
                    <caption class="sr-only">Automatic posting roles and their assigned ledger accounts</caption>
                    <thead><tr><th scope="col">Posting purpose</th><th scope="col">When it is used</th><th scope="col">Assigned ledger account</th><th scope="col">Status</th></tr></thead>
                    <tbody>
                        @forelse($mappingRows as $row)
                        @php $map = $row['map']; @endphp
                        <tr data-mapping-row data-status="{{ $row['valid'] ? 'ready' : 'review' }}">
                            <th scope="row"><strong>{{ $row['label'] }}</strong><small class="d-block text-muted font-monospace">{{ strtoupper(str_replace('_', ' ', $map->semantic_role)) }}</small></th>
                            <td class="mapping-purpose">{{ $row['help'] }}</td>
                            <td>
                                <label class="sr-only" for="mapping-{{ $map->id }}">Ledger for {{ $row['label'] }}</label>
                                <select id="mapping-{{ $map->id }}" name="mappings[{{ $map->semantic_role }}]" class="form-control form-control-sm" required @disabled(!$canManageMappings)>
                                    <option value="" disabled @selected(!$row['valid'] && !old('mappings.'.$map->semantic_role))>{{ $row['valid'] ? 'Choose a ledger' : 'Choose a compatible ledger' }}</option>
                                    @foreach($row['choices'] as $account)<option value="{{ $account->id }}" @selected((string) old('mappings.'.$map->semantic_role, $map->account_id) === (string) $account->id)>{{ $account->code }} — {{ $account->name }} ({{ ucfirst($account->type) }})</option>@endforeach
                                </select>
                                @if(!$row['choices']->count())<small class="text-danger">No compatible ledger. Create an account in Chart of Accounts first.</small>@endif
                            </td>
                            <td><span class="badge {{ $row['valid'] ? 'badge-success' : 'badge-warning' }}">{{ $row['valid'] ? 'Ready' : 'Needs review' }}</span></td>
                        </tr>
                        @empty
                        <tr><td colspan="4" class="text-center text-muted py-4">No posting roles are configured. Ask a company administrator to complete accounting setup.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                <p id="mapping-no-results" class="text-center text-muted p-3" hidden>No roles match these filters. Clear the search or show all roles.</p>
            </div>
            <div class="mapping-footer"><span id="mapping-changes" role="status" aria-live="polite">Changes apply only after saving.</span>@if($canManageMappings && $totalRoles)<button type="submit" class="btn btn-primary">Save mappings</button>@endif</div>
        </form>
    </div>
</section>

@endsection

@push('scripts')<script src="{{ versioned_asset('js/accounting-mappings.js') }}"></script>@endpush

@push('css')
    <link rel="stylesheet" href="<?php echo asset('css/commercial-entry.css') . '?v=' . (file_exists(public_path('css/commercial-entry.css')) ? filemtime(public_path('css/commercial-entry.css')) : time()) ?>" type="text/css">
    <link rel="stylesheet" href="<?php echo asset('css/commercial-workspace.css') . '?v=' . (file_exists(public_path('css/commercial-workspace.css')) ? filemtime(public_path('css/commercial-workspace.css')) : time()) ?>" type="text/css">
    <link rel="stylesheet" href="<?php echo asset('css/zolo-erp-neo.css') . '?v=' . (file_exists(public_path('css/zolo-erp-neo.css')) ? filemtime(public_path('css/zolo-erp-neo.css')) : time()) ?>" type="text/css">
    <link rel="stylesheet" href="{{ versioned_asset('css/pages/accounting-semantic-mappings.css') }}">
@endpush
