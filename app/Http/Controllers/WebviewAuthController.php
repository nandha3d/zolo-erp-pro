<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\InternalRedirectValidator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\Guard;

class WebviewAuthController extends Controller
{
    public function __invoke(Request $request, InternalRedirectValidator $redirects)
    {
        $header = $request->header('Authorization');
        abort_unless(is_string($header) && str_starts_with($header, 'Bearer '), 401, 'Missing or invalid Authorization header');
        // Use Sanctum's expiry, provider and token-authentication checks without accepting an existing web session.
        $guard = new Guard(Auth::getFacadeRoot(), config('sanctum.expiration'), 'users');
        $previousGuards = config('sanctum.guard');
        config(['sanctum.guard' => []]);
        try {
            $user = $guard($request);
        } finally {
            config(['sanctum.guard' => $previousGuards]);
        }
        abort_unless($user instanceof User && $user->is_active && !$user->is_deleted, 401, 'Invalid token');
        $destination = $redirects->withQuery($request->query('redirect', '/'), ['app' => 'true']);
        Auth::login($user);
        $request->session()->regenerate();

        return redirect($destination);
    }
}
