@extends('backend.layout.main')

@section('content')


<section class="ledger-workspace-view">
    <!-- Top Command Bar -->
    <x-accounting-command-bar active="ledger" title="General ledger" purpose="Choose an account to review its opening balance, transactions and running balance.">
            <button type="button" class="btn btn-outline-secondary btn-action-pill" data-erp-print>
                <i class="dripicons-print"></i> Print / PDF
            </button>
    </x-accounting-command-bar>

    <!-- Filter Card -->
    <div class="desk-filter-card d-print-none">
        <form action="{{ route('accounting.general-ledger') }}" method="GET" class="m-0">
            <div class="row align-items-end u-m-0-6px">
                <div class="col-md-4 u-p-0-6px">
                    <label for="general-ledger-field-1" class="font-weight-600 mb-1 u-fs-11px u-tt-uppercase u-c-text-muted">Select Account</label>
                    <select id="general-ledger-field-1" name="account_id" class="form-control form-control-sm u-h-32px u-fs-12px" required>
                        @foreach($accounts as $acc)
                            <option value="{{ $acc->id }}" {{ $accountId == $acc->id ? 'selected' : '' }}>
                                {{ $acc->code }} - {{ $acc->name }} ({{ ucfirst($acc->type) }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 u-p-0-6px">
                    <label for="general-ledger-field-2" class="font-weight-600 mb-1 u-fs-11px u-tt-uppercase u-c-text-muted">Start Date</label>
                    <input id="general-ledger-field-2" type="date" name="start_date" class="form-control form-control-sm u-h-32px u-fs-12px" value="{{ $startDate }}">
                </div>
                <div class="col-md-3 u-p-0-6px">
                    <label for="general-ledger-field-3" class="font-weight-600 mb-1 u-fs-11px u-tt-uppercase u-c-text-muted">End Date</label>
                    <input id="general-ledger-field-3" type="date" name="end_date" class="form-control form-control-sm u-h-32px u-fs-12px" value="{{ $endDate }}">
                </div>
                <div class="col-md-2 d-flex u-p-0-6px">
                    <button type="submit" class="btn btn-sm btn-primary flex-grow-1 u-h-32px u-fs-12px u-fw-600">
                        <i class="dripicons-search"></i> Apply filters
                    </button>
                    <a href="{{ route('accounting.general-ledger', ['account_id' => $accountId]) }}" class="btn btn-sm btn-outline-secondary ml-1">Reset dates</a>
                </div>
            </div>
        </form>
    </div>

    @if($report)
        <div class="d-flex justify-content-between align-items-center mb-2 px-1">
            <a href="{{ route('accounting.monthly-ledger', $accountId) }}" class="btn btn-sm btn-outline-info font-weight-bold u-fs-11px u-h-26px u-p-2px-10px">
                <i class="dripicons-calendar mr-1"></i> Monthly Ledger
            </a>
            <div class="text-muted u-fs-11px">
                Account Type: <span class="badge badge-primary u-tt-uppercase">{{ $report['account']->type }}</span>
            </div>
        </div>

        <!-- Account Summary Header Cards -->
        <div class="kpi-summary-strip">
            <div class="kpi-card">
                <div>
                    <div class="kpi-label">OPENING BALANCE</div>
                    <div class="kpi-number">₹ {{ number_format($report['opening_balance'], 2) }}</div>
                </div>
                <div class="z-icon-tile z-tone-neutral">
                    <i class="dripicons-flag u-fs-18px"></i>
                </div>
            </div>
            <div class="kpi-card">
                <div>
                    <div class="kpi-label">PERIOD DEBITS</div>
                    <div class="kpi-number u-c-info">₹ {{ number_format($report['total_debit'], 2) }}</div>
                </div>
                <div class="z-icon-tile z-tone-info">
                    <i class="dripicons-arrow-up u-fs-18px"></i>
                </div>
            </div>
            <div class="kpi-card">
                <div>
                    <div class="kpi-label">PERIOD CREDITS</div>
                    <div class="kpi-number u-c-warning">₹ {{ number_format($report['total_credit'], 2) }}</div>
                </div>
                <div class="z-icon-tile z-tone-warning">
                    <i class="dripicons-arrow-down u-fs-18px"></i>
                </div>
            </div>
            <div class="kpi-card">
                <div>
                    <div class="kpi-label">CLOSING BALANCE</div>
                    <div class="kpi-number u-c-success">₹ {{ number_format($report['closing_balance'], 2) }}</div>
                </div>
                <div class="z-icon-tile z-tone-success">
                    <i class="dripicons-checkmark u-fs-18px"></i>
                </div>
            </div>
        </div>

        <!-- Ledger Transactions Table -->
        <div class="desk-card-wrapper">
            <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                <h5 class="font-weight-bold mb-0 u-c-text u-fs-15px">
                    {{ $report['account']->code }} - {{ $report['account']->name }}
                </h5>
                <span class="badge badge-pill badge-soft-primary u-fs-11px u-p-4px-8px">
                    {{ ucfirst($report['account']->type) }} &bull; {{ str_replace('_', ' ', $report['account']->sub_type) }}
                </span>
            </div>
            <div class="table-responsive">
                <table class="table ledger-grid-table mb-0">
                <caption class="sr-only">General ledger</caption>
                    <thead>
                        <tr>
                            <th scope="col">Date</th>
                            <th scope="col">Entry #</th>
                            <th scope="col">Reference</th>
                            <th scope="col">Memo / Description</th>
                            <th scope="col" class="text-right">Debit</th>
                            <th scope="col" class="text-right">Credit</th>
                            <th scope="col" class="text-right">Running Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Opening Balance Row -->
                        <tr class="u-bg-surface-3 u-fw-700">
                            <td>{{ $startDate ?: '-' }}</td>
                            <td colspan="3" class="text-muted">OPENING BALANCE</td>
                            <td class="text-right">-</td>
                            <td class="text-right">-</td>
                            <td class="text-right font-monospace">₹ {{ number_format($report['opening_balance'], 2) }}</td>
                        </tr>
                        @forelse($report['transactions'] as $tx)
                            <tr>
                                <td>{{ $tx['date'] }}</td>
                                <td class="font-monospace font-weight-bold text-primary">{{ $tx['entry_number'] }}</td>
                                <td>
                                    <span class="badge badge-light border text-uppercase u-fs-10px u-p-2px-5px">
                                        {{ $tx['reference_type'] }}
                                    </span>
                                    @if($tx['reference_no'])
                                        <small class="text-muted d-block">{{ $tx['reference_no'] }}</small>
                                    @endif
                                </td>
                                <td>{{ $tx['memo'] }}</td>
                                <td class="text-right font-weight-600 font-monospace">{{ $tx['debit'] > 0 ? '₹ ' . number_format($tx['debit'], 2) : '-' }}</td>
                                <td class="text-right font-weight-600 font-monospace">{{ $tx['credit'] > 0 ? '₹ ' . number_format($tx['credit'], 2) : '-' }}</td>
                                <td class="text-right font-weight-bold text-dark font-monospace">₹ {{ number_format($tx['running_balance'], 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">No transactions found for this account in the selected date range.</td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot class="u-bg-surface-3 u-fw-700 u-c-text">
                        <tr>
                            <td colspan="4" class="text-right u-fs-11_5px">PERIOD TOTALS:</td>
                            <td class="text-right font-monospace u-c-info">₹ {{ number_format($report['total_debit'], 2) }}</td>
                            <td class="text-right font-monospace u-c-warning">₹ {{ number_format($report['total_credit'], 2) }}</td>
                            <td class="text-right font-monospace u-c-success">₹ {{ number_format($report['closing_balance'], 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    @else
        <div class="desk-card-wrapper text-center py-4" role="status"><h2 class="h6">No account selected</h2><p class="text-muted mb-2">Select an active account to view its ledger. If no accounts are available, review the Chart of Accounts.</p><a href="{{ route('accounting.coa') }}" class="btn btn-outline-primary btn-sm">Chart of Accounts</a></div>
    @endif
</section>

@endsection

@push('css')
    <link rel="stylesheet" href="<?php echo asset('css/commercial-entry.css') . '?v=' . (file_exists(public_path('css/commercial-entry.css')) ? filemtime(public_path('css/commercial-entry.css')) : time()) ?>" type="text/css">
    <link rel="stylesheet" href="<?php echo asset('css/commercial-workspace.css') . '?v=' . (file_exists(public_path('css/commercial-workspace.css')) ? filemtime(public_path('css/commercial-workspace.css')) : time()) ?>" type="text/css">
    <link rel="stylesheet" href="<?php echo asset('css/zolo-erp-neo.css') . '?v=' . (file_exists(public_path('css/zolo-erp-neo.css')) ? filemtime(public_path('css/zolo-erp-neo.css')) : time()) ?>" type="text/css">
    <link rel="stylesheet" href="{{ versioned_asset('css/pages/accounting-general-ledger.css') }}">
@endpush
