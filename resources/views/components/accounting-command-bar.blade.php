@props(['active' => 'vouchers', 'title' => 'Accounting', 'purpose' => ''])
@php
    $access = new \App\Services\Platform\NavigationAccess($role_has_permissions_list ?? null);
    $switches = app(\App\Services\Platform\MenuSwitches::class);
    $groups = [
        'Transactions' => [
            ['voucher-entry', 'Voucher Entry', 'accounting.voucher.entry', 'accounting.voucher.post'],
            ['vouchers', 'Voucher Hub', 'accounting.vouchers', 'accounting.reports.view'],
            ['transfers', 'Money Transfer', 'money-transfers.index', 'money-transfer'],
            ['journals', 'Journal Entries', 'accounting.journal-entries', 'accounting.reports.view'],
            ['day-book', 'Day Book', 'accounting.day-book', 'accounting.reports.view'],
            ['cash-book', 'Cash Book', 'accounting.cash-book', 'accounting.reports.view'],
            ['ageing', 'Receivables / Payables', 'accounting.ageing', 'accounting.reports.view'],
            ['collections', 'Collections', 'accounting.collections', 'accounting.reports.view'],
        ],
        'Reports' => [
            ['ledger', 'General Ledger', 'accounting.general-ledger', 'accounting.reports.view'],
            ['monthly-ledger', 'Monthly Ledger · choose account', 'accounting.general-ledger', 'accounting.reports.view'],
            ['trial-balance', 'Trial Balance', 'accounting.trial-balance', 'accounting.reports.view'],
            ['profit-loss', 'Profit & Loss', 'accounting.profit-loss', 'accounting.reports.view'],
            ['balance-sheet', 'Balance Sheet', 'accounting.balance-sheet', 'accounting.reports.view'],
            ['cash-flow', 'Cash Flow Statement', 'accounting.cash-flow', 'accounting.reports.view'],
            ['inventory-close', 'Inventory Valuation Preview', 'accounting.inventory-close', 'accounting.reports.view'],
        ],
        'Accounts & Setup' => [
            ['coa', 'Chart of Accounts', 'accounting.coa', 'accounting.reports.view'],
            ['accounts', 'Cash & Bank Accounts', 'accounts.index', 'account-index'],
            ['mappings', 'Automatic Posting Accounts', 'accounting.semantic-mappings', 'accounting.reports.view'],
        ],
    ];
    foreach ($groups as $label => $links) {
        $groups[$label] = array_values(array_filter($links, fn ($link) => \Illuminate\Support\Facades\Route::has($link[2]) && $access->allows($link[3]) && $switches->routeAllowed($link[2])));
    }
@endphp
@once
    @push('css')
        <link rel="stylesheet" href="{{ versioned_asset('css/product-workspace.css') }}">
        <link rel="stylesheet" href="{{ versioned_asset('css/pages/accounting-workspace.css') }}">
    @endpush
    @push('scripts')
        <script src="{{ versioned_asset('js/accounting-workspace.js') }}" defer></script>
    @endpush
    <script>document.documentElement.classList.add('accounting-screen'); document.body.classList.add('accounting-screen');</script>
@endonce
<div class="comm-command-bar accounting-command-bar" data-accounting-active="{{ $active }}">
    <div class="comm-title-group"><h1>{{ $title }}</h1></div>
    <nav class="accounting-navigation" aria-label="Accounting screens">
        @foreach($groups as $label => $links)
            @if($links)
                <details class="accounting-nav-group" @if(in_array($active, array_column($links, 0), true)) data-current-group @endif>
                    <summary>{{ $label }}</summary>
                    <div class="accounting-nav-menu">
                        @foreach($links as [$key, $label, $route, $permission])
                            <a href="{{ route($route) }}" @if($active === $key) aria-current="page" @endif>{{ $label }}</a>
                        @endforeach
                    </div>
                </details>
            @endif
        @endforeach
    </nav>
    @if(trim($slot))<div class="comm-actions">{{ $slot }}</div>@endif
</div>
@if($purpose)<p class="accounting-purpose">{{ $purpose }}</p>@endif
