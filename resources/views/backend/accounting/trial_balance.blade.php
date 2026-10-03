@extends('backend.layout.main')

@section('content')

<section class="forms">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="font-weight-bold text-dark mb-1">Trial Balance</h3>
                <p class="text-muted mb-0">Summary of all general ledger account ending debit and credit balances.</p>
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
                <form action="{{ route('accounting.trial-balance') }}" method="GET" class="form-row align-items-end">
                    <div class="col-md-4">
                        <label class="font-weight-600 mb-1" style="font-size: 13px;">Start Date</label>
                        <input type="date" name="start_date" class="form-control form-control-sm" value="{{ $startDate }}">
                    </div>
                    <div class="col-md-4">
                        <label class="font-weight-600 mb-1" style="font-size: 13px;">End Date</label>
                        <input type="date" name="end_date" class="form-control form-control-sm" value="{{ $endDate }}">
                    </div>
                    <div class="col-md-4 d-flex">
                        <button type="submit" class="btn btn-sm btn-primary flex-grow-1 mr-2">Generate Report</button>
                        <a href="{{ route('accounting.trial-balance') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Validation Banner -->
        @if($report['is_balanced'])
            <div class="alert alert-success d-flex align-items-center mb-4 border-0 shadow-sm" role="alert">
                <i class="dripicons-checkmark font-weight-bold mr-2" style="font-size: 18px;"></i>
                <div>
                    <strong>Balanced Trial Balance:</strong> Total Debits ({{ number_format($report['total_debit'], 2) }}) exactly equal Total Credits ({{ number_format($report['total_credit'], 2) }}).
                </div>
            </div>
        @else
            <div class="alert alert-danger d-flex align-items-center mb-4 border-0 shadow-sm" role="alert">
                <i class="dripicons-warning font-weight-bold mr-2" style="font-size: 18px;"></i>
                <div>
                    <strong>Trial Balance Out of Balance:</strong> Difference of {{ number_format($report['difference'], 2) }} detected between debits and credits.
                </div>
            </div>
        @endif

        <!-- Trial Balance Table -->
        <div class="card neo-card shadow-sm border-0">
            <div class="card-header bg-white border-0 py-3 text-center">
                <h4 class="font-weight-bold mb-1">{{ $general_setting->site_title ?? 'zoloERP' }}</h4>
                <h5 class="text-muted mb-0">Trial Balance Statement</h5>
                @if($startDate || $endDate)
                    <small class="text-muted">Period: {{ $startDate ?: 'Inception' }} to {{ $endDate ?: 'Present' }}</small>
                @endif
            </div>
            <div class="table-responsive px-3 pb-3">
                <table class="table neo-table table-bordered mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th style="width: 15%;">Account Code</th>
                            <th>Account Name</th>
                            <th style="width: 15%;">Type</th>
                            <th style="width: 20%;" class="text-right">Debit Balance</th>
                            <th style="width: 20%;" class="text-right">Credit Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($report['accounts'] as $row)
                            <tr>
                                <td class="font-monospace font-weight-bold text-primary">{{ $row['code'] }}</td>
                                <td class="font-weight-600">
                                    <a href="{{ route('accounting.general-ledger', ['account_id' => $row['id']]) }}" class="text-dark">
                                        {{ $row['name'] }}
                                    </a>
                                </td>
                                <td>
                                    <span class="badge badge-pill 
                                        @if($row['type'] == 'asset') badge-soft-primary
                                        @elseif($row['type'] == 'liability') badge-soft-danger
                                        @elseif($row['type'] == 'equity') badge-soft-info
                                        @elseif($row['type'] == 'revenue') badge-soft-success
                                        @else badge-soft-warning @endif">
                                        {{ ucfirst($row['type']) }}
                                    </span>
                                </td>
                                <td class="text-right font-weight-bold">
                                    {{ $row['debit'] > 0 ? number_format($row['debit'], 2) : '-' }}
                                </td>
                                <td class="text-right font-weight-bold">
                                    {{ $row['credit'] > 0 ? number_format($row['credit'], 2) : '-' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted">No account activity recorded for this period.</td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot class="bg-light font-weight-bold text-dark" style="font-size: 15px;">
                        <tr>
                            <td colspan="3" class="text-right">TOTAL:</td>
                            <td class="text-right text-primary">{{ number_format($report['total_debit'], 2) }}</td>
                            <td class="text-right text-primary">{{ number_format($report['total_credit'], 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</section>

@endsection
