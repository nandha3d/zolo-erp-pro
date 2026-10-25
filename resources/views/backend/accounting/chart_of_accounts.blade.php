@extends('backend.layout.main')

@section('content')


<x-success-message key="message" />
<x-error-message key="not_permitted" />

<section class="coa-workspace-view">
    <!-- Top Command Bar -->
    <div class="comm-command-bar">
        <div class="comm-title-group">
            <h1><i class="dripicons-network text-primary"></i> Chart of Accounts Command Center</h1>
            <span class="comm-title-badge"><i class="dripicons-tag"></i> Hierarchical Ledger Classification</span>
        </div>
        <ul class="comm-nav-pills">
            <li><a class="nav-link" href="{{ route('accounting.voucher.entry') }}"><i class="dripicons-pencil"></i> Voucher Entry · F9</a></li>
            <li><a class="nav-link" href="{{ route('accounting.journal-entries') }}"><i class="dripicons-align-justify"></i> Journal Entries</a></li>
            <li><a class="nav-link active" href="{{ route('accounting.coa') }}"><i class="dripicons-network"></i> Chart of Accounts</a></li>
            <li><a class="nav-link" href="{{ route('accounting.general-ledger') }}"><i class="dripicons-view-thumb"></i> General Ledger</a></li>
            <li><a class="nav-link" href="{{ route('accounting.trial-balance') }}"><i class="dripicons-scale"></i> Trial Balance</a></li>
            <li><a class="nav-link" href="{{ route('accounting.profit-loss') }}"><i class="dripicons-graph-line"></i> P&amp;L</a></li>
            <li><a class="nav-link" href="{{ route('accounting.balance-sheet') }}"><i class="dripicons-document"></i> Balance Sheet</a></li>
        </ul>
        <div class="comm-actions">
            <button type="button" class="btn btn-primary btn-action-pill u-bg-primary u-bc-primary u-c-xffffff" data-toggle="modal" data-target="#addAccountModal">
                <i class="dripicons-plus"></i> Add Account
            </button>
        </div>
    </div>

    <!-- Account Types Summary Cards -->
    <div class="kpi-summary-strip">
        @php
            $typeStats = [
                'asset' => ['label' => 'Assets', 'tone' => 'info', 'icon' => 'dripicons-wallet'],
                'liability' => ['label' => 'Liabilities', 'tone' => 'danger', 'icon' => 'dripicons-warning'],
                'equity' => ['label' => 'Equity', 'tone' => 'primary', 'icon' => 'dripicons-graph-bar'],
                'revenue' => ['label' => 'Revenue', 'tone' => 'success', 'icon' => 'dripicons-arrow-up'],
                'expense' => ['label' => 'Expenses', 'tone' => 'warning', 'icon' => 'dripicons-arrow-down'],
            ];
        @endphp
        @foreach($typeStats as $typeKey => $stat)
            @php
                $accGroup = $allAccounts->where('type', $typeKey);
                $totalBal = $accGroup->sum(function($a) { return (float)$a->current_balance; });
            @endphp
            <div class="kpi-card">
                <div>
                    <div class="kpi-label">{{ $stat['label'] }}</div>
                    <div class="kpi-number u-fs-16px z-tone-text-{{ $stat['tone'] }}">
                        ₹ {{ number_format($totalBal, 2) }}
                    </div>
                    <div class="u-fs-10_5px u-c-text-muted u-fw-600">{{ $accGroup->count() }} accounts</div>
                </div>
                <div class="z-icon-tile z-tone-{{ $stat['tone'] }}">
                    <i class="{{ $stat['icon'] }} u-fs-18px"></i>
                </div>
            </div>
        @endforeach
    </div>

    <!-- Accounts Table -->
    <div class="desk-card-wrapper">
        <div class="table-responsive">
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
                                    <span class="badge badge-light border text-muted ml-1 u-fs-10px u-p-2px-4px">System</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge badge-pill 
                                    @if($acc->type == 'asset') badge-soft-primary
                                    @elseif($acc->type == 'liability') badge-soft-danger
                                    @elseif($acc->type == 'equity') badge-soft-info
                                    @elseif($acc->type == 'revenue') badge-soft-success
                                    @else badge-soft-warning @endif u-p-4px-8px u-fs-11px"
                                   >
                                    {{ ucfirst($acc->type) }}
                                </span>
                            </td>
                            <td><span class="text-muted text-capitalize">{{ str_replace('_', ' ', $acc->sub_type) }}</span></td>
                            <td class="text-right font-weight-bold font-monospace text-dark">
                                ₹ {{ number_format((float)$acc->current_balance, 2) }}
                            </td>
                            <td>
                                @if($acc->is_active)
                                    <span class="badge badge-success u-bg-success-soft u-c-success u-b-1px-solid-success-border u-fs-10px u-p-3px-6px">Active</span>
                                @else
                                    <span class="badge badge-secondary u-fs-10px u-p-3px-6px">Inactive</span>
                                @endif
                            </td>
                            <td class="text-center">
                                <a href="{{ route('accounting.general-ledger', ['account_id' => $acc->id]) }}" class="btn btn-sm btn-outline-primary u-h-26px u-w-26px u-p-0 u-d-inline-flex u-items-center u-justify-center u-r-4px" title="View Ledger">
                                    <i class="dripicons-preview"></i>
                                </a>
                                <button type="button" class="btn btn-sm btn-outline-secondary coa-edit-btn u-h-26px u-w-26px u-p-0 u-d-inline-flex u-items-center u-justify-center u-r-4px" title="Edit" data-url="{{ route('accounting.coa.update', $acc->id) }}" data-code="{{ $acc->code }}" data-name="{{ $acc->name }}" data-description="{{ $acc->description }}">
                                    <i class="dripicons-pencil"></i>
                                </button>
                                @unless($acc->is_system)
                                <form action="{{ route('accounting.coa.destroy', $acc->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete account {{ $acc->code }}? Accounts with postings, sub-accounts or links cannot be deleted.');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger u-h-26px u-w-26px u-p-0 u-d-inline-flex u-items-center u-justify-center u-r-4px" title="Delete">
                                        <i class="dripicons-trash"></i>
                                    </button>
                                </form>
                                @endunless
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</section>

<!-- Add Account Modal -->
<div id="addAccountModal" tabindex="-1" role="dialog" class="modal fade" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content u-r-8px u-ov-hidden u-b-1px-solid-border-strong">
            <div class="modal-header u-bg-ink u-c-xffffff u-p-12px-18px">
                <h5 class="modal-title font-weight-bold u-fs-15px u-c-xffffff">
                    <i class="dripicons-plus mr-1"></i> Add New Ledger Account
                </h5>
                <button type="button" class="close u-c-xffffff u-op-0_8" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <form action="{{ route('accounting.coa.store') }}" method="POST">
                @csrf
                <div class="modal-body u-p-16px-20px">
                    <div class="form-group mb-2">
                        <label class="font-weight-600 mb-1 u-fs-11_5px u-c-text-2">Account Code <span class="text-danger">*</span></label>
                        <input type="text" name="code" class="form-control form-control-sm u-h-32px u-fs-12_5px" placeholder="e.g. 1050" required>
                    </div>
                    <div class="form-group mb-2">
                        <label class="font-weight-600 mb-1 u-fs-11_5px u-c-text-2">Account Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control form-control-sm u-h-32px u-fs-12_5px" placeholder="e.g. Petty Cash - Branch A" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 form-group mb-2">
                            <label class="font-weight-600 mb-1 u-fs-11_5px u-c-text-2">Account Type <span class="text-danger">*</span></label>
                            <select name="type" class="form-control form-control-sm u-h-32px u-fs-12_5px" required>
                                <option value="asset">Asset</option>
                                <option value="liability">Liability</option>
                                <option value="equity">Equity</option>
                                <option value="revenue">Revenue</option>
                                <option value="expense">Expense</option>
                            </select>
                        </div>
                        <div class="col-md-6 form-group mb-2">
                            <label class="font-weight-600 mb-1 u-fs-11_5px u-c-text-2">Sub-Type <span class="text-danger">*</span></label>
                            <input type="text" name="sub_type" class="form-control form-control-sm u-h-32px u-fs-12_5px" placeholder="e.g. cash, bank, operating_expense" required>
                        </div>
                    </div>
                    <div class="form-group mb-2">
                        <label class="font-weight-600 mb-1 u-fs-11_5px u-c-text-2">Parent Account (Optional)</label>
                        <select name="parent_id" class="form-control form-control-sm u-h-32px u-fs-12_5px">
                            <option value="">-- No Parent (Header Account) --</option>
                            @foreach($allAccounts as $acc)
                                <option value="{{ $acc->id }}">{{ $acc->code }} - {{ $acc->name }} ({{ ucfirst($acc->type) }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group mb-2">
                        <label class="font-weight-600 mb-1 u-fs-11_5px u-c-text-2" data-help="Balance of the ledger on the day you start. New ledgers start at zero; post a dated opening balance through the opening-balance migration.|0.00">Opening Balance</label>
                        <input type="number" step="0.01" name="opening_balance" readonly aria-describedby="opening-balance-policy" class="form-control form-control-sm u-h-32px u-fs-12_5px u-bg-surface-3" value="0.00">
                        <small id="opening-balance-policy" class="text-muted u-fs-10_5px">New accounts start at zero. Dated opening balances require the separate opening-balance migration.</small>
                    </div>
                    <div class="form-group mb-0">
                        <label class="font-weight-600 mb-1 u-fs-11_5px u-c-text-2">Description</label>
                        <textarea name="description" class="form-control form-control-sm u-fs-12_5px" rows="2" placeholder="Optional notes..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 p-3 u-bg-surface-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm font-weight-bold u-bg-primary u-bc-primary">Create Account</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Account Modal -->
<div id="editAccountModal" tabindex="-1" role="dialog" class="modal fade" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content u-r-8px u-ov-hidden u-b-1px-solid-border-strong">
            <div class="modal-header u-bg-ink u-c-xffffff u-p-12px-18px">
                <h5 class="modal-title font-weight-bold u-fs-15px u-c-xffffff">
                    <i class="dripicons-pencil mr-1"></i> Edit Ledger Account <span id="edit-account-code" class="font-monospace"></span>
                </h5>
                <button type="button" class="close u-c-xffffff u-op-0_8" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <form id="editAccountForm" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-body u-p-16px-20px">
                    <div class="form-group mb-2">
                        <label class="font-weight-600 mb-1 u-fs-11_5px u-c-text-2">Account Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control form-control-sm u-h-32px u-fs-12_5px" required>
                    </div>
                    <div class="form-group mb-0">
                        <label class="font-weight-600 mb-1 u-fs-11_5px u-c-text-2">Description</label>
                        <textarea name="description" class="form-control form-control-sm u-fs-12_5px" rows="2"></textarea>
                        <small class="text-muted u-fs-10_5px">Code, type and parent are fixed once created so posted balances stay in the right group.</small>
                    </div>
                </div>
                <div class="modal-footer border-0 p-3 u-bg-surface-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm font-weight-bold u-bg-primary u-bc-primary">Update Account</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script type="text/javascript">
    $(document).on('click', '.coa-edit-btn', function() {
        var $btn = $(this), $form = $('#editAccountForm');
        $form.attr('action', $btn.attr('data-url'));
        $form.find('[name=name]').val($btn.attr('data-name'));
        $form.find('[name=description]').val($btn.attr('data-description'));
        $('#edit-account-code').text($btn.attr('data-code'));
        $('#editAccountModal').modal('show');
    });

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

@push('css')
    <link rel="stylesheet" href="<?php echo asset('css/commercial-entry.css') . '?v=' . (file_exists(public_path('css/commercial-entry.css')) ? filemtime(public_path('css/commercial-entry.css')) : time()) ?>" type="text/css">
    <link rel="stylesheet" href="<?php echo asset('css/commercial-workspace.css') . '?v=' . (file_exists(public_path('css/commercial-workspace.css')) ? filemtime(public_path('css/commercial-workspace.css')) : time()) ?>" type="text/css">
    <link rel="stylesheet" href="<?php echo asset('css/zolo-erp-neo.css') . '?v=' . (file_exists(public_path('css/zolo-erp-neo.css')) ? filemtime(public_path('css/zolo-erp-neo.css')) : time()) ?>" type="text/css">
    <link rel="stylesheet" href="{{ versioned_asset('css/pages/accounting-chart-of-accounts.css') }}">
@endpush
