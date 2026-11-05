@extends('backend.layout.main')
@section('content')
<section class="product-workspace-view bill-payment-document">
    <div class="comm-command-bar d-print-none"><div class="comm-title-group"><h1>Accounting Voucher</h1></div><div class="comm-actions"><a class="btn btn-outline-primary" href="{{ route('accounting.vouchers') }}">Voucher Hub</a><button class="btn btn-primary" onclick="window.print()">Print voucher</button></div></div>
    <article class="card"><div class="card-body">
        <h2>{{ ucfirst($entry->voucher_type ?? $entry->reference_type) }} Voucher · {{ $entry->entry_number }}</h2>
        <p><strong>Date:</strong> {{ $entry->entry_date->format('Y-m-d') }} · <strong>Status:</strong> {{ $entry->is_reversed ? 'Reversed' : ucfirst($entry->status) }}</p>
        <p><strong>Source:</strong> {{ $entry->reference_no ?: 'Internal voucher' }}</p>
        @if($original)<p><strong>Replaces bill:</strong> {{ $original->reference_no }}. Original bill and accounting history remain linked.</p>@endif
        <p>{{ $entry->description }}</p>
        <div class="table-responsive"><table class="table table-sm"><thead><tr><th>Ledger</th><th>Memo</th><th class="text-right">Debit</th><th class="text-right">Credit</th></tr></thead><tbody>
            @foreach($entry->items as $line)<tr><td>{{ $line->account?->code }} · {{ $line->account?->name }}</td><td>{{ $line->memo }}</td><td class="text-right">{{ number_format($line->debit, 2) }}</td><td class="text-right">{{ number_format($line->credit, 2) }}</td></tr>@endforeach
        </tbody><tfoot><tr><th colspan="2">Total</th><th class="text-right">{{ number_format($entry->total_debit, 2) }}</th><th class="text-right">{{ number_format($entry->total_credit, 2) }}</th></tr></tfoot></table></div>
        <p class="mt-4">Prepared by: ____________________ &nbsp; Checked by: ____________________ &nbsp; Authorised by: ____________________</p>
    </div></article>
</section>
@endsection

@push('css')<link rel="stylesheet" href="{{ versioned_asset('css/product-workspace.css') }}">@endpush
@push('css')<link rel="stylesheet" href="{{ versioned_asset('css/pages/bill-payments.css') }}">@endpush
