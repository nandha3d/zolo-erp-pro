@extends('backend.layout.main')
@section('content')
<section class="forms"><div class="container-fluid">
    <h1 class="h3" id="voucher-hub" tabindex="-1">Voucher Hub <small class="text-muted">F9</small></h1>
    <p>{{ $year->name }} · {{ $year->start_date->format('Y-m-d') }} – {{ $year->end_date->format('Y-m-d') }} · {{ ucfirst($year->status) }}@if($year->lock_date) · Locked through {{ $year->lock_date->format('Y-m-d') }}@endif</p>
    <nav aria-label="Accounting reports" class="d-flex flex-wrap mb-3" style="gap: .5rem">
        <a class="btn btn-outline-secondary" href="{{ route('accounting.day-book') }}">Day Book</a>
        <a class="btn btn-outline-secondary" href="{{ route('accounting.cash-book') }}">Cash Book</a>
        <a class="btn btn-outline-secondary" href="{{ route('accounting.ageing') }}">Receivables / Payables</a>
        <a class="btn btn-outline-secondary" href="{{ route('accounting.general-ledger') }}">Ledger</a>
        <a class="btn btn-outline-secondary" href="{{ route('accounting.semantic-mappings') }}">Account mappings</a>
    </nav>
    @if(session('message'))<div class="alert alert-success" role="status">{{ session('message') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger" role="alert"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @if($canPost)
    <form method="POST" action="{{ route('accounting.vouchers.store') }}" id="accounting-voucher-form" class="card neo-card">
        @csrf
        <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', (string) Illuminate\Support\Str::uuid()) }}">
        <div class="card-body">
            <div class="row">
                <div class="col-md-4 form-group"><label for="voucher-type">Voucher type</label><select id="voucher-type" name="voucher_type" class="form-control">
                    @foreach(['contra' => 'Contra (F4)', 'payment' => 'Payment (F5)', 'receipt' => 'Receipt (F6)', 'journal' => 'Journal (F7)'] as $value => $label)<option value="{{ $value }}" @selected(old('voucher_type', 'journal') === $value)>{{ $label }}</option>@endforeach
                </select></div>
                <div class="col-md-4 form-group"><label for="voucher-date">Posting date</label><input id="voucher-date" name="entry_date" type="date" class="form-control" min="{{ $year->start_date->format('Y-m-d') }}" max="{{ $year->end_date->format('Y-m-d') }}" value="{{ old('entry_date', max($year->start_date->format('Y-m-d'), min(now()->toDateString(), $year->end_date->format('Y-m-d')))) }}" required></div>
                <div class="col-md-4 form-group"><label for="reference-mode">Allocation mode</label><select id="reference-mode" name="reference_mode" class="form-control">
                    @foreach(['new_reference' => 'New Reference', 'against_reference' => 'Against Reference', 'advance' => 'Advance', 'on_account' => 'On Account'] as $value => $label)<option value="{{ $value }}" @selected(old('reference_mode') === $value)>{{ $label }}</option>@endforeach
                </select></div>
            </div>
            <div class="row">
                <div class="col-md-8 form-group"><label for="voucher-description">Narration</label><textarea id="voucher-description" name="description" class="form-control" maxlength="2000" rows="2" required>{{ old('description') }}</textarea></div>
                <div class="col-md-4 form-group"><label for="narration-template">Narration template</label><select id="narration-template" class="form-control"><option value="">Choose a template</option><option>Cash deposited in bank</option><option>Supplier bill payment</option><option>Customer bill receipt</option><option>Account adjustment</option></select></div>
            </div>
            <div class="table-responsive"><table class="table"><caption class="sr-only">Voucher debit and credit lines</caption><thead><tr><th scope="col">Account</th><th scope="col">Party (AR/AP)</th><th scope="col">Debit</th><th scope="col">Credit</th><th scope="col">Memo</th><th scope="col">Action</th></tr></thead><tbody id="voucher-lines"></tbody></table></div>
            <button class="btn btn-outline-primary" type="button" id="add-voucher-line">Add line</button>
            <div class="row mt-3">
                <div class="col-md-4 form-group"><label for="voucher-due-date">Due date</label><input type="date" id="voucher-due-date" name="due_date" class="form-control" value="{{ old('due_date') }}"></div>
                <div class="col-md-4 form-group"><label for="cheque-no">Cheque number</label><input id="cheque-no" name="cheque_no" class="form-control" maxlength="100" value="{{ old('cheque_no') }}"></div>
                <div class="col-md-4 form-group"><label for="cheque-date">Cheque date</label><input type="date" id="cheque-date" name="cheque_date" class="form-control" value="{{ old('cheque_date') }}"></div>
            </div>
            <button type="button" id="choose-bills" class="btn btn-outline-secondary" hidden data-toggle="modal" data-target="#allocation-modal">Choose bills</button>
            <div id="voucher-allocations"></div><p id="allocation-summary" class="mt-2 text-muted"></p>
            <p id="voucher-balance" role="status" aria-live="polite" class="mt-3">Enter balanced debit and credit lines.</p>
            <button type="submit" class="btn btn-primary" id="post-voucher" disabled>Post voucher (Ctrl+Enter)</button>
        </div>
    </form>
    <template id="voucher-line-template"><tr>
        <td><select class="form-control" data-field="chart_of_account_id" required><option value="">Choose account</option>@foreach($accounts as $account)<option value="{{ $account->id }}" data-control="{{ $account->control_type }}">{{ $account->code }} — {{ $account->name }}</option>@endforeach</select></td>
        <td><select class="form-control" data-party><option value="">No party</option>@foreach($customers as $party)<option value="customer:{{ $party->id }}">Customer: {{ $party->name }}</option>@endforeach @foreach($suppliers as $party)<option value="supplier:{{ $party->id }}">Supplier: {{ $party->name }}</option>@endforeach</select><input type="hidden" data-field="partner_type"><input type="hidden" data-field="partner_id"></td>
        <td><input class="form-control" type="number" step="0.0001" min="0" data-field="debit" value="0" style="min-width: 7rem"></td>
        <td><input class="form-control" type="number" step="0.0001" min="0" data-field="credit" value="0" style="min-width: 7rem"></td>
        <td><input class="form-control" data-field="memo" maxlength="255"></td><td><button type="button" class="btn btn-outline-danger" data-remove>Remove</button></td>
    </tr></template>
    <div class="modal fade" id="allocation-modal" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="allocation-title"><div class="modal-dialog modal-lg"><div class="modal-content">
        <div class="modal-header"><h2 class="h5 modal-title" id="allocation-title">Allocate against bills</h2><button type="button" class="close" data-dismiss="modal" aria-label="Close">&times;</button></div>
        <div class="modal-body"><p>Select a matching voucher line and amount for each bill. Account and party must agree.</p><div class="table-responsive"><table class="table"><thead><tr><th scope="col">Bill</th><th scope="col">Party</th><th scope="col">Open amount</th><th scope="col">Voucher line</th><th scope="col">Allocate</th></tr></thead><tbody>
            @forelse($openItems as $item)<tr data-bill-id="{{ $item->id }}" data-account-id="{{ $item->account_id }}" data-party="{{ $item->party_type }}:{{ $item->party_id }}" data-open="{{ $item->open_amount }}"><td>{{ $item->document_no }}</td><td>{{ $item->party_type }} #{{ $item->party_id }}</td><td>{{ $item->open_amount }}</td><td><select class="form-control" data-bill-line aria-label="Voucher line for {{ $item->document_no }}"><option value="">Skip bill</option></select></td><td><input type="number" class="form-control" step="0.0001" min="0" max="{{ abs((float) $item->open_amount) }}" data-bill-amount aria-label="Amount for {{ $item->document_no }}" value="0"></td></tr>@empty<tr><td colspan="5">No open bills in this branch.</td></tr>@endforelse
        </tbody></table></div></div><div class="modal-footer"><button type="button" class="btn btn-primary" id="apply-allocations">Apply allocations</button><button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancel</button></div>
    </div></div></div>
    @else<div class="alert alert-info">Ask a company administrator for voucher posting permission.</div>@endif
    <div class="card neo-card"><div class="card-body"><h2 class="h5">Posted journal history</h2><div class="table-responsive"><table class="table"><thead><tr><th scope="col">Number</th><th scope="col">Date</th><th scope="col">Narration</th><th scope="col">Debit / Credit</th><th scope="col">History</th></tr></thead><tbody>
        @forelse($entries as $entry)<tr><td>{{ $entry->entry_number }}</td><td>{{ $entry->entry_date->format('Y-m-d') }}</td><td>{{ $entry->description }}</td><td>{{ $entry->total_debit }} / {{ $entry->total_credit }}</td><td><details><summary>Details</summary><ul>@foreach($entry->items as $line)<li>{{ $line->account->name }}: Dr {{ $line->debit }} / Cr {{ $line->credit }}</li>@endforeach</ul>
            @if($entry->reversal_of_id)<p>Reversal of journal #{{ $entry->reversal_of_id }}</p>@elseif($canManage)<form method="POST" action="{{ route('accounting.journal.reverse', $entry->id) }}">@csrf<label for="reverse-date-{{ $entry->id }}">Reversal date</label><input class="form-control" id="reverse-date-{{ $entry->id }}" type="date" name="entry_date" required><label for="reverse-reason-{{ $entry->id }}">Reason</label><input class="form-control" id="reverse-reason-{{ $entry->id }}" name="reason" maxlength="500" required><button class="btn btn-outline-danger mt-2" type="submit">Post reversal</button></form>@endif
        </details></td></tr>@empty<tr><td colspan="5">No posted journals in this financial year.</td></tr>@endforelse
    </tbody></table></div>{{ $entries->links() }}</div></div>
    @if($canManage)
    <details class="card neo-card"><summary class="card-body">Period controls and settlement account links</summary><div class="card-body">
        <form method="POST" action="{{ route('accounting.period') }}" class="mb-4">@csrf<div class="row"><div class="col-md-3 form-group"><label for="period-action">Action</label><select name="action" id="period-action" class="form-control"><option value="lock">Lock through date</option><option value="unlock">Unlock / reopen year</option><option value="close">Close financial year</option></select></div><div class="col-md-3 form-group"><label for="period-date">Lock date</label><input type="date" name="lock_date" id="period-date" class="form-control"></div><div class="col-md-6 form-group"><label for="period-reason">Reason (recorded in audit history)</label><input name="reason" id="period-reason" class="form-control" maxlength="500" required></div></div><button class="btn btn-outline-primary" type="submit">Update period</button></form>
        <ul>@foreach($periodEvents as $event)<li>{{ $event->created_at }} · {{ $event->action }} · actor #{{ $event->actor_id }} · {{ $event->reason }}</li>@endforeach</ul>
        <h3 class="h6">Settlement account links</h3><p>Unlinked accounts use the company cash or bank mapping.</p>
        @foreach($legacyAccounts as $legacy)<form method="POST" action="{{ route('accounting.settlement-accounts.link', $legacy->id) }}" class="form-group">@csrf<label for="account-link-{{ $legacy->id }}">{{ $legacy->name }}</label><div class="d-flex"><select name="chart_of_account_id" id="account-link-{{ $legacy->id }}" class="form-control" required><option value="">Select cash or bank account</option>@foreach($accounts->whereIn('control_type', ['cash', 'bank']) as $account)<option value="{{ $account->id }}" @selected($legacy->chart_of_account_id == $account->id)>{{ $account->name }}</option>@endforeach</select><button type="submit" class="btn btn-outline-primary ml-2">Link</button></div></form>@endforeach
    </div></details>
    @endif
</div></section>
@endsection
@push('scripts')
@if($canPost)<script type="application/json" id="previous-voucher-lines">@json(old('items', []))</script><script src="{{ asset('js/accounting-vouchers.js') }}"></script>@endif
@endpush
