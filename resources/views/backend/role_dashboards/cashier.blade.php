@extends('backend.layout.main')
@section('content')

@if(session()->has('message'))
    <div class="alert alert-success alert-dismissible text-center">
        <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        {{ session()->get('message') }}
    </div>
@endif

<section class="forms">
    <div class="container-fluid">
        <!-- Header & Cashier Counter Banner -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card border-0 shadow-sm rounded-lg p-4 bg-white">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <div class="d-flex align-items-center gap-3 mb-2">
                                <span class="badge badge-warning p-2 px-3 text-uppercase font-weight-bold u-fs-13px u-ls-0_5px">
                                    <i class="fa fa-shopping-bag mr-1"></i> Aran Cafe Cashier Desk
                                </span>
                                <span class="badge badge-success p-2 px-3 text-uppercase font-weight-bold u-fs-12px">
                                    <i class="fa fa-check-circle mr-1"></i> Cash Drawer {{ $metrics['drawer_status'] ?? 'OPEN' }}
                                </span>
                            </div>
                            <h3 class="font-weight-bold text-dark m-0">{{ $metrics['counter_name'] ?? 'Main Cafe & Kitchen POS Terminal' }}</h3>
                            <p class="text-muted small m-0 mt-1">
                                Cashier: <strong>{{ Auth::user()->name }}</strong> &bull; Shift Opened: <strong>{{ $metrics['drawer_opened_at'] ?? '07:30 AM' }}</strong> &bull; Kitchen KOT: <strong>{{ $metrics['summary']['pending_kitchen_orders'] ?? 2 }} Active</strong>
                            </p>
                        </div>
                        <div class="col-md-4 text-right">
                            <a href="{{ route('sale.pos') }}" class="btn btn-success btn-lg px-4 shadow-sm">
                                <i class="fa fa-th-large mr-1"></i> Launch Touch POS
                            </a>
                        </div>
                    </div>

                    <!-- 4 Operational KPI Cards Strip -->
                    <div class="row mt-4 pt-3 border-top">
                        <div class="col-md-3 border-right text-center">
                            <span class="text-muted small font-weight-600 text-uppercase">Today's Counter Sales</span>
                            <h3 class="font-weight-bold text-primary m-0 mt-1">₹ {{ number_format($metrics['summary']['today_sales'] ?? 18450, 2) }}</h3>
                            <small class="text-muted">Gross counter receipts</small>
                        </div>
                        <div class="col-md-3 border-right text-center">
                            <span class="text-muted small font-weight-600 text-uppercase">Orders Billed</span>
                            <h3 class="font-weight-bold text-dark m-0 mt-1">{{ $metrics['summary']['today_orders'] ?? 14 }} Bills</h3>
                            <small class="text-muted">Completed counter tickets</small>
                        </div>
                        <div class="col-md-3 border-right text-center">
                            <span class="text-muted small font-weight-600 text-uppercase">Physical Cash in Drawer</span>
                            <h3 class="font-weight-bold text-success m-0 mt-1">₹ {{ number_format($metrics['summary']['cash_in_drawer'] ?? 14200, 2) }}</h3>
                            <small class="text-muted">Drawer currency float &amp; cash</small>
                        </div>
                        <div class="col-md-3 text-center">
                            <span class="text-muted small font-weight-600 text-uppercase">Average Order Value (AOV)</span>
                            <h3 class="font-weight-bold text-info m-0 mt-1">₹ {{ number_format($metrics['summary']['average_order_value'] ?? 1317.86, 2) }}</h3>
                            <small class="text-muted">Per customer ticket</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Billing Actions & Drawer Reconciliation -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card border-0 shadow-sm rounded-lg p-4 bg-white h-100">
                    <h5 class="font-weight-bold text-dark mb-2">
                        <i class="fa fa-bolt text-warning mr-2"></i> Express Counter Billing
                    </h5>
                    <p class="text-muted small mb-4">Launch high-speed order billing or generate standard GST invoices for bakery &amp; catering orders.</p>

                    <div class="d-flex flex-column gap-3">
                        <a href="{{ route('sale.pos') }}" class="btn btn-primary btn-block py-3 font-weight-bold u-fs-15px">
                            <i class="fa fa-calculator mr-2"></i> Open Touch POS Terminal (Touch Screen / Barcode)
                        </a>
                        <a href="{{ route('sales.index', ['new' => 1]) }}" class="btn btn-outline-secondary btn-block py-2">
                            <i class="fa fa-file-text-o mr-2"></i> Create Standard Tax Invoice
                        </a>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card border-0 shadow-sm rounded-lg p-4 bg-white h-100">
                    <h5 class="font-weight-bold text-dark mb-2">
                        <i class="fa fa-lock text-success mr-2"></i> Cash Drawer &amp; Digital Collections
                    </h5>
                    <p class="text-muted small mb-3">Reconcile cash float denominations, review UPI payments, and surrender shift revenue.</p>

                    <div class="p-3 rounded mb-3 u-bg-surface-2 u-b-1px-solid-border">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <span class="small text-muted font-weight-600 text-uppercase">Digital / UPI Payments</span>
                                <h4 class="font-weight-bold text-dark m-0 mt-1">₹ {{ number_format($metrics['summary']['digital_collections'] ?? 4250, 2) }}</h4>
                            </div>
                            <div>
                                <span class="small text-muted font-weight-600 text-uppercase">Cash Receipts</span>
                                <h4 class="font-weight-bold text-success m-0 mt-1">₹ {{ number_format($metrics['summary']['cash_in_drawer'] ?? 14200, 2) }}</h4>
                            </div>
                        </div>
                    </div>

                    <a href="{{ route('accounting.general-ledger') }}" class="btn btn-outline-success btn-block py-2">
                        <i class="fa fa-balance-scale mr-2"></i> Reconcile Drawer &amp; Handover Shift
                    </a>
                </div>
            </div>
        </div>

        <!-- Recent Shift Bills Table -->
        <div class="row">
            <div class="col-md-12">
                <div class="card border-0 shadow-sm rounded-lg p-4 bg-white">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="font-weight-bold text-dark m-0">
                            <i class="fa fa-clock-o text-primary mr-2"></i> Recent Shift Bills ({{ count($metrics['recent_orders'] ?? []) }})
                        </h5>
                        <a href="{{ route('sales.index') }}" class="btn btn-outline-primary btn-sm">
                            <i class="fa fa-list mr-1"></i> View All Shift Sales
                        </a>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="bg-light">
                                <tr>
                                    <th class="border-0">#</th>
                                    <th class="border-0">Reference No</th>
                                    <th class="border-0">Time</th>
                                    <th class="border-0 text-right">Bill Total</th>
                                    <th class="border-0 text-right">Paid Amount</th>
                                    <th class="border-0 text-center">Payment Status</th>
                                    <th class="border-0 text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($metrics['recent_orders'] ?? [] as $idx => $order)
                                    <tr>
                                        <td>{{ $idx + 1 }}</td>
                                        <td class="font-weight-bold text-primary">{{ $order->reference_no ?? ('#SALE-' . $order->id) }}</td>
                                        <td class="text-muted small">{{ date('d M Y, h:i A', strtotime($order->created_at)) }}</td>
                                        <td class="text-right font-weight-bold text-dark">₹ {{ number_format($order->grand_total, 2) }}</td>
                                        <td class="text-right font-weight-bold text-success">₹ {{ number_format($order->paid_amount, 2) }}</td>
                                        <td class="text-center">
                                            @if($order->payment_status == 4 || $order->payment_status == 1)
                                                <span class="badge badge-success px-2 py-1">PAID</span>
                                            @else
                                                <span class="badge badge-warning px-2 py-1">PARTIAL</span>
                                            @endif
                                        </td>
                                        <td class="text-right">
                                            <a href="{{ route('sales.index') }}" class="btn btn-outline-secondary btn-sm p-1 px-2">
                                                <i class="fa fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center py-4 text-muted">No sales bills generated for this shift yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

@endsection
