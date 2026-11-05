@extends('backend.layout.main')
@section('content')


<section class="cf-workspace-view">
    <!-- Top Command Bar -->
    <x-accounting-command-bar active="cash-flow" title="Cash flow statement" purpose="Review total movements across cash and bank accounts. Activity classifications are not yet available.">
            <button type="button" data-erp-print class="btn btn-outline-secondary btn-action-pill">
                <i class="dripicons-print"></i> Print Statement
            </button>
    </x-accounting-command-bar>

    <!-- Filter Bar -->
    <div class="desk-filter-card d-print-none">
        <form action="{{ route('accounting.cash-flow') }}" method="GET" class="m-0">
            <div class="row align-items-end u-m-0-6px">
                <div class="col-md-4 u-p-0-6px">
                    <label for="cash-flow-statement-field-1" class="font-weight-600 mb-1 u-fs-11px u-tt-uppercase u-c-text-muted">From Date</label>
                    <input id="cash-flow-statement-field-1" type="date" name="start_date" class="form-control form-control-sm u-h-32px u-fs-12px" value="{{ $startDate }}">
                </div>
                <div class="col-md-4 u-p-0-6px">
                    <label for="cash-flow-statement-field-2" class="font-weight-600 mb-1 u-fs-11px u-tt-uppercase u-c-text-muted">To Date</label>
                    <input id="cash-flow-statement-field-2" type="date" name="end_date" class="form-control form-control-sm u-h-32px u-fs-12px" value="{{ $endDate }}">
                </div>
                <div class="col-md-4 d-flex align-items-end u-gap-4px u-p-0-6px">
                    <button type="submit" class="btn btn-primary btn-sm flex-grow-1 u-h-32px u-fs-12px u-fw-600">
                        <i class="dripicons-search"></i> Apply filters
                    </button>
                    <a href="{{ route('accounting.cash-flow') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
                </div>
            </div>
        </form>
    </div>

    <div class="alert alert-info accounting-report-note" role="note"><strong>Cash movement summary:</strong> These totals include all cash and bank postings, including transfers between your accounts. Transfers increase both movement totals but cancel in the net change. Investing and financing classifications are not calculated.</div>

    <!-- KPI Summary Strip -->
    <div class="kpi-summary-strip">
        <div class="kpi-card">
            <div>
                <div class="kpi-label">CASH / BANK DEBITS</div>
                <div class="kpi-number u-c-success">+ ₹ {{ number_format($operatingDebits, 2) }}</div>
            </div>
            <div class="z-icon-tile z-tone-success">
                <i class="dripicons-arrow-up u-fs-18px"></i>
            </div>
        </div>
        <div class="kpi-card">
            <div>
                <div class="kpi-label">CASH / BANK CREDITS</div>
                <div class="kpi-number u-c-danger">- ₹ {{ number_format($operatingCredits, 2) }}</div>
            </div>
            <div class="z-icon-tile z-tone-danger">
                <i class="dripicons-arrow-down u-fs-18px"></i>
            </div>
        </div>
        <div class="kpi-card">
            <div>
                <div class="kpi-label">NET MOVEMENT</div>
                <div class="kpi-number {{ $netOperatingCash >= 0 ? 'u-c-success' : 'u-c-danger' }}">
                    {{ $netOperatingCash >= 0 ? '+' : '' }}₹ {{ number_format($netOperatingCash, 2) }}
                </div>
            </div>
            <div class="z-icon-tile {{ $netOperatingCash >= 0 ? 'z-tone-success' : 'z-tone-danger' }}">
                <i class="dripicons-pulse u-fs-18px"></i>
            </div>
        </div>
        <div class="kpi-card">
            <div>
                <div class="kpi-label">NET CHANGE IN CASH / BANK</div>
                <div class="kpi-number {{ $netCashFlow >= 0 ? 'u-c-success' : 'u-c-danger' }}">
                    {{ $netCashFlow >= 0 ? '+' : '' }}₹ {{ number_format($netCashFlow, 2) }}
                </div>
            </div>
            <div class="z-icon-tile {{ $netCashFlow >= 0 ? 'z-tone-info' : 'z-tone-danger' }}">
                <i class="dripicons-wallet u-fs-18px"></i>
            </div>
        </div>
    </div>

    <!-- Statement Table -->
    <div class="desk-card-wrapper">
        <div class="table-responsive">
            <table class="table cf-table mb-0">
                <caption class="sr-only">Cash flow statement</caption>
                <thead>
                    <tr>
                        <th scope="col">Cash and bank activity</th>
                        <th scope="col" class="text-right u-w-250px">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- 1. Operating Activities -->
                    <tr class="u-bg-surface-3 u-fw-700 u-tt-uppercase">
                        <td colspan="2"><i class="dripicons-pulse text-primary mr-1"></i> 1. All cash and bank movements (unclassified)</td>
                    </tr>
                    <tr>
                        <td class="pl-4 font-weight-500">Total debits to cash and bank accounts</td>
                        <td class="text-right text-success font-weight-bold font-monospace">+ ₹ {{ number_format($operatingDebits, 2) }}</td>
                    </tr>
                    <tr>
                        <td class="pl-4 font-weight-500">Total credits to cash and bank accounts</td>
                        <td class="text-right text-danger font-weight-bold font-monospace">- ₹ {{ number_format($operatingCredits, 2) }}</td>
                    </tr>
                    <tr class="u-bg-surface-2 u-fw-700">
                        <td class="pl-3 font-weight-bold">Net movement across cash and bank accounts</td>
                        <td class="text-right font-monospace font-weight-bold {{ $netOperatingCash >= 0 ? 'text-success' : 'text-danger' }}">
                            {{ $netOperatingCash >= 0 ? '+' : '' }}₹ {{ number_format($netOperatingCash, 2) }}
                        </td>
                    </tr>

                    <!-- 2. Investing Activities -->
                    <tr class="u-bg-surface-3 u-fw-700 u-tt-uppercase">
                        <td colspan="2"><i class="dripicons-graph-pie text-info mr-1"></i> 2. Investing classification</td>
                    </tr>
                    <tr>
                        <td class="pl-4 font-weight-500">Capital equipment and fixed asset payments</td>
                        <td class="text-right font-weight-bold text-muted font-monospace">Not calculated</td>
                    </tr>
                    <tr class="u-bg-surface-2 u-fw-700">
                        <td class="pl-3 font-weight-bold">Investing subtotal</td>
                        <td class="text-right text-muted font-monospace font-weight-bold">Not calculated</td>
                    </tr>

                    <!-- 3. Financing Activities -->
                    <tr class="u-bg-surface-3 u-fw-700 u-tt-uppercase">
                        <td colspan="2"><i class="dripicons-wallet text-warning mr-1"></i> 3. Financing classification</td>
                    </tr>
                    <tr>
                        <td class="pl-4 font-weight-500">Owner contributions and loan movements</td>
                        <td class="text-right font-weight-bold text-muted font-monospace">Not calculated</td>
                    </tr>
                    <tr class="u-bg-surface-2 u-fw-700">
                        <td class="pl-3 font-weight-bold">Financing subtotal</td>
                        <td class="text-right text-muted font-monospace font-weight-bold">Not calculated</td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr class="u-bg-ink u-c-xffffff">
                        <td><h6 class="m-0 font-weight-bold u-c-xffffff u-fs-14px">Net Increase / (Decrease) in Cash and Cash Equivalents</h6></td>
                        <td class="text-right font-monospace">
                            <h5 class="m-0 font-weight-bold {{ $netCashFlow >= 0 ? 'text-success' : 'text-danger' }} u-fs-16px">
                                {{ $netCashFlow >= 0 ? '+' : '' }}₹ {{ number_format($netCashFlow, 2) }}
                            </h5>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</section>

@endsection

@push('css')
    <link rel="stylesheet" href="<?php echo asset('css/commercial-entry.css') . '?v=' . (file_exists(public_path('css/commercial-entry.css')) ? filemtime(public_path('css/commercial-entry.css')) : time()) ?>" type="text/css">
    <link rel="stylesheet" href="<?php echo asset('css/commercial-workspace.css') . '?v=' . (file_exists(public_path('css/commercial-workspace.css')) ? filemtime(public_path('css/commercial-workspace.css')) : time()) ?>" type="text/css">
    <link rel="stylesheet" href="<?php echo asset('css/zolo-erp-neo.css') . '?v=' . (file_exists(public_path('css/zolo-erp-neo.css')) ? filemtime(public_path('css/zolo-erp-neo.css')) : time()) ?>" type="text/css">
    <link rel="stylesheet" href="{{ versioned_asset('css/pages/accounting-cash-flow-statement.css') }}">
@endpush
