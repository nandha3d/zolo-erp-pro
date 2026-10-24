<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>{{$general_setting->site_title}} — Sign In</title>
    <meta name="description" content="Sign in to your zoloERP enterprise account">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="all,follow">
    <link rel="manifest" href="{{url('manifest.json')}}">
    @if(!config('database.connections.zoloerp_landlord'))
    <link rel="icon" type="image/png" href="{{url('logo', $general_setting->site_logo)}}" />
    <!-- Bootstrap CSS-->
    <link rel="stylesheet" href="<?php echo asset('vendor/bootstrap/css/bootstrap.min.css') ?>" type="text/css">
    <!-- Font Awesome CSS-->
    <link rel="preload" href="<?php echo asset('vendor/font-awesome/css/font-awesome.min.css') ?>" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link href="<?php echo asset('vendor/font-awesome/css/font-awesome.min.css') ?>" rel="stylesheet"></noscript>
    <!-- Modern Auth Stylesheet with dynamic cache-busting -->
    <?php 
      $authCssPath = public_path('css/auth.css');
      $authCssVer = file_exists($authCssPath) ? filemtime($authCssPath) : time();
    ?>
    <link rel="stylesheet" href="<?php echo asset('css/auth.css') . '?v=' . $authCssVer ?>" id="theme-stylesheet" type="text/css">
    @else
    <link rel="icon" type="image/png" href="{{url('../../logo', $general_setting->site_logo)}}" />
    <!-- Bootstrap CSS-->
    <link rel="stylesheet" href="<?php echo asset('../../vendor/bootstrap/css/bootstrap.min.css') ?>" type="text/css">
    <!-- Font Awesome CSS-->
    <link rel="preload" href="<?php echo asset('../../vendor/font-awesome/css/font-awesome.min.css') ?>" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link href="<?php echo asset('../../vendor/font-awesome/css/font-awesome.min.css') ?>" rel="stylesheet"></noscript>
    <!-- login stylesheet-->
    <link rel="stylesheet" href="<?php echo asset('../../css/auth.css') ?>" id="theme-stylesheet" type="text/css">
    @endif

    <!-- Google fonts -->
    @if($general_setting->font_css)
      {!! $general_setting->font_css !!}
    @else
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    @endif

    <!-- Custom CSS from general settings -->
    {!! $general_setting->auth_css !!}
  </head>
  <body>
    <div class="page login-page">
      <div class="container">
        <div class="form-outer">
          <div class="form-inner">
            <!-- Brand Logo & Header -->
            <div class="zolo-auth-logo">
              <div class="zolo-brand-container">
                <div class="zolo-brand-mark">
                  <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M4 6.5H20L10 17.5H20" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                  </svg>
                </div>
                <div class="zolo-brand-badge">zolo<span class="zolo-accent">ERP</span> <span class="zolo-pro-pill">PRO</span></div>
              </div>
              <p class="zolo-auth-tagline">Intelligent Commercial &amp; Financial ERP Platform</p>
            </div>

            <!-- Flash Session Alerts -->
            @if(session()->has('delete_message'))
              <div class="auth-alert auth-alert-danger alert-dismissible fade show" role="alert">
                <div class="auth-alert-icon"><i class="fa fa-trash"></i></div>
                <div class="auth-alert-content">
                  <div class="auth-alert-message">{{ session()->get('delete_message') }}</div>
                </div>
                <button type="button" class="close auth-alert-close" data-dismiss="alert" aria-label="Close">
                  <span aria-hidden="true">&times;</span>
                </button>
              </div>
            @endif

            @if(session()->has('message'))
              <div class="auth-alert auth-alert-success alert-dismissible fade show" role="alert">
                <div class="auth-alert-icon"><i class="fa fa-check-circle"></i></div>
                <div class="auth-alert-content">
                  <div class="auth-alert-message">{!! session()->get('message') !!}</div>
                </div>
                <button type="button" class="close auth-alert-close" data-dismiss="alert" aria-label="Close">
                  <span aria-hidden="true">&times;</span>
                </button>
              </div>
            @endif

            @if(session()->has('not_permitted'))
              <div class="auth-alert auth-alert-danger alert-dismissible fade show" role="alert">
                <div class="auth-alert-icon"><i class="fa fa-ban"></i></div>
                <div class="auth-alert-content">
                  <div class="auth-alert-message">{{ session()->get('not_permitted') }}</div>
                </div>
                <button type="button" class="close auth-alert-close" data-dismiss="alert" aria-label="Close">
                  <span aria-hidden="true">&times;</span>
                </button>
              </div>
            @endif

            @if(session()->has('error'))
              <div class="auth-alert auth-alert-danger alert-dismissible fade show" role="alert">
                <div class="auth-alert-icon"><i class="fa fa-exclamation-triangle"></i></div>
                <div class="auth-alert-content">
                  <div class="auth-alert-title">Sign In Failed</div>
                  <div class="auth-alert-message">{{ session()->get('error') }}</div>
                </div>
                <button type="button" class="close auth-alert-close" data-dismiss="alert" aria-label="Close">
                  <span aria-hidden="true">&times;</span>
                </button>
              </div>
            @endif

            <!-- Sign In Form -->
            <form method="POST" action="{{ route('login') }}" id="login-form">
              @csrf
              <div class="form-group-modern">
                <label for="login-username" class="modern-label">{{__('db.UserName')}} or Email</label>
                <div class="input-icon-wrapper">
                  <i class="fa fa-user input-icon"></i>
                  <input id="login-username" type="text" name="name" required class="form-control modern-input" placeholder="e.g. admin or admin@gmail.com" value="{{ old('name') }}" autocomplete="username" autofocus>
                </div>
              </div>

              <div class="form-group-modern">
                <div class="d-flex justify-content-between align-items-center mb-1">
                  <label for="login-password" class="modern-label mb-0">{{__('db.Password')}}</label>
                  @if($general_setting->disable_forgot_password == 0)
                  <a href="{{ route('password.request') }}" class="forgot-pass-inline">{{__('db.Forgot Password?')}}</a>
                  @endif
                </div>
                <div class="input-icon-wrapper">
                  <i class="fa fa-lock input-icon"></i>
                  <input id="login-password" type="password" name="password" required class="form-control modern-input" placeholder="••••••••" autocomplete="current-password">
                  <span id="togglePassword" class="password-toggle-btn" title="Toggle password visibility">
                    <i class="fa fa-eye-slash"></i>
                  </span>
                </div>
              </div>

              <button type="submit" class="btn btn-primary btn-block btn-modern-submit" id="login-submit-btn">
                <span>{{__('db.LogIn')}}</span>
                <i class="fa fa-arrow-right ml-2"></i>
              </button>
            </form>

            @if($general_setting->disable_signup == 0)
            <p class="register-section">
              {{__('db.Do not have an account?')}}
              <a href="{{url('register')}}" class="signup">{{__('db.Register')}}</a>
            </p>
            @endif
          </div>

          <!-- Centered Footer & Security Badge -->
          <div class="copyrights text-center">
            <div class="auth-security-badge">
              <span class="security-dot"></span>
              <i class="fa fa-shield mr-1"></i> 256-Bit SSL Encrypted · Enterprise Ready
            </div>
            <p>&copy; {{ date('Y') }} Animazon's <span class="external">zoloERP Pro</span>. All Rights Reserved.</p>
          </div>
        </div>
      </div>

      <!-- Quick Demo Access Bar -->
      @if(!config('app.user_verified') && !config('database.connections.zoloerp_landlord'))
      <div class="switch-theme" id="switch-theme">
        <div class="d-flex align-items-center justify-content-between flex-wrap">
          <div class="demo-label mb-1 mb-sm-0">
            <i class="fa fa-key mr-1"></i> Quick Fill Credentials:
          </div>
          <div class="d-flex align-items-center flex-wrap">
            <button data-page="back_admin" data-env=".env" class="btn btn-sm demo-btn demo-btn-admin" title="Default username: admin, password: admin">Admin (admin/admin)</button>
            <button data-page="back_staff" data-env=".env" class="btn btn-sm demo-btn demo-btn-staff">Staff</button>
            <button data-page="back_customer" data-env=".env" class="btn btn-sm demo-btn demo-btn-customer">Customer</button>
          </div>
        </div>
      </div>
      @endif
    </div>

    @if(!config('database.connections.zoloerp_landlord'))
    <script type="text/javascript" src="<?php echo asset('vendor/jquery/jquery.min.js') ?>"></script>
    <script type="text/javascript" src="<?php echo asset('vendor/bootstrap/js/bootstrap.bundle.min.js') ?>"></script>
    @else
    <script type="text/javascript" src="<?php echo asset('../../vendor/jquery/jquery.min.js') ?>"></script>
    <script type="text/javascript" src="<?php echo asset('../../vendor/bootstrap/js/bootstrap.bundle.min.js') ?>"></script>
    @endif
    <script>
        @if(config('database.connections.zoloerp_landlord'))
            if(localStorage.getItem("message")) {
                alert(localStorage.getItem("message"));
                localStorage.removeItem("message");
            }
            numberOfUserAccount = <?php echo json_encode($numberOfUserAccount)?>;
            $.ajax({
                type: 'GET',
                async: false,
                url: '{{route("package.fetchData", $general_setting->package_id)}}',
                success: function(data) {
                    if(data['number_of_user_account'] > 0 && data['number_of_user_account'] <= numberOfUserAccount) {
                        $(".register-section").addClass('d-none');
                    }
                }
            });
        @endif

        // Auto dismiss flash alerts
        $("div.auth-alert").delay(5000).slideUp(600);

        // Toggle password visibility
        $('#togglePassword').on('click', function() {
            var passwordField = $("#login-password");
            var icon = $(this).find("i");

            if (passwordField.attr("type") === "password") {
                passwordField.attr("type", "text");
                icon.removeClass("fa-eye-slash").addClass("fa-eye");
            } else {
                passwordField.attr("type", "password");
                icon.removeClass("fa-eye").addClass("fa-eye-slash");
            }
        });

        function setEnvCookie(cookieValue) {
            var cookieName = "env_name";
            var expireDays = 1;
            var date = new Date();
            date.setTime(date.getTime() + (expireDays * 24 * 60 * 60 * 1000));
            var expires = "; expires=" + date.toUTCString();
            document.cookie = cookieName + "=" + cookieValue + expires + "; path=/";
        }

        // Quick demo fill buttons
        $('.demo-btn').on('click', function(e) {
            e.preventDefault();
            setEnvCookie($(this).data('env'));
            if ($(this).data('env') == '.env.ecom' && $(this).data('page') == 'ecom_front') {
                window.open("{{ url('/') }}", "_blank");
            }
            else {
                if ($(this).data('page') == 'back_staff') {
                    $("input[name='name']").val('staff');
                    $("input[name='password']").val('staff');
                }
                else if ($(this).data('page') == 'back_customer') {
                    $("input[name='name']").val('james');
                    $("input[name='password']").val('james');
                }
                else {
                    $("input[name='name']").val('admin');
                    $("input[name='password']").val('admin');
                }
                let form = $('#login-form');
                if ($(this).attr('href')) {
                    form.attr('action', $(this).attr('href'));
                }
                form.submit();
            }
        });
    </script>
  </body>
</html>
