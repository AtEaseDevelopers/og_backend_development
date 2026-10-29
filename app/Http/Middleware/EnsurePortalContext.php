<?php

namespace App\Http\Middleware;

use App\Support\PortalSelection;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Portal pages need a selected customer account (BRN) that the user is still approved for. */
class EnsurePortalContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $customerId = PortalSelection::customerId();

        if (! $customerId) {
            return redirect()->route('portal.select-company');
        }

        if (! $request->user()->approvedCustomerIds()->contains($customerId)) {
            PortalSelection::clear();

            return redirect()->route('portal.select-company')
                ->withErrors(['account' => 'Your access to that company account is no longer approved.']);
        }

        return $next($request);
    }
}
