@extends('backend.compliance.layout')
@section('title','Job work for customers')
@section('compliance_body')
@include('backend.partials.job-work-direction', ['direction' => 'in'])
<p class="cx-lead">For dyeing, printing or processing units: the customer's material stays theirs (not in your stock or books). Record what arrives on their challan, send it back on your return challan with the loss, and bill only the processing charge.</p>
<div class="cx-grid">
    <div class="cx-card">
        <h2><i class="dripicons-plus"></i> New job order</h2>
        <form method="POST" action="{{ url('/trade/customer-jobs/order') }}" class="cx-form" data-enter-flow>@csrf
            <div><label for="pjo-customer">Customer (principal)</label><select id="pjo-customer" name="customer_id" required><option value="">Select</option>@foreach($customers as $c)<option value="{{ $c->id }}">{{ $c->company_name ?: $c->name }}</option>@endforeach</select></div>
            <div><label for="pjo-date">Date</label><input id="pjo-date" name="business_date" type="date" value="{{ date('Y-m-d') }}" required></div>
            <div><label for="pjo-process">Process</label><input id="pjo-process" name="process" maxlength="100" placeholder="e.g. Reactive dyeing" required></div>
            <div><label for="pjo-service">Charge item (SAC 9988)</label><select id="pjo-service" name="service_product_id" required><option value="">Select</option>@foreach($services as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach</select></div>
            <div><label for="pjo-rate">Rate per unit</label><input id="pjo-rate" name="rate" type="number" min="0" step="0.0001" required></div>
            <div><label for="pjo-loss">Allowed loss %</label><input id="pjo-loss" name="max_loss_percent" type="number" min="0" max="100" step="0.01" value="5"></div>
            <div><button type="submit" class="cx-btn">Create job order</button></div>
        </form>
    </div>
    <div class="cx-card u-gc-span-2">
        <h2><i class="dripicons-list"></i> Job orders</h2>
        <div class="cx-table-wrap u-maxh-60vh u-ov-auto"><table class="cx-table">
            <thead><tr><th>Order</th><th>Customer</th><th>Process</th><th class="text-right">Received</th><th class="text-right">Returned</th><th class="text-right">Loss</th><th class="text-right">With us</th><th class="text-right">To bill</th></tr></thead>
            <tbody>@forelse($orders as $o)<tr><td><a href="{{ url('/trade/customer-jobs/'.$o['id']) }}">{{ $o['reference_no'] }}</a></td><td>{{ $o['customer'] }}</td><td>{{ $o['process'] }}</td>
                <td class="text-right">{{ $o['received'] }}</td><td class="text-right">{{ $o['returned'] }}</td><td class="text-right">{{ $o['loss'] }}</td><td class="text-right"><strong>{{ $o['with_us'] }}</strong></td><td class="text-right">{{ $o['unbilled'] }}</td></tr>
            @empty<tr><td colspan="8" class="text-center text-muted py-4">No job orders yet.</td></tr>@endforelse</tbody>
        </table></div>
    </div>
</div>
@endsection
