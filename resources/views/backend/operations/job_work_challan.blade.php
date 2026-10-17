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
<style>
@page { size: A4; margin: 8mm; }
* { box-sizing: border-box; }
body { margin: 0; background: #fff; color: #000; font: 10px/1.35 "DejaVu Sans", Arial, Helvetica, sans-serif; }
.sheet { width: 194mm; margin: 12px auto; }
.copy { page-break-after: always; } .copy:last-child { page-break-after: auto; }
table { width: 100%; border-collapse: collapse; }
td, th { padding: 3px 5px; vertical-align: top; }
.box { border: 1px solid #000; }
.box > tbody > tr > td, .box > thead > tr > th, .box > tbody > tr > th, .box > tfoot > tr > td { border: 1px solid #000; }
.top td { border: 0; padding: 1px 0 3px; }
.title { text-align: center; font-size: 13px; font-weight: bold; text-transform: uppercase; text-decoration: underline; }
.sub { text-align: center; font-size: 9.5px; }
.letterhead td { border: 0; vertical-align: middle; }
.letterhead td.logo { width: 30mm; } .letterhead td.logo img { max-width: 28mm; max-height: 18mm; }
.company { text-align: center; } .company h1 { margin: 0 0 2px; font-size: 18px; text-transform: uppercase; } .company p { margin: 0; }
.label { font-weight: bold; text-decoration: underline; }
.party { font-size: 12px; font-weight: bold; text-transform: uppercase; }
.meta td { border: 0; padding: 1px 3px; } .meta td.k { white-space: nowrap; width: 38%; } .meta td.v { font-weight: bold; }
.items th { background: #f2f2f2; font-size: 9.5px; text-align: center; }
.num { text-align: right; white-space: nowrap; } .c { text-align: center; }
.small { font-size: 9px; } .note { margin: 0 0 2px; }
.sign td { border: 0; height: 52px; vertical-align: bottom; }
.print-bar { text-align: center; margin: 10px; } @media print { .print-bar { display: none; } .sheet { margin: 0 auto; } }
</style></head><body>
<div class="print-bar"><button type="button" onclick="window.print()">Print 3 copies</button></div>
@foreach(['Original for consignee (job worker)', 'Duplicate for transporter', 'Triplicate for consignor'] as $copy)
<div class="sheet copy">
    <table class="top"><tr>
        <td style="width:33%"><strong>GSTIN: {{ $c['company']['gstin'] ?: 'Unregistered' }}</strong></td>
        <td style="width:34%" class="title">Delivery Challan</td>
        <td style="width:33%;text-align:right"><em>{{ $copy }}</em></td>
    </tr><tr><td colspan="3" class="sub">Goods sent for job work · Rule 55(1)(c) of the CGST Rules, 2017 · Section 143 of the CGST Act</td></tr></table>

    <table class="box">
        <tr><td colspan="2" style="padding:0"><table class="letterhead"><tr>
            <td class="logo">@if($c['company']['logo'])<img src="{{ $c['company']['logo'] }}" alt="">@endif</td>
            <td class="company"><h1>{{ $c['company']['name'] }}</h1>
                @if(strcasecmp($c['company']['legal'], $c['company']['name']) !== 0)<p>{{ $c['company']['legal'] }}</p>@endif
                <p>{{ $c['company']['address'] }}</p>
                <p>{{ implode(' · ', array_filter([$c['company']['state'] ? 'State: '.$c['company']['state'] : null, $c['company']['phone'] ? 'Ph: '.$c['company']['phone'] : null, $c['company']['email']])) }}</p></td>
            <td class="logo"></td></tr></table></td></tr>
        <tr>
            <td style="width:55%"><div class="label">Consignee (Job worker)</div>
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

    <table class="box items" style="margin-top:-1px">
        <thead><tr><th style="width:5%">S.No</th><th>Description of goods</th><th style="width:10%">HSN</th><th style="width:13%">Quantity</th><th style="width:11%">Rate</th><th style="width:14%">Taxable value</th><th style="width:7%">GST %</th></tr></thead>
        <tbody>
        @foreach($c['lines'] as $i => $line)
            <tr><td class="c">{{ $i + 1 }}</td><td><strong>{{ $line['name'] }}</strong> <span class="small">{{ $line['code'] }}</span>@if($line['lot'])<div class="small">Lot / batch: {{ $line['lot'] }}</div>@endif</td>
                <td class="c">{{ $line['hsn'] }}</td><td class="num">{{ $qty($line['qty']) }} {{ $line['unit'] }}</td><td class="num">{{ $money($line['rate']) }}</td>
                <td class="num">{{ $money($line['value']) }}</td><td class="c">{{ $line['gst_rate'] !== null ? (float) $line['gst_rate'].'%' : '—' }}</td></tr>
        @endforeach
        </tbody>
        <tfoot><tr><td colspan="3" class="num"><strong>Total</strong></td><td class="num"><strong>{{ $qty(array_sum(array_column($c['lines'], 'qty'))) }}@if(count(array_unique(array_column($c['lines'], 'unit'))) === 1) {{ $c['lines'][0]['unit'] ?? '' }}@endif</strong></td><td></td><td class="num"><strong>{{ $money($c['total']) }}</strong></td><td></td></tr></tfoot>
    </table>

    <table class="box" style="margin-top:-1px"><tr><td>
        <p class="note"><strong>Value of goods (in words):</strong> {{ $c['value_words'] }}</p>
        <p class="note"><strong>Tax:</strong> {{ $c['tax_note'] }}@if($rates) Applicable GST rate on these goods: {{ implode(', ', array_map(fn ($rate) => (float) $rate.'%', $rates)) }}.@endif @if($c['inter_state'])Inter-State movement: carry the e-way bill.@endif</p>
        @if(!empty($t['notes']))<p class="note"><strong>Instructions:</strong> {{ $t['notes'] }}</p>@endif
        @if($c['notes'])<p class="note"><strong>Order note:</strong> {{ $c['notes'] }}</p>@endif
        <p class="note small">Inputs sent for job work must be received back within one year (capital goods within three years) of this challan, failing which they are treated as supplied on the date of dispatch.</p>
    </td></tr></table>

    <table class="box sign" style="margin-top:-1px"><tr>
        <td style="width:50%">Received the above goods in good condition.<br><br>Receiver's signature, name &amp; date<br>for {{ $c['worker']['name'] }}</td>
        <td style="text-align:right">For {{ $c['company']['name'] }}<br><br><br>Authorised Signatory</td>
    </tr></table>
</div>
@endforeach
</body></html>
