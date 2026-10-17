{{-- One job work workspace: the order is the parent; Material DC goes out, Job Work GRN comes back, the service bill closes it.
     Returns, rework and reversals are actions on the document they belong to, not separate modules. --}}
@php
    $dcLabel = \App\Services\Industry\IndustryProfileService::label('dispatch', 'Material DC');
    $grnLabel = \App\Services\Industry\IndustryProfileService::label('receipt', 'Job Work GRN');
    $process = $record->details_json['process'];
    $conversion = (bool) $process['conversion'];
    $outputDefault = $route->output_product_id ?? null;
    $receiptRefs = $receipts->pluck('reference_no', 'id');
    $dcRefs = $dispatches->pluck('reference_no', 'id');
    $activeDispatches = $dispatches->whereNull('reversed_at');
    $activeReceipts = $receipts->whereNull('reversed_at');
    $lineOutside = fn ($line) => max(0, round((float) $line->qty_base - (float) ($settledQty[$line->id] ?? 0), 4));
    $outside = $activeDispatches->sum(fn ($d) => $dispatchLines->get($d->id, collect())->sum($lineOutside));
    $sent = $activeDispatches->sum(fn ($d) => (float) $dispatchLines->get($d->id, collect())->sum('qty_base'));
    $lost = $activeReceipts->sum(fn ($r) => (float) ($r->details_json['loss_qty'] ?? 0));
    $back = $activeReceipts->sum(fn ($r) => (float) ($r->details_json['received_qty'] ?? 0));
    $unbilled = $activeReceipts->filter(fn ($r) => !$r->service_purchase_id && ($r->receipt_kind ?? 'process') !== 'material_return')->count();
    $billed = $activeReceipts->whereNotNull('service_purchase_id')->count();
    $openPanel = old('form_id');
    $qty = fn ($value) => rtrim(rtrim(number_format((float) $value, 3, '.', ''), '0'), '.');
@endphp

<div class="jw-workspace" @if($openPanel) data-open-form="{{ $openPanel }}" @endif>
<section class="ops-panel">
    <div class="ops-metrics">
        <div><span>Job worker</span><strong>{{ $jobWorker->name ?? '—' }}</strong></div>
        <div><span>Process</span><strong>{{ $process['name'] }}</strong></div>
        <div><span>Comes back as</span><strong>{{ $conversion ? (optional($products->firstWhere('id', $outputDefault))->name ?? 'a new item') : 'the same item' }}</strong></div>
        <div><span>Maximum loss</span><strong>{{ (float) $process['max_loss_percent'] }}%</strong></div>
        <div><span>Expected return</span><strong>{{ $record->expected_return_date ?? 'Not set' }}</strong></div>
    </div>
    <ol class="jw-flow" aria-label="Job work progress">
        <li class="done"><strong>Job Work Order</strong><span>{{ $record->reference_no }}</span></li>
        <li class="{{ $activeDispatches->count() ? 'done' : 'current' }}"><strong>{{ $dcLabel }}</strong><span>{{ $activeDispatches->count() }} sent · {{ $qty($sent) }} qty</span></li>
        <li class="{{ $activeReceipts->count() ? ($outside > 0 ? 'current' : 'done') : '' }}"><strong>{{ $grnLabel }}</strong><span>{{ $qty($back) }} back · {{ $qty($lost) }} loss · <b>{{ $qty($outside) }} outside</b></span></li>
        <li class="{{ $billed && !$unbilled ? 'done' : ($unbilled ? 'current' : '') }}"><strong>Service Bill</strong><span>{{ $billed }} billed · {{ $unbilled }} to bill</span></li>
    </ol>
    <p>Material stays company stock at the job worker until it is received back or booked as loss. The job worker's bill is added to the cost of what comes back.</p>
</section>

{{-- ===================================================================== Material DC --}}
<section class="ops-panel">
    <div class="jw-section-head">
        <h2>{{ $dcLabel }}</h2>
        <button type="button" class="ops-primary jw-toggle" data-jw-toggle="new-dc" aria-expanded="false">+ New {{ $dcLabel }}</button>
    </div>
    <div class="jw-panel" id="new-dc" hidden>
        @include('backend.operations.form_start',['formId'=>'dispatch-form','action'=>'job-dispatch/'.$record->id])
        <div class="ops-fields">@include('backend.operations.warehouse')@include('backend.operations.date')<label>Vehicle no.<input name="transport[vehicle_number]" maxlength="30" placeholder="TN39 AB 1234"></label><label>Transporter<input name="transport[transporter_name]" maxlength="100"></label><label>E-way bill no.<input name="transport[eway_bill_no]" maxlength="30"></label><label>Challan note<input name="transport[notes]" maxlength="500" placeholder="Shade, lot or process instructions"></label></div>
        <div class="ops-table-wrap"><table data-lines><thead><tr><th>Product</th><th>Quantity</th><th>Unit</th><th>Tracking</th><th></th></tr></thead><tbody>@include('backend.operations.stock_line',['prefix'=>'lines[0]'])</tbody></table></div>
        <button type="button" data-add-line>Add material</button><button class="ops-primary">Send material</button></form>
    </div>

    <div class="ops-table-wrap">
    <table class="jw-register">
        <thead><tr><th>{{ $dcLabel }}</th><th>Date</th><th>Type</th><th class="num">Sent</th><th class="num">Outside</th><th>Status</th><th class="jw-actions-col">Actions</th></tr></thead>
        <tbody>
        @forelse($dispatches as $dispatch)
            @php
                $lines = $dispatchLines->get($dispatch->id, collect());
                $dcOutside = $lines->sum($lineOutside);
                $dcReceipts = $receipts->where('dispatch_id', $dispatch->id);
                $hasActiveReceipts = $dcReceipts->whereNull('reversed_at')->isNotEmpty();
                $isRework = ($dispatch->purpose ?? 'process') === 'rework';
                $status = $dispatch->reversed_at ? 'Reversed' : ($dcOutside <= 0 ? 'Settled' : ($hasActiveReceipts ? 'Part received' : 'At job worker'));
            @endphp
            <tr class="{{ $dispatch->reversed_at ? 'jw-muted' : '' }}">
                <td><strong>{{ $dispatch->reference_no }}</strong></td>
                <td>{{ $dispatch->business_date }}</td>
                <td>{{ $isRework ? 'Rework of '.($receiptRefs[$dispatch->rework_of_receipt_id] ?? 'GRN') : 'Process' }}</td>
                <td class="num">{{ $qty($lines->sum('qty_base')) }}</td>
                <td class="num">{{ $dispatch->reversed_at ? '—' : $qty($dcOutside) }}</td>
                <td><span class="ops-badge jw-status-{{ \Illuminate\Support\Str::slug($status) }}">{{ $status }}</span></td>
                <td class="jw-actions">
                    <button type="button" class="jw-toggle" data-jw-toggle="dc-{{ $dispatch->id }}-view">View</button>
                    <a class="jw-action-link" href="{{ url('/operations/dispatch/'.$dispatch->id.'/print') }}" target="_blank" rel="noopener" title="Print job work challan (GST)">Print</a>
                    @if(!$dispatch->reversed_at && $dcOutside > 0)
                        <button type="button" class="jw-toggle jw-main" data-jw-toggle="dc-{{ $dispatch->id }}-receive">Receive {{ $grnLabel }}</button>
                        <button type="button" class="jw-toggle" data-jw-toggle="dc-{{ $dispatch->id }}-return">Return material</button>
                    @endif
                    @if(!$dispatch->reversed_at && !$hasActiveReceipts)
                        <button type="button" class="jw-toggle jw-danger" data-jw-toggle="dc-{{ $dispatch->id }}-reverse">Reverse</button>
                    @endif
                </td>
            </tr>
            <tr class="jw-panel-row"><td colspan="7">
                <div class="jw-panel" id="dc-{{ $dispatch->id }}-view" hidden>
                    <p>{{ $dispatch->business_date }} · <a href="{{ url('/operations/dispatch/'.$dispatch->id.'/print') }}" target="_blank" rel="noopener">Print job work challan (GST)</a>@if(in_array('printing.dot_matrix',$navigation)) · <a href="{{ url('/operations/dispatch/'.$dispatch->id.'/print?format=dot_matrix') }}" target="_blank" rel="noopener">68-line output</a>@endif
                    @if(!empty($dispatch->details_json['transport'])) · {{ implode(' · ', array_filter([$dispatch->details_json['transport']['vehicle_number'] ?? null, $dispatch->details_json['transport']['transporter_name'] ?? null, isset($dispatch->details_json['transport']['eway_bill_no']) ? 'E-way '.$dispatch->details_json['transport']['eway_bill_no'] : null])) }}@endif</p>
                    <table><thead><tr><th>Material</th><th class="num">Sent</th><th class="num">Settled</th><th class="num">Outside</th></tr></thead><tbody>
                    @foreach($lines as $line)<tr><td>{{ $line->name }}</td><td class="num">{{ $qty($line->qty_base) }}</td><td class="num">{{ $qty($settledQty[$line->id] ?? 0) }}</td><td class="num">{{ $qty($lineOutside($line)) }}</td></tr>@endforeach
                    </tbody></table>
                    @if($dcReceipts->isNotEmpty())<p>{{ $grnLabel }}: {{ $dcReceipts->map(fn ($r) => $r->reference_no.($r->reversed_at ? ' (reversed)' : ''))->implode(', ') }}</p>@endif
                    @if($dispatch->reversed_at)<p>Dispatch reversed: {{ $dispatch->reversal_reason }}</p>@endif
                </div>

                @if(!$dispatch->reversed_at && $dcOutside > 0)
                <div class="jw-panel" id="dc-{{ $dispatch->id }}-receive" hidden>
                    @include('backend.operations.form_start',['formId'=>'receive-'.$dispatch->id,'action'=>'job-receive/'.$dispatch->id])
                    <h3>{{ $grnLabel }} against {{ $dispatch->reference_no }}</h3>
                    <div class="ops-fields">@include('backend.operations.warehouse',['warehouseLabel'=>'Receiving warehouse','selectedWarehouse'=>$dispatch->warehouse_id])@include('backend.operations.date')<label>Quarantine warehouse<select name="rejected_warehouse_id"><option value="">Required for rejected material</option>@foreach($warehouses as $warehouse)<option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>@endforeach</select></label></div>
                    <div class="ops-table-wrap"><table><thead><tr><th>Material / outside</th><th>Accepted</th><th>Rejected at receipt</th><th>Loss</th></tr></thead><tbody>
                    @foreach($lines as $i=>$line)<tr><td>{{ $line->name }} · {{ $qty($lineOutside($line)) }}<input type="hidden" name="lines[{{ $i }}][dispatch_line_id]" value="{{ $line->id }}"></td><td><label>Accepted · {{ $line->name }}<input name="lines[{{ $i }}][accepted_qty]" type="number" min="0" max="{{ $lineOutside($line) }}" step="0.0001" value="{{ $lineOutside($line) }}" required></label></td><td><label>Rejected · {{ $line->name }}<input name="lines[{{ $i }}][rejected_qty]" type="number" min="0" step="0.0001" value="0" required></label></td><td><label>Loss · {{ $line->name }}<input name="lines[{{ $i }}][loss_qty]" type="number" min="0" step="0.0001" value="0" required></label></td></tr>@endforeach
                    </tbody></table></div>
                    @if($conversion && !$isRework)<h3>Converted outputs</h3><div class="ops-table-wrap"><table data-lines><thead><tr><th>Product</th><th>Quantity / cost weight</th><th>Unit</th><th>Tracking</th><th></th></tr></thead><tbody>@include('backend.operations.stock_line',['prefix'=>'outputs[0]','withWeight'=>true,'defaultProduct'=>$outputDefault ? (int) $outputDefault : null])</tbody></table></div><button type="button" data-add-line>Add output</button>
                    @elseif($conversion && $isRework)<section><p>Reworked material comes back as the same item, unless it was sent unprocessed and now comes back converted.</p><label><input type="checkbox" data-optional-lines> Comes back converted (e.g. rejected grey now dyed)</label><fieldset data-optional-group hidden disabled><h3>Converted outputs</h3><div class="ops-table-wrap"><table data-lines><thead><tr><th>Product</th><th>Quantity / cost weight</th><th>Unit</th><th>Tracking</th><th></th></tr></thead><tbody>@include('backend.operations.stock_line',['prefix'=>'outputs[0]','withWeight'=>true,'defaultProduct'=>$outputDefault ? (int) $outputDefault : null])</tbody></table></div><button type="button" data-add-line>Add output</button></fieldset></section>
                    @elseif($isRework)<p>Rework comes back as the same item that was sent.</p>@endif
                    <button class="ops-primary">Receive and reconcile</button></form>
                </div>

                <div class="jw-panel" id="dc-{{ $dispatch->id }}-return" hidden>
                    @include('backend.operations.form_start',['formId'=>'return-'.$dispatch->id,'action'=>'job-return/'.$dispatch->id])
                    <h3>Return material from {{ $dispatch->reference_no }}</h3>
                    <p>The job worker sends material back unprocessed, as the same item. No loss check and no processing charge.</p>
                    <div class="ops-fields">@include('backend.operations.warehouse',['warehouseLabel'=>'Return into warehouse','selectedWarehouse'=>$dispatch->warehouse_id])@include('backend.operations.date')</div>
                    <div class="ops-table-wrap"><table><thead><tr><th>Material / outside</th><th>Returned unprocessed</th></tr></thead><tbody>
                    @foreach($lines as $i=>$line)<tr><td>{{ $line->name }} · {{ $qty($lineOutside($line)) }}<input type="hidden" name="lines[{{ $i }}][dispatch_line_id]" value="{{ $line->id }}"></td><td><label>Returned · {{ $line->name }}<input name="lines[{{ $i }}][returned_qty]" type="number" min="0" max="{{ $lineOutside($line) }}" step="0.0001" value="0" required></label></td></tr>@endforeach
                    </tbody></table></div>
                    <button class="ops-primary">Return material</button></form>
                </div>
                @endif

                @if(!$dispatch->reversed_at && !$hasActiveReceipts)
                <div class="jw-panel" id="dc-{{ $dispatch->id }}-reverse" hidden>
                    @include('backend.operations.form_start',['formId'=>'dispatch-reverse-'.$dispatch->id,'action'=>'job-reverse-dispatch/'.$dispatch->id])
                    <h3>Reverse {{ $dispatch->reference_no }}</h3><p>Brings the material back into the warehouse it was sent from. Use it only when the DC was wrong.</p>
                    <div class="ops-fields">@include('backend.operations.date')<label>Reason<input name="reason" required maxlength="500"></label></div><button type="submit">Post reversal</button></form>
                </div>
                @endif
            </td></tr>
        @empty
            <tr><td colspan="7" class="jw-empty">No {{ $dcLabel }} yet. Use <strong>+ New {{ $dcLabel }}</strong> to send material to {{ $jobWorker->name ?? 'the job worker' }}.</td></tr>
        @endforelse
        </tbody>
    </table>
    </div>
</section>

{{-- ===================================================================== Job Work GRN --}}
<section class="ops-panel">
    <div class="jw-section-head"><h2>{{ $grnLabel }}</h2></div>
    <div class="ops-table-wrap">
    <table class="jw-register">
        <thead><tr><th>{{ $grnLabel }}</th><th>Date</th><th>Against</th><th>Type</th><th class="num">Accepted</th><th class="num">Rejected</th><th class="num">Loss</th><th>Service bill</th><th class="jw-actions-col">Actions</th></tr></thead>
        <tbody>
        @forelse($receipts as $receipt)
            @php
                $rLines = $receiptLines->get($receipt->id, collect());
                $kind = $receipt->receipt_kind ?? 'process';
                $fromRework = ($dispatches->firstWhere('id', $receipt->dispatch_id)->purpose ?? 'process') === 'rework';
                $kindLabel = $kind === 'material_return' ? 'Returned unprocessed' : ($fromRework ? 'Rework received' : 'Processed');
                $reworkDcs = $dispatches->where('rework_of_receipt_id', $receipt->id);
                $billable = !$receipt->reversed_at && !$receipt->service_purchase_id && $kind !== 'material_return';
                $rejectedLines = $rLines->where('rejected_qty', '>', 0)->values();
                $cost = $receipt->details_json['processing_cost'] ?? null;
            @endphp
            <tr class="{{ $receipt->reversed_at ? 'jw-muted' : '' }}">
                <td><strong>{{ $receipt->reference_no }}</strong></td>
                <td>{{ $receipt->business_date }}</td>
                <td>{{ $dcRefs[$receipt->dispatch_id] ?? '' }}</td>
                <td>{{ $kindLabel }}@if($receipt->reversed_at) · <em>reversed</em>@endif</td>
                <td class="num">{{ $qty($rLines->sum('accepted_qty')) }}</td>
                <td class="num">{{ $qty($rLines->sum('rejected_qty')) }}</td>
                <td class="num">{{ $qty($rLines->sum('loss_qty')) }}@if((float) ($receipt->details_json['loss_qty'] ?? 0) > 0) ({{ round((float) $receipt->details_json['loss_percent'], 2) }}%)@endif</td>
                <td>@if($receipt->service_purchase_id){{ $billNumbers[$receipt->service_purchase_id] ?? ('#'.$receipt->service_purchase_id) }}@elseif($kind === 'material_return')—@else<span class="ops-badge jw-status-at-job-worker">To bill</span>@endif</td>
                <td class="jw-actions">
                    <button type="button" class="jw-toggle" data-jw-toggle="grn-{{ $receipt->id }}-view">View</button>
                    @if($billable)<button type="button" class="jw-toggle jw-main" data-jw-toggle="grn-{{ $receipt->id }}-bill">Service bill</button>@endif
                    @if(!$receipt->reversed_at && $kind !== 'material_return')<button type="button" class="jw-toggle" data-jw-toggle="grn-{{ $receipt->id }}-rework">Return / Send for rework</button>@endif
                    @if(!$receipt->reversed_at)<button type="button" class="jw-toggle jw-danger" data-jw-toggle="grn-{{ $receipt->id }}-reverse">Reverse</button>@endif
                </td>
            </tr>
            <tr class="jw-panel-row"><td colspan="9">
                <div class="jw-panel" id="grn-{{ $receipt->id }}-view" hidden>
                    <p>Received {{ $receipt->details_json['received_qty'] }} · Loss {{ $receipt->details_json['loss_qty'] }} ({{ $receipt->details_json['loss_percent'] }}%)@if(!empty($receipt->details_json['lots']['input']) || !empty($receipt->details_json['lots']['output'])) · Lots {{ implode(', ', $receipt->details_json['lots']['input'] ?? []) ?: '—' }} → {{ implode(', ', $receipt->details_json['lots']['output'] ?? []) ?: '—' }}@endif</p>
                    <table><thead><tr><th>Material</th><th class="num">Accepted</th><th class="num">Rejected</th><th class="num">Loss</th></tr></thead><tbody>
                    @foreach($rLines as $line)<tr><td>{{ $line->name }}</td><td class="num">{{ $qty($line->accepted_qty) }}</td><td class="num">{{ $qty($line->rejected_qty) }}</td><td class="num">{{ $qty($line->loss_qty) }}</td></tr>@endforeach
                    </tbody></table>
                    @if($receipt->output_movement_id && $receiptOutputs->has($receipt->output_movement_id))<p>Came back as: {{ $receiptOutputs[$receipt->output_movement_id]->map(fn ($o) => $o->name.' '.$qty($o->qty_base))->implode(', ') }}</p>@endif
                    @if($cost)<p>Processing charge {{ $cost['purchase_no'] }}: ₹{{ number_format($cost['amount'], 2) }} added to cost (₹{{ number_format($cost['to_stock'], 2) }} to stock{{ '' }}@if($cost['to_cogs'] > 0), ₹{{ number_format($cost['to_cogs'], 2) }} to cost of goods already sold{{ '' }}@endif)@if($cost['per_unit']) · material ₹{{ number_format($cost['material_per_unit'], 2) }} + processing ₹{{ number_format($cost['per_unit'], 2) }} = ₹{{ number_format($cost['material_per_unit'] + $cost['per_unit'], 2) }} per unit{{ '' }}@endif</p>@endif
                    @if($receipt->service_purchase_id)<p>Job worker bill: {{ $cost['purchase_no'] ?? ($billNumbers[$receipt->service_purchase_id] ?? ('purchase #'.$receipt->service_purchase_id)) }}</p>@endif
                    @if($reworkDcs->isNotEmpty())<p>Sent for rework: {{ $reworkDcs->map(fn ($d) => $d->reference_no.($d->reversed_at ? ' (reversed)' : ''))->implode(', ') }}</p>@endif
                    @if($receipt->reversed_at)<p>Receipt reversed: {{ $receipt->reversal_reason }}</p>@endif
                </div>

                @if($billable)
                <div class="jw-panel" id="grn-{{ $receipt->id }}-bill" hidden>
                    @include('backend.operations.form_start',['formId'=>'bill-'.$receipt->id,'action'=>'job-bill/'.$receipt->id])
                    <h3>Service bill for {{ $receipt->reference_no }}</h3>
                    <div class="ops-fields">@include('backend.operations.date')<label>Processing charge item<select name="items[0][product_id]" required><option value="">Select service</option>@foreach($products->where('type','service') as $product)<option value="{{ $product->id }}" @selected((int) ($route->service_product_id ?? 0) === $product->id)>{{ $product->name }}</option>@endforeach</select></label><label>Quantity charged<input name="items[0][qty]" type="number" min="0.0001" step="0.0001" value="{{ (float) ($acceptedQty[$receipt->id] ?? 1) ?: 1 }}" required></label><label>Rate per unit (before GST)<input name="items[0][net_unit_cost]" type="number" min="0" step="0.0001" value="{{ isset($route->service_rate) ? (float) $route->service_rate : '' }}" required></label></div>
                    <button class="ops-primary">Book job worker bill</button></form>
                </div>
                @endif

                @if(!$receipt->reversed_at && $kind !== 'material_return')
                <div class="jw-panel" id="grn-{{ $receipt->id }}-rework" hidden>
                    @include('backend.operations.form_start',['formId'=>'rework-'.$receipt->id,'action'=>'job-rework/'.$receipt->id])
                    <h3>Return {{ $receipt->reference_no }} to {{ $jobWorker->name ?? 'the job worker' }} for rework</h3>
                    <p>Creates a new {{ $dcLabel }} under this order, referencing {{ $receipt->reference_no }}. When it comes back, receive it on that DC.</p>
                    <div class="ops-fields">@include('backend.operations.warehouse',['warehouseLabel'=>'Send from warehouse','selectedWarehouse'=>$receipt->details_json['rejected_warehouse_id'] ?? $receipt->warehouse_id])@include('backend.operations.date')<label>Vehicle no.<input name="transport[vehicle_number]" maxlength="30"></label><label>Rework note<input name="transport[notes]" maxlength="500" placeholder="What to correct: shade, stains, finish"></label></div>
                    <div class="ops-table-wrap"><table data-lines><thead><tr><th>Product</th><th>Quantity</th><th>Unit</th><th>Tracking</th><th></th></tr></thead><tbody>
                    @forelse($rejectedLines as $i=>$line)
                        @include('backend.operations.stock_line',['prefix'=>'lines['.$i.']','defaultProduct'=>(int) $line->product_id,'defaultQty'=>(float) $line->rejected_qty,'defaultUnit'=>(int) optional($products->firstWhere('id', (int) $line->product_id))->unit_id])
                    @empty
                        @php $first = $rLines->first(); @endphp
                        @include('backend.operations.stock_line',['prefix'=>'lines[0]','defaultProduct'=>$first ? (int) $first->product_id : null,'defaultUnit'=>$first ? (int) optional($products->firstWhere('id', (int) $first->product_id))->unit_id : null])
                    @endforelse
                    </tbody></table></div>
                    <button type="button" data-add-line>Add material</button><button class="ops-primary">Send for rework</button></form>
                </div>
                @endif

                @if(!$receipt->reversed_at)
                <div class="jw-panel" id="grn-{{ $receipt->id }}-reverse" hidden>
                    @include('backend.operations.form_start',['formId'=>'receipt-reverse-'.$receipt->id,'action'=>'job-reverse-receipt/'.$receipt->id])
                    <h3>Reverse {{ $receipt->reference_no }}</h3><p>Puts the material back outside with the job worker. Reverse its service bill and any rework DC first.</p>
                    <div class="ops-fields">@include('backend.operations.date')<label>Reason<input name="reason" required maxlength="500"></label></div><button type="submit">Post reversal</button></form>
                </div>
                @endif
            </td></tr>
        @empty
            <tr><td colspan="9" class="jw-empty">Nothing received yet. Use <strong>Receive {{ $grnLabel }}</strong> on a {{ $dcLabel }} when material comes back.</td></tr>
        @endforelse
        </tbody>
    </table>
    </div>
</section>
</div>
