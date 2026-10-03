<!DOCTYPE html>
<html lang="en">

<head>
    <title>zoloERP Installer | Step-1</title>
    <link rel="shortcut icon" type="image/x-icon" href="{{ asset('install-assets/images/favicon.ico') }}">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="{{ asset('install-assets/css/bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{ asset('install-assets/css/font-awesome.min.css') }}" rel="stylesheet">
    <link href="{{ asset('install-assets/css/style.css') }}" rel="stylesheet">
</head>

<body>
    <div class="col-md-6 offset-md-3">
        <div class="wrapper">
            <header>
                <img src="{{ asset('install-assets/images/logo.png') }}" alt="Logo" style="max-width: 120px;" />
                <h1 class="text-center">zoloERP Auto Installer</h1>
            </header>
            <hr>
            <div class="content text-center">
                <p class="text-muted mb-4">Welcome to Animazon's zoloERP — Comprehensive Commercial, Financial & Accounting Platform.</p>
                <a href="{{ route('install-step-2') }}" class="btn btn-primary btn-lg px-4">Let's Start</a>
            </div>
            <hr>
            <footer>Copyright &copy; {{ date('Y') }} Animazon's zoloERP. All Rights Reserved.</footer>
        </div>
    </div>
</body>

</html>