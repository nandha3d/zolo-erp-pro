<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Delivery challan {{ $challan }}</title>
<style>body{font:13px/1.45 Arial,sans-serif;color:#111;margin:24px}table{width:100%;border-collapse:collapse}th,td{border:1px solid #333;padding:6px;text-align:left}h1{font-size:18px;text-align:center;margin:0 0 4px}.r{text-align:right}.muted{color:#555;font-size:12px}@media print{.noprint{display:none}}</style></head>
<body>
<p class="noprint"><button onclick="window.print()">Print</button></p>
<h1>Delivery Challan</h1>
<p class="muted" style="text-align:center">Return of processed goods to the principal after job work · Rule 55, CGST Rules · not a supply</p>
<table>
    <tr><td><strong>Challan no.</strong> {{ $challan }}</td><td><strong>Date</strong> {{ \Carbon\Carbon::parse($lines[0]->business_date ?? now())->format('d-m-Y') }}</td><td><strong>Job order</strong> {{ $order->reference_no }}</td></tr>
    <tr><td colspan="3"><strong>Principal (consignee):</strong> {{ $customer->company_name ?: $customer->name }}, {{ $customer->address ?? '' }} {{ $customer->city ?? '' }}</td></tr>
    <tr><td colspan="3"><strong>Process:</strong> {{ $order->process }}</td></tr>
</table>
<table style="margin-top:10px"><thead><tr><th>#</th><th>Description</th><th>HSN</th><th>Unit</th><th class="r">Quantity</th></tr></thead>
<tbody>@foreach($lines as $i => $line)<tr><td>{{ $i + 1 }}</td><td>{{ $line->description }}{{ $line->direction === 'loss' ? ' — process loss (not returned)' : '' }}</td><td>{{ $line->hsn_code }}</td><td>{{ $line->uqc }}</td><td class="r">{{ (float) $line->qty }}</td></tr>@endforeach</tbody></table>
<p class="muted" style="margin-top:30px">Original for consignee · Duplicate for transporter · Triplicate for consignor</p>
<p style="text-align:right;margin-top:40px">Authorised Signatory</p>
</body></html>
