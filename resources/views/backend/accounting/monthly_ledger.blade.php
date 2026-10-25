@extends('backend.layout.main')

@section('content')


<section class="monthly-workspace-view">
    <!-- Top Command Bar -->
    <div class="comm-command-bar">
        <div class="comm-title-group">
            <h1><i class="dripicons-calendar text-primary"></i> Monthly Ledger: {{ $report['account']->code }} - {{ $report['account']->name }}</h1>
            <span class="comm-title-badge"><i class="dripicons-tag"></i> Financial Year Breakdown</span>
        </div>
        <ul class="comm-nav-pills">
            <li><a class="nav-link" href="{{ route('accounting.voucher.entry') }}"><i class="dripicons-pencil"></i> Voucher Entry · F9</a></li>
            <li><a class="nav-link" href="{{ route('accounting.general-ledger', ['account_id' => $report['account']->id]) }}"><i class="dripicons-view-thumb"></i> General Ledger</a></li>
            <li><a class="nav-link active" href="#"><i class="dripicons-calendar"></i> Monthly Breakup</a></li>
            <li><a class="nav-link" href="{{ route('accounting.coa') }}"><i class="dripicons-network"></i> Chart of Accounts</a></li>
        </ul>
        <div class="comm-actions">
            <a href="{{ route('accounting.general-ledger', ['account_id' => $report['account']->id]) }}" class="btn btn-outline-secondary btn-sm font-weight-bold u-h-28px u-fs-11px u-d-inline-flex u-items-center u-gap-4px">
                <i class="dripicons-arrow-thin-left"></i> Back to Ledger
            </a>
        </div>
    </div>

    <!-- Monthly Summary Table -->
    <div class="desk-card-wrapper mt-3">
        <div class="table-responsive">
            <table class="table monthly-table mb-0">
                <thead>
                    <tr>
                        <th scope="col">Month</th>
                        <th scope="col" class="text-right">Debit</th>
                        <th scope="col" class="text-right">Credit</th>
                        <th scope="col" class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($report['months'] as $month)
                        <tr>
                            <td class="font-weight-600">{{ $month['label'] }}</td>
                            <td class="text-right font-monospace font-weight-600 u-c-info">
                                {{ (float)$month['debit'] > 0 ? '₹ ' . number_format((float)$month['debit'], 2) : '-' }}
                            </td>
                            <td class="text-right font-monospace font-weight-600 u-c-warning">
                                {{ (float)$month['credit'] > 0 ? '₹ ' . number_format((float)$month['credit'], 2) : '-' }}
                            </td>
                            <td class="text-center">
                                <a href="{{ route('accounting.general-ledger', ['account_id' => $report['account']->id, 'start_date' => $month['start_date'], 'end_date' => $month['end_date']]) }}" class="btn btn-sm btn-outline-primary u-h-26px u-fs-11px u-p-2px-10px u-r-4px">
                                    Open Ledger
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</section>

@endsection

@push('css')
    <link rel="stylesheet" href="<?php echo asset('css/commercial-entry.css') . '?v=' . (file_exists(public_path('css/commercial-entry.css')) ? filemtime(public_path('css/commercial-entry.css')) : time()) ?>" type="text/css">
    <link rel="stylesheet" href="<?php echo asset('css/commercial-workspace.css') . '?v=' . (file_exists(public_path('css/commercial-workspace.css')) ? filemtime(public_path('css/commercial-workspace.css')) : time()) ?>" type="text/css">
    <link rel="stylesheet" href="<?php echo asset('css/zolo-erp-neo.css') . '?v=' . (file_exists(public_path('css/zolo-erp-neo.css')) ? filemtime(public_path('css/zolo-erp-neo.css')) : time()) ?>" type="text/css">
    <link rel="stylesheet" href="{{ versioned_asset('css/pages/accounting-monthly-ledger.css') }}">
@endpush
