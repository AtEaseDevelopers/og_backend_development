<?php

namespace App\Support;

use App\Domains\MasterData\Models\Branch;
use App\Domains\MasterData\Models\Company;
use App\Models\User;
use Filament\Facades\Filament;

class SwitchBranch
{
    public static function enter(User $user, Branch $branch): string
    {
        abort_unless($user->canAccessBranch($branch), 403);

        SelectedBranch::set($branch);

        $company = $branch->defaultCompany();
        abort_unless($company instanceof Company, 404);
        abort_unless($user->canAccessTenant($company), 403);

        $panel = Filament::getCurrentPanel() ?? Filament::getPanel('admin');

        return $panel->getUrl($company);
    }
}
