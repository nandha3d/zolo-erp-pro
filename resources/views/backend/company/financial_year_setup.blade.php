<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Financial years — {{ $company->legal_name }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #4f46e5;
            --primary-hover: #4338ca;
            --primary-light: #eef2ff;
            --success: #059669;
            --success-light: #ecfdf5;
            --success-border: #a7f3d0;
            --text-dark: #0f172a;
            --text-muted: #64748b;
            --bg-page: #f8fafc;
            --bg-card: #ffffff;
            --border-color: #e2e8f0;
            --radius: 12px;
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.06), 0 1px 2px rgba(0,0,0,0.04);
            --shadow-md: 0 4px 6px -1px rgba(0,0,0,0.07), 0 2px 4px -2px rgba(0,0,0,0.05);
            --shadow-lg: 0 10px 15px -3px rgba(0,0,0,0.08), 0 4px 6px -4px rgba(0,0,0,0.04);
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background-color: var(--bg-page);
            color: var(--text-dark);
            line-height: 1.5;
            padding: 2.5rem 1rem 4rem;
        }
        .container {
            max-width: 52rem;
            margin: 0 auto;
        }
        .header-brand {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
        }
        .brand-badge {
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            color: #ffffff;
            font-weight: 700;
            font-size: 1.1rem;
            padding: 0.4rem 0.8rem;
            border-radius: 8px;
            letter-spacing: -0.02em;
            display: inline-flex;
            align-items: center;
            box-shadow: 0 2px 8px rgba(79, 70, 229, 0.3);
        }
        .brand-badge span { color: #a5b4fc; }
        .page-title {
            font-size: 1.75rem;
            font-weight: 700;
            letter-spacing: -0.03em;
            color: var(--text-dark);
            margin-bottom: 0.25rem;
        }
        .page-meta {
            font-size: 0.9rem;
            color: var(--text-muted);
            margin-bottom: 1.5rem;
        }
        .card {
            background: var(--bg-card);
            border-radius: var(--radius);
            border: 1px solid var(--border-color);
            padding: 1.75rem;
            margin-bottom: 1.5rem;
            box-shadow: var(--shadow-sm);
        }
        .card-title {
            font-size: 1.15rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: var(--text-dark);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        /* Proceed Banner */
        .proceed-banner {
            background: var(--success-light);
            border: 1px solid var(--success-border);
            border-radius: var(--radius);
            padding: 1.5rem 1.75rem;
            margin-bottom: 1.75rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1.25rem;
            box-shadow: var(--shadow-sm);
        }
        .proceed-banner-text {
            flex: 1;
        }
        .proceed-banner-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--success);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.25rem;
        }
        .proceed-banner-desc {
            font-size: 0.88rem;
            color: #065f46;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.65rem 1.25rem;
            font-size: 0.95rem;
            font-weight: 600;
            border-radius: 8px;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.15s ease;
            border: 1px solid transparent;
        }
        .btn-proceed {
            background: var(--primary);
            color: #ffffff;
            box-shadow: 0 2px 4px rgba(79, 70, 229, 0.25);
            padding: 0.75rem 1.5rem;
            font-size: 1rem;
            white-space: nowrap;
        }
        .btn-proceed:hover {
            background: var(--primary-hover);
            transform: translateY(-1px);
            box-shadow: 0 4px 10px rgba(79, 70, 229, 0.35);
            color: #ffffff;
        }
        .btn-create {
            background: var(--primary);
            color: #ffffff;
            margin-top: 1.25rem;
        }
        .btn-create:hover {
            background: var(--primary-hover);
        }
        .btn-outline {
            background: transparent;
            color: var(--text-muted);
            border: 1px solid var(--border-color);
        }
        .btn-outline:hover {
            background: #f1f5f9;
            color: var(--text-dark);
        }
        /* Alerts */
        .alert-status {
            background: #f0fdf4;
            color: #166534;
            border: 1px solid #bbf7d0;
            padding: 0.85rem 1.25rem;
            border-radius: 8px;
            font-size: 0.9rem;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .error {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
            padding: 0.85rem 1.25rem;
            border-radius: 8px;
            font-size: 0.9rem;
            margin-bottom: 1.25rem;
        }
        .error ul { padding-left: 1.25rem; }
        /* Table */
        table {
            border-collapse: separate;
            border-spacing: 0;
            width: 100%;
            margin-top: 0.5rem;
        }
        th, td {
            text-align: left;
            padding: 0.75rem 1rem;
            border-bottom: 1px solid var(--border-color);
            font-size: 0.9rem;
        }
        th {
            background: #f8fafc;
            color: var(--text-muted);
            font-weight: 600;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        th:first-child { border-top-left-radius: 6px; }
        th:last-child { border-top-right-radius: 6px; }
        tr:last-child td { border-bottom: none; }
        .badge {
            display: inline-flex;
            align-items: center;
            padding: 0.2rem 0.55rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .badge-open {
            background: #dcfce7;
            color: #15803d;
        }
        .badge-active {
            background: #dbeafe;
            color: #1e40af;
            margin-left: 0.4rem;
        }
        .empty-state {
            color: var(--text-muted);
            font-style: italic;
            padding: 0.5rem 0;
        }
        /* Form fields */
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        .form-full {
            grid-column: span 2;
        }
        .form-group {
            margin-top: 0.5rem;
        }
        label {
            display: block;
            font-size: 0.85rem;
            font-weight: 600;
            color: #334155;
            margin-bottom: 0.4rem;
        }
        input {
            width: 100%;
            font: inherit;
            padding: 0.65rem 0.85rem;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            background: #ffffff;
            color: var(--text-dark);
            outline: none;
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
        }
        input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.15);
        }
        .form-actions {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-top: 0.5rem;
        }
        @media (max-width: 640px) {
            .form-grid { grid-template-columns: 1fr; }
            .form-full { grid-column: span 1; }
            .proceed-banner { flex-direction: column; align-items: stretch; text-align: center; }
            .btn-proceed { width: 100%; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header-brand">
            <div class="brand-badge">zolo<span>ERP</span></div>
        </div>

        <h1 class="page-title">Financial years</h1>
        <p class="page-meta">{{ $company->legal_name }} &middot; Business date: <strong>{{ $today }}</strong> ({{ $company->timezone }})</p>

        @if(session('status'))
            <div class="alert-status" role="status">
                <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span>{{ session('status') }}</span>
            </div>
        @endif

        @if($errors->any())
            <div role="alert" class="error">
                <ul>
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @php
            $activeYear = $years->first(function ($y) use ($today) {
                return $y->status === 'open' && $y->start_date->toDateString() <= $today && $y->end_date->toDateString() >= $today;
            });
        @endphp

        {{-- PROCEED ACTION CALLOUT --}}
        @if($years->isNotEmpty())
            <div class="proceed-banner">
                <div class="proceed-banner-text">
                    <div class="proceed-banner-title">
                        <svg width="22" height="22" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                        </svg>
                        @if($activeYear)
                            Active Financial Year Configured
                        @else
                            Financial Year Available
                        @endif
                    </div>
                    <div class="proceed-banner-desc">
                        @if($activeYear)
                            Current business period: <strong>{{ $activeYear->name }}</strong> ({{ $activeYear->start_date->toDateString() }} &ndash; {{ $activeYear->end_date->toDateString() }}). You can proceed into the system now.
                        @else
                            You have configured financial years. Make sure an open year covers today's business date ({{ $today }}).
                        @endif
                    </div>
                </div>
                <a href="{{ url('dashboard') }}" class="btn btn-proceed" id="btn-proceed-to-dashboard">
                    Proceed to Dashboard &rarr;
                </a>
            </div>
        @endif

        {{-- EXISTING FINANCIAL YEARS CARD --}}
        <div class="card">
            <div class="card-title">
                <span>Existing financial years</span>
                @if($years->isNotEmpty())
                    <a href="{{ url('dashboard') }}" class="btn btn-outline" style="padding: 0.35rem 0.75rem; font-size: 0.82rem;">
                        Go to Dashboard &rarr;
                    </a>
                @endif
            </div>

            @if($years->isNotEmpty())
                <table>
                    <thead>
                        <tr>
                            <th scope="col">Name</th>
                            <th scope="col">Start</th>
                            <th scope="col">End</th>
                            <th scope="col">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($years as $year)
                            @php
                                $isCurrent = ($year->status === 'open' && $year->start_date->toDateString() <= $today && $year->end_date->toDateString() >= $today);
                            @endphp
                            <tr>
                                <td>
                                    <strong>{{ $year->name }}</strong>
                                    @if($isCurrent)
                                        <span class="badge badge-active">Current Active</span>
                                    @endif
                                </td>
                                <td>{{ $year->start_date->toDateString() }}</td>
                                <td>{{ $year->end_date->toDateString() }}</td>
                                <td>
                                    <span class="badge badge-open">{{ $year->status }}</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <p class="empty-state">No financial year exists for this company.</p>
            @endif
        </div>

        {{-- CREATE FINANCIAL YEAR CARD --}}
        <div class="card">
            <div class="card-title">Create financial year</div>
            <p style="font-size: 0.88rem; color: var(--text-muted); margin-bottom: 1rem;">
                Create a year covering the business dates you use. Existing year dates and opening balances are preserved.
            </p>

            <form method="post" action="{{ route('company.financial-years.store', ['company_id' => $company->id]) }}">
                @csrf
                <div class="form-grid">
                    <div class="form-group form-full">
                        <label for="name">Year name</label>
                        <input id="name" name="name" maxlength="100" required value="{{ old('name') }}" placeholder="e.g. 2026-2027">
                    </div>
                    <div class="form-group">
                        <label for="start_date">Start date</label>
                        <input type="date" id="start_date" name="start_date" min="1000-01-01" required value="{{ old('start_date') }}">
                    </div>
                    <div class="form-group">
                        <label for="end_date">End date</label>
                        <input type="date" id="end_date" name="end_date" min="1000-01-01" required value="{{ old('end_date') }}">
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-create">Create financial year</button>
                    @if($years->isNotEmpty())
                        <a href="{{ url('dashboard') }}" class="btn btn-outline" style="margin-top: 1.25rem;">
                            Return to Dashboard
                        </a>
                    @endif
                </div>
            </form>
        </div>
    </div>
</body>
</html>
