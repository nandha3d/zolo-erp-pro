@extends('backend.layout.main')
@section('content')
@php
    $sales = $kind === 'sale';
    $prefix = $sales ? 'selling' : 'buying';
    $selectedId = old('bill_id', $selected?->id);
    $options = collect($bills->items())->keyBy('id');
    if ($selected) $options->put($selected->id, $selected);
@endphp
<section class="product-workspace-view bill-payment-workspace">
    <x-section-command-bar :title="$sales ? 'Sales Payments & Receipts' : 'Supplier Payments & Vouchers'">
        <a class="btn btn-primary btn-sm" href="{{ route('dues.index', ['tab' => $sales ? 'sales' : 'purchases']) }}"><i class="fa fa-bolt mr-1"></i> Due Management Command Center</a>
        <a class="btn btn-outline-primary btn-sm" href="{{ $sales ? route('sales.index') : route('purchases.index') }}">Bills</a>
        <a class="btn btn-outline-primary btn-sm" href="{{ route('accounting.vouchers', ['voucher_type' => $sales ? 'receipt' : 'payment']) }}">Allocate across bills</a>
    </x-section-command-bar>
    @if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
    <div class="payment-workspace-grid">
        <div class="payment-registers">
            <div class="card payment-register"><div class="card-body">
                <div class="payment-section-heading"><div><h2>Outstanding bills</h2><p>Select a bill to {{ $sales ? 'receive' : 'record' }} its payment.</p></div><span class="badge badge-light">{{ $bills->total() }} bills</span></div>
                <form method="get" class="payment-search"><label for="payment-search" class="sr-only">Search bill or party</label><input id="payment-search" name="search" class="form-control" placeholder="Search bill number or party" value="{{ request('search') }}"><button class="btn btn-outline-primary" type="submit">Search</button></form>
                <div class="table-responsive"><table class="table table-sm payment-due-table"><thead><tr><th>Bill / party</th><th class="text-right">Bill total</th><th class="text-right">Due</th><th class="text-right">Payment</th></tr></thead><tbody>
                    @forelse($bills as $bill)<tr data-payment-bill-row="{{ $bill->id }}" @class(['payment-bill-selected' => (string)$selectedId === (string)$bill->id])>
                        <td><strong>{{ $bill->reference_no }}</strong><span class="payment-cell-detail">{{ $bill->$party?->name }}</span><small>{{ $bill->created_at->format('d M Y') }}</small></td>
                        <td class="text-right">{{ number_format($bill->grand_total, 2) }}<small class="payment-cell-detail">Settled {{ number_format($bill->paid_amount, 2) }}</small></td>
                        <td class="text-right payment-due-value">{{ number_format($bill->grand_total - $bill->paid_amount, 2) }}</td>
                        <td class="text-right"><a class="btn btn-primary bill-payment-action" data-pay-bill="{{ $bill->id }}" href="{{ route($prefix.'.payments', ['bill' => $bill->id]) }}#payment-entry"><i class="dripicons-wallet" aria-hidden="true"></i> Add payment</a></td>
                    </tr>@empty<tr><td colspan="4">No outstanding bills match.</td></tr>@endforelse
                </tbody></table></div>{{ $bills->links() }}
            </div></div>
            <div class="card payment-register"><div class="card-body"><h2>Recent {{ $sales ? 'receipts' : 'payment vouchers' }}</h2>
                <div class="table-responsive"><table class="table table-sm"><thead><tr><th>Document / bill</th><th>Account / method</th><th class="text-right">Amount</th><th class="text-right">Document</th></tr></thead><tbody>
                    @forelse($payments as $payment)<tr><td>{{ $payment->payment_reference }}<span class="payment-cell-detail">{{ $payment->$kind?->reference_no }}</span><small>{{ ($payment->payment_at ?? $payment->created_at)->format('d M Y') }}</small>@if($payment->$kind?->reversed_at)<span class="badge badge-warning">Reversed bill</span>@endif</td><td>{{ $payment->account?->name }}<small class="payment-cell-detail">{{ $payment->paying_method }}</small></td><td class="text-right">{{ number_format($payment->amount, 2) }}</td><td class="text-right"><a class="btn btn-secondary bill-payment-action" href="{{ route('bill-payments.show', $payment->id) }}">View / Print</a></td></tr>
                    @empty<tr><td colspan="4">No payments recorded.</td></tr>@endforelse
                </tbody></table></div>
            </div></div>
        </div>
        <aside class="card payment-entry-card" id="payment-entry" aria-labelledby="payment-entry-title"><div class="card-body">
            <div class="payment-section-heading"><div><h2 id="payment-entry-title">{{ $sales ? 'Receive customer payment' : 'Pay supplier' }}</h2><p>{{ $sales ? 'Creates a receipt and updates the customer balance.' : 'Creates a payment voucher and updates the supplier balance.' }}</p></div></div>
            <form method="post" action="{{ route($prefix.'.payments.store') }}" id="bill-payment-form" data-direction="{{ $sales ? 'receive' : 'pay' }}">@csrf
                <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', (string) Str::uuid()) }}">
                <div class="form-group"><label for="payment-bill">Bill *</label><select id="payment-bill" name="bill_id" class="form-control" required>
                    <option value="">Choose an outstanding bill</option>
                    @foreach($options as $bill)<option value="{{ $bill->id }}" data-due="{{ max(0, $bill->grand_total - $bill->paid_amount) }}" data-party="{{ $bill->$party?->name }}" data-reference="{{ $bill->reference_no }}" @selected((string)$selectedId === (string)$bill->id)>{{ $bill->reference_no }} · {{ $bill->$party?->name }}</option>@endforeach
                </select><small id="payment-bill-due" class="payment-field-hint"></small></div>
                <div class="payment-form-pair">
                    <div class="form-group"><label for="payment-date">Payment date *</label><input type="date" id="payment-date" name="payment_at" class="form-control" value="{{ old('payment_at', now()->toDateString()) }}" required></div>
                    <div class="form-group"><label for="payment-amount">Amount *</label><input type="number" step="0.0001" min="0.0001" id="payment-amount" name="amount" class="form-control" value="{{ old('amount') }}" required></div>
                </div>
                <div class="form-group"><label for="payment-method">Payment method *</label><select id="payment-method" name="paying_method" class="form-control" required>@foreach(['Cash' => 'Cash', 'Bank' => 'Bank transfer / UPI', 'Cheque' => 'Cheque', 'Credit Card' => 'Card payment'] as $value => $label)<option value="{{ $value }}" @selected(old('paying_method', 'Cash') === $value)>{{ $label }}</option>@endforeach</select><small class="payment-field-hint">{{ $sales ? 'How the customer pays you.' : 'How you pay the supplier.' }}</small></div>
                <div class="form-group"><div class="payment-field-heading"><label for="payment-account" id="payment-account-label">{{ $sales ? 'Receive into' : 'Pay from' }} *</label>@if($canManageAccounts)<button type="button" class="payment-add-account" id="payment-add-account">+ Add account</button>@endif</div>
                    <select id="payment-account" name="account_id" class="form-control" aria-describedby="payment-account-help" required><option value="">Choose cash or bank account</option>@foreach($accounts as $account)<option value="{{ $account['id'] }}" data-kind="{{ $account['kind'] }}" data-name="{{ $account['name'] }}" data-ledger="{{ $account['ledger_name'] }}" @selected((string)old('account_id') === (string)$account['id'])>{{ $account['name'] }}{{ $account['last_four'] ? ' · •••• '.$account['last_four'] : '' }}</option>@endforeach</select>
                    <small id="payment-account-help" class="payment-field-hint">Choose the actual account holding this money.</small>
                    <div id="payment-account-empty" class="payment-account-notice" role="status" hidden>No matching account is configured. {{ $canManageAccounts ? 'Use + Add account to create a linked cash or bank account.' : 'Ask your administrator to add a cash or bank account.' }}</div>
                    <div id="payment-generic-bank" class="payment-account-notice" hidden>Only a generic Bank account is configured. Add named accounts, such as HDFC Current or SBI Current, to track each bank separately.</div>
                </div>
                <div class="form-group"><label for="payment-note">{{ $sales ? 'Payment reference / note' : 'Bank reference / note' }}</label><input id="payment-note" name="payment_note" class="form-control" maxlength="2000" placeholder="UTR, cheque number or other reference" value="{{ old('payment_note') }}"></div>
                <details class="payment-deductions" @if(old('discount', 0) || old('tds_amount', 0)) open @endif><summary>Discount{{ $sales ? ' / TDS' : '' }} adjustments</summary><div class="payment-form-pair mt-3">
                    <div class="form-group"><label for="payment-discount">Cash discount</label><input id="payment-discount" type="number" name="discount" class="form-control" min="0" step="0.0001" value="{{ old('discount', 0) }}"></div>
                    @if($sales)<div class="form-group"><label for="payment-tds">Customer TDS</label><input id="payment-tds" type="number" name="tds_amount" class="form-control" min="0" step="0.0001" value="{{ old('tds_amount', 0) }}"></div>@endif
                </div><div class="form-group"><label for="payment-discount-reason">Discount reason</label><input id="payment-discount-reason" name="discount_reason" class="form-control" maxlength="255" value="{{ old('discount_reason') }}"></div></details>
                <div class="payment-posting-preview" aria-live="polite"><strong>After saving</strong><p id="payment-flow-preview">Choose a bill, method and account to preview the linked entry.</p><div><span>Bill due remaining</span><strong id="payment-due-preview">—</strong></div></div>
                <div class="payment-form-actions"><button type="submit" class="btn btn-primary bill-payment-action">{{ $sales ? 'Save receipt' : 'Save payment voucher' }}</button></div>
            </form>
        </div></aside>
    </div>
    @if($canManageAccounts)
    <dialog id="payment-account-dialog" class="payment-account-dialog" aria-labelledby="payment-account-dialog-title">
        <form id="payment-account-form" action="{{ route('bill-payments.accounts.store') }}" method="post">@csrf
            <div class="payment-section-heading"><h2 id="payment-account-dialog-title">Add cash / bank account</h2><button type="button" class="close" data-close-account aria-label="Close">&times;</button></div>
            <p>Creates a named payment account linked to its own ledger. Opening balance is zero.</p>
            <div class="form-group"><label for="new-payment-account-kind">Account type *</label><select id="new-payment-account-kind" name="kind" class="form-control" required><option value="bank">Bank account</option><option value="cash">Cash account</option></select></div>
            <div class="form-group"><label for="new-payment-account-name">Account name *</label><input id="new-payment-account-name" name="name" class="form-control" placeholder="e.g. HDFC Current or SBI Current" maxlength="150" required></div>
            <div class="form-group"><label for="new-payment-account-number">Bank account number</label><input id="new-payment-account-number" name="account_no" class="form-control" maxlength="50" autocomplete="off"><small class="payment-field-hint">Optional. Only the last four characters appear in the payment picker.</small></div>
            <p id="payment-account-status" role="status"></p>
            <div class="payment-form-actions"><button type="button" class="btn btn-secondary" data-close-account>Cancel</button><button type="submit" class="btn btn-primary">Create account</button></div>
        </form>
    </dialog>
    @endif
</section>
@endsection
@push('scripts')<script src="{{ versioned_asset('js/bill-payments.js') }}"></script>@endpush
@push('css')<link rel="stylesheet" href="{{ versioned_asset('css/pages/bill-payments.css') }}">@endpush
