<?php

namespace App\Http\Middleware;

use App\Support\PortalSelection;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePortalContext
{
    /** @var list<string> */
    private array $except = [
        'portal.select-branch',
        'portal.select-company',
        'portal.logout',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (in_array($request->route()?->getName(), $this->except, true)) {
            return $next($request);
        }

        if (! PortalSelection::branchId()) {
            return redirect()->route('portal.select-branch');
        }

        if (! PortalSelection::companyId()) {
            return redirect()->route('portal.select-company');
        }

        return $next($request);
    }
}
