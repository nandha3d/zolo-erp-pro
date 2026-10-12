@extends('backend.layout.main')
@section('content')

<link rel="stylesheet" href="<?php echo asset('css/commercial-entry.css') . '?v=' . (file_exists(public_path('css/commercial-entry.css')) ? filemtime(public_path('css/commercial-entry.css')) : time()) ?>" type="text/css">
<link rel="stylesheet" href="<?php echo asset('css/commercial-workspace.css') . '?v=' . (file_exists(public_path('css/commercial-workspace.css')) ? filemtime(public_path('css/commercial-workspace.css')) : time()) ?>" type="text/css">
<link rel="stylesheet" href="<?php echo asset('css/zolo-erp-neo.css') . '?v=' . (file_exists(public_path('css/zolo-erp-neo.css')) ? filemtime(public_path('css/zolo-erp-neo.css')) : time()) ?>" type="text/css">

<style>
    .book-workspace-view {
        padding: 12px 18px 24px;
        background: #f8fafc;
        min-height: calc(100vh - 40px);
    }
    body.dark-mode .book-workspace-view {
        background: #0b0f19;
    }
    .desk-filter-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 10px 14px;
        margin-bottom: 14px;
        box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03);
    }
    body.dark-mode .desk-filter-card {
        background: #141c2e;
        border-color: #334155;
    }
    .desk-card-wrapper {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
        padding: 18px 20px;
    }
    body.dark-mode .desk-card-wrapper {
        background: #141c2e;
        border-color: #334155;
    }
    .book-table {
        border-collapse: collapse !important;
        width: 100% !important;
    }
    .book-table thead th {
        background: #0f172a !important;
        color: #ffffff !important;
        font-size: 10.5px !important;
        font-weight: 700 !important;
        text-transform: uppercase !important;
        letter-spacing: 0.04em !important;
        padding: 9px 12px !important;
        border: none !important;
    }
    body.dark-mode .book-table thead th {
        background: #090d16 !important;
        color: #f8fafc !important;
    }
    .book-table tbody tr:nth-child(even) {
        background: #f8fafc;
    }
    .book-table tbody tr:nth-child(odd) {
        background: #ffffff;
    }
    body.dark-mode .book-table tbody tr:nth-child(even) {
        background: #141c2c;
    }
    body.dark-mode .book-table tbody tr:nth-child(odd) {
        background: #1e293b;
    }
    .book-table tbody tr:hover {
        background: #e2e8f0 !important;
    }
    body.dark-mode .book-table tbody tr:hover {
        background: #27354f !important;
    }
    .book-table td {
        padding: 9px 12px !important;
        border-bottom: 1px solid #e2e8f0 !important;
        vertical-align: middle !important;
        font-size: 12.5px;
    }
    body.dark-mode .book-table td {
        border-color: #334155 !important;
        color: #e2e8f0;
    }
</style>

<section class="book-workspace-view">
    <!-- Top Command Bar -->
    <div class="comm-command-bar">
        <div class="comm-title-group">
            <h1><i class="dripicons-document text-primary"></i> {{ $type === 'cash' ? 'Cash Book' : 'Day Book' }} Command Center</h1>
            <span class="comm-title-badge"><i class="dripicons-tag"></i> {{ $type === 'cash' ? 'Cash Account Transactions' : 'Daily Chronological Activity' }}</span>
        </div>
        <ul class="comm-nav-pills">
            <li><a class="nav-link" href="{{ route('accounting.vouchers') }}"><i class="dripicons-home"></i> Voucher Hub</a></li>
            <li><a class="nav-link {{ $type === 'day' ? 'active' : '' }}" href="{{ route('accounting.day-book') }}"><i class="dripicons-calendar"></i> Day Book</a></li>
            <li><a class="nav-link {{ $type === 'cash' ? 'active' : '' }}" href="{{ route('accounting.cash-book') }}"><i class="dripicons-wallet"></i> Cash Book</a></li>
            <li><a class="nav-link" href="{{ route('accounting.ageing') }}"><i class="dripicons-clock"></i> Receivables / Payables</a></li>
            <li><a class="nav-link" href="{{ route('accounting.general-ledger') }}"><i class="dripicons-view-thumb"></i> General Ledger</a></li>
        </ul>
        <div class="comm-actions">
            <button onclick="window.print();" class="btn btn-outline-secondary btn-sm" style="height:28px; font-size:11px;">
                <i class="dripicons-print"></i> Print Book
            </button>
        </div>
    </div>

    <!-- Filter Card -->
    <div class="desk-filter-card">
        <form method="GET" class="m-0">
            <div class="row align-items-end" style="margin: 0 -6px;">
                <div class="col-md-4" style="padding: 0 6px;">
                    <label class="font-weight-600 mb-1" style="font-size: 11px; text-transform: uppercase; color: #64748b;">Start Date</label>
                    <input class="form-control form-control-sm" type="date" id="book-start" name="start_date" value="{{ $report['start_date'] }}" style="height:32px; font-size:12px;">
                </div>
                <div class="col-md-4" style="padding: 0 6px;">
                    <label class="font-weight-600 mb-1" style="font-size: 11px; text-transform: uppercase; color: #64748b;">End Date</label>
                    <input class="form-control form-control-sm" type="date" id="book-end" name="end_date" value="{{ $report['end_date'] }}" style="height:32px; font-size:12px;">
                </div>
                <div class="col-md-4 d-flex" style="padding: 0 6px;">
                    <button class="btn btn-primary btn-sm flex-grow-1" type="submit" style="height:32px; font-size:12px; font-weight:600;">
                        <i class="dripicons-search"></i> Show Book
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Table Container -->
    <div class="desk-card-wrapper">
        <div class="table-responsive">
            <table class="table book-table mb-0">
                <thead>
                    <tr>
                        <th scope="col">Date</th>
                        <th scope="col">Entry Number</th>
                        <th scope="col">Narration</th>
                        <th scope="col" class="text-right">Debit</th>
                        <th scope="col" class="text-right">Credit</th>
                        <th scope="col">Details</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($report['entries'] as $entry)
                        <tr>
                            <td>{{ $entry->entry_date->format('Y-m-d') }}</td>
                            <td><span class="font-weight-bold font-monospace text-primary">{{ $entry->entry_number }}</span></td>
                            <td>{{ $entry->description }}</td>
                            <td class="text-right font-monospace font-weight-600" style="color: #0284c7;">
                                {{ (float)($entry->cash_debit ?? $entry->total_debit) > 0 ? '₹ ' . number_format((float)($entry->cash_debit ?? $entry->total_debit), 2) : '-' }}
                            </td>
                            <td class="text-right font-monospace font-weight-600" style="color: #059669;">
                                {{ (float)($entry->cash_credit ?? $entry->total_credit) > 0 ? '₹ ' . number_format((float)($entry->cash_credit ?? $entry->total_credit), 2) : '-' }}
                            </td>
                            <td>
                                <details>
                                    <summary class="font-weight-600 text-primary" style="cursor:pointer; font-size: 11.5px;">Journal Lines</summary>
                                    <ul class="mt-2 mb-0 pl-3 small text-muted">
                                        @foreach($entry->items as $line)
                                            <li>
                                                <a href="{{ route('accounting.general-ledger', ['account_id' => $line->chart_of_account_id, 'start_date' => $entry->entry_date->format('Y-m-d'), 'end_date' => $entry->entry_date->format('Y-m-d')]) }}" class="font-weight-600">
                                                    {{ $line->account->name }}
                                                </a>: Dr ₹ {{ number_format($line->debit, 2) }} / Cr ₹ {{ number_format($line->credit, 2) }} &bull; {{ $line->memo }}
                                            </li>
                                        @endforeach
                                    </ul>
                                </details>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">No posted entries for this period.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($report['entries']->hasPages())
            <div class="mt-3 d-flex justify-content-end">
                {{ $report['entries']->links() }}
            </div>
        @endif
    </div>
</section>

@endsection
