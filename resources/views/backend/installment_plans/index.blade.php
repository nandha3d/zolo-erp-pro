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
        <div class="row align-items-center mb-4">
            <div class="col-md-6">
                <h3 class="font-weight-bold text-dark m-0">Customer Installment Plans (EMI)</h3>
                <p class="text-muted small m-0">Manage customer deferred sales, financing terms, down payments, and monthly collection schedules.</p>
            </div>
            <div class="col-md-6 text-right">
                <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#createInstallmentModal">
                    <i class="dripicons-plus"></i> + Create Installment Plan
                </button>
            </div>
        </div>

        <div class="card border-0 shadow-sm rounded-lg">
            <div class="card-body p-4">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="installment-table">
                        <thead class="bg-light text-muted uppercase small">
                            <tr>
                                <th>Plan ID</th>
                                <th>Sale Ref</th>
                                <th>Customer</th>
                                <th>Total Financed</th>
                                <th>Down Payment</th>
                                <th>Remaining EMI</th>
                                <th>Tenure</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($installmentPlans as $plan)
                                @php
                                    $paidCount = $plan->installments->where('status', 'paid')->count();
                                    $totalCount = $plan->installments->count();
                                    $remainingBal = $plan->total_amount - $plan->down_payment - ($plan->installments->where('status', 'paid')->sum('amount'));
                                @endphp
                                <tr>
                                    <td><span class="badge badge-secondary px-2 py-1 font-mono">EMI-{{ $plan->id }}</span></td>
                                    <td>{{ $plan->sale_id ? 'SALE-'.$plan->sale_id : 'Direct' }}</td>
                                    <td><strong>{{ $plan->customer->name ?? 'Customer #'.$plan->customer_id }}</strong></td>
                                    <td><strong class="text-dark">{{ number_format($plan->total_amount, 2) }}</strong></td>
                                    <td><span class="text-success font-weight-bold">{{ number_format($plan->down_payment, 2) }}</span></td>
                                    <td><span class="text-danger font-weight-bold">{{ number_format($remainingBal, 2) }}</span></td>
                                    <td>
                                        <span class="badge badge-info px-2 py-1">{{ $paidCount }} / {{ $totalCount }} Paid</span>
                                    </td>
                                    <td>
                                        <span class="badge badge-{{ $remainingBal <= 0 ? 'success' : 'warning' }} px-2 py-1">
                                            {{ $remainingBal <= 0 ? 'Fully Paid' : 'Active' }}
                                        </span>
                                    </td>
                                    <td>
                                        <a href="{{ route('installmentplan.show', $plan->id) }}" class="btn btn-outline-primary btn-sm px-3">
                                            <i class="dripicons-preview"></i> View Schedule
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="text-center text-muted py-4">No installment financing plans recorded yet. Click "+ Create Installment Plan" to finance a sale.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Create Installment Plan Modal -->
<div id="createInstallmentModal" tabindex="-1" role="dialog" aria-labelledby="createInstallmentModalLabel" aria-hidden="true" class="modal fade text-left">
    <div role="document" class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <form action="{{ route('installmentplan.store') }}" method="POST">
                @csrf
                <div class="modal-header bg-dark text-white border-0 py-3">
                    <h5 id="createInstallmentModalLabel" class="modal-title font-weight-bold">Create Sale Installment Plan (EMI)</h5>
                    <button type="button" data-dismiss="modal" aria-label="Close" class="close text-white opacity-75"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body p-4">
                    <div class="form-group mb-3">
                        <label class="font-weight-600 small">Select Customer *</label>
                        <select name="customer_id" class="form-control selectpicker" data-live-search="true" required>
                            <option value="">Choose Customer...</option>
                            @foreach($customers as $c)
                                <option value="{{ $c->id }}">{{ $c->name }} ({{ $c->phone_number }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group mb-3">
                        <label class="font-weight-600 small">Linked Sale Reference *</label>
                        <select name="sale_id" class="form-control selectpicker" data-live-search="true" required>
                            <option value="">Choose Sale...</option>
                            @foreach($sales as $s)
                                <option value="{{ $s->id }}">{{ $s->reference_no }} — Total: {{ number_format($s->grand_total, 2) }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="row">
                        <div class="col-md-6 form-group mb-3">
                            <label class="font-weight-600 small">Total Financed Amount *</label>
                            <input type="number" step="any" min="1" name="total_amount" class="form-control" placeholder="Total Sale Amount" required>
                        </div>
                        <div class="col-md-6 form-group mb-3">
                            <label class="font-weight-600 small">Initial Down Payment *</label>
                            <input type="number" step="any" min="0" name="down_payment" class="form-control" placeholder="Down Payment" required>
                        </div>
                    </div>

                    <div class="form-group mb-0">
                        <label class="font-weight-600 small">Financing Duration (Number of Months) *</label>
                        <select name="months" class="form-control" required>
                            <option value="3">3 Months (Quarterly)</option>
                            <option value="6" selected>6 Months (Half-Yearly)</option>
                            <option value="9">9 Months</option>
                            <option value="12">12 Months (1 Year)</option>
                            <option value="24">24 Months (2 Years)</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer border-0 p-3 bg-light">
                    <button type="button" class="btn btn-secondary px-4" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4 font-weight-bold">Generate EMI Schedule</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection
