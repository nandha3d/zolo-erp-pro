<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Financial years — {{ $company->legal_name }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ versioned_asset('css/pages/company-financial-year-setup.css') }}">
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
                    <a href="{{ url('dashboard') }}" class="btn btn-outline u-p-0_35rem-0_75rem u-fs-0_82rem">
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
            <p class="u-fs-0_88rem u-c-page-text-muted u-mb-1rem">
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
                        <a href="{{ url('dashboard') }}" class="btn btn-outline u-mt-1_25rem">
                            Return to Dashboard
                        </a>
                    @endif
                </div>
            </form>
        </div>
    </div>
</body>
</html>
