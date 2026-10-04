@extends('backend.compliance.layout')
@section('title','GST review')
@section('content')
<p>Review posted snapshots and notes before filing. This export is not a GST portal filing file.</p>
<form method="GET" class="panel fields"><label for="report-from">From<input id="report-from" name="from" type="date" value="{{ $from }}" required></label><label for="report-to">To<input id="report-to" name="to" type="date" value="{{ $to }}" required></label><button type="submit">Review period</button>
<a href="{{ url('/compliance/gst/export?'.http_build_query(['from'=>$from,'to'=>$to,'version'=>'zolo-gst-review-v1'])) }}">Download review JSON</a></form>
<section class="panel"><div class="table-scroll"><table><thead><tr><th scope="col">Document</th><th scope="col">Section</th><th scope="col">GSTIN</th><th scope="col">Tax snapshot</th><th scope="col">Total</th></tr></thead><tbody>
@forelse($rows as $row)<tr><td>{{ $row['document_no'] }}<br>{{ $row['document_date'] }} · {{ str_replace('_',' ',$row['source_type']) }}</td><td>{{ strtoupper($row['section']) }}</td><td>{{ $row['snapshot']['buyer_gstin'] ?: 'Unregistered' }}</td><td>
@foreach($row['snapshot']['lines'] as $line)<p>{{ $line['hsn_sac'] }} · {{ $line['rate'] }}%<br>Taxable {{ number_format($line['taxable_value'],4,'.','') }}<br>CGST {{ $line['cgst'] }} · SGST {{ $line['sgst'] }} · IGST {{ $line['igst'] }} · Cess {{ $line['cess'] }}</p>@endforeach
</td><td>{{ ($row['snapshot']['sign'] ?? 1) < 0 ? '−' : '' }}{{ number_format($row['snapshot']['grand_total'],4,'.','') }}</td></tr>
@empty<tr><td colspan="5">No posted GST documents in this period.</td></tr>@endforelse</tbody></table></div></section>
@endsection
