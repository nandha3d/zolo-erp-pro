@php
    $money = fn ($value) => \App\Services\Documents\DocumentRenderingService::inr((float) $value);
    $qty = fn ($value) => rtrim(rtrim(number_format((float) $value, 3, '.', ','), '0'), '.');
    $t = $c['transport'];
    $meta = array_filter([
        'Challan No.' => $dispatch->reference_no, 'Challan Date' => $c['date'], 'Job work order' => $order->reference_no,
        'Process' => $c['process']['name'] ?? null, 'Expected return' => $c['expected_return'], 'Place of supply' => $c['place_of_supply'],
        'Vehicle No.' => $t['vehicle_number'] ?? null, 'Transporter' => $t['transporter_name'] ?? null, 'E-way bill No.' => $t['eway_bill_no'] ?? null,
    ], fn ($value) => $value !== null && $value !== '');
    $rates = array_values(array_unique(array_filter(array_column($c['lines'], 'gst_rate'), fn ($rate) => $rate !== null)));
@endphp
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Delivery Challan {{ $dispatch->reference_no }}</title>
<link rel="stylesheet" href="{{ versioned_asset('css/pages/operations-job-work-challan.css') }}"></head><body>
<div class="print-bar"><button type="button" onclick="window.print()">Print 3 copies</button></div>
@foreach(['Original for consignee (job worker)', 'Duplicate for transporter', 'Triplicate for consignor'] as $copy)
<div class="sheet copy">
    <table class="top"><tr>
        <td class="u-w-33pct"><strong>GSTIN: {{ $c['company']['gstin'] ?: 'Unregistered' }}</strong></td>
        <td class="title u-w-34pct">Delivery Challan</td>
        <td class="u-w-33pct u-ta-right"><em>{{ $copy }}</em></td>
    </tr><tr><td colspan="3" class="sub">Goods sent for job work · Rule 55(1)(c) of the CGST Rules, 2017 · Section 143 of the CGST Act</td></tr></table>

    <table class="box">
        <tr><td colspan="2" class="u-p-0"><table class="letterhead"><tr>
            <td class="logo">@if($c['company']['logo'])<img src="{{ $c['company']['logo'] }}" alt="">@endif</td>
            <td class="company"><h1>{{ $c['company']['name'] }}</h1>
                @if(strcasecmp($c['company']['legal'], $c['company']['name']) !== 0)<p>{{ $c['company']['legal'] }}</p>@endif
                <p>{{ $c['company']['address'] }}</p>
                <p>{{ implode(' · ', array_filter([$c['company']['state'] ? 'State: '.$c['company']['state'] : null, $c['company']['phone'] ? 'Ph: '.$c['company']['phone'] : null, $c['company']['email']])) }}</p></td>
            <td class="logo"></td></tr></table></td></tr>
        <tr>
            <td class="u-w-55pct"><div class="label">Consignee (Job worker)</div>
                <div class="party">{{ $c['worker']['name'] }}</div>
                @if($c['worker']['contact'] && $c['worker']['contact'] !== $c['worker']['name'])<div>{{ $c['worker']['contact'] }}</div>@endif
                <div>{{ $c['worker']['address'] ?: 'Address not recorded' }}</div>
                @if($c['worker']['state'])<div>State: {{ $c['worker']['state'] }}</div>@endif
                @if($c['worker']['phone'])<div>Ph: {{ $c['worker']['phone'] }}</div>@endif
                <div><strong>GSTIN: {{ $c['worker']['gstin'] ?: 'Unregistered job worker' }}</strong></div>
            </td>
            <td><table class="meta">@foreach($meta as $key => $value)<tr><td class="k">{{ $key }}</td><td>:</td><td class="v">{{ $value }}</td></tr>@endforeach</table></td>
        </tr>
        <tr><td colspan="2"><strong>Purpose:</strong> {{ $c['purpose'] }}. The goods remain the property of {{ $c['company']['legal'] }} and are to be returned after processing.</td></tr>
    </table>

    <table class="box items u-mt-1px">
        <thead><tr><th class="u-w-5pct">S.No</th><th>Description of goods</th><th class="u-w-10pct">HSN</th><th class="u-w-13pct">Quantity</th><th class="u-w-11pct">Rate</th><th class="u-w-14pct">Taxable value</th><th class="u-w-7pct">GST %</th></tr></thead>
        <tbody>
        @foreach($c['lines'] as $i => $line)
            <tr><td class="c">{{ $i + 1 }}</td><td><strong>{{ $line['name'] }}</strong> <span class="small">{{ $line['code'] }}</span>@if($line['lot'])<div class="small">Lot / batch: {{ $line['lot'] }}</div>@endif</td>
                <td class="c">{{ $line['hsn'] }}</td><td class="num">{{ $qty($line['qty']) }} {{ $line['unit'] }}</td><td class="num">{{ $money($line['rate']) }}</td>
                <td class="num">{{ $money($line['value']) }}</td><td class="c">{{ $line['gst_rate'] !== null ? (float) $line['gst_rate'].'%' : '—' }}</td></tr>
        @endforeach
        </tbody>
        <tfoot><tr><td colspan="3" class="num"><strong>Total</strong></td><td class="num"><strong>{{ $qty(array_sum(array_column($c['lines'], 'qty'))) }}@if(count(array_unique(array_column($c['lines'], 'unit'))) === 1) {{ $c['lines'][0]['unit'] ?? '' }}@endif</strong></td><td></td><td class="num"><strong>{{ $money($c['total']) }}</strong></td><td></td></tr></tfoot>
    </table>

    <table class="box u-mt-1px"><tr><td>
        <p class="note"><strong>Value of goods (in words):</strong> {{ $c['value_words'] }}</p>
        <p class="note"><strong>Tax:</strong> {{ $c['tax_note'] }}@if($rates) Applicable GST rate on these goods: {{ implode(', ', array_map(fn ($rate) => (float) $rate.'%', $rates)) }}.@endif @if($c['inter_state'])Inter-State movement: carry the e-way bill.@endif</p>
        @if(!empty($t['notes']))<p class="note"><strong>Instructions:</strong> {{ $t['notes'] }}</p>@endif
        @if($c['notes'])<p class="note"><strong>Order note:</strong> {{ $c['notes'] }}</p>@endif
        <p class="note small">Inputs sent for job work must be received back within one year (capital goods within three years) of this challan, failing which they are treated as supplied on the date of dispatch.</p>
    </td></tr></table>

    <table class="box sign u-mt-1px"><tr>
        <td class="u-w-50pct">Received the above goods in good condition.<br><br>Receiver's signature, name &amp; date<br>for {{ $c['worker']['name'] }}</td>
        <td class="u-ta-right">For {{ $c['company']['name'] }}<br><br><br>Authorised Signatory</td>
    </tr></table>
</div>
@endforeach
</body></html>
