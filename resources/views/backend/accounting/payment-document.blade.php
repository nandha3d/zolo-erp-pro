@extends('backend.layout.main')
@section('content')
<section class="product-workspace-view bill-payment-document">
    <div class="comm-command-bar d-print-none"><div class="comm-title-group"><h1>{{ $title }}</h1></div><div class="comm-actions"><a class="btn btn-outline-primary" href="{{ route($payment->sale_id ? 'selling.payments' : 'buying.payments') }}">Payments</a>@if($payment->sale_id)<a class="btn btn-outline-primary" href="{{ route('bill-payments.show', ['id' => $payment->id, 'voucher' => !$voucher]) }}">{{ $voucher ? 'Customer receipt' : 'Internal voucher' }}</a>@endif<button class="btn btn-primary" onclick="window.print()">Print {{ strtolower($title) }}</button></div></div>
    @if(session('message'))<div class="alert alert-success d-print-none" role="status">{{ session('message') }}</div>@endif
    <article class="card"><div class="card-body">
        <h2>{{ $title }} · {{ $payment->payment_reference }}</h2>
        @if($bill->reversed_at || ($entry && ($entry->status !== 'posted' || $entry->is_reversed)))<p class="alert alert-warning">Reversed — retained for accounting history.</p>@endif
        <p><strong>Date:</strong> {{ ($payment->payment_at ?? $payment->created_at)->format('Y-m-d') }}</p>
        <p><strong>{{ $payment->sale_id ? 'Received from' : 'Paid to' }}:</strong> {{ $payment->sale_id ? $bill->customer?->name : $bill->supplier?->name }}</p>
        <p><strong>Against bill:</strong> {{ $bill->reference_no }} · {{ $bill->created_at->format('Y-m-d') }}</p>
        <p><strong>Method:</strong> {{ $payment->paying_method }} · <strong>Account:</strong> {{ $payment->account?->name }}</p>
        <table class="table table-sm"><tbody><tr><th>Amount {{ $payment->sale_id ? 'received' : 'paid' }}</th><td class="text-right">{{ number_format($payment->amount, 2) }}</td></tr>
            @if($payment->discount)<tr><th>Cash discount</th><td class="text-right">{{ number_format($payment->discount, 2) }}</td></tr>@endif
            @if($payment->tds_amount)<tr><th>TDS deducted</th><td class="text-right">{{ number_format($payment->tds_amount, 2) }}</td></tr>@endif
        </tbody></table>
        @if($payment->payment_note)<p><strong>Note:</strong> {{ $payment->payment_note }}</p>@endif
        @if($entry && $voucher)
            <h3 class="h5">Accounting voucher · {{ $entry->entry_number }}</h3><p>{{ $entry->description }}</p>
            <table class="table table-sm"><thead><tr><th>Ledger</th><th class="text-right">Debit</th><th class="text-right">Credit</th></tr></thead><tbody>@foreach($entry->items as $line)<tr><td>{{ $line->account?->name }}</td><td class="text-right">{{ number_format($line->debit, 2) }}</td><td class="text-right">{{ number_format($line->credit, 2) }}</td></tr>@endforeach</tbody><tfoot><tr><th>Total</th><th class="text-right">{{ number_format($entry->total_debit, 2) }}</th><th class="text-right">{{ number_format($entry->total_credit, 2) }}</th></tr></tfoot></table>
        @elseif(!$entry && $voucher)<p>Historical payment. No separate accounting voucher is linked.</p>@endif
        <p class="mt-4">{{ $payment->sale_id ? 'Received by' : 'Prepared by' }}: ____________________ &nbsp; Authorised by: ____________________</p>
    </div></article>
</section>
@endsection

@push('css')<link rel="stylesheet" href="{{ versioned_asset('css/product-workspace.css') }}">@endpush
@push('css')<link rel="stylesheet" href="{{ versioned_asset('css/pages/bill-payments.css') }}">@endpush
