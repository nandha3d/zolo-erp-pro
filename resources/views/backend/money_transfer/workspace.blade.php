@extends('backend.layout.main')
@section('content')
<section class="money-transfer-workspace-view">
    <x-accounting-command-bar active="transfers" title="Money Transfer" purpose="Move money between your own cash and bank accounts. The source decreases, the destination increases, and no income or expense is created.">
        @if($canPostTransfers)<a class="btn btn-primary" href="{{ route('accounting.vouchers', ['voucher_type' => 'contra']) }}">New transfer · Contra voucher</a>@endif
    </x-accounting-command-bar>
    <x-success-message key="message" />
    <x-error-message key="not_permitted" />
    @unless($canPostTransfers)<p class="accounting-help">You can review transfers. Ask a company administrator for voucher posting permission to create a transfer.</p>@endunless
    <div class="kpi-summary-strip">
        <div class="kpi-card"><div><div class="kpi-label">Posted transfers</div><div class="kpi-number">{{ $transferCount }}</div></div></div>
        <div class="kpi-card"><div><div class="kpi-label">Recorded transfer amount</div><div class="kpi-number">₹ {{ number_format($transferTotal, 2) }}</div></div></div>
        <div class="kpi-card"><div><div class="kpi-label">Cash & bank accounts</div><div class="kpi-number">{{ $lims_account_list->count() }}</div></div></div>
    </div>
    <div class="desk-card-wrapper">
        <form method="GET" action="{{ route('money-transfers.index') }}" class="accounting-toolbar desk-filter-card" id="money-transfer-filters">
            <div><label for="transfer-start">From date</label><input id="transfer-start" type="date" name="start_date" class="form-control" value="{{ $startDate }}" required></div>
            <div><label for="transfer-end">To date</label><input id="transfer-end" type="date" name="end_date" class="form-control" value="{{ $endDate }}" required></div>
            <button class="btn btn-primary btn-accounting-text" type="submit">Apply filters</button>
            <a class="btn btn-outline-secondary btn-accounting-text" href="{{ route('money-transfers.index') }}">Reset</a>
            <button type="button" class="btn btn-outline-secondary btn-accounting-text" data-erp-print>Print transfers</button>
        </form>
        <div class="table-responsive"><table class="table mb-0" id="posted-money-transfers">
            <caption class="sr-only">Posted cash and bank transfers for the selected dates</caption>
            <thead><tr><th>Date</th><th>Reference</th><th>From account</th><th>To account</th><th class="text-right">Amount</th><th>Description</th><th>Status</th><th>Action</th></tr></thead>
            <tbody>
                @forelse($entries as $entry)
                <tr>
                    <td>{{ $entry->entry_date->format('d/m/Y') }}</td><td>{{ $entry->entry_number }}</td>
                    <td>{{ $entry->items->filter(fn ($line) => (float) $line->credit > 0)->map(fn ($line) => $line->account->name)->implode(', ') }}</td>
                    <td>{{ $entry->items->filter(fn ($line) => (float) $line->debit > 0)->map(fn ($line) => $line->account->name)->implode(', ') }}</td>
                    <td class="text-right">₹ {{ number_format($entry->total_debit, 2) }}</td><td>{{ $entry->description }}</td>
                    <td>@if(in_array($entry->id, $reversedIds))<span class="badge badge-warning">Reversed</span>@else<span class="badge badge-success">Posted</span>@endif</td>
                    <td>@if($canViewVouchers)<a href="{{ route('accounting.vouchers.show', ['id' => $entry->id, 'branch_scope' => request('branch_scope')]) }}" class="btn btn-outline-primary btn-sm btn-accounting-text">View voucher</a>@else<span class="text-muted">Voucher access required</span>@endif</td>
                </tr>
                @empty
                <tr><td colspan="8" class="accounting-empty">No posted transfers for these dates. @if($canPostTransfers)<a href="{{ route('accounting.vouchers', ['voucher_type' => 'contra']) }}">Create a transfer</a>.@endif</td></tr>
                @endforelse
            </tbody>
        </table></div>
        {{ $entries->links() }}
    </div>
    <details class="desk-card-wrapper">
        <summary>Older transfer register ({{ $lims_money_transfer_all->count() }})</summary>
        <p class="text-muted small mt-2">Earlier register entries are read-only. New transfers appear above after their voucher posts.</p>
        <div class="table-responsive"><table class="table mb-0">
            <thead><tr><th>Reference</th><th>Date</th><th>From account</th><th>To account</th><th class="text-right">Amount</th></tr></thead>
            <tbody>@forelse($lims_money_transfer_all as $transfer)<tr><td>{{ $transfer->reference_no }}</td><td>{{ $transfer->created_at->format('d/m/Y') }}</td><td>{{ $transfer->fromAccount->name ?? 'Unavailable account' }}</td><td>{{ $transfer->toAccount->name ?? 'Unavailable account' }}</td><td class="text-right">₹ {{ number_format($transfer->amount, 2) }}</td></tr>@empty<tr><td colspan="5" class="accounting-empty">No older transfers.</td></tr>@endforelse</tbody>
        </table></div>
    </details>
</section>
@endsection
@push('css')
    <link rel="stylesheet" href="{{ versioned_asset('css/commercial-workspace.css') }}">
@endpush
