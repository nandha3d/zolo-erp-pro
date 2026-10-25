@extends('backend.layout.main')
@section('content')

<link rel="stylesheet" href="<?php echo asset('css/commercial-entry.css') . '?v=' . (file_exists(public_path('css/commercial-entry.css')) ? filemtime(public_path('css/commercial-entry.css')) : time()) ?>" type="text/css">
<link rel="stylesheet" href="<?php echo asset('css/commercial-workspace.css') . '?v=' . (file_exists(public_path('css/commercial-workspace.css')) ? filemtime(public_path('css/commercial-workspace.css')) : time()) ?>" type="text/css">
<link rel="stylesheet" href="<?php echo asset('css/zolo-erp-neo.css') . '?v=' . (file_exists(public_path('css/zolo-erp-neo.css')) ? filemtime(public_path('css/zolo-erp-neo.css')) : time()) ?>" type="text/css">

<style>
    .ageing-workspace-view {
        padding: 12px 18px 24px;
        background: #f8fafc;
        min-height: calc(100vh - 40px);
    }
    body.dark-mode .ageing-workspace-view {
        background: #0b0f19;
    }
    .kpi-summary-strip {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
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
        font-size: 17px;
        font-weight: 700;
        color: #0f172a;
    }
    body.dark-mode .kpi-card .kpi-number {
        color: #f8fafc;
    }
    .desk-filter-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 10px 14px;
        margin-bottom: 14px;
        box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03);
    }
    body.dark-mode .desk-filter-card {
        background: #141c2e;
        border-color: #334155;
    }
    .desk-card-wrapper {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
        padding: 18px 20px;
    }
    body.dark-mode .desk-card-wrapper {
        background: #141c2e;
        border-color: #334155;
    }
    .ageing-table {
        border-collapse: collapse !important;
        width: 100% !important;
    }
    .ageing-table thead th {
        background: var(--z-table-head-bg, #022051) !important;
        color: var(--z-table-head-text, #ffffff) !important;
        font-size: 10.5px !important;
        font-weight: 700 !important;
        text-transform: uppercase !important;
        letter-spacing: 0.04em !important;
        padding: 9px 12px !important;
        border: none !important;
    }
    body.dark-mode .ageing-table thead th {
        background: var(--z-table-head-bg, #022051) !important;
        color: var(--z-table-head-text, #ffffff) !important;
    }
    .ageing-table tbody tr:nth-child(even) {
        background: var(--z-table-stripe, #f8fafc);
    }
    .ageing-table tbody tr:nth-child(odd) {
        background: var(--z-surface, #ffffff);
    }
    body.dark-mode .ageing-table tbody tr:nth-child(even) {
        background: #141c2c;
    }
    body.dark-mode .ageing-table tbody tr:nth-child(odd) {
        background: #1e293b;
    }
    .ageing-table tbody tr:hover {
        background: #e2e8f0 !important;
    }
    body.dark-mode .ageing-table tbody tr:hover {
        background: #27354f !important;
    }
    .ageing-table td {
        padding: 9px 12px !important;
        border-bottom: 1px solid #e2e8f0 !important;
        vertical-align: middle !important;
        font-size: 12.5px;
    }
    body.dark-mode .ageing-table td {
        border-color: #334155 !important;
        color: #e2e8f0;
    }
</style>

<section class="ageing-workspace-view">
    <!-- Top Command Bar -->
    <div class="comm-command-bar">
        <div class="comm-title-group">
            <h1><i class="dripicons-clock text-primary"></i> Receivables &amp; Payables Ageing</h1>
            <span class="comm-title-badge"><i class="dripicons-tag"></i> Maturity Bucketing &amp; Outstanding Debt Analysis</span>
        </div>
        <ul class="comm-nav-pills">
            <li><a class="nav-link" href="{{ route('accounting.vouchers') }}"><i class="dripicons-home"></i> Voucher Hub</a></li>
            <li><a class="nav-link" href="{{ route('accounting.general-ledger') }}"><i class="dripicons-view-thumb"></i> General Ledger</a></li>
            <li><a class="nav-link active" href="{{ route('accounting.ageing') }}"><i class="dripicons-clock"></i> Ageing</a></li>
            <li><a class="nav-link" href="{{ route('accounting.day-book') }}"><i class="dripicons-calendar"></i> Day Book</a></li>
            <li><a class="nav-link" href="{{ route('accounting.cash-book') }}"><i class="dripicons-wallet"></i> Cash Book</a></li>
        </ul>
        <div class="comm-actions">
            <a href="{{ route('accounting.collections') }}" class="btn btn-outline-primary btn-sm">Collections</a>
            <a href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}" class="btn btn-outline-secondary btn-sm">Export all filtered rows</a>
            <button onclick="window.print();" class="btn btn-outline-secondary btn-sm" style="height:28px; font-size:11px;">
                <i class="dripicons-print"></i> Print Report
            </button>
        </div>
    </div>

    <!-- Filter Card -->
    <div class="desk-filter-card">
        <form method="GET" class="m-0">
            <div class="row align-items-end" style="margin: 0 -6px;">
                <div class="col-md-4" style="padding: 0 6px;">
                    <label for="ageing-party" class="font-weight-600 mb-1" style="font-size: 11px; text-transform: uppercase; color: #64748b;">Party Type</label>
                    <select name="party_type" id="ageing-party" class="form-control form-control-sm" style="height:32px; font-size:12px;">
                        <option value="customer" @selected($report['party_type'] === 'customer')>Customers (Receivables)</option>
                        <option value="supplier" @selected($report['party_type'] === 'supplier')>Suppliers (Payables)</option>
                    </select>
                </div>
                <div class="col-md-4" style="padding: 0 6px;">
                    <label for="ageing-date" class="font-weight-600 mb-1" style="font-size: 11px; text-transform: uppercase; color: #64748b;">As of Date</label>
                    <input class="form-control form-control-sm" id="ageing-date" name="as_of_date" type="date" value="{{ $report['as_of_date'] }}" required style="height:32px; font-size:12px;">
                </div>
                <div class="col-md-4 d-flex" style="padding: 0 6px;">
                    <button class="btn btn-primary btn-sm flex-grow-1" type="submit" style="height:32px; font-size:12px; font-weight:600;">
                        <i class="dripicons-search"></i> Show Ageing
                    </button>
                </div>
            </div>
            <div class="row mt-2">
                @foreach(['party_id' => 'Party', 'agent_id' => 'Agent (invoice/current)', 'area_id' => 'Current area', 'salesman_id' => 'Invoice salesman', 'ledger_group_id' => 'Ledger parent group'] as $field => $label)
                    <div class="col-md-2"><label for="ageing-{{ $field }}">{{ $label }}</label><select class="form-control form-control-sm" name="{{ $field }}" id="ageing-{{ $field }}"><option value="">All</option>@foreach($options[$field] as $option)<option value="{{ $option->id }}" @selected((string)request($field) === (string)$option->id)>{{ $option->name }}</option>@endforeach</select></div>
                @endforeach
                <div class="col-md-2"><label for="ageing-cutoffs">Bucket cutoffs (days)</label><input class="form-control form-control-sm" id="ageing-cutoffs" value="{{ implode(',', $report['bucket_cutoffs']) }}" pattern="[0-9]+(,[0-9]+)*" required></div>
                <span id="ageing-bucket-inputs">@foreach($report['bucket_cutoffs'] as $cutoff)<input type="hidden" name="bucket_cutoffs[]" value="{{ $cutoff }}">@endforeach</span>
            </div>
        </form>
    </div>
    <div class="alert {{ $report['reconciliation']['is_reconciled'] ? 'alert-success' : 'alert-warning' }} py-2">
        <strong>Branch reconciliation:</strong> Control {{ $report['reconciliation']['control_balance'] }};
        open items including credits {{ $report['reconciliation']['open_item_balance'] }};
        difference {{ $report['reconciliation']['difference'] }}. Filtered net {{ $report['reconciliation']['filtered_balance'] }}.
        <small class="d-block">{{ $report['reconciliation']['scope'] }} {{ $report['metadata_basis'] }}</small>
    </div>

    <!-- KPI Summary Strip -->
    <div class="kpi-summary-strip">
        @foreach($report['totals'] as $bucket => $amount)
            <div class="kpi-card">
                <div>
                    <div class="kpi-label">{{ strtoupper($bucket) }}{{ $bucket === 'credits' ? '' : ' DAYS' }}</div>
                    <div class="kpi-number font-monospace" style="font-size: 16px;">
                        ₹ {{ is_numeric($amount) ? number_format((float)$amount, 2) : $amount }}
                    </div>
                </div>
                <div style="width:34px;height:34px;border-radius:6px;background:#f1f5f9;display:flex;align-items:center;justify-content:center;color:#475569;">
                    <i class="dripicons-clock" style="font-size:16px;"></i>
                </div>
            </div>
        @endforeach
    </div>

    <!-- Ageing Details Table -->
    <div class="desk-card-wrapper">
        <div class="table-responsive">
            <table class="table ageing-table mb-0">
                <thead>
                    <tr>
                        <th scope="col">Document / Bill</th>
                        <th scope="col">Party</th>
                        <th scope="col">Document Date</th>
                        <th scope="col">Due Date</th>
                        <th scope="col" class="text-center">Days Overdue</th>
                        <th scope="col">Aging Bucket</th>
                        <th scope="col" class="text-right">Open Amount</th>
                        <th scope="col">Agent / Area / Salesman / Group</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($report['rows'] as $row)
                        <tr>
                            <td><span class="font-weight-bold font-monospace text-primary">{{ $row['item']->document_no }}</span></td>
                            <td class="font-weight-600">{{ $row['party_name'] }}</td>
                            <td>{{ $row['document_date'] }}</td>
                            <td>{{ $row['item']->due_date->format('Y-m-d') }}</td>
                            <td class="text-center font-monospace">{{ $row['days'] }}</td>
                            <td>
                                <span class="badge badge-secondary" style="font-size: 11px; padding: 3px 6px;">
                                    {{ $row['bucket'] }}
                                </span>
                            </td>
                            <td class="text-right font-monospace font-weight-bold" style="color: #0f172a;">
                                ₹ {{ number_format((float)$row['open_amount'], 2) }}
                            </td>
                            <td>{{ $row['agent_name'] ?? '—' }} ({{ $row['agent_basis'] }}) / {{ $row['area_name'] ?? '—' }} / {{ $row['salesman_name'] ?? '—' }} / {{ $row['ledger_group_name'] ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">No open items as of this date.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</section>
<script>
document.getElementById('ageing-cutoffs').addEventListener('input', function () {
    const container = document.getElementById('ageing-bucket-inputs');
    container.replaceChildren();
    this.value.split(',').forEach(value => { const input = document.createElement('input'); input.type = 'hidden'; input.name = 'bucket_cutoffs[]'; input.value = value.trim(); container.append(input); });
});
</script>

@endsection
