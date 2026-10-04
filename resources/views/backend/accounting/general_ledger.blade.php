@extends('backend.layout.main')

@section('content')

<section class="forms">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="font-weight-bold text-dark mb-1">General Ledger</h3>
                <p class="text-muted mb-0">Detailed chronological audit trail and running balance for any individual ledger account.</p>
            </div>
            <div>
                <button type="button" class="btn btn-outline-secondary neo-btn" onclick="window.print()">
                    <i class="dripicons-print mr-1"></i> Print / PDF
                </button>
            </div>
        </div>

        <!-- Filter Card -->
        <div class="card neo-card shadow-sm border-0 mb-4 d-print-none">
            <div class="card-body p-3">
                <form action="{{ route('accounting.general-ledger') }}" method="GET" class="form-row align-items-end">
                    <div class="col-md-4">
                        <label class="font-weight-600 mb-1" style="font-size: 13px;">Select Account</label>
                        <select name="account_id" class="form-control form-control-sm selectpicker" data-live-search="true" required>
                            @foreach($accounts as $acc)
                                <option value="{{ $acc->id }}" {{ $accountId == $acc->id ? 'selected' : '' }}>
                                    {{ $acc->code }} - {{ $acc->name }} ({{ ucfirst($acc->type) }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="font-weight-600 mb-1" style="font-size: 13px;">Start Date</label>
                        <input type="date" name="start_date" class="form-control form-control-sm" value="{{ $startDate }}">
                    </div>
                    <div class="col-md-3">
                        <label class="font-weight-600 mb-1" style="font-size: 13px;">End Date</label>
                        <input type="date" name="end_date" class="form-control form-control-sm" value="{{ $endDate }}">
                    </div>
                    <div class="col-md-2 d-flex">
                        <button type="submit" class="btn btn-sm btn-primary flex-grow-1">Search</button>
                    </div>
                </form>
            </div>
        </div>

        @if($report)
            <p><a href="{{ route('accounting.monthly-ledger', $accountId) }}">View monthly breakup</a></p>
            <!-- Account Summary Header Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card neo-card border-0 shadow-sm">
                        <div class="card-body p-3">
                            <span class="text-muted text-uppercase font-weight-bold" style="font-size: 11px;">Opening Balance</span>
                            <h4 class="font-weight-bold mb-0 mt-1">{{ number_format($report['opening_balance'], 2) }}</h4>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card neo-card border-0 shadow-sm">
                        <div class="card-body p-3">
                            <span class="text-muted text-uppercase font-weight-bold" style="font-size: 11px;">Period Debits</span>
                            <h4 class="font-weight-bold text-primary mb-0 mt-1">{{ number_format($report['total_debit'], 2) }}</h4>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card neo-card border-0 shadow-sm">
                        <div class="card-body p-3">
                            <span class="text-muted text-uppercase font-weight-bold" style="font-size: 11px;">Period Credits</span>
                            <h4 class="font-weight-bold text-info mb-0 mt-1">{{ number_format($report['total_credit'], 2) }}</h4>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card neo-card border-0 shadow-sm">
                        <div class="card-body p-3">
                            <span class="text-muted text-uppercase font-weight-bold" style="font-size: 11px;">Closing Balance</span>
                            <h4 class="font-weight-bold text-success mb-0 mt-1">{{ number_format($report['closing_balance'], 2) }}</h4>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Ledger Transactions Table -->
            <div class="card neo-card shadow-sm border-0">
                <div class="card-header bg-white border-bottom py-3">
                    <h5 class="card-title font-weight-bold mb-0">
                        {{ $report['account']->code }} - {{ $report['account']->name }}
                        <span class="badge badge-pill badge-soft-primary ml-2">{{ ucfirst($report['account']->type) }}</span>
                    </h5>
                </div>
                <div class="table-responsive">
                    <table class="table neo-table table-hover mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th>Date</th>
                                <th>Entry #</th>
                                <th>Reference</th>
                                <th>Memo / Description</th>
                                <th class="text-right">Debit</th>
                                <th class="text-right">Credit</th>
                                <th class="text-right">Running Balance</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Opening Balance Row -->
                            <tr class="bg-light font-weight-bold">
                                <td>{{ $startDate ?: '-' }}</td>
                                <td colspan="3">OPENING BALANCE</td>
                                <td class="text-right">-</td>
                                <td class="text-right">-</td>
                                <td class="text-right">{{ number_format($report['opening_balance'], 2) }}</td>
                            </tr>
                            @forelse($report['transactions'] as $tx)
                                <tr>
                                    <td>{{ $tx['date'] }}</td>
                                    <td class="font-monospace font-weight-bold text-primary">{{ $tx['entry_number'] }}</td>
                                    <td>
                                        <span class="badge badge-light border text-uppercase" style="font-size: 10px;">
                                            {{ $tx['reference_type'] }}
                                        </span>
                                        @if($tx['reference_no'])
                                            <small class="text-muted d-block">{{ $tx['reference_no'] }}</small>
                                        @endif
                                    </td>
                                    <td>{{ $tx['memo'] }}</td>
                                    <td class="text-right font-weight-600">{{ $tx['debit'] > 0 ? number_format($tx['debit'], 2) : '-' }}</td>
                                    <td class="text-right font-weight-600">{{ $tx['credit'] > 0 ? number_format($tx['credit'], 2) : '-' }}</td>
                                    <td class="text-right font-weight-bold text-dark">{{ number_format($tx['running_balance'], 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-muted">No transactions found for this account in the selected date range.</td>
                                </tr>
                            @endforelse
                        </tbody>
                        <tfoot class="bg-light font-weight-bold text-dark">
                            <tr>
                                <td colspan="4" class="text-right">PERIOD TOTALS:</td>
                                <td class="text-right">{{ number_format($report['total_debit'], 2) }}</td>
                                <td class="text-right">{{ number_format($report['total_credit'], 2) }}</td>
                                <td class="text-right text-success">{{ number_format($report['closing_balance'], 2) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        @endif
    </div>
</section>

@endsection
