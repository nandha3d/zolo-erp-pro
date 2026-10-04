<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $document['number'] }}</title><style>
@page { size: {{ $profile['format'] === 'thermal' ? $profile['width'].'mm auto' : 'A4' }}; margin: {{ $profile['format'] === 'thermal' ? '3mm' : '12mm' }}; } *{box-sizing:border-box} body{margin:0;color:#14202e;font:12px Arial,sans-serif;background:white}
.document{max-width:{{ $profile['format'] === 'thermal' ? $profile['width'].'mm' : '190mm' }};margin:16px auto;padding:12px}
h1{font-size:22px;margin:0 0 8px}h2{font-size:16px;margin:12px 0 4px}p{margin:5px 0;overflow-wrap:anywhere}.meta{border-bottom:2px solid #14202e;padding-bottom:12px}
table{width:100%;border-collapse:collapse;margin:16px 0;font-size:11px}th,td{text-align:left;border-bottom:1px solid #ccc;padding:6px 3px;vertical-align:top}.amount{text-align:right;font-variant-numeric:tabular-nums}
.total{border-top:2px solid #14202e;margin-top:16px;padding-top:10px;font-size:18px;font-weight:bold}.copy{break-after:page}.copy:last-child{break-after:auto}
.tax-lines{font-size:10px} .print-action{display:block;margin:12px auto;padding:8px 20px}
@media print{.print-action{display:none}.document{margin:0;padding:0}.copy{page-break-after:always}.copy:last-child{page-break-after:auto}}
@media(max-width:600px){.document{max-width:100%;padding:10px}table{font-size:10px}th,td{padding:4px 2px}}
</style></head><body><button type="button" class="print-action" onclick="window.print()">Print document</button>
@foreach($profile['copies'] as $copy)<article class="document copy"><header class="meta"><h1>{{ $document['company_name'] }}</h1>
@if($document['reversed_at'] ?? null)<p><strong>Reversed on {{ $document['reversed_at'] }}</strong></p>@endif
<p>{{ $document['company_address'] }}</p><p>GSTIN {{ $document['company_gstin'] ?: 'Not registered' }}</p><h2>{{ $document['title'] }} · {{ $copy }}</h2>
<p><strong>{{ $document['number'] }}</strong> · {{ $document['date'] }}</p>
@if($document['source_number'])<p>Against {{ $document['source_number'] }}</p>@endif</header>
<h2>{{ $document['party_name'] }}</h2><p>{{ $document['party_address'] }}</p><p>GSTIN {{ $document['party_gstin'] ?: 'Unregistered' }} · Place of supply {{ $document['place_of_supply'] ?: '—' }}</p>
<p>Reverse charge: {{ $document['reverse_charge'] ? 'Yes' : 'No' }}</p>
<table><thead><tr><th scope="col">Item / HSN-SAC</th><th scope="col" class="amount">Qty</th><th scope="col" class="amount">Rate</th><th scope="col" class="amount">Tax</th><th scope="col" class="amount">Total</th></tr></thead>
<tbody>@foreach($document['lines'] as $line)<tr><td>{{ $line['name'] }}<br>{{ $line['code'] }} · {{ $line['hsn_sac'] ?: '—' }}
<p class="tax-lines">Taxable {{ number_format($line['taxable_value'],4,'.','') }} · GST {{ number_format($line['tax_rate'],4,'.','') }}% · Cess {{ number_format($line['cess_rate'],4,'.','') }}%</p>
@if($line['cgst'] || $line['sgst'] || $line['igst'] || $line['cess'])<p class="tax-lines">CGST {{ number_format($line['cgst'],4,'.','') }} · SGST {{ number_format($line['sgst'],4,'.','') }} · IGST {{ number_format($line['igst'],4,'.','') }} · Cess {{ number_format($line['cess'],4,'.','') }}</p>@endif
@if($dimension = $line['dimensions'] ?? null)<p>Piece {{ $dimension['identity_no'] ?? '' }} · {{ $dimension['length'] }} × {{ $dimension['width'] }} × {{ $dimension['thickness'] }} {{ $dimension['dimension_uom'] }} · CFT {{ $dimension['line_cft'] ?? $dimension['computed_cft'] }} · CBM {{ $dimension['line_cbm'] ?? $dimension['computed_cbm'] }} · {{ $dimension['formula_version'] }}</p>@endif</td>
<td class="amount">{{ number_format($line['qty'],$document['quantity_scale'] ?? 4,'.','') }}</td><td class="amount">{{ number_format($line['price'],4,'.','') }}</td><td class="amount">{{ number_format($line['tax'],4,'.','') }}</td><td class="amount">{{ number_format($line['total'],4,'.','') }}</td></tr>@endforeach</tbody></table>
<p>Total tax {{ $document['currency'] }} {{ number_format($document['total_tax'],4,'.','') }}</p><p>Invoice discount {{ number_format($document['discount'],4,'.','') }} · Freight {{ number_format($document['shipping'],4,'.','') }}</p>
<p class="total">Grand total {{ $document['currency'] }} {{ number_format($document['grand_total'],4,'.','') }}</p>
@foreach($document['transport'] as $label => $value)<p>{{ ucfirst(str_replace('_',' ',$label)) }}: {{ $value }}</p>@endforeach
</article>@endforeach</body></html>
