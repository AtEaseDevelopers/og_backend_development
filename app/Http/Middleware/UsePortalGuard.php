<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * The customer portal has its own login (guard "portal", user 11 Nov 2026): on a portal page auth()->user(),
 * $request->user() and Auth::attempt() are the portal's, whoever is logged in to the admin panel in the same browser.
 */
class UsePortalGuard
{
    public function handle(Request $request, Closure $next): Response
    {
        Auth::shouldUse('portal');

        return $next($request);
    }
}
