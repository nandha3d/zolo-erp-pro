@php
    // Indian digit grouping: 1,23,456.78
    $inr = function ($n, int $d = 2, bool $symbol = true) {
        $neg = $n < 0; [$int, $dec] = array_pad(explode('.', number_format(abs((float) $n), $d, '.', '')), 2, '');
        $last = substr($int, -3); $rest = substr($int, 0, -3);
        if ($rest !== '') $rest = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest).',';
        return ($neg ? '−' : '').($symbol ? '₹' : '').$rest.$last.($d ? '.'.$dec : '');
    };
    $qty = fn ($n) => rtrim(rtrim(number_format((float) $n, 3, '.', ','), '0'), '.');
    $date = fn ($d) => $d ? \Carbon\Carbon::parse($d)->format('d M Y') : '—';
    $name = $company->trade_name ?: $company->legal_name;
    $maxMargin = max(1, collect($channels)->max('margin_pct'));
    $tb = ['dr' => collect($ledger)->where('net', '>', 0)->sum('net'), 'cr' => -collect($ledger)->where('net', '<', 0)->sum('net')];
@endphp
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sweet Soil End-to-End</title>
<link rel="stylesheet" href="{{ versioned_asset('css/pages/demo-sweet-soil-report.css') }}">
</head>
<body>
<header class="hero">
    <div class="wrap">
        @if($company->logo)<img src="../../public/{{ $company->logo }}" alt="">@endif
        <div>
            <h1>{{ $name }}: buy bulk → pack in-house → sell to 4 channels</h1>
            <p>{{ $company->legal_name }} · GSTIN {{ $gstin }} · {{ $company->city }}, Tamil Nadu</p>
            <p class="u-mt-6px"><span class="pill">Live data from zoloERP</span> <span class="pill accent">Demo company {{ $company->code }}</span></p>
        </div>
        <div class="meta">
            Generated {{ $generated_at }}<br>
            Refresh after app entries: <code>php artisan erp:demo-sweet-soil --report-only</code>
        </div>
    </div>
</header>

<nav class="toc"><div class="wrap">
    <a href="#flow">Flow</a><a href="#masters">1 · Masters</a><a href="#buy">2 · Buy</a><a href="#pack">3 · Pack &amp; label</a>
    <a href="#sell">4 · Sell</a><a href="#collect">5 · Collect &amp; pay</a><a href="#stock">6 · Stock &amp; batches</a>
    <a href="#books">7 · GST &amp; books</a><a href="#how">Do it in the app</a>
</div></nav>

<main class="wrap">
<section id="flow">
    <h2>The business in one line</h2>
    <p class="lead">The manufacturer supplies the product in bulk. Sweet Soil fills it into its own pouches and bottles, labels it (FSSAI, batch, MRP, expiry) and sells it to four kinds of buyer.
        Every number on this page is read from the posted documents, stock ledger and journals of the demo company.</p>
    <div class="flow">
        <div class="node"><small>Setup</small><strong>Masters</strong><em>{{ $masters['counts']['Items'] }} items · {{ $masters['counts']['Channels'] }} channels · {{ $masters['counts']['Packing BOMs'] }} packing BOMs</em></div>
        <div class="node"><small>Purchase</small><strong>Buy bulk + packing</strong><em>{{ count($purchases) }} bills · {{ $inr($kpi['bought'], 0) }}</em></div>
        <div class="node"><small>Production</small><strong>Pack &amp; label</strong><em>{{ $qty($kpi['packs']) }} packs &amp; hampers · {{ count($production) }} batches</em></div>
        <div class="node"><small>Sales</small><strong>Sell to 4 channels</strong><em>{{ count($sales) }} invoices · {{ $inr($kpi['revenue'], 0) }} + GST</em></div>
        <div class="node"><small>Cash</small><strong>Collect &amp; pay</strong><em>{{ count($payments) }} receipts / payments</em></div>
        <div class="node"><small>Control</small><strong>Stock, GST, books</strong><em>FEFO batches · trial balance {{ abs($tb['dr'] - $tb['cr']) < 0.05 ? 'balanced' : 'check' }}</em></div>
    </div>
    <div class="grid kpis">
        <div class="card kpi"><span>Bought (incl. GST)</span><b>{{ $inr($kpi['bought'], 0) }}</b></div>
        <div class="card kpi"><span>Sales (taxable)</span><b>{{ $inr($kpi['revenue'], 0) }}</b></div>
        <div class="card kpi"><span>Gross margin</span><b>{{ $inr($kpi['margin'], 0) }}</b><span class="u-tt-none">{{ $kpi['revenue'] ? round($kpi['margin'] / $kpi['revenue'] * 100, 1) : 0 }}% of sales</span></div>
        <div class="card kpi"><span>To collect</span><b>{{ $inr($kpi['receivable'], 0) }}</b></div>
        <div class="card kpi"><span>To pay</span><b>{{ $inr($kpi['payable'], 0) }}</b></div>
        <div class="card kpi"><span>Stock on hand</span><b>{{ $inr($kpi['stock_value'], 0) }}</b></div>
    </div>
    <div class="callout">
        <b>Why no job work challan here?</b> The manufacturer owns the raw material and sells you finished bulk product, so it is a normal <b>purchase</b>.
        Filling and labelling happen at your own unit, so it is an in-house <b>packing run (BOM)</b>, not job work.
        You would need a job work DC (Sec 143, ITC-04) only if you sent <i>your own</i> material (for example, your printed pouches) to someone else's premises.
    </div>
</section>

<section id="masters">
    <h2><span class="step">1</span>Masters: channels, parties, items, packing recipes</h2>
    <p class="lead">Each customer sits in a channel (customer group), so sales and margins can be read by channel. Finished goods are batch-tracked, so every pack carries a batch, expiry and MRP.</p>
    <div class="grid two">
        <div class="card table-scroll"><table>
            <thead><tr><th>Customer</th><th>Channel</th><th>GSTIN</th><th class="n">Credit</th></tr></thead>
            <tbody>@foreach($masters['customers'] as $c)<tr><td>{{ $c->name }}<div class="muted small">{{ $c->city }}, {{ $c->state }}</div></td><td><span class="pill">{{ $c->channel }}</span></td><td class="small">{{ $c->tax_no ?: 'Unregistered (B2C)' }}</td><td class="n">{{ $c->credit_days ? $c->credit_days.' days' : 'Prepaid' }}</td></tr>@endforeach</tbody>
        </table></div>
        <div class="card table-scroll"><table>
            <thead><tr><th>Supplier</th><th>Supplies</th><th>GSTIN</th><th class="n">Credit</th></tr></thead>
            <tbody>@foreach($masters['suppliers'] as $i => $s)<tr><td>{{ $s->name }}<div class="muted small">{{ $s->city }}</div></td><td class="small">{{ ['Bulk product + chocolates (manufacturer)', 'Pouches, bottles, gift boxes', 'Printed labels'][$i] ?? '' }}</td><td class="small">{{ $s->vat_number }}</td><td class="n">{{ $s->credit_days }} days</td></tr>@endforeach</tbody>
        </table></div>
    </div>
    <h3>Items</h3>
    <div class="card table-scroll"><table>
        <thead><tr><th>Code</th><th>Item</th><th>Category</th><th>Unit</th><th>HSN</th><th>GST</th><th>Batch</th><th class="n">MRP</th></tr></thead>
        <tbody>@foreach($masters['products'] as $p)<tr><td class="small">{{ $p->code }}</td><td>{{ $p->name }}</td><td class="muted small">{{ $p->category }}</td><td>{{ $p->unit }}</td><td class="small">{{ $p->hsn_code }}</td><td class="small">{{ str_replace('GST ', '', $p->gst) }}</td><td>{!! $p->is_batch ? '<span class="pill">Batch + expiry</span>' : '<span class="muted small">—</span>' !!}</td><td class="n">{{ (float) $p->price ? $inr($p->price, 0) : '—' }}</td></tr>@endforeach</tbody>
    </table></div>
    <h3>Packing recipes (BOMs)</h3>
    <div class="grid three">
        @foreach($boms as $b)
            <div class="card pad bom"><h4>{{ $qty($b['output_qty']) }} × {{ $b['name'] }}</h4><ul>
                @foreach($b['lines'] as $l)<li>{{ $qty($l->qty) }} {{ $l->unit }} {{ $l->name }}@if((float) $l->scrap_percent) <span class="small">(+{{ $qty($l->scrap_percent) }}% spillage)</span>@endif</li>@endforeach
            </ul></div>
        @endforeach
    </div>
</section>

<section id="buy">
    <h2><span class="step">2</span>Buy: bulk from the manufacturer, packing material from vendors</h2>
    <p class="lead">Three purchase bills. All suppliers are in Tamil Nadu, so input tax is CGST + SGST and is claimed as ITC. Stock goes into the main warehouse at cost before GST.</p>
    <div class="card table-scroll"><table>
        <thead><tr><th>Bill</th><th>Date</th><th>Supplier (their invoice)</th><th class="n">Taxable</th><th class="n">CGST</th><th class="n">SGST</th><th class="n">Total</th><th class="n">Paid</th><th class="n">Balance</th></tr></thead>
        <tbody>
        @foreach($purchases as $p)
            <tr><td><a href="{{ $app_url }}/purchases">{{ $p['number'] }}</a></td><td>{{ $date($p['date']) }}</td><td>{{ $p['party'] }} <span class="muted small">({{ $p['supplier_invoice'] }})</span></td>
                <td class="n">{{ $inr($p['taxable']) }}</td><td class="n">{{ $inr($p['cgst']) }}</td><td class="n">{{ $inr($p['sgst']) }}</td><td class="n">{{ $inr($p['total']) }}</td><td class="n">{{ $inr($p['paid']) }}</td>
                <td class="n">{!! $p['balance'] > 0 ? '<span class="pill warn">'.$inr($p['balance']).'</span>' : '<span class="ok">Paid</span>' !!}</td></tr>
            <tr><td colspan="9" class="u-p-0"><details><summary>{{ count($p['lines']) }} {{ Str::plural('line', count($p['lines'])) }}</summary><table>
                @foreach($p['lines'] as $l)<tr><td>{{ $l['name'] }}</td><td class="n">{{ $qty($l['qty']) }} {{ $l['unit'] }}</td><td class="n">@ {{ $inr($l['price']) }}</td><td class="n">{{ $inr($l['taxable_value']) }}</td><td class="n muted">GST {{ $qty($l['tax_rate']) }}%</td></tr>@endforeach
            </table></details></td></tr>
        @endforeach
        </tbody>
        <tfoot><tr><td colspan="3">Total</td><td class="n">{{ $inr(collect($purchases)->sum('taxable')) }}</td><td class="n">{{ $inr(collect($purchases)->sum('cgst')) }}</td><td class="n">{{ $inr(collect($purchases)->sum('sgst')) }}</td><td class="n">{{ $inr(collect($purchases)->sum('total')) }}</td><td class="n">{{ $inr(collect($purchases)->sum('paid')) }}</td><td class="n">{{ $inr(collect($purchases)->sum('balance')) }}</td></tr></tfoot>
    </table></div>
</section>

<section id="pack">
    <h2><span class="step">3</span>Pack &amp; label under the Sweet Soil brand</h2>
    <p class="lead">Each packing run uses up the bulk product, pouches/bottles and labels on its BOM, adds the packing labour, and books the branded packs into stock with a new batch, expiry and MRP.
        The pack cost is calculated by the ERP from what was actually used, not typed in. Hampers are packed from finished packs; their batches are picked first-expiry-first-out (FEFO).</p>
    <div class="legend"><span><i class="u-bg-brand"></i>Material (bulk + packing)</span><span><i class="u-bg-accent"></i>Packing labour</span></div>
    <div class="card table-scroll"><table>
        <thead><tr><th>Run</th><th>Date</th><th>Output</th><th>Batch</th><th>Expiry</th><th class="n">Packs</th><th class="n">Material</th><th class="n">Labour</th><th class="n">Cost / pack</th><th class="n">MRP</th><th>Cost build-up</th></tr></thead>
        <tbody>
        @foreach($production as $r)
            <tr><td><a href="{{ $app_url }}/operations/production/{{ $r['id'] }}">{{ $r['number'] }}</a></td><td>{{ $date($r['date']) }}</td><td>{{ $r['name'] }}</td><td class="small">{{ $r['batch'] }}</td><td class="small">{{ $date($r['expiry']) }}</td>
                <td class="n">{{ $qty($r['qty']) }}</td><td class="n">{{ $inr($r['material']) }}</td><td class="n">{{ $inr($r['labour']) }}</td><td class="n"><b>{{ $inr($r['unit_cost']) }}</b></td><td class="n">{{ $r['mrp'] ? $inr($r['mrp'], 0) : '—' }}</td>
                <td><div class="build" title="Material {{ $inr($r['material']) }} · Labour {{ $inr($r['labour']) }}"><i class="u-bg-brand" style="width:{{ $r['total_cost'] ? $r['material'] / $r['total_cost'] * 100 : 0 }}%"></i><i class="u-bg-accent" style="width:{{ $r['total_cost'] ? $r['labour'] / $r['total_cost'] * 100 : 0 }}%"></i></div></td></tr>
            <tr><td colspan="11" class="u-p-0"><details><summary>Consumed for {{ $r['batch'] }}</summary><table>
                @foreach($r['materials'] as $m)<tr><td>{{ $m['name'] }}</td><td class="n">{{ $qty($m['qty']) }} {{ $m['unit'] }}</td><td class="n">{{ $inr($m['value']) }}</td><td class="n muted">{{ $inr($m['value'] / max(1, $r['qty'])) }} per pack</td></tr>@endforeach
            </table></details></td></tr>
        @endforeach
        </tbody>
    </table></div>
</section>

<section id="sell">
    <h2><span class="step">4</span>Sell: wholesale, organic stores, direct customers, gifting companies</h2>
    <p class="lead">Same stock, four price levels. The distributor buys Palm Sugar by the <b>case of 50</b>. The organic store gets a <b>12 + 1 free</b> launch scheme, and the free packs post at zero value.
        The website customer pays MRP including GST. The Bengaluru gifting company is <b>inter-state, so IGST</b>; its hamper is a mixed supply taxed at the highest component rate (5%).
        The batch on every invoice line was picked by FEFO.</p>
    <h3>Channel profitability</h3>
    <div class="card table-scroll"><table>
        <thead><tr><th>Channel</th><th class="n">Invoices</th><th class="n">Sales (taxable)</th><th class="n">Cost of goods</th><th class="n">Gross margin</th><th>Margin %</th><th>GST</th><th class="n">Outstanding</th></tr></thead>
        <tbody>@foreach($channels as $ch)<tr><td><b>{{ $ch['channel'] }}</b></td><td class="n">{{ $ch['invoices'] }}</td><td class="n">{{ $inr($ch['revenue']) }}</td><td class="n">{{ $inr($ch['cogs']) }}</td><td class="n">{{ $inr($ch['margin']) }}</td>
            <td><div class="u-d-flex u-items-center u-gap-8px"><div class="bar u-flex-1"><i style="width:{{ max(0, $ch['margin_pct']) / $maxMargin * 100 }}%"></i></div><span class="small u-minw-42px u-ta-right">{{ $ch['margin_pct'] }}%</span></div></td>
            <td class="small">{{ $ch['gst'] }}</td><td class="n">{{ $ch['outstanding'] > 0 ? $inr($ch['outstanding']) : '—' }}</td></tr>@endforeach</tbody>
        <tfoot><tr><td>All channels</td><td class="n">{{ count($sales) }}</td><td class="n">{{ $inr($kpi['revenue']) }}</td><td class="n">{{ $inr($kpi['cogs']) }}</td><td class="n">{{ $inr($kpi['margin']) }}</td><td class="small">{{ $kpi['revenue'] ? round($kpi['margin'] / $kpi['revenue'] * 100, 1) : 0 }}%</td><td></td><td class="n">{{ $inr($kpi['receivable']) }}</td></tr></tfoot>
    </table></div>
    <h3>Invoices</h3>
    <div class="card table-scroll"><table>
        <thead><tr><th>Invoice</th><th>Date</th><th>Customer</th><th class="n">Taxable</th><th class="n">CGST</th><th class="n">SGST</th><th class="n">IGST</th><th class="n">Total</th><th>Due</th><th class="n">Balance</th></tr></thead>
        <tbody>
        @foreach($sales as $s)
            <tr><td><a href="{{ $app_url }}/sales">{{ $s['number'] }}</a></td><td>{{ $date($s['date']) }}</td><td>{{ $s['party'] }}<div><span class="pill">{{ $s['channel'] }}</span></div></td>
                <td class="n">{{ $inr($s['taxable']) }}</td><td class="n">{{ $inr($s['cgst']) }}</td><td class="n">{{ $inr($s['sgst']) }}</td><td class="n">{{ $inr($s['igst']) }}</td><td class="n">{{ $inr($s['total']) }}</td>
                <td class="small">{{ $date($s['due']) }}</td><td class="n">{!! $s['balance'] > 0 ? '<span class="pill warn">'.$inr($s['balance']).'</span>' : '<span class="ok">Paid</span>' !!}</td></tr>
            <tr><td colspan="10" class="u-p-0"><details><summary>{{ count($s['lines']) }} {{ Str::plural('line', count($s['lines'])) }} · margin {{ $inr($s['margin']) }} on cost {{ $inr($s['cogs']) }}</summary><table>
                @foreach($s['lines'] as $l)<tr><td>{{ $l['name'] }}@if((float) $l['price'] === 0.0) <span class="pill accent">Free (scheme)</span>@endif</td><td class="small muted">{{ $l['hsn_sac'] }}</td><td class="n">{{ $qty($l['qty']) }} {{ $l['unit'] }}</td><td class="n">@ {{ $inr($l['price']) }}</td><td class="n">{{ $inr($l['taxable_value']) }}</td><td class="n muted">GST {{ $qty($l['tax_rate']) }}%</td></tr>@endforeach
            </table></details></td></tr>
        @endforeach
        </tbody>
        <tfoot><tr><td colspan="3">Total</td><td class="n">{{ $inr($kpi['revenue']) }}</td><td class="n">{{ $inr(collect($sales)->sum('cgst')) }}</td><td class="n">{{ $inr(collect($sales)->sum('sgst')) }}</td><td class="n">{{ $inr(collect($sales)->sum('igst')) }}</td><td class="n">{{ $inr(collect($sales)->sum('total')) }}</td><td></td><td class="n">{{ $inr($kpi['receivable']) }}</td></tr></tfoot>
    </table></div>
</section>

<section id="collect">
    <h2><span class="step">5</span>Collect &amp; pay</h2>
    <p class="lead">Receipts are matched against invoices and payments against bills, so whatever is still open shows up as an outstanding with its due date (from the party's credit days). UPI and NEFT post through the Bank account.</p>
    <div class="grid two">
        <div class="card table-scroll"><table>
            <thead><tr><th>Voucher</th><th>Date</th><th>Against</th><th>Mode</th><th class="n">Amount</th></tr></thead>
            <tbody>@foreach($payments as $y)<tr><td class="small">{{ $y->payment_reference }}</td><td class="small">{{ $date($y->payment_at) }}</td>
                <td>{!! $y->sale_id ? '<span class="pill">In</span> ' : '<span class="pill accent">Out</span> ' !!}{{ $y->customer ?? $y->supplier }}<div class="muted small">{{ $y->sale_no ?? $y->purchase_no }}</div></td>
                <td class="small">{{ $y->paying_method }}<div class="muted">{{ $y->payment_note }}</div></td><td class="n">{{ $inr($y->amount) }}</td></tr>@endforeach</tbody>
        </table></div>
        <div class="card table-scroll"><table>
            <thead><tr><th>Outstanding</th><th>Document</th><th>Due</th><th class="n">Open</th></tr></thead>
            <tbody>@forelse($open_items as $o)<tr><td>{!! $o['party_type'] === 'customer' ? '<span class="pill">To collect</span>' : '<span class="pill accent">To pay</span>' !!}<div>{{ $o['party'] }}</div></td>
                <td class="small">{{ $o['document_no'] }}<div class="muted">of {{ $inr($o['original_amount']) }}</div></td>
                <td class="small">{{ $date($o['due_date']) }}@if($o['overdue']) <span class="pill bad">Overdue</span>@endif</td><td class="n">{{ $inr($o['open_amount']) }}</td></tr>@empty<tr><td colspan="4" class="muted">Nothing outstanding.</td></tr>@endforelse</tbody>
        </table></div>
    </div>
</section>

<section id="stock">
    <h2><span class="step">6</span>Stock &amp; batches</h2>
    <p class="lead">Bulk product and packing material go down as packs are made; packs go down as they are sold. Each batch shows how many days of shelf life it has left, so slow stock can be pushed before it expires.</p>
    <h3>Batches (FEFO order)</h3>
    <div class="card table-scroll"><table>
        <thead><tr><th>Batch</th><th>Product</th><th>Packed</th><th>Expiry</th><th class="n">MRP</th><th class="n">In stock</th><th>Shelf life left</th></tr></thead>
        <tbody>@foreach($batches as $b)<tr><td class="small">{{ $b['batch_no'] }}</td><td>{{ $b['name'] }}</td><td class="small">{{ $date($b['mfg_date']) }}</td><td class="small">{{ $date($b['expired_date']) }}</td><td class="n">{{ $b['mrp'] ? $inr($b['mrp'], 0) : '—' }}</td><td class="n">{{ $qty($b['qty']) }}</td>
            <td>@if($b['days_left'] < 0)<span class="pill bad">Expired</span>@elseif($b['days_left'] <= 90)<span class="pill warn">{{ $b['days_left'] }} days</span>@else<span class="pill">{{ $b['days_left'] }} days</span>@endif</td></tr>@endforeach</tbody>
    </table></div>
    <h3>Stock summary</h3>
    <div class="card table-scroll"><table>
        <thead><tr><th>Item</th><th>Category</th><th class="n">In</th><th class="n">Out</th><th class="n">Closing</th><th class="n">Value</th></tr></thead>
        <tbody>@foreach($stock as $s)<tr><td>{{ $s->name }} <span class="muted small">{{ $s->code }}</span></td><td class="muted small">{{ $s->category }}</td><td class="n">{{ $qty($s->qty_in) }}</td><td class="n">{{ $qty($s->qty_out) }}</td><td class="n"><b>{{ $qty($s->qty) }}</b> {{ $s->unit }}</td><td class="n">{{ $inr($s->value) }}</td></tr>@endforeach</tbody>
        <tfoot><tr><td colspan="5">Closing stock value</td><td class="n">{{ $inr($kpi['stock_value']) }}</td></tr></tfoot>
    </table></div>
</section>

<section id="books">
    <h2><span class="step">7</span>GST &amp; books</h2>
    <p class="lead">Nobody keyed in a journal: every bill, packing run, invoice and receipt posted its own entry. The packing run moves value from bulk and packing material into finished goods, and adds the labour.</p>
    <div class="grid two">
        <div class="card table-scroll"><table>
            <thead><tr><th>GST for the period</th><th class="n">CGST</th><th class="n">SGST</th><th class="n">IGST</th><th class="n">Total</th></tr></thead>
            <tbody>
                <tr><td>Output tax (on sales)</td>@foreach($gst['output_split'] as $v)<td class="n">{{ $inr($v) }}</td>@endforeach<td class="n">{{ $inr($gst['output']) }}</td></tr>
                <tr><td>Less input tax credit (on purchases)</td>@foreach($gst['input_split'] as $v)<td class="n">{{ $inr($v) }}</td>@endforeach<td class="n">{{ $inr($gst['input']) }}</td></tr>
            </tbody>
            <tfoot><tr><td colspan="4">{{ $gst['payable'] >= 0 ? 'Net GST payable' : 'ITC carried forward' }}</td><td class="n">{{ $inr(abs($gst['payable'])) }}</td></tr></tfoot>
        </table>
        <p class="pad small muted u-m-0">IGST output is set off first against IGST credit, then CGST and SGST credit (Sec 49). Check the final GSTR-3B figures in Compliance › GST report.</p></div>
        <div class="card table-scroll"><table>
            <thead><tr><th>Trial balance</th><th class="n">Debit</th><th class="n">Credit</th></tr></thead>
            <tbody>@foreach($ledger as $a)<tr><td>{{ $a['name'] }} <span class="muted small">{{ $a['code'] }}</span></td><td class="n">{{ $a['net'] > 0 ? $inr($a['net']) : '' }}</td><td class="n">{{ $a['net'] < 0 ? $inr(-$a['net']) : '' }}</td></tr>@endforeach</tbody>
            <tfoot><tr><td>Total {!! abs($tb['dr'] - $tb['cr']) < 0.05 ? '<span class="ok">✓ balanced</span>' : '<span class="pill bad">difference '.$inr($tb['dr'] - $tb['cr']).'</span>' !!}</td><td class="n">{{ $inr($tb['dr']) }}</td><td class="n">{{ $inr($tb['cr']) }}</td></tr></tfoot>
        </table></div>
    </div>
</section>

<section id="how">
    <h2>Do it yourself in zoloERP</h2>
    <p class="lead">Open the picker at <a href="{{ $app_url }}/select-company">{{ $app_url }}/select-company</a> and choose <b>{{ $company->legal_name }}</b>. The screens below hold the same documents. To run the next cycle, follow the same order.</p>
    <ol class="steps">
        <li><span class="path">Masters › Customers / Suppliers</span>: give every customer a channel (customer group) and credit days, and enter the GSTIN (state comes from it). <a href="{{ $app_url }}/customer">Open</a></li>
        <li><span class="path">Masters › Items</span>: one item per bulk product and packing material, plus each branded pack (batch-tracked, MRP, HSN, GST). Add a CASE unit (×50) for distributor packs. <a href="{{ $app_url }}/products">Open</a></li>
        <li><span class="path">Operations › Manufacturing › BOM</span>: one packing recipe per pack size (bulk kg + pouch + label, spillage %). <a href="{{ $app_url }}/operations/manufacturing">Open</a></li>
        <li><span class="path">Purchases › New bill (F2)</span>: the manufacturer's bulk invoice and the packing vendors' invoices, with their invoice numbers. <a href="{{ $app_url }}/purchases">Open</a></li>
        <li><span class="path">Operations › Manufacturing › Production</span>: plan the run, then complete it with the packed quantity, labour cost, batch no., mfg/expiry date and MRP. <a href="{{ $app_url }}/operations/manufacturing">Open</a></li>
        <li><span class="path">Sales › New invoice (F12)</span>: pick the customer and the channel price; batches fill in by FEFO; choose a scheme for free goods; IGST applies on its own for out-of-state buyers. <a href="{{ $app_url }}/sales">Open</a></li>
        <li><span class="path">Add payment on the invoice / bill</span>: record UPI/NEFT as Bank and cheques as Cheque, with the reference in the note.</li>
        <li><span class="path">Reports</span>: <a href="{{ $app_url }}/report/product-expiry">Product expiry</a> · <a href="{{ $app_url }}/compliance/gst/report">GST report</a> · <a href="{{ $app_url }}/accounting/trial-balance">Trial balance</a>. Then rerun <code>php artisan erp:demo-sweet-soil --report-only</code> to refresh this page.</li>
    </ol>
</section>
</main>
<footer class="wrap">
    Demo data only: GSTINs are checksum-valid samples, not real registrations, and GST rates are illustrative, so confirm HSN rates with your CA. Your live company <b>SWEETSOIL</b> was not touched.
    Generated by <code>erp:demo-sweet-soil</code> from company #{{ $company->id }} on {{ $generated_at }}.
</footer>
</body>
</html>
