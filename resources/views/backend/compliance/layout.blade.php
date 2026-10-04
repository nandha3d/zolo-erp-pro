<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}"><title>@yield('title') · zoloERP</title>
<link rel="stylesheet" href="{{ asset('css/zolo-erp-neo.css') }}"><link rel="stylesheet" href="{{ asset('css/commercial-entry.css') }}">
<link rel="stylesheet" href="{{ asset('css/compliance.css') }}">@stack('scripts')</head>
<body><header class="topbar"><a href="{{ url('/dashboard') }}">zoloERP</a><span>Company {{ $context->companyId }} / Branch {{ $context->branchId }}</span>
<nav aria-label="Compliance"><a href="{{ url('/compliance/returns') }}">Returns & notes</a><a href="{{ url('/compliance/gst/report') }}">GST review</a><a href="{{ url('/compliance/setup') }}">Settings</a></nav></header>
<main><div class="heading"><div><p class="eyebrow">COMMERCIAL CONTROL</p><h1>@yield('title')</h1></div></div>
@if(session('message'))<p class="notice" role="status">{{ session('message') }}</p>@endif
@if($errors->any())<div class="notice error" role="alert"><h2>Review these fields</h2><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
@yield('content')</main></body></html>
