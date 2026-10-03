@extends('backend.layout.main')

@section('content')

@if(session()->has('message'))
    <div class="alert alert-success alert-dismissible text-center"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>{{ session()->get('message') }}</div>
@endif
@if(session()->has('not_permitted'))
    <div class="alert alert-danger alert-dismissible text-center"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>{{ session()->get('not_permitted') }}</div>
@endif

<section class="forms">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="font-weight-bold text-dark mb-1">Chart of Accounts (COA)</h3>
                <p class="text-muted mb-0">Manage hierarchical ledger accounts, types, and real-time balances.</p>
            </div>
            <div>
                <button type="button" class="btn btn-primary neo-btn-glow" data-toggle="modal" data-target="#addAccountModal">
                    <i class="dripicons-plus mr-1"></i> Add Account
                </button>
            </div>
        </div>

        <!-- Account Types Summary Cards -->
        <div class="row mb-4">
            @php
                $typeStats = [
                    'asset' => ['label' => 'Assets', 'color' => 'primary', 'icon' => 'dripicons-wallet'],
                    'liability' => ['label' => 'Liabilities', 'color' => 'danger', 'icon' => 'dripicons-warning'],
                    'equity' => ['label' => 'Equity', 'color' => 'info', 'icon' => 'dripicons-graph-bar'],
                    'revenue' => ['label' => 'Revenue', 'color' => 'success', 'icon' => 'dripicons-arrow-up'],
                    'expense' => ['label' => 'Expenses', 'color' => 'warning', 'icon' => 'dripicons-arrow-down'],
                ];
            @endphp
            @foreach($typeStats as $typeKey => $stat)
                @php
                    $accGroup = $allAccounts->where('type', $typeKey);
                    $totalBal = $accGroup->sum(function($a) { return (float)$a->current_balance; });
                @endphp
                <div class="col-md">
                    <div class="card neo-card shadow-sm border-0 mb-3">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="text-uppercase text-muted font-weight-bold" style="font-size: 11px;">{{ $stat['label'] }}</span>
                                    <h4 class="font-weight-bold mb-0 mt-1">{{ number_format($totalBal, 2) }}</h4>
                                    <small class="text-muted">{{ $accGroup->count() }} accounts</small>
                                </div>
                                <div class="badge badge-soft-{{ $stat['color'] }} p-2 rounded-circle">
                                    <i class="{{ $stat['icon'] }} font-weight-bold" style="font-size: 18px;"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <!-- Accounts Table -->
        <div class="card neo-card shadow-sm border-0">
            <div class="card-header bg-white border-0 py-3">
                <h5 class="card-title font-weight-bold mb-0">General Ledger Accounts</h5>
            </div>
            <div class="table-responsive px-3 pb-3">
                <table id="coa-table" class="table neo-table table-hover">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Account Name</th>
                            <th>Category</th>
                            <th>Sub-Type</th>
                            <th class="text-right">Current Balance</th>
                            <th>Status</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($allAccounts as $acc)
                            <tr>
                                <td class="font-weight-bold text-primary font-monospace">{{ $acc->code }}</td>
                                <td>
                                    @if($acc->parent_id)
                                        <span class="text-muted mr-2">&mdash;&mdash;</span>
                                    @endif
                                    <span class="font-weight-600">{{ $acc->name }}</span>
                                    @if($acc->is_system)
                                        <span class="badge badge-light border text-muted ml-1" style="font-size: 10px;">System</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge badge-pill 
                                        @if($acc->type == 'asset') badge-soft-primary
                                        @elseif($acc->type == 'liability') badge-soft-danger
                                        @elseif($acc->type == 'equity') badge-soft-info
                                        @elseif($acc->type == 'revenue') badge-soft-success
                                        @else badge-soft-warning @endif">
                                        {{ ucfirst($acc->type) }}
                                    </span>
                                </td>
                                <td><span class="text-muted text-capitalize">{{ str_replace('_', ' ', $acc->sub_type) }}</span></td>
                                <td class="text-right font-weight-bold font-monospace text-dark">
                                    {{ number_format((float)$acc->current_balance, 2) }}
                                </td>
                                <td>
                                    @if($acc->is_active)
                                        <span class="badge badge-success-soft">Active</span>
                                    @else
                                        <span class="badge badge-secondary">Inactive</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <a href="{{ route('accounting.general-ledger', ['account_id' => $acc->id]) }}" class="btn btn-sm btn-outline-primary btn-action" title="View Ledger">
                                        <i class="dripicons-preview"></i>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>

<!-- Add Account Modal -->
<div id="addAccountModal" tabindex="-1" role="dialog" class="modal fade neo-modal" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title font-weight-bold">Add New Ledger Account</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <form action="{{ route('accounting.coa.store') }}" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="form-group mb-3">
                        <label class="font-weight-600">Account Code <span class="text-danger">*</span></label>
                        <input type="text" name="code" class="form-control" placeholder="e.g. 1050" required>
                    </div>
                    <div class="form-group mb-3">
                        <label class="font-weight-600">Account Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" placeholder="e.g. Petty Cash - Branch A" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 form-group mb-3">
                            <label class="font-weight-600">Account Type <span class="text-danger">*</span></label>
                            <select name="type" class="form-control" required>
                                <option value="asset">Asset</option>
                                <option value="liability">Liability</option>
                                <option value="equity">Equity</option>
                                <option value="revenue">Revenue</option>
                                <option value="expense">Expense</option>
                            </select>
                        </div>
                        <div class="col-md-6 form-group mb-3">
                            <label class="font-weight-600">Sub-Type <span class="text-danger">*</span></label>
                            <input type="text" name="sub_type" class="form-control" placeholder="e.g. cash, bank, operating_expense" required>
                        </div>
                    </div>
                    <div class="form-group mb-3">
                        <label class="font-weight-600">Parent Account (Optional)</label>
                        <select name="parent_id" class="form-control selectpicker" data-live-search="true">
                            <option value="">-- No Parent (Header Account) --</option>
                            @foreach($allAccounts as $acc)
                                <option value="{{ $acc->id }}">{{ $acc->code }} - {{ $acc->name }} ({{ ucfirst($acc->type) }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group mb-3">
                        <label class="font-weight-600">Opening Balance</label>
                        <input type="number" step="0.01" name="opening_balance" class="form-control" placeholder="0.00">
                    </div>
                    <div class="form-group mb-0">
                        <label class="font-weight-600">Description</label>
                        <textarea name="description" class="form-control" rows="2" placeholder="Optional notes..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary neo-btn-glow">Create Account</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script type="text/javascript">
    $(document).ready(function() {
        $('#coa-table').DataTable({
            pageLength: 25,
            ordering: true,
            order: [[0, 'asc']],
            language: {
                search: "",
                searchPlaceholder: "Search accounts...",
                lengthMenu: "Show _MENU_ entries"
            }
        });
    });
</script>
@endpush
