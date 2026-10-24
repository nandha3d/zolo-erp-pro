@extends('backend.compliance.layout')
@section('title','Job order '.$order->reference_no)
@section('compliance_body')
@php $directions = ['received' => 'Received from customer', 'returned' => 'Returned processed', 'loss' => 'Process loss', 'billed' => 'Processing invoice']; @endphp
<div class="cx-kpis mb-2 d-flex flex-wrap" style="gap:8px;">
    @foreach(['received' => 'Received', 'returned' => 'Returned', 'loss' => 'Loss', 'with_us' => 'Still with us', 'unbilled' => 'Returned, not billed'] as $key => $label)
        <div class="cx-kpi"><span>{{ $label }}</span><strong>{{ $balance[$key] }}</strong></div>
    @endforeach
</div>
<p class="cx-lead">{{ $customer->company_name ?: $customer->name }} · {{ $order->process }} · ₹{{ (float) $order->rate }} per unit · allowed loss {{ (float) $order->max_loss_percent }}%</p>
<div class="cx-grid">
    <div class="cx-card">
        <h2>Receive material</h2>
        <form method="POST" action="{{ url('/trade/customer-jobs/receive/'.$order->id) }}" class="cx-form">@csrf
            <div><label for="r-date">Date</label><input id="r-date" name="business_date" type="date" value="{{ date('Y-m-d') }}" required></div>
            <div><label for="r-challan">Their challan no.</label><input id="r-challan" name="challan_no" maxlength="60" required></div>
            <div><label for="r-cdate">Challan date</label><input id="r-cdate" name="challan_date" type="date" value="{{ date('Y-m-d') }}" required></div>
            <div><label for="r-desc">Material</label><input id="r-desc" name="lines[0][description]" maxlength="255" placeholder="Grey cotton 60s" required></div>
            <div><label for="r-hsn">HSN</label><input id="r-hsn" name="lines[0][hsn_code]" maxlength="8" value="5208"></div>
            <div><label for="r-uqc">Unit</label><input id="r-uqc" name="lines[0][uqc]" maxlength="10" value="MTR" required></div>
            <div><label for="r-qty">Quantity</label><input id="r-qty" name="lines[0][qty]" type="number" min="0.001" step="0.001" required></div>
            <div><label for="r-value">Value on their challan</label><input id="r-value" name="lines[0][value]" type="number" min="0" step="0.01"></div>
            <div><button type="submit" class="cx-btn">Record receipt</button></div>
        </form>
    </div>
    <div class="cx-card">
        <h2>Return processed goods</h2>
        <form method="POST" action="{{ url('/trade/customer-jobs/return/'.$order->id) }}" class="cx-form">@csrf
            <div><label for="t-date">Date</label><input id="t-date" name="business_date" type="date" value="{{ date('Y-m-d') }}" required></div>
            <div><label for="t-desc">Goods</label><input id="t-desc" name="lines[0][description]" maxlength="255" placeholder="Dyed cotton 60s - Navy" required></div>
            <div><label for="t-hsn">HSN</label><input id="t-hsn" name="lines[0][hsn_code]" maxlength="8" value="5208"></div>
            <div><label for="t-uqc">Unit</label><input id="t-uqc" name="lines[0][uqc]" maxlength="10" value="MTR" required></div>
            <div><label for="t-qty">Quantity returned</label><input id="t-qty" name="lines[0][qty]" type="number" min="0" step="0.001" value="{{ $balance['with_us'] }}" required></div>
            <div><label for="t-loss">Process loss</label><input id="t-loss" name="lines[0][loss_qty]" type="number" min="0" step="0.001" value="0"></div>
            <div><button type="submit" class="cx-btn">Make return challan</button></div>
        </form>
    </div>
    <div class="cx-card">
        <h2>Bill processing charge</h2>
        <form method="POST" action="{{ url('/trade/customer-jobs/bill/'.$order->id) }}" class="cx-form">@csrf
            <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
            <div><label for="b-date">Date</label><input id="b-date" name="business_date" type="date" value="{{ date('Y-m-d') }}" required></div>
            <div><label for="b-qty">Quantity</label><input id="b-qty" name="qty" type="number" min="0.001" step="0.001" value="{{ $balance['unbilled'] }}"></div>
            <div><label for="b-rate">Rate</label><input id="b-rate" name="rate" type="number" min="0" step="0.0001" value="{{ (float) $order->rate }}"></div>
            <div><button type="submit" class="cx-btn" @disabled($balance['unbilled'] <= 0)>Create invoice</button></div>
        </form>
    </div>
</div>
<div class="cx-card mt-2">
    <h2>History</h2>
    <div class="cx-table-wrap"><table class="cx-table">
        <thead><tr><th>Date</th><th>What</th><th>Challan / invoice</th><th>Material</th><th>Unit</th><th class="text-right">Qty</th><th></th></tr></thead>
        <tbody>@forelse($movements as $m)<tr><td>{{ $m->business_date }}</td><td>{{ $directions[$m->direction] ?? $m->direction }}</td><td>{{ $m->challan_no }}</td><td>{{ $m->description }}</td><td>{{ $m->uqc }}</td><td class="text-right">{{ (float) $m->qty }}</td>
            <td>@if($m->direction === 'returned')<a href="{{ url('/trade/customer-jobs/'.$order->id.'?challan='.urlencode($m->challan_no)) }}" target="_blank" rel="noopener">Print challan</a>@elseif($m->sale_id)<a href="{{ url('/compliance/documents/sale/'.$m->sale_id) }}">Invoice</a>@endif</td></tr>
        @empty<tr><td colspan="7" class="text-muted">Nothing recorded yet.</td></tr>@endforelse</tbody>
    </table></div>
</div>
@endsection
