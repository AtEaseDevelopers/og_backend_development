<?php

namespace App\Http\Middleware;

use App\Domains\MasterData\Models\Company;
use App\Support\SelectedBranch;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SyncSelectedBranchFromTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = Filament::getTenant();

        if ($tenant instanceof Company && $tenant->branch) {
            SelectedBranch::set($tenant->branch);
        }

        return $next($request);
    }
}
