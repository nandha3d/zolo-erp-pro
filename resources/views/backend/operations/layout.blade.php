<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}"><title>@yield('title') · zoloERP</title>
    <link rel="stylesheet" href="{{ asset('css/zolo-erp-neo.css') }}">
    <script defer src="{{ asset('js/operations.js') }}"></script>
</head>
<body class="erp-operations">
<header class="ops-topbar"><a href="{{ url('/dashboard') }}">zoloERP</a><span>Company {{ $context->companyId }} · Branch {{ $context->branchId }} · FY {{ $context->financialYearId }}</span></header>
<div class="ops-shell">
<nav class="ops-nav" aria-label="Operations">
    <span class="ops-eyebrow">OPERATIONS</span>
    @php($navigation = app(\App\Services\Platform\CapabilityService::class)->forNavigation($context))
    @if(in_array('manufacturing.bom', $navigation))<a href="{{ url('/operations/manufacturing') }}">BOM &amp; production</a>@endif
    @if(in_array('operations.job_work', $navigation))<a href="{{ url('/operations/job-work') }}">Job Work</a>@endif
    @if(in_array('operations.projects', $navigation))<a href="{{ url('/operations/projects') }}">Sites &amp; projects</a>@endif
    <a href="{{ url('/operations/stock') }}">Stock tools</a><a href="{{ url('/operations/profiles') }}">Business profile</a>
</nav>
<main class="ops-main" id="main-content">
    @if(session('message'))<p class="ops-status" role="status">{{ session('message') }}</p>@endif
    @if($errors->any())<div class="ops-errors" role="alert" tabindex="-1"><strong>Save failed. Review these fields.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @yield('content')
</main>
</div>
</body></html>
