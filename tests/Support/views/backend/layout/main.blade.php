{{-- Minimal stand-in for the application layout in tests that run without the Common middleware and the full legacy schema. --}}
<!doctype html><html><head>@stack('css')</head><body>@yield('content')@stack('scripts')</body></html>
