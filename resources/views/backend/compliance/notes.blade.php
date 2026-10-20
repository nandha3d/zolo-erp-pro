@extends('backend.compliance.layout')
@section('title', 'Returns & Commercial Notes')

@section('compliance_body')
@include('backend.partials.document-draft-tabs', ['draftLabel' => request()->is('*return-purchase*') ? 'purchase return' : 'sales return', 'draftKey' => request()->is('*return-purchase*') ? 'Shift+F12' : 'Shift+F2'])
<style>
    .notes-kpi-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 12px;
        margin-bottom: 16px;
    }
    .notes-kpi-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 12px 16px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        box-shadow: 0 1px 3px rgba(15,23,42,0.03);
    }
    body.dark-mode .notes-kpi-card {
        background: #141c2e;
        border-color: #334155;
    }
    .notes-kpi-title {
        font-size: 10px;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        font-weight: 700;
        color: #64748b;
        margin-bottom: 4px;
    }
    .notes-kpi-val {
        font-size: 18px;
        font-weight: 700;
        color: #0f172a;
    }
    body.dark-mode .notes-kpi-val {
        color: #f8fafc;
    }
    .notes-split { display: grid; grid-template-columns: repeat(auto-fit, minmax(440px, 1fr)); gap: 12px; align-items: start; }
    .notes-split .notes-table-card { margin-bottom: 0; }
    .notes-table-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        margin-bottom: 20px;
        box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
        overflow: hidden;
    }
    body.dark-mode .notes-table-card {
        background: #141c2e;
        border-color: #334155;
    }
    .notes-card-header {
        padding: 10px 16px;
        background: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    body.dark-mode .notes-card-header {
        background: #1e293b;
        border-color: #334155;
    }
    .notes-card-title {
        font-size: 13px;
        font-weight: 700;
        color: #0f172a;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    body.dark-mode .notes-card-title {
        color: #f8fafc;
    }
    .notes-grid-table {
        width: 100%;
        border-collapse: collapse;
        margin: 0;
        font-size: 12px;
    }
    .notes-grid-table thead tr th {
        background: #0f172a !important;
        color: #ffffff !important;
        font-size: 10.5px !important;
        font-weight: 700 !important;
        text-transform: uppercase !important;
        letter-spacing: 0.04em !important;
        padding: 8px 12px !important;
        border: none !important;
        white-space: nowrap;
    }
    .notes-grid-table tbody tr:nth-child(even) {
        background: #f8fafc;
    }
    .notes-grid-table tbody tr:nth-child(odd) {
        background: #ffffff;
    }
    body.dark-mode .notes-grid-table tbody tr:nth-child(even) {
        background: #141c2c;
    }
    body.dark-mode .notes-grid-table tbody tr:nth-child(odd) {
        background: #1e293b;
    }
    .notes-grid-table tbody tr:hover {
        background: #e2e8f0 !important;
    }
    body.dark-mode .notes-grid-table tbody tr:hover {
        background: #27354f !important;
    }
    .notes-grid-table td {
        padding: 9px 12px;
        border-bottom: 1px solid #e2e8f0;
        color: #1e293b;
        vertical-align: middle;
    }
    body.dark-mode .notes-grid-table td {
        border-color: #334155;
        color: #e2e8f0;
    }
    .note-ref-badge {
        font-weight: 700;
        color: #7c3aed;
    }
    .note-status-badge {
        font-size: 10.5px;
        font-weight: 600;
        padding: 2px 8px;
        border-radius: 4px;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    .status-awaiting {
        background: #fef3c7;
        color: #92400e;
        border: 1px solid #fde68a;
    }
    .status-approved, .status-posted {
        background: #dcfce7;
        color: #166534;
        border: 1px solid #bbf7d0;
    }
    .btn-approve-note {
        background: #7c3aed;
        border: 1px solid #6d28d9;
        color: #ffffff;
        font-size: 11px;
        padding: 3px 10px;
        border-radius: 4px;
        font-weight: 600;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    .btn-approve-note:hover {
        background: #6d28d9;
    }
    .btn-print-note {
        background: #ffffff;
        border: 1px solid #cbd5e1;
        color: #334155;
        font-size: 11px;
        padding: 3px 10px;
        border-radius: 4px;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        text-decoration: none !important;
    }
    .btn-print-note:hover {
        background: #f1f5f9;
        color: #0f172a;
    }
    .btn-exchange-note {
        background: #e0f2fe;
        border: 1px solid #bae6fd;
        color: #0369a1;
        font-size: 11px;
        padding: 3px 10px;
        border-radius: 4px;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        text-decoration: none !important;
    }
    .btn-exchange-note:hover {
        background: #bae6fd;
        color: #075985;
    }
    .empty-notes-state {
        padding: 32px 16px;
        text-align: center;
        color: #64748b;
    }
</style>

<!-- Top Instructions & Quick Actions Strip -->
<div class="compliance-desk-card d-flex align-items-center justify-content-between flex-wrap gap-2" style="padding: 10px 16px;">
    <div class="d-flex align-items-center gap-2">
        <i class="dripicons-information text-primary" style="font-size: 18px;"></i>
        <span style="font-size: 12.5px; color: #475569;">
            Open an active posted sale or purchase to create a debit or credit note. Approvals preserve the original invoice and audit history.
        </span>
    </div>
    <div class="d-flex align-items-center gap-2">
        @if(($side ?? null) !== 'sale')
        <a href="{{ url('/purchases?new=1') }}" class="btn btn-sm btn-outline-secondary py-1 px-3" style="font-size:11px; border-radius:4px;">
            <i class="dripicons-card"></i> New Purchase
        </a>
        @endif
        @if(($side ?? null) !== 'purchase')
        <a href="{{ url('/sales?new=1') }}" class="btn btn-sm btn-outline-secondary py-1 px-3" style="font-size:11px; border-radius:4px;">
            <i class="dripicons-cart"></i> New Sale
        </a>
        @endif
    </div>
</div>

<!-- KPI Summary Cards -->
@php
    $totalPurchaseNotes = count($purchases);
    $totalPurchaseAmt = $purchases->sum('grand_total');
    $totalSaleNotes = count($sales);
    $totalSaleAmt = $sales->sum('grand_total');
    $awaitingCount = $purchases->where('status', 'awaiting_approval')->count() + $sales->where('status', 'awaiting_approval')->count();
@endphp
<div class="notes-kpi-grid">
    @if(($side ?? null) !== 'sale')
    <div class="notes-kpi-card">
        <div>
            <div class="notes-kpi-title">Purchase Returns (Debit Notes)</div>
            <div class="notes-kpi-val">{{ $totalPurchaseNotes }} <small style="font-size:12px; font-weight:normal; color:#64748b;">(₹ {{ number_format($totalPurchaseAmt, 2) }})</small></div>
        </div>
        <div style="background:#f3e8ff; color:#7c3aed; width:36px; height:36px; border-radius:6px; display:flex; align-items:center; justify-content:center;">
            <i class="dripicons-return" style="font-size:18px;"></i>
        </div>
    </div>
    @endif
    @if(($side ?? null) !== 'purchase')
    <div class="notes-kpi-card">
        <div>
            <div class="notes-kpi-title">Sales Returns (Credit Notes)</div>
            <div class="notes-kpi-val">{{ $totalSaleNotes }} <small style="font-size:12px; font-weight:normal; color:#64748b;">(₹ {{ number_format($totalSaleAmt, 2) }})</small></div>
        </div>
        <div style="background:#e0f2fe; color:#0284c7; width:36px; height:36px; border-radius:6px; display:flex; align-items:center; justify-content:center;">
            <i class="dripicons-swap" style="font-size:18px;"></i>
        </div>
    </div>
    @endif
    <div class="notes-kpi-card">
        <div>
            <div class="notes-kpi-title">Awaiting Approval</div>
            <div class="notes-kpi-val" style="color: {{ $awaitingCount > 0 ? '#b45309' : '#166534' }};">{{ $awaitingCount }}</div>
        </div>
        <div style="background:#fef3c7; color:#b45309; width:36px; height:36px; border-radius:6px; display:flex; align-items:center; justify-content:center;">
            <i class="dripicons-clock" style="font-size:18px;"></i>
        </div>
    </div>
</div>

<!-- Purchase and sales notes side by side so both registers fit one screen. -->
<div class="notes-split">
@if(($side ?? null) !== 'sale')
<!-- SECTION 1: PURCHASE NOTES (PURCHASE RETURNS) -->
<div class="notes-table-card">
    <div class="notes-card-header">
        <h3 class="notes-card-title">
            <i class="dripicons-return text-primary"></i> Purchase Notes &amp; Returns (Vendor Debit Notes)
        </h3>
        <span class="badge badge-light-primary px-2 py-1" style="font-weight:600; font-size:11px;">
            {{ count($purchases) }} Record(s)
        </span>
    </div>
    <div class="table-responsive">
        <table class="notes-grid-table">
            <thead>
                <tr>
                    <th>Number &amp; Date</th>
                    <th>Reason / Remarks</th>
                    <th>Note Type</th>
                    <th style="text-align:right;">Total (₹)</th>
                    <th>Status</th>
                    <th style="text-align:center;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($purchases as $note)
                @php
                    $isAwaiting = ($note->status === 'awaiting_approval');
                @endphp
                <tr>
                    <td>
                        <span class="note-ref-badge">{{ $note->reference_no }}</span>
                        <div style="font-size:11px; color:#64748b;">{{ $note->created_at ? $note->created_at->toDateString() : '' }}</div>
                    </td>
                    <td>{{ $note->return_note ?? 'Vendor return adjustment' }}</td>
                    <td>
                        <span class="badge badge-secondary" style="font-size:11px; font-weight:500;">
                            {{ ucfirst($note->note_type ?? 'Debit') }} · {{ str_replace('_', ' ', $note->adjustment_type ?? 'quantity') }}
                        </span>
                    </td>
                    <td style="text-align:right; font-weight:700; color:#0f172a;">
                        ₹ {{ number_format($note->grand_total, 2) }}
                    </td>
                    <td>
                        @if($isAwaiting)
                            <span class="note-status-badge status-awaiting"><i class="fa fa-clock-o"></i> Awaiting Approval</span>
                        @else
                            <span class="note-status-badge status-posted"><i class="fa fa-check"></i> {{ ucfirst(str_replace('_', ' ', $note->status ?? 'Posted')) }}</span>
                        @endif
                    </td>
                    <td style="text-align:center;">
                        <div class="d-inline-flex align-items-center gap-1">
                            @if($isAwaiting)
                                <form method="POST" action="{{ url('/compliance/purchase/notes/'.$note->id.'/approve') }}" style="display:inline;">
                                    @csrf
                                    <button type="submit" class="btn-approve-note">
                                        <i class="dripicons-checkmark"></i> Approve &amp; Post
                                    </button>
                                </form>
                            @elseif($note->posted_at)
                                <a href="{{ url('/compliance/documents/purchase_note/'.$note->id) }}" class="btn-print-note">
                                    <i class="dripicons-print"></i> Print / Send
                                </a>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="empty-notes-state">
                        <i class="dripicons-inbox" style="font-size:32px; color:#94a3b8; display:block; margin-bottom:8px;"></i>
                        No purchase notes or returns recorded in this active financial year and branch.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endif

@if(($side ?? null) !== 'purchase')
<!-- SECTION 2: SALE NOTES (SALES RETURNS) -->
<div class="notes-table-card">
    <div class="notes-card-header">
        <h3 class="notes-card-title">
            <i class="dripicons-swap text-info"></i> Sales Notes &amp; Returns (Customer Credit Notes)
        </h3>
        <span class="badge badge-light-info px-2 py-1" style="font-weight:600; font-size:11px;">
            {{ count($sales) }} Record(s)
        </span>
    </div>
    <div class="table-responsive">
        <table class="notes-grid-table">
            <thead>
                <tr>
                    <th>Number &amp; Date</th>
                    <th>Reason / Remarks</th>
                    <th>Note Type</th>
                    <th style="text-align:right;">Total (₹)</th>
                    <th>Status</th>
                    <th style="text-align:center;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($sales as $note)
                @php
                    $isAwaiting = ($note->status === 'awaiting_approval');
                @endphp
                <tr>
                    <td>
                        <span class="note-ref-badge" style="color:#0284c7;">{{ $note->reference_no }}</span>
                        <div style="font-size:11px; color:#64748b;">{{ $note->created_at ? $note->created_at->toDateString() : '' }}</div>
                    </td>
                    <td>{{ $note->return_note ?? 'Customer return credit' }}</td>
                    <td>
                        <span class="badge badge-secondary" style="font-size:11px; font-weight:500;">
                            {{ ucfirst($note->note_type ?? 'Credit') }} · {{ str_replace('_', ' ', $note->adjustment_type ?? 'quantity') }}
                        </span>
                    </td>
                    <td style="text-align:right; font-weight:700; color:#0f172a;">
                        ₹ {{ number_format($note->grand_total, 2) }}
                    </td>
                    <td>
                        @if($isAwaiting)
                            <span class="note-status-badge status-awaiting"><i class="fa fa-clock-o"></i> Awaiting Approval</span>
                        @else
                            <span class="note-status-badge status-posted"><i class="fa fa-check"></i> {{ ucfirst(str_replace('_', ' ', $note->status ?? 'Posted')) }}</span>
                        @endif
                    </td>
                    <td style="text-align:center;">
                        <div class="d-inline-flex align-items-center gap-1">
                            @if($isAwaiting)
                                <form method="POST" action="{{ url('/compliance/sale/notes/'.$note->id.'/approve') }}" style="display:inline;">
                                    @csrf
                                    <button type="submit" class="btn-approve-note">
                                        <i class="dripicons-checkmark"></i> Approve &amp; Post
                                    </button>
                                </form>
                            @elseif($note->posted_at)
                                <a href="{{ url('/compliance/documents/sale_note/'.$note->id) }}" class="btn-print-note">
                                    <i class="dripicons-print"></i> Print / Send
                                </a>
                                @if($note->adjustment_type === 'quantity')
                                    <a href="{{ url('/sales?new=1&exchange_return_id='.$note->id) }}" class="btn-exchange-note">
                                        <i class="dripicons-swap"></i> Exchange
                                    </a>
                                @endif
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="empty-notes-state">
                        <i class="dripicons-inbox" style="font-size:32px; color:#94a3b8; display:block; margin-bottom:8px;"></i>
                        No sales notes or customer returns recorded in this active financial year and branch.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endif
</div>
@endsection

@push('scripts')
@php $purchaseReturns = request()->is('*return-purchase*'); @endphp
@include('backend.partials.form-drafts', ['draft' => [
    'kind' => $purchaseReturns ? 'preturn' : 'sreturn', 'label' => $purchaseReturns ? 'Purchase Return' : 'Sales Return',
    'shortcut' => $purchaseReturns ? 'purchase-return' : 'sale-return',
    'createUrl' => route($purchaseReturns ? 'return-purchase.create' : 'return-sale.create'),
    'newUrl' => route($purchaseReturns ? 'return-purchase.index' : 'return-sale.index', ['new' => 1]),
]])
@endpush
