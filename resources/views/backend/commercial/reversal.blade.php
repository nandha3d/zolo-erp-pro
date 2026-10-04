<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reverse {{ $document->reference_no }} · zoloERP</title>
    <link rel="stylesheet" href="{{ asset('css/zolo-erp-neo.css') }}">
    <link rel="stylesheet" href="{{ asset('css/commercial-entry.css') }}">
</head>
<body>
<header class="topbar"><a href="{{ url($kind === 'sale' ? '/sales' : '/purchases') }}">Back to {{ $kind === 'sale' ? 'sales' : 'purchases' }}</a><span>Company {{ $context->companyId }} / Branch {{ $context->branchId }}</span></header>
<main>
    <h1>Reverse {{ $document->reference_no }}</h1>
    <p>The original document remains in history. Reversal restores its stock effects and cancels its accounting entries and settlements.</p>
    <p>Invoice amount: {{ number_format($document->grand_total, 4) }}</p>
    @if($document->reversed_at)<p>This document was already reversed.</p>
    @elseif(!$document->branch_id)<p>This legacy document needs a reviewed migration before reversal.</p>
    @else
    @if($errors->any())<div role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    <form class="panel fields" method="post" action="{{ url('/commercial/'.$kind.'/'.$document->id.'/reverse') }}">
        @csrf
        <label for="reversal-date">Reversal date<input id="reversal-date" name="business_date" type="date" required value="{{ old('business_date', Carbon\CarbonImmutable::now(App\Models\Company::findOrFail($context->companyId)->timezone)->toDateString()) }}"></label>
        <label for="reversal-reason">Reason<textarea id="reversal-reason" name="reason" required minlength="3" maxlength="500" autofocus>{{ old('reason') }}</textarea></label>
        <button class="primary" type="submit">Reverse document</button>
    </form>
    @endif
</main>
</body>
</html>
