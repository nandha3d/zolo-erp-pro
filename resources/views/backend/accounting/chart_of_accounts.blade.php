@extends('backend.layout.main')

@section('content')

<link rel="stylesheet" href="<?php echo asset('css/commercial-entry.css') . '?v=' . (file_exists(public_path('css/commercial-entry.css')) ? filemtime(public_path('css/commercial-entry.css')) : time()) ?>" type="text/css">
<link rel="stylesheet" href="<?php echo asset('css/commercial-workspace.css') . '?v=' . (file_exists(public_path('css/commercial-workspace.css')) ? filemtime(public_path('css/commercial-workspace.css')) : time()) ?>" type="text/css">
<link rel="stylesheet" href="<?php echo asset('css/zolo-erp-neo.css') . '?v=' . (file_exists(public_path('css/zolo-erp-neo.css')) ? filemtime(public_path('css/zolo-erp-neo.css')) : time()) ?>" type="text/css">

<style>
    .coa-workspace-view {
        padding: 12px 18px 24px;
        background: #f8fafc;
        min-height: calc(100vh - 40px);
    }
    body.dark-mode .coa-workspace-view {
        background: #0b0f19;
    }
    .kpi-summary-strip {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 12px;
        margin-bottom: 14px;
    }
    .kpi-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 12px 16px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03);
    }
    body.dark-mode .kpi-card {
        background: #141c2e;
        border-color: #334155;
    }
    .kpi-card .kpi-label {
        font-size: 10px;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        font-weight: 700;
        color: #64748b;
        margin-bottom: 2px;
    }
    .kpi-card .kpi-number {
        font-size: 18px;
        font-weight: 700;
        color: #0f172a;
    }
    body.dark-mode .kpi-card .kpi-number {
        color: #f8fafc;
    }
    .desk-card-wrapper {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
        padding: 14px 16px;
    }
    body.dark-mode .desk-card-wrapper {
        background: #141c2e;
        border-color: #334155;
    }
    #coa-table {
        border-collapse: collapse !important;
        width: 100% !important;
    }
    #coa-table thead th {
        background: #0f172a !important;
        color: #ffffff !important;
        font-size: 10.5px !important;
        font-weight: 700 !important;
        text-transform: uppercase !important;
        letter-spacing: 0.04em !important;
        padding: 9px 12px !important;
        border: none !important;
        white-space: nowrap;
    }
    body.dark-mode #coa-table thead th {
        background: #090d16 !important;
        color: #f8fafc !important;
    }
    #coa-table tbody tr:nth-child(even) {
        background: #f8fafc;
    }
    #coa-table tbody tr:nth-child(odd) {
        background: #ffffff;
    }
    body.dark-mode #coa-table tbody tr:nth-child(even) {
        background: #141c2c;
    }
    body.dark-mode #coa-table tbody tr:nth-child(odd) {
        background: #1e293b;
    }
    #coa-table tbody tr:hover {
        background: #e2e8f0 !important;
    }
    body.dark-mode #coa-table tbody tr:hover {
        background: #27354f !important;
    }
    #coa-table td {
        padding: 9px 12px !important;
        border-bottom: 1px solid #e2e8f0 !important;
        vertical-align: middle !important;
        font-size: 12.5px;
    }
    body.dark-mode #coa-table td {
        border-color: #334155 !important;
        color: #e2e8f0;
    }
    .btn-action-pill {
        height: 28px !important;
        font-size: 11px !important;
        padding: 0 12px !important;
        border-radius: 4px !important;
        font-weight: 600 !important;
        display: inline-flex !important;
        align-items: center !important;
        gap: 5px !important;
    }
</style>

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
            <button type="button" class="btn btn-primary btn-action-pill" data-toggle="modal" data-target="#addAccountModal" style="background:#7c3aed; border-color:#7c3aed; color:#fff;">
                <i class="dripicons-plus"></i> Add Account
            </button>
        </div>
    </div>

    <!-- Account Types Summary Cards -->
    <div class="kpi-summary-strip">
        @php
            $typeStats = [
                'asset' => ['label' => 'Assets', 'color' => '#0284c7', 'bg' => '#e0f2fe', 'icon' => 'dripicons-wallet'],
                'liability' => ['label' => 'Liabilities', 'color' => '#b91c1c', 'bg' => '#fee2e2', 'icon' => 'dripicons-warning'],
                'equity' => ['label' => 'Equity', 'color' => '#7c3aed', 'bg' => '#ede9fe', 'icon' => 'dripicons-graph-bar'],
                'revenue' => ['label' => 'Revenue', 'color' => '#059669', 'bg' => '#d1fae5', 'icon' => 'dripicons-arrow-up'],
                'expense' => ['label' => 'Expenses', 'color' => '#d97706', 'bg' => '#fef3c7', 'icon' => 'dripicons-arrow-down'],
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
                    <div class="kpi-number" style="color: {{ $stat['color'] }}; font-size: 16px;">
                        ₹ {{ number_format($totalBal, 2) }}
                    </div>
                    <div style="font-size: 10.5px; color: #94a3b8; font-weight: 600;">{{ $accGroup->count() }} accounts</div>
                </div>
                <div style="width:36px;height:36px;border-radius:6px;background:{{ $stat['bg'] }};display:flex;align-items:center;justify-content:center;color:{{ $stat['color'] }};">
                    <i class="{{ $stat['icon'] }}" style="font-size:18px;"></i>
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
                                    <span class="badge badge-light border text-muted ml-1" style="font-size: 10px; padding: 2px 4px;">System</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge badge-pill 
                                    @if($acc->type == 'asset') badge-soft-primary
                                    @elseif($acc->type == 'liability') badge-soft-danger
                                    @elseif($acc->type == 'equity') badge-soft-info
                                    @elseif($acc->type == 'revenue') badge-soft-success
                                    @else badge-soft-warning @endif"
                                    style="padding: 4px 8px; font-size: 11px;">
                                    {{ ucfirst($acc->type) }}
                                </span>
                            </td>
                            <td><span class="text-muted text-capitalize">{{ str_replace('_', ' ', $acc->sub_type) }}</span></td>
                            <td class="text-right font-weight-bold font-monospace text-dark">
                                ₹ {{ number_format((float)$acc->current_balance, 2) }}
                            </td>
                            <td>
                                @if($acc->is_active)
                                    <span class="badge badge-success" style="background:#dcfce7; color:#15803d; border:1px solid #bbf7d0; font-size:10px; padding:3px 6px;">Active</span>
                                @else
                                    <span class="badge badge-secondary" style="font-size:10px; padding:3px 6px;">Inactive</span>
                                @endif
                            </td>
                            <td class="text-center">
                                <a href="{{ route('accounting.general-ledger', ['account_id' => $acc->id]) }}" class="btn btn-sm btn-outline-primary" title="View Ledger" style="height:26px; width:26px; padding:0; display:inline-flex; align-items:center; justify-content:center; border-radius:4px;">
                                    <i class="dripicons-preview"></i>
                                </a>
                                <button type="button" class="btn btn-sm btn-outline-secondary coa-edit-btn" title="Edit" data-url="{{ route('accounting.coa.update', $acc->id) }}" data-code="{{ $acc->code }}" data-name="{{ $acc->name }}" data-description="{{ $acc->description }}" style="height:26px; width:26px; padding:0; display:inline-flex; align-items:center; justify-content:center; border-radius:4px;">
                                    <i class="dripicons-pencil"></i>
                                </button>
                                @unless($acc->is_system)
                                <form action="{{ route('accounting.coa.destroy', $acc->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete account {{ $acc->code }}? Accounts with postings, sub-accounts or links cannot be deleted.');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete" style="height:26px; width:26px; padding:0; display:inline-flex; align-items:center; justify-content:center; border-radius:4px;">
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
        <div class="modal-content" style="border-radius: 8px; overflow: hidden; border: 1px solid #cbd5e1;">
            <div class="modal-header" style="background:#0f172a; color:#fff; padding: 12px 18px;">
                <h5 class="modal-title font-weight-bold" style="font-size: 15px; color:#fff;">
                    <i class="dripicons-plus mr-1"></i> Add New Ledger Account
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="color:#fff; opacity:0.8;"><span aria-hidden="true">&times;</span></button>
            </div>
            <form action="{{ route('accounting.coa.store') }}" method="POST">
                @csrf
                <div class="modal-body" style="padding: 16px 20px;">
                    <div class="form-group mb-2">
                        <label class="font-weight-600 mb-1" style="font-size: 11.5px; color: #475569;">Account Code <span class="text-danger">*</span></label>
                        <input type="text" name="code" class="form-control form-control-sm" placeholder="e.g. 1050" required style="height:32px; font-size:12.5px;">
                    </div>
                    <div class="form-group mb-2">
                        <label class="font-weight-600 mb-1" style="font-size: 11.5px; color: #475569;">Account Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control form-control-sm" placeholder="e.g. Petty Cash - Branch A" required style="height:32px; font-size:12.5px;">
                    </div>
                    <div class="row">
                        <div class="col-md-6 form-group mb-2">
                            <label class="font-weight-600 mb-1" style="font-size: 11.5px; color: #475569;">Account Type <span class="text-danger">*</span></label>
                            <select name="type" class="form-control form-control-sm" required style="height:32px; font-size:12.5px;">
                                <option value="asset">Asset</option>
                                <option value="liability">Liability</option>
                                <option value="equity">Equity</option>
                                <option value="revenue">Revenue</option>
                                <option value="expense">Expense</option>
                            </select>
                        </div>
                        <div class="col-md-6 form-group mb-2">
                            <label class="font-weight-600 mb-1" style="font-size: 11.5px; color: #475569;">Sub-Type <span class="text-danger">*</span></label>
                            <input type="text" name="sub_type" class="form-control form-control-sm" placeholder="e.g. cash, bank, operating_expense" required style="height:32px; font-size:12.5px;">
                        </div>
                    </div>
                    <div class="form-group mb-2">
                        <label class="font-weight-600 mb-1" style="font-size: 11.5px; color: #475569;">Parent Account (Optional)</label>
                        <select name="parent_id" class="form-control form-control-sm" style="height:32px; font-size:12.5px;">
                            <option value="">-- No Parent (Header Account) --</option>
                            @foreach($allAccounts as $acc)
                                <option value="{{ $acc->id }}">{{ $acc->code }} - {{ $acc->name }} ({{ ucfirst($acc->type) }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group mb-2">
                        <label class="font-weight-600 mb-1" style="font-size: 11.5px; color: #475569;" data-help="Balance of the ledger on the day you start. New ledgers start at zero; post a dated opening balance through the opening-balance migration.|0.00">Opening Balance</label>
                        <input type="number" step="0.01" name="opening_balance" readonly aria-describedby="opening-balance-policy" class="form-control form-control-sm" value="0.00" style="height:32px; font-size:12.5px; background:#f1f5f9;">
                        <small id="opening-balance-policy" class="text-muted" style="font-size: 10.5px;">New accounts start at zero. Dated opening balances require the separate opening-balance migration.</small>
                    </div>
                    <div class="form-group mb-0">
                        <label class="font-weight-600 mb-1" style="font-size: 11.5px; color: #475569;">Description</label>
                        <textarea name="description" class="form-control form-control-sm" rows="2" placeholder="Optional notes..." style="font-size:12.5px;"></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 p-3" style="background:#f8fafc;">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm font-weight-bold" style="background:#7c3aed; border-color:#7c3aed;">Create Account</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Account Modal -->
<div id="editAccountModal" tabindex="-1" role="dialog" class="modal fade" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 8px; overflow: hidden; border: 1px solid #cbd5e1;">
            <div class="modal-header" style="background:#0f172a; color:#fff; padding: 12px 18px;">
                <h5 class="modal-title font-weight-bold" style="font-size: 15px; color:#fff;">
                    <i class="dripicons-pencil mr-1"></i> Edit Ledger Account <span id="edit-account-code" class="font-monospace"></span>
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="color:#fff; opacity:0.8;"><span aria-hidden="true">&times;</span></button>
            </div>
            <form id="editAccountForm" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-body" style="padding: 16px 20px;">
                    <div class="form-group mb-2">
                        <label class="font-weight-600 mb-1" style="font-size: 11.5px; color: #475569;">Account Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control form-control-sm" required style="height:32px; font-size:12.5px;">
                    </div>
                    <div class="form-group mb-0">
                        <label class="font-weight-600 mb-1" style="font-size: 11.5px; color: #475569;">Description</label>
                        <textarea name="description" class="form-control form-control-sm" rows="2" style="font-size:12.5px;"></textarea>
                        <small class="text-muted" style="font-size: 10.5px;">Code, type and parent are fixed once created so posted balances stay in the right group.</small>
                    </div>
                </div>
                <div class="modal-footer border-0 p-3" style="background:#f8fafc;">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm font-weight-bold" style="background:#7c3aed; border-color:#7c3aed;">Update Account</button>
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
