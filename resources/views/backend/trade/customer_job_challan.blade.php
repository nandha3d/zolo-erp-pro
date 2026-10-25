<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Delivery challan {{ $challan }}</title>
<link rel="stylesheet" href="{{ versioned_asset('css/pages/trade-customer-job-challan.css') }}"></head>
<body>
<p class="noprint"><button onclick="window.print()">Print</button></p>
<h1>Delivery Challan</h1>
<p class="muted u-ta-center">Return of processed goods to the principal after job work · Rule 55, CGST Rules · not a supply</p>
<table>
    <tr><td><strong>Challan no.</strong> {{ $challan }}</td><td><strong>Date</strong> {{ \Carbon\Carbon::parse($lines[0]->business_date ?? now())->format('d-m-Y') }}</td><td><strong>Job order</strong> {{ $order->reference_no }}</td></tr>
    <tr><td colspan="3"><strong>Principal (consignee):</strong> {{ $customer->company_name ?: $customer->name }}, {{ $customer->address ?? '' }} {{ $customer->city ?? '' }}</td></tr>
    <tr><td colspan="3"><strong>Process:</strong> {{ $order->process }}</td></tr>
</table>
<table class="u-mt-10px"><thead><tr><th>#</th><th>Description</th><th>HSN</th><th>Unit</th><th class="r">Quantity</th></tr></thead>
<tbody>@foreach($lines as $i => $line)<tr><td>{{ $i + 1 }}</td><td>{{ $line->description }}{{ $line->direction === 'loss' ? ' — process loss (not returned)' : '' }}</td><td>{{ $line->hsn_code }}</td><td>{{ $line->uqc }}</td><td class="r">{{ (float) $line->qty }}</td></tr>@endforeach</tbody></table>
<p class="muted u-mt-30px">Original for consignee · Duplicate for transporter · Triplicate for consignor</p>
<p class="u-ta-right u-mt-40px">Authorised Signatory</p>
</body></html>
