@extends('backend.layout.main')
@section('content')
<section class="collections-workspace-view">
    <div class="comm-command-bar"><div class="comm-title-group"><h1>Collections</h1><span class="comm-title-badge">Follow-up, PDC register and preview</span></div><div class="comm-actions"><a class="btn btn-outline-primary btn-sm" href="{{ route('accounting.ageing') }}">Ageing and reconciliation</a><a class="btn btn-outline-secondary btn-sm" href="{{ route('accounting.vouchers') }}">Post receipt in Voucher Hub</a></div></div>
    @if(session('message'))<div role="status" class="alert alert-success">{{ session('message') }}</div>@endif
    @if($errors->any())<div role="alert" class="alert alert-danger">{{ $errors->first() }}</div>@endif
    <p class="mb-0">Collection records are operational. PDC never posts money. Cleared status requires an existing posted receipt. Reminders record plans only; no messages are sent.</p>
    @if(!$installed)<div class="alert alert-warning">Apply the collection tracking migration to enable this workspace.</div>@else
    @if($canWrite)<details class="collection-card"><summary><strong>Add follow-up, PDC or reminder</strong></summary>
        <form action="{{ route('accounting.collections.store') }}" method="POST" class="collection-grid mt-2" data-collection-form>@csrf
            <input type="hidden" name="idempotency_key" value="{{ (string) Str::uuid() }}">
            <div><label for="collection-source">Customer invoice</label><select id="collection-source" name="open_item_id" class="form-control" required><option value="">Select invoice</option>@foreach($items as $item)@if((float)$item->open_amount > 0)<option value="{{ $item->id }}">{{ $parties->get($item->party_id)?->name }} / {{ $item->document_no }} / {{ $item->open_amount }}</option>@endif @endforeach</select></div>
            <div><label for="collection-kind">Record type</label><select id="collection-kind" name="kind" class="form-control"><option value="follow_up">Follow-up</option><option value="pdc">PDC</option><option value="reminder">Reminder plan</option></select></div>
            <div><label for="collection-date">Follow-up / cheque date</label><input id="collection-date" class="form-control" type="date" name="action_date" value="{{ now()->toDateString() }}" required></div>
            <input type="hidden" name="status" value="planned">
            <div><label for="collection-notes">Notes</label><input id="collection-notes" name="notes" class="form-control" maxlength="2000" required></div>
            <div data-pdc><label for="collection-amount">Cheque amount</label><input id="collection-amount" name="amount" type="number" min="0.0001" step="0.0001" class="form-control"></div>
            <div data-pdc><label for="collection-cheque">Cheque number</label><input id="collection-cheque" name="cheque_no" class="form-control" maxlength="100"></div>
            <div data-pdc><label for="collection-bank">Bank</label><input id="collection-bank" name="bank_name" class="form-control" maxlength="150"></div>
            <div class="align-self-end"><button type="submit" class="btn btn-primary btn-sm">Save collection record</button></div>
        </form>
    </details>@endif
    <details class="collection-card"><summary><strong>Interest and cash discount preview</strong></summary>
        <form method="POST" action="{{ route('accounting.collections.preview') }}" class="collection-grid mt-2" id="collection-preview">@csrf
            <div><label for="preview-item">Customer invoice</label><select id="preview-item" name="open_item_id" class="form-control" required>@foreach($items as $item)<option value="{{ $item->id }}">{{ $parties->get($item->party_id)?->name }} / {{ $item->document_no }}</option>@endforeach</select></div>
            <div><label for="preview-date">As of date</label><input id="preview-date" name="as_of_date" type="date" class="form-control" value="{{ now()->toDateString() }}" required></div>
            <div><label for="preview-interest">Annual interest %</label><input id="preview-interest" name="interest_rate" type="number" min="0" max="100" step="0.0001" value="0" class="form-control" required></div>
            <div><label for="preview-basis">Day basis</label><select id="preview-basis" name="day_basis" class="form-control"><option>365</option><option>360</option><option>366</option></select></div>
            <div><label for="preview-discount">Cash discount %</label><input id="preview-discount" name="discount_rate" type="number" min="0" max="100" step="0.0001" value="0" class="form-control" required></div>
            <div><label for="preview-until">Discount eligible through</label><input id="preview-until" name="discount_eligible_until" type="date" value="{{ now()->toDateString() }}" class="form-control" required></div>
            <div class="align-self-end"><button class="btn btn-outline-primary btn-sm">Calculate preview</button></div>
        </form><output id="collection-preview-result" role="status"></output>
    </details>
    <form method="GET" class="collection-grid collection-card">
        <div><label for="filter-party">Customer</label><select id="filter-party" name="party_id" class="form-control"><option value="">All</option>@foreach($parties as $party)<option value="{{ $party->id }}" @selected((string)request('party_id') === (string)$party->id)>{{ $party->name }}</option>@endforeach</select></div>
        <div><label for="filter-kind">Type</label><select id="filter-kind" name="kind" class="form-control"><option value="">All</option>@foreach(['follow_up','pdc','reminder'] as $kind)<option @selected(request('kind') === $kind)>{{ $kind }}</option>@endforeach</select></div>
        <div><label for="filter-status">Status</label><select id="filter-status" name="status" class="form-control"><option value="">All</option>@foreach(['planned','completed','presented','cleared','bounced','cancelled'] as $status)<option @selected(request('status') === $status)>{{ $status }}</option>@endforeach</select></div>
        <div><label for="filter-from">From</label><input type="date" id="filter-from" name="from" value="{{ request('from') }}" class="form-control"></div><div><label for="filter-until">Through</label><input type="date" id="filter-until" name="until" value="{{ request('until') }}" class="form-control"></div><div class="align-self-end"><button class="btn btn-primary btn-sm">Filter records</button></div>
    </form>
    <div class="table-responsive collection-card"><table class="table collection-table"><thead><tr><th>Customer / Invoice</th><th>Type / Date</th><th>PDC</th><th>Update record</th><th>Audit history</th></tr></thead><tbody>
        @forelse($records as $record)
            @php($source = $items->get($record->open_item_id))
            <tr><td>{{ $parties->get($source?->party_id)?->name }}<br>{{ $source?->document_no }}<br>Current outstanding {{ $source?->open_amount }}</td><td>{{ $record->kind }}<br>{{ $record->action_date->toDateString() }}<br>{{ str_replace('_', ' ', $record->effective_status) }}@if($record->receipt_warning)<div class="text-danger" role="alert">{{ $record->receipt_warning }}</div>@endif</td><td>{{ $record->cheque_no }}<br>{{ $record->bank_name }}<br>{{ $record->amount }}@if($record->receipt_entry_id)<br>Receipt #{{ $record->receipt_entry_id }}@endif</td><td>
            @if($canWrite)<form action="{{ route('accounting.collections.update', $record->id) }}" method="POST" class="collection-record-form" data-collection-form>@csrf @method('PUT')
                <input type="hidden" name="open_item_id" value="{{ $record->open_item_id }}"><input type="hidden" name="kind" value="{{ $record->kind }}"><input type="hidden" name="version" value="{{ $record->version }}"><input type="hidden" name="idempotency_key" value="{{ (string) Str::uuid() }}">
                @foreach(['amount','cheque_no','bank_name'] as $field)<input type="hidden" name="{{ $field }}" value="{{ $record->$field }}">@endforeach
                <label for="record-status-{{ $record->id }}">Status</label><select id="record-status-{{ $record->id }}" name="status" class="form-control">@foreach($record->kind === 'reminder' ? ['planned','cancelled'] : ($record->kind === 'pdc' ? ['planned','presented','cleared','bounced','cancelled'] : ['planned','completed','cancelled']) as $status)<option @selected($record->status === $status)>{{ $status }}</option>@endforeach</select>
                <label for="record-date-{{ $record->id }}">Follow-up / cheque date</label><input id="record-date-{{ $record->id }}" name="action_date" type="date" value="{{ $record->action_date->toDateString() }}" class="form-control" required>
                <label for="record-notes-{{ $record->id }}">Notes</label><input id="record-notes-{{ $record->id }}" name="notes" value="{{ $record->notes }}" maxlength="2000" class="form-control" required>
                @if($record->kind === 'pdc')<label for="record-receipt-{{ $record->id }}">Existing receipt (cleared only)</label><select id="record-receipt-{{ $record->id }}" name="receipt_entry_id" class="form-control"><option value="">No receipt</option>@foreach($receipts as $receipt)<option value="{{ $receipt->id }}" @selected((int)$record->receipt_entry_id === $receipt->id)>#{{ $receipt->id }} {{ $receipt->entry_number }} {{ $receipt->entry_date->toDateString() }}</option>@endforeach</select>@endif
                <button class="btn btn-outline-primary btn-sm mt-1">Save update</button><span class="collection-error" role="alert"></span>
            </form>@else {{ $record->notes }} <br>Posting permission is required to update records.@endif</td><td><details><summary>{{ $record->version }} revisions</summary><div class="collection-history">@foreach($events->get($record->id, collect()) as $event)<p>v{{ $event->version }} · actor #{{ $event->actor_id }} · {{ $event->created_at }}<br>{{ $event->snapshot['status'] }} / {{ $event->snapshot['action_date'] }}<br>{{ $event->snapshot['notes'] }}</p>@endforeach</div></details></td></tr>
        @empty<tr><td colspan="5">No collection records match these filters.</td></tr>@endforelse
    </tbody></table></div>
    @endif
</section>
<script src="{{ asset('js/accounting-collections.js') }}?v={{ filemtime(public_path('js/accounting-collections.js')) }}"></script>
@endsection

@push('css')
    <link rel="stylesheet" href="{{ asset('css/commercial-workspace.css') }}">
    <link rel="stylesheet" href="{{ versioned_asset('css/pages/accounting-collections.css') }}">
@endpush
