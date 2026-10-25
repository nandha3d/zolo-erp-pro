@extends('backend.compliance.layout')
@section('title','Brokerage')
@section('compliance_body')
@php $inr = fn ($n) => \App\Services\Documents\DocumentRenderingService::inr((float) $n); @endphp
<form method="GET" class="cx-filter mb-2" data-enter-flow>
    <div class="cx-field"><label for="brk-from">From</label><input id="brk-from" name="from" type="date" value="{{ $from }}" required></div>
    <div class="cx-field"><label for="brk-to">To</label><input id="brk-to" name="to" type="date" value="{{ $to }}" required></div>
    <div class="cx-field"><label for="brk-agent">Agent</label><select id="brk-agent" name="agent_id"><option value="">All agents</option>@foreach($agents as $agent)<option value="{{ $agent->id }}" @selected($agentId === $agent->id)>{{ $agent->name }} ({{ (float) $agent->commission_rate }}%)</option>@endforeach</select></div>
    <div class="cx-field"><label for="brk-basis">Commission on</label><select id="brk-basis" name="basis"><option value="invoice" @selected($basis === 'invoice')>Invoice value</option><option value="collection" @selected($basis === 'collection')>Amount collected</option></select></div>
    <button type="submit" class="cx-btn"><i class="dripicons-search"></i> Show</button>
</form>
<div class="cx-card">
    <h2><i class="dripicons-user-group"></i> Invoices through agents</h2>
    <p class="cx-help">Brokerage is the agent's rate (Masters › Agents) on the value before GST. Booking posts brokerage expense to brokerage payable, less 2% TDS under 194H once an agent's brokerage for the year passes ₹20,000.</p>
    <div class="cx-table-wrap u-maxh-52vh u-ov-auto"><table class="cx-table">
        <thead><tr><th>Invoice</th><th>Date</th><th>Agent</th><th class="text-right">Value before GST</th><th class="text-right">Collected</th><th class="text-right">Base</th><th class="text-right">Rate</th><th class="text-right">Brokerage</th><th>Status</th></tr></thead>
        <tbody>
        @forelse($rows as $row)
            <tr><td>{{ $row['invoice'] }}</td><td>{{ $row['date'] }}</td><td>{{ $row['agent'] }}</td><td class="text-right">{{ $inr($row['taxable_value']) }}</td><td class="text-right">{{ $row['collected_percent'] }}%</td>
                <td class="text-right">{{ $inr($row['base']) }}</td><td class="text-right">{{ $row['rate'] }}%</td><td class="text-right"><strong>{{ $inr($row['commission']) }}</strong></td>
                <td>{!! $row['booked'] ? '<span class="badge badge-success">Booked</span>' : '<span class="badge badge-warning">Not booked</span>' !!}</td></tr>
        @empty
            <tr><td colspan="9" class="text-center text-muted py-4">No invoices with an agent in this period. Choose the agent on the sales invoice.</td></tr>
        @endforelse
        </tbody>
        @if($rows)<tfoot><tr><td colspan="7">Total</td><td class="text-right"><strong>{{ $inr(array_sum(array_column($rows, 'commission'))) }}</strong></td><td></td></tr></tfoot>@endif
    </table></div>
    @if($agentId && collect($rows)->where('booked', false)->where('commission', '>', 0)->isNotEmpty())
    <form method="POST" action="{{ url('/trade/brokerage') }}" class="cx-form mt-2">@csrf
        <input type="hidden" name="agent_id" value="{{ $agentId }}"><input type="hidden" name="from" value="{{ $from }}"><input type="hidden" name="to" value="{{ $to }}"><input type="hidden" name="basis" value="{{ $basis }}">
        <div><label for="brk-date">Book on</label><input id="brk-date" name="business_date" type="date" value="{{ $to }}" required></div>
        <div><button type="submit" class="cx-btn">Book unbooked brokerage</button></div>
    </form>
    @elseif(!$agentId && $rows)<p class="cx-help mt-2 mb-0">Pick one agent to book their brokerage.</p>@endif
</div>
@endsection
