<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>@yield('title') · zoloERP</title><link rel="stylesheet" href="/css/zolo-erp-neo.css"></head>
<body class="erp-operations"><a href="#workspace-content" class="workspace-skip">Skip to content</a><header class="ops-topbar"><a href="/workspace">zoloERP · Home</a><nav aria-label="Workspace"><a href="/workspace">Home</a></nav></header>
<main class="ops-main" id="workspace-content" tabindex="-1">@if(session('status'))<p role="status" class="ops-panel">{{ session('status') }}</p>@endif
@if($errors->any())<section class="ops-panel" role="alert"><h2>Check these fields</h2><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></section>@endif
@yield('content')</main></body></html>
