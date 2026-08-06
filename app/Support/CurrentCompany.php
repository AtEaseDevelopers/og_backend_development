<?php

namespace App\Support;

use App\Domains\MasterData\Models\Branch;
use App\Domains\MasterData\Models\Company;
use Filament\Facades\Filament;

class CurrentCompany
{
    public static function get(): ?Company
    {
        $tenant = Filament::getTenant();

        return $tenant instanceof Company ? $tenant : null;
    }

    public static function id(): ?int
    {
        return self::get()?->id;
    }

    public static function branch(): ?Branch
    {
        return self::get()?->branch;
    }

    public static function branchId(): ?int
    {
        return self::branch()?->id ?? self::get()?->branch_id;
    }
}
