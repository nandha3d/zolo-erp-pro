@extends('backend.compliance.layout')
@section('title','GST review')
@section('compliance_body')
@php
    $inr = function ($n) {
        $neg = $n < 0; [$int, $dec] = explode('.', number_format(abs((float) $n), 2, '.', ''));
        $last = substr($int, -3); $rest = substr($int, 0, -3);
        if ($rest !== '') $rest = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest).',';
        return ($neg ? '−' : '').'₹'.$rest.$last.'.'.$dec;
    };
    $sectionNames = ['b2b' => ['B2B invoices', 'GSTR-1 4A'], 'b2c' => ['B2C invoices', 'GSTR-1 7'],
        'cdnr' => ['Notes, registered', 'GSTR-1 9B'], 'cdnur' => ['Notes, unregistered', 'GSTR-1 9B'],
        'inward' => ['Inward supplies (ITC)', 'GSTR-3B 4A']];
    $tax = fn ($r) => $r['cgst'] + $r['sgst'] + $r['igst'] + $r['cess'];
@endphp
<link rel="stylesheet" href="{{ versioned_asset('css/pages/compliance-report.css') }}">

<div class="gst-review">
    <div class="gst-top">
        <form method="GET" class="cx-filter" data-enter-flow>
            <div class="cx-field"><label for="report-from">From</label><input id="report-from" name="from" type="date" value="{{ $from }}" required></div>
            <div class="cx-field"><label for="report-to">To</label><input id="report-to" name="to" type="date" value="{{ $to }}" required></div>
            <button type="submit" class="cx-btn"><i class="dripicons-search"></i> Review period</button>
            <a class="cx-btn ghost" href="{{ url('/compliance/gst/export?'.http_build_query(['from' => $from, 'to' => $to, 'version' => 'zolo-gst-review-v1'])) }}"><i class="dripicons-download"></i> Review JSON</a>
        </form>
        <p class="cx-lead mb-0">Posted invoices, bills and notes as they will be reported. Check before filing; the JSON is for your CA, not a portal upload.</p>
    </div>

    <div class="cx-kpis">
        <div class="cx-kpi"><span>Outward taxable</span><b>{{ $inr($kpi['outward_taxable']) }}</b></div>
        <div class="cx-kpi"><span>Output tax</span><b>{{ $inr($kpi['output_tax']) }}</b></div>
        <div class="cx-kpi"><span>Inward taxable</span><b>{{ $inr($kpi['inward_taxable']) }}</b></div>
        <div class="cx-kpi"><span>Input tax credit</span><b>{{ $inr($kpi['input_tax']) }}</b></div>
        <div class="cx-kpi accent"><span>{{ $kpi['net'] >= 0 ? 'Net tax payable' : 'ITC carried forward' }}</span><b>{{ $inr(abs($kpi['net'])) }}</b></div>
    </div>

    <div class="gst-body">
        <div class="gst-col">
            <div>
                <h3>By return section</h3>
                <div class="cx-table-wrap"><table class="cx-table">
                    <thead><tr><th>Section</th><th class="n">Docs</th><th class="n">Taxable</th><th class="n">CGST</th><th class="n">SGST</th><th class="n">IGST</th></tr></thead>
                    <tbody>
                    @foreach($sectionNames as $key => [$name, $where])
                        @continue(!isset($sections[$key]))
                        <tr><td><b>{{ $name }}</b> <span class="text-muted small">{{ $where }}</span></td><td class="n">{{ $sections[$key]['count'] }}</td>
                            <td class="n">{{ $inr($sections[$key]['taxable_value']) }}</td><td class="n">{{ $inr($sections[$key]['cgst']) }}</td>
                            <td class="n">{{ $inr($sections[$key]['sgst']) }}</td><td class="n">{{ $inr($sections[$key]['igst']) }}</td></tr>
                    @endforeach
                    @if($sections->isEmpty())<tr><td colspan="6" class="text-muted text-center py-3">No posted GST documents in this period.</td></tr>@endif
                    </tbody>
                </table></div>
            </div>
            <div class="grow">
                <h3>HSN summary, outward <span class="text-muted font-weight-normal">(GSTR-1 table 12)</span></h3>
                <div class="cx-table-wrap"><table class="cx-table">
                    <thead><tr><th>HSN / SAC</th><th class="n">Rate</th><th class="n">Taxable</th><th class="n">Tax</th></tr></thead>
                    <tbody>
                    @forelse($hsn as $h)
                        <tr><td>{{ $h['hsn'] }}</td><td class="n">{{ rtrim(rtrim(number_format($h['rate'], 2), '0'), '.') }}%</td><td class="n">{{ $inr($h['taxable_value']) }}</td><td class="n">{{ $inr($tax($h)) }}</td></tr>
                    @empty
                        <tr><td colspan="4" class="text-muted text-center py-3">No outward supplies.</td></tr>
                    @endforelse
                    </tbody>
                    @if($hsn->isNotEmpty())<tfoot><tr><td colspan="2">Total</td><td class="n">{{ $inr($hsn->sum('taxable_value')) }}</td>
                        <td class="n">{{ $inr($hsn->sum('cgst') + $hsn->sum('sgst') + $hsn->sum('igst') + $hsn->sum('cess')) }}</td></tr></tfoot>@endif
                </table></div>
            </div>
        </div>

        <div class="gst-col">
            <div class="grow">
                <h3>Documents ({{ count($documents) }})</h3>
                <div class="cx-table-wrap"><table class="cx-table">
                    <thead><tr><th>Date / document</th><th>Sec.</th><th>Party / GSTIN</th><th>Place</th><th class="n">Taxable</th><th class="n">Tax</th><th class="n">Total</th></tr></thead>
                    <tbody>
                    @forelse($documents as $row)
                        <tr title="@foreach($row['snapshot']['lines'] ?? [] as $line){{ $line['hsn_sac'] }} @ {{ $line['rate'] }}%: taxable {{ $line['taxable_value'] }}&#10;@endforeach">
                            <td class="text-nowrap"><b>{{ $row['document_no'] }}</b><div class="text-muted small">{{ \Carbon\Carbon::parse($row['document_date'])->format('d M Y') }} · {{ str_replace('_', ' ', $row['source_type']) }}</div></td>
                            <td><span class="cx-pill {{ $row['section'] === 'inward' ? 'muted' : '' }}">{{ strtoupper($row['section']) }}</span></td>
                            <td>{{ $row['party'] ?: '—' }}<div class="text-muted small">{{ $row['party_gstin'] ?: 'Unregistered' }}</div></td>
                            <td class="small">{{ $row['place'] ? (\App\Services\Tax\Gstin::$states[$row['place']] ?? $row['place']) : '—' }}</td>
                            <td class="n">{{ $inr($row['taxable_value']) }}</td>
                            <td class="n">{{ $inr($tax($row)) }}<div class="text-muted small">{{ $row['igst'] ? 'IGST' : 'CGST + SGST' }}</div></td>
                            <td class="n"><b>{{ $inr($row['total']) }}</b></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-muted text-center py-3">No posted GST documents in this period.</td></tr>
                    @endforelse
                    </tbody>
                </table></div>
            </div>
        </div>
    </div>
</div>
@endsection
