<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

/** Kept as HTTP tombstones so existing clients receive an explicit retirement response. */
class AddonInstallController extends Controller
{
    public function saasInstall(Request $request)
    {
        return $this->unavailable();
    }

    public function ecommerceInstall(Request $request)
    {
        return $this->unavailable();
    }

    public function woocommerceInstall(Request $request)
    {
        return $this->unavailable();
    }

    public function apiInstall(Request $request)
    {
        return $this->unavailable();
    }

    private function unavailable()
    {
        return response()->json(['message' => 'Remote add-on installation is retired. Deploy reviewed packages through the application release process.'], 410);
    }
}
