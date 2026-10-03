<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Financial years — {{ $company->legal_name }}</title>
    <style>
        body { font: 1rem/1.5 system-ui, sans-serif; max-width: 48rem; margin: 2rem auto; padding: 0 1rem; color: #172033; }
        label { display: block; margin-top: 1rem; }
        input, button { font: inherit; padding: .5rem; max-width: 100%; box-sizing: border-box; }
        input { width: 22rem; }
        button { margin-top: 1.5rem; cursor: pointer; }
        table { border-collapse: collapse; width: 100%; }
        th, td { text-align: left; padding: .5rem; border-bottom: 1px solid #ccd1da; }
        .error { color: #a31515; }
    </style>
</head>
<body>
    <h1>Financial years</h1>
    <p>{{ $company->legal_name }} · Business date: {{ $today }} ({{ $company->timezone }})</p>
    <p>Create a year covering the business dates you use. Existing year dates and opening balances are preserved.</p>
    @if(session('status')) <p role="status">{{ session('status') }}</p> @endif
    @if($errors->any())
        <div role="alert" class="error"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    @if($years->isNotEmpty())
        <table><caption>Existing financial years</caption><thead><tr><th scope="col">Name</th><th scope="col">Start</th><th scope="col">End</th><th scope="col">Status</th></tr></thead>
        <tbody>@foreach($years as $year)<tr><td>{{ $year->name }}</td><td>{{ $year->start_date->toDateString() }}</td><td>{{ $year->end_date->toDateString() }}</td><td>{{ $year->status }}</td></tr>@endforeach</tbody></table>
    @else
        <p>No financial year exists for this company.</p>
    @endif
    <form method="post" action="{{ route('company.financial-years.store', ['company_id' => $company->id]) }}">
        @csrf
        <label for="name">Year name</label><input id="name" name="name" maxlength="255" required value="{{ old('name') }}">
        <label for="start_date">Start date</label><input type="date" id="start_date" name="start_date" required value="{{ old('start_date') }}">
        <label for="end_date">End date</label><input type="date" id="end_date" name="end_date" required value="{{ old('end_date') }}">
        <br><button type="submit">Create financial year</button>
    </form>
</body>
</html>
