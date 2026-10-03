@extends('backend.layout.main')
@section('content')

<section class="forms">
    <div class="container-fluid">
        <div class="row align-items-center mb-4">
            <div class="col-md-6">
                <h3 class="font-weight-bold text-dark m-0">Statement of Cash Flows</h3>
                <p class="text-muted small m-0">Comprehensive GAAP-compliant analysis of Operating, Investing, and Financing cash flows.</p>
            </div>
            <div class="col-md-6 text-right">
                <button onclick="window.print();" class="btn btn-outline-dark btn-sm">
                    <i class="dripicons-print"></i> Print Statement
                </button>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="card border-0 shadow-sm rounded-lg mb-4">
            <div class="card-body p-3">
                <form action="{{ route('accounting.cash-flow') }}" method="GET" class="row align-items-end">
                    <div class="col-md-4 form-group mb-0">
                        <label class="small font-weight-600 text-muted">From Date</label>
                        <input type="date" name="start_date" class="form-control form-control-sm" value="{{ $startDate }}">
                    </div>
                    <div class="col-md-4 form-group mb-0">
                        <label class="small font-weight-600 text-muted">To Date</label>
                        <input type="date" name="end_date" class="form-control form-control-sm" value="{{ $endDate }}">
                    </div>
                    <div class="col-md-4 form-group mb-0">
                        <button type="submit" class="btn btn-primary btn-sm btn-block">Generate Cash Flow</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card border-0 shadow-sm rounded-lg">
            <div class="card-body p-4">
                <div class="table-responsive">
                    <table class="table table-bordered mb-0">
                        <thead class="bg-dark text-white">
                            <tr>
                                <th>Cash Flow Activity Category</th>
                                <th class="text-right" style="width: 250px;">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- 1. Operating Activities -->
                            <tr class="bg-light font-weight-bold text-uppercase">
                                <td colspan="2"><i class="dripicons-pulse text-primary mr-1"></i> Cash Flows from Operating Activities</td>
                            </tr>
                            <tr>
                                <td class="pl-4">Cash Received from Customers & Sales</td>
                                <td class="text-right text-success font-weight-bold">+ {{ number_format($operatingDebits, 2) }}</td>
                            </tr>
                            <tr>
                                <td class="pl-4">Cash Paid to Suppliers, Inventory & Operating Expenses</td>
                                <td class="text-right text-danger font-weight-bold">- {{ number_format($operatingCredits, 2) }}</td>
                            </tr>
                            <tr class="table-info font-weight-bold">
                                <td>Net Cash Provided by (Used in) Operating Activities</td>
                                <td class="text-right {{ $netOperatingCash >= 0 ? 'text-success' : 'text-danger' }}">
                                    {{ $netOperatingCash >= 0 ? '+' : '' }}{{ number_format($netOperatingCash, 2) }}
                                </td>
                            </tr>

                            <!-- 2. Investing Activities -->
                            <tr class="bg-light font-weight-bold text-uppercase">
                                <td colspan="2"><i class="dripicons-graph-pie text-info mr-1"></i> Cash Flows from Investing Activities</td>
                            </tr>
                            <tr>
                                <td class="pl-4">Capital Equipment & Fixed Asset Acquisitions</td>
                                <td class="text-right font-weight-bold text-muted">0.00</td>
                            </tr>
                            <tr class="table-info font-weight-bold">
                                <td>Net Cash Provided by (Used in) Investing Activities</td>
                                <td class="text-right text-muted">0.00</td>
                            </tr>

                            <!-- 3. Financing Activities -->
                            <tr class="bg-light font-weight-bold text-uppercase">
                                <td colspan="2"><i class="dripicons-wallet text-warning mr-1"></i> Cash Flows from Financing Activities</td>
                            </tr>
                            <tr>
                                <td class="pl-4">Owner Equity Contributions / Loan Borrowings</td>
                                <td class="text-right font-weight-bold text-muted">0.00</td>
                            </tr>
                            <tr class="table-info font-weight-bold">
                                <td>Net Cash Provided by (Used in) Financing Activities</td>
                                <td class="text-right text-muted">0.00</td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr class="bg-dark text-white font-weight-bold">
                                <td><h5 class="m-0 font-weight-bold text-white">Net Increase / (Decrease) in Cash and Cash Equivalents</h5></td>
                                <td class="text-right">
                                    <h5 class="m-0 font-weight-bold {{ $netCashFlow >= 0 ? 'text-success' : 'text-danger' }}">
                                        {{ $netCashFlow >= 0 ? '+' : '' }}{{ number_format($netCashFlow, 2) }}
                                    </h5>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
</section>

@endsection
