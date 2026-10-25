@extends('backend.compliance.layout')
@section('title', 'Returns & Commercial Notes')

@section('compliance_body')
@include('backend.partials.document-draft-tabs', ['draftLabel' => request()->is('*return-purchase*') ? 'purchase return' : 'sales return', 'draftKey' => request()->is('*return-purchase*') ? 'Shift+F12' : 'Shift+F2'])
<link rel="stylesheet" href="{{ versioned_asset('css/pages/compliance-notes.css') }}">

<!-- Top Instructions & Quick Actions Strip -->
<div class="compliance-desk-card d-flex align-items-center justify-content-between flex-wrap gap-2 u-p-10px-16px">
    <div class="d-flex align-items-center gap-2">
        <i class="dripicons-information text-primary u-fs-18px"></i>
        <span class="u-fs-12_5px u-c-text-2">
            Open an active posted sale or purchase to create a debit or credit note. Approvals preserve the original invoice and audit history.
        </span>
    </div>
    <div class="d-flex align-items-center gap-2">
        @if(($side ?? null) !== 'sale')
        <a href="{{ url('/purchases?new=1') }}" class="btn btn-sm btn-outline-secondary py-1 px-3 u-fs-11px u-r-4px">
            <i class="dripicons-card"></i> New Purchase
        </a>
        @endif
        @if(($side ?? null) !== 'purchase')
        <a href="{{ url('/sales?new=1') }}" class="btn btn-sm btn-outline-secondary py-1 px-3 u-fs-11px u-r-4px">
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
            <div class="notes-kpi-val">{{ $totalPurchaseNotes }} <small class="u-fs-12px u-fw-normal u-c-text-muted">(₹ {{ number_format($totalPurchaseAmt, 2) }})</small></div>
        </div>
        <div class="z-icon-tile z-tone-primary">
            <i class="dripicons-return u-fs-18px"></i>
        </div>
    </div>
    @endif
    @if(($side ?? null) !== 'purchase')
    <div class="notes-kpi-card">
        <div>
            <div class="notes-kpi-title">Sales Returns (Credit Notes)</div>
            <div class="notes-kpi-val">{{ $totalSaleNotes }} <small class="u-fs-12px u-fw-normal u-c-text-muted">(₹ {{ number_format($totalSaleAmt, 2) }})</small></div>
        </div>
        <div class="z-icon-tile z-tone-info">
            <i class="dripicons-swap u-fs-18px"></i>
        </div>
    </div>
    @endif
    <div class="notes-kpi-card">
        <div>
            <div class="notes-kpi-title">Awaiting Approval</div>
            <div class="notes-kpi-val {{ $awaitingCount > 0 ? 'u-c-warning' : 'u-c-success' }}">{{ $awaitingCount }}</div>
        </div>
        <div class="z-icon-tile z-tone-warning">
            <i class="dripicons-clock u-fs-18px"></i>
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
        <span class="badge badge-light-primary px-2 py-1 u-fw-600 u-fs-11px">
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
                    <th class="u-ta-right">Total (₹)</th>
                    <th>Status</th>
                    <th class="u-ta-center">Actions</th>
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
                        <div class="u-fs-11px u-c-text-muted">{{ $note->created_at ? $note->created_at->toDateString() : '' }}</div>
                    </td>
                    <td>{{ $note->return_note ?? 'Vendor return adjustment' }}</td>
                    <td>
                        <span class="badge badge-secondary u-fs-11px u-fw-500">
                            {{ ucfirst($note->note_type ?? 'Debit') }} · {{ str_replace('_', ' ', $note->adjustment_type ?? 'quantity') }}
                        </span>
                    </td>
                    <td class="u-ta-right u-fw-700 u-c-text">
                        ₹ {{ number_format($note->grand_total, 2) }}
                    </td>
                    <td>
                        @if($isAwaiting)
                            <span class="note-status-badge status-awaiting"><i class="fa fa-clock-o"></i> Awaiting Approval</span>
                        @else
                            <span class="note-status-badge status-posted"><i class="fa fa-check"></i> {{ ucfirst(str_replace('_', ' ', $note->status ?? 'Posted')) }}</span>
                        @endif
                    </td>
                    <td class="u-ta-center">
                        <div class="d-inline-flex align-items-center gap-1">
                            @if($isAwaiting)
                                <form method="POST" action="{{ url('/compliance/purchase/notes/'.$note->id.'/approve') }}" class="u-d-inline">
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
                        <i class="dripicons-inbox u-fs-32px u-c-text-muted u-d-block u-mb-8px"></i>
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
        <span class="badge badge-light-info px-2 py-1 u-fw-600 u-fs-11px">
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
                    <th class="u-ta-right">Total (₹)</th>
                    <th>Status</th>
                    <th class="u-ta-center">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($sales as $note)
                @php
                    $isAwaiting = ($note->status === 'awaiting_approval');
                @endphp
                <tr>
                    <td>
                        <span class="note-ref-badge u-c-info">{{ $note->reference_no }}</span>
                        <div class="u-fs-11px u-c-text-muted">{{ $note->created_at ? $note->created_at->toDateString() : '' }}</div>
                    </td>
                    <td>{{ $note->return_note ?? 'Customer return credit' }}</td>
                    <td>
                        <span class="badge badge-secondary u-fs-11px u-fw-500">
                            {{ ucfirst($note->note_type ?? 'Credit') }} · {{ str_replace('_', ' ', $note->adjustment_type ?? 'quantity') }}
                        </span>
                    </td>
                    <td class="u-ta-right u-fw-700 u-c-text">
                        ₹ {{ number_format($note->grand_total, 2) }}
                    </td>
                    <td>
                        @if($isAwaiting)
                            <span class="note-status-badge status-awaiting"><i class="fa fa-clock-o"></i> Awaiting Approval</span>
                        @else
                            <span class="note-status-badge status-posted"><i class="fa fa-check"></i> {{ ucfirst(str_replace('_', ' ', $note->status ?? 'Posted')) }}</span>
                        @endif
                    </td>
                    <td class="u-ta-center">
                        <div class="d-inline-flex align-items-center gap-1">
                            @if($isAwaiting)
                                <form method="POST" action="{{ url('/compliance/sale/notes/'.$note->id.'/approve') }}" class="u-d-inline">
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
                        <i class="dripicons-inbox u-fs-32px u-c-text-muted u-d-block u-mb-8px"></i>
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
