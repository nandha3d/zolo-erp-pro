@extends('backend.layout.main')

@section('content')

<section class="forms">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="font-weight-bold text-dark mb-1">Profit & Loss Statement</h3>
                <p class="text-muted mb-0">Income statement reflecting revenue, cost of goods sold, and operating expenses.</p>
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
                <form action="{{ route('accounting.profit-loss') }}" method="GET" class="form-row align-items-end">
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
                        <a href="{{ route('accounting.profit-loss') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- KPI Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card neo-card border-0 shadow-sm">
                    <div class="card-body p-3">
                        <span class="text-muted text-uppercase font-weight-bold" style="font-size: 11px;">Total Revenue</span>
                        <h4 class="font-weight-bold text-success mb-0 mt-1">{{ number_format($report['total_revenue'], 2) }}</h4>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card neo-card border-0 shadow-sm">
                    <div class="card-body p-3">
                        <span class="text-muted text-uppercase font-weight-bold" style="font-size: 11px;">Cost of Goods Sold</span>
                        <h4 class="font-weight-bold text-warning mb-0 mt-1">{{ number_format($report['total_cogs'], 2) }}</h4>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card neo-card border-0 shadow-sm">
                    <div class="card-body p-3">
                        <span class="text-muted text-uppercase font-weight-bold" style="font-size: 11px;">Gross Profit</span>
                        <h4 class="font-weight-bold text-primary mb-0 mt-1">{{ number_format($report['gross_profit'], 2) }}</h4>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card neo-card border-0 shadow-sm">
                    <div class="card-body p-3">
                        <span class="text-muted text-uppercase font-weight-bold" style="font-size: 11px;">Net Income / (Loss)</span>
                        <h4 class="font-weight-bold {{ $report['net_income'] >= 0 ? 'text-success' : 'text-danger' }} mb-0 mt-1">
                            {{ number_format($report['net_income'], 2) }}
                        </h4>
                    </div>
                </div>
            </div>
        </div>

        <!-- Detailed Statement Card -->
        <div class="card neo-card shadow-sm border-0">
            <div class="card-header bg-white border-0 py-3 text-center">
                <h4 class="font-weight-bold mb-1">{{ $general_setting->site_title ?? 'zoloERP' }}</h4>
                <h5 class="text-muted mb-0">Statement of Profit & Loss</h5>
                <small class="text-muted">Period: {{ $startDate }} to {{ $endDate }}</small>
            </div>
            <div class="card-body p-4">
                
                <!-- 1. Operating Revenue -->
                <h6 class="font-weight-bold text-dark text-uppercase border-bottom pb-2 mb-3">1. Operating Revenue</h6>
                <table class="table table-borderless table-sm mb-4">
                    <tbody>
                        @forelse($report['revenues'] as $rev)
                            <tr>
                                <td class="pl-3" style="width: 70%;">{{ $rev['code'] }} - {{ $rev['name'] }}</td>
                                <td class="text-right font-weight-bold" style="width: 30%;">{{ number_format($rev['amount'], 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="2" class="pl-3 text-muted">No revenue recorded in this period.</td></tr>
                        @endforelse
                    </tbody>
                    <tfoot class="border-top border-bottom font-weight-bold bg-light">
                        <tr>
                            <td>Total Operating Revenue:</td>
                            <td class="text-right text-success">{{ number_format($report['total_revenue'], 2) }}</td>
                        </tr>
                    </tfoot>
                </table>

                <!-- 2. Cost of Goods Sold -->
                <h6 class="font-weight-bold text-dark text-uppercase border-bottom pb-2 mb-3">2. Cost of Goods Sold (COGS)</h6>
                <table class="table table-borderless table-sm mb-4">
                    <tbody>
                        @forelse($report['cogs'] as $cg)
                            <tr>
                                <td class="pl-3" style="width: 70%;">{{ $cg['code'] }} - {{ $cg['name'] }}</td>
                                <td class="text-right font-weight-bold" style="width: 30%;">{{ number_format($cg['amount'], 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="2" class="pl-3 text-muted">No cost of goods sold recorded.</td></tr>
                        @endforelse
                    </tbody>
                    <tfoot class="border-top border-bottom font-weight-bold bg-light">
                        <tr>
                            <td>Total Cost of Goods Sold:</td>
                            <td class="text-right text-warning">({{ number_format($report['total_cogs'], 2) }})</td>
                        </tr>
                    </tfoot>
                </table>

                <!-- Gross Profit Highlight -->
                <div class="d-flex justify-content-between align-items-center p-3 mb-4 rounded bg-soft-primary border">
                    <h5 class="font-weight-bold text-primary mb-0">GROSS PROFIT</h5>
                    <h4 class="font-weight-bold text-primary mb-0">{{ number_format($report['gross_profit'], 2) }}</h4>
                </div>

                <!-- 3. Operating Expenses -->
                <h6 class="font-weight-bold text-dark text-uppercase border-bottom pb-2 mb-3">3. Operating Expenses</h6>
                <table class="table table-borderless table-sm mb-4">
                    <tbody>
                        @forelse($report['operating_expenses'] as $exp)
                            <tr>
                                <td class="pl-3" style="width: 70%;">{{ $exp['code'] }} - {{ $exp['name'] }}</td>
                                <td class="text-right font-weight-bold" style="width: 30%;">{{ number_format($exp['amount'], 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="2" class="pl-3 text-muted">No operating expenses recorded.</td></tr>
                        @endforelse
                    </tbody>
                    <tfoot class="border-top border-bottom font-weight-bold bg-light">
                        <tr>
                            <td>Total Operating Expenses:</td>
                            <td class="text-right text-danger">({{ number_format($report['total_operating_expenses'], 2) }})</td>
                        </tr>
                    </tfoot>
                </table>

                <!-- Net Income Highlight -->
                <div class="d-flex justify-content-between align-items-center p-3 rounded {{ $report['net_income'] >= 0 ? 'bg-soft-success border border-success' : 'bg-soft-danger border border-danger' }}">
                    <div>
                        <h4 class="font-weight-bold mb-0 {{ $report['net_income'] >= 0 ? 'text-success' : 'text-danger' }}">
                            NET {{ $report['net_income'] >= 0 ? 'PROFIT' : 'LOSS' }} FOR PERIOD
                        </h4>
                        <small class="text-muted">Transferable to Balance Sheet Retained Earnings</small>
                    </div>
                    <h3 class="font-weight-bold mb-0 {{ $report['net_income'] >= 0 ? 'text-success' : 'text-danger' }}">
                        {{ number_format($report['net_income'], 2) }}
                    </h3>
                </div>

            </div>
        </div>
    </div>
</section>

@endsection
