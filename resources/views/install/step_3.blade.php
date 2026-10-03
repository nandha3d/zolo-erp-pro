<!DOCTYPE html>
<html lang="en">

<head>
	<title>zoloERP Installer | Step-3</title>
	<link rel="shortcut icon" type="image/x-icon" href="{{ asset('install-assets/images/favicon.ico') }}">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<link href="{{ asset('install-assets/css/bootstrap.min.css') }}" rel="stylesheet">
	<link href="{{ asset('install-assets/css/font-awesome.min.css') }}" rel="stylesheet">
	<link href="{{ asset('install-assets/css/style.css') }}" rel="stylesheet">
</head>

<body>
	<div class="col-md-6 offset-md-3">
		<div class='wrapper'>
			<header>
				<img src="{{ asset('install-assets/images/logo.png') }}" alt="Logo" style="max-width: 120px;" />
				<h1 class="text-center">zoloERP Auto Installer</h1>

				@include('includes.session_message')
			</header>
			<hr>
			<div class="content">
				<?php
if (isset($_GET['_error'])) {
	if ($_GET['_error'] != '') {
		echo '<h4 class="text-danger">' . $_GET['_error'] . '</h4>';
	}
}
		        ?>
				<form action="{{ route('install-process') }}" method="post">
					@csrf
					<fieldset>
						<label>Database Host</label>
						<input type='text' class="form-control" name="db_host" placeholder="Ex: localhost" required>
						<label>Database Username</label>
						<input type='text' class="form-control" name="db_username" placeholder="Ex: zoloerp_user"
							required>
						<label>Database Password</label>
						<input type='password' class="form-control" name="db_password" placeholder="Kosongkan jika tidak ada password">
						<label>Database Name</label>
						<input type='text' class="form-control" name="db_name" placeholder="Ex: zoloerp_db" required>
						<button type='submit' class='btn btn-primary btn-block'>Submit</button>
					</fieldset>
				</form>
			</div>
			<hr>
			<footer>Copyright &copy; {{ date('Y') }} Animazon's zoloERP. All Rights Reserved.</footer>
		</div>
	</div>


	<script src="{{ asset('install-assets/js/jquery.min.js')}}"></script>
	<script src="{{ asset('install-assets/js/bootstrap.min.js')}}"></script>


</body>

</html>