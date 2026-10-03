@extends('backend.layout.main')

@section('content')

<section class="forms">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="font-weight-bold text-dark mb-1">Balance Sheet</h3>
                <p class="text-muted mb-0">Financial position statement verifying the fundamental accounting equation: Assets = Liabilities + Equity.</p>
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
                <form action="{{ route('accounting.balance-sheet') }}" method="GET" class="form-row align-items-end">
                    <div class="col-md-6">
                        <label class="font-weight-600 mb-1" style="font-size: 13px;">As of Date</label>
                        <input type="date" name="as_of_date" class="form-control form-control-sm" value="{{ $asOfDate }}">
                    </div>
                    <div class="col-md-6 d-flex">
                        <button type="submit" class="btn btn-sm btn-primary flex-grow-1 mr-2">Update Statement</button>
                        <a href="{{ route('accounting.balance-sheet') }}" class="btn btn-sm btn-outline-secondary">Today</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Equation Check Banner -->
        @if($report['is_balanced'])
            <div class="alert alert-success d-flex align-items-center mb-4 border-0 shadow-sm" role="alert">
                <i class="dripicons-checkmark font-weight-bold mr-2" style="font-size: 18px;"></i>
                <div>
                    <strong>Accounting Equation Balanced:</strong> Total Assets ({{ number_format($report['total_assets'], 2) }}) = Total Liabilities & Equity ({{ number_format($report['total_liabilities_and_equity'], 2) }}).
                </div>
            </div>
        @else
            <div class="alert alert-danger d-flex align-items-center mb-4 border-0 shadow-sm" role="alert">
                <i class="dripicons-warning font-weight-bold mr-2" style="font-size: 18px;"></i>
                <div>
                    <strong>Equation Discrepancy:</strong> Difference of {{ number_format($report['difference'], 2) }} between Total Assets and Total Liabilities & Equity.
                </div>
            </div>
        @endif

        <!-- Two Column Balance Sheet Layout -->
        <div class="row">
            <!-- ASSETS COLUMN -->
            <div class="col-md-6">
                <div class="card neo-card shadow-sm border-0 h-100">
                    <div class="card-header bg-white border-bottom py-3">
                        <h5 class="font-weight-bold text-primary mb-0">ASSETS</h5>
                    </div>
                    <div class="card-body p-3">
                        <table class="table table-borderless table-sm mb-4">
                            <tbody>
                                @forelse($report['assets'] as $asset)
                                    <tr>
                                        <td style="width: 70%;">
                                            <span class="font-monospace text-muted mr-1">{{ $asset['code'] }}</span>
                                            <span class="font-weight-600">{{ $asset['name'] }}</span>
                                            <small class="text-muted d-block text-capitalize">{{ str_replace('_', ' ', $asset['sub_type']) }}</small>
                                        </td>
                                        <td class="text-right font-weight-bold align-middle" style="width: 30%;">
                                            {{ number_format($asset['balance'], 2) }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="2" class="text-muted py-3">No assets recorded.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="card-footer bg-light border-top p-3 d-flex justify-content-between align-items-center">
                        <h5 class="font-weight-bold text-dark mb-0">TOTAL ASSETS</h5>
                        <h4 class="font-weight-bold text-primary mb-0">{{ number_format($report['total_assets'], 2) }}</h4>
                    </div>
                </div>
            </div>

            <!-- LIABILITIES & EQUITY COLUMN -->
            <div class="col-md-6">
                <div class="card neo-card shadow-sm border-0 h-100">
                    <div class="card-header bg-white border-bottom py-3">
                        <h5 class="font-weight-bold text-danger mb-0">LIABILITIES</h5>
                    </div>
                    <div class="card-body p-3">
                        <table class="table table-borderless table-sm mb-4">
                            <tbody>
                                @forelse($report['liabilities'] as $liab)
                                    <tr>
                                        <td style="width: 70%;">
                                            <span class="font-monospace text-muted mr-1">{{ $liab['code'] }}</span>
                                            <span class="font-weight-600">{{ $liab['name'] }}</span>
                                            <small class="text-muted d-block text-capitalize">{{ str_replace('_', ' ', $liab['sub_type']) }}</small>
                                        </td>
                                        <td class="text-right font-weight-bold align-middle" style="width: 30%;">
                                            {{ number_format($liab['balance'], 2) }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="2" class="text-muted py-2">No liabilities recorded.</td></tr>
                                @endforelse
                            </tbody>
                            <tfoot class="border-top font-weight-bold">
                                <tr>
                                    <td>Total Liabilities:</td>
                                    <td class="text-right text-danger">{{ number_format($report['total_liabilities'], 2) }}</td>
                                </tr>
                            </tfoot>
                        </table>

                        <h5 class="font-weight-bold text-info mt-4 mb-2">EQUITY</h5>
                        <table class="table table-borderless table-sm mb-2">
                            <tbody>
                                @forelse($report['equity'] as $eq)
                                    <tr>
                                        <td style="width: 70%;">
                                            <span class="font-monospace text-muted mr-1">{{ $eq['code'] }}</span>
                                            <span class="font-weight-600">{{ $eq['name'] }}</span>
                                        </td>
                                        <td class="text-right font-weight-bold align-middle" style="width: 30%;">
                                            {{ number_format($eq['balance'], 2) }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="2" class="text-muted py-2">No equity recorded.</td></tr>
                                @endforelse
                            </tbody>
                            <tfoot class="border-top font-weight-bold">
                                <tr>
                                    <td>Total Equity:</td>
                                    <td class="text-right text-info">{{ number_format($report['total_equity'], 2) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    <div class="card-footer bg-light border-top p-3 d-flex justify-content-between align-items-center">
                        <h5 class="font-weight-bold text-dark mb-0">TOTAL LIABILITIES & EQUITY</h5>
                        <h4 class="font-weight-bold text-dark mb-0">{{ number_format($report['total_liabilities_and_equity'], 2) }}</h4>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

@endsection
