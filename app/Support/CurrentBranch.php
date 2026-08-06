<?php

namespace App\Support;

use App\Domains\MasterData\Models\Branch;
use Filament\Facades\Filament;

class CurrentBranch
{
    public static function get(): ?Branch
    {
        if ($branch = CurrentCompany::branch()) {
            return $branch;
        }

        $tenant = Filament::getTenant();

        return $tenant instanceof Branch ? $tenant : null;
    }

    public static function id(): ?int
    {
        return self::get()?->id ?? CurrentCompany::branchId();
    }
}
