<?php

namespace App\Http\Middleware;

use App\Support\PortalSelection;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Logging out of the admin panel empties the whole session; a customer logged in to the portal in the same browser
 * stays logged in (user, 11 Nov 2026: the two do not affect each other), with the account they were working in.
 */
class KeepPortalLoginOnAdminLogout
{
    private const KEYS = [PortalSelection::CUSTOMER_KEY, PortalSelection::BRANCH_KEY, PortalSelection::COMPANY_KEY];

    public function handle(Request $request, Closure $next): Response
    {
        $portalUser = $request->routeIs('filament.*.auth.logout') ? Auth::guard('portal')->user() : null;
        $selection = $portalUser ? array_filter($request->session()->only(self::KEYS)) : [];

        $response = $next($request);

        if ($portalUser) {
            Auth::guard('portal')->login($portalUser);
            $request->session()->put($selection);
        }

        return $response;
    }
}
