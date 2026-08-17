<?php

namespace App\Support;

use App\Domains\MasterData\Models\Branch;
use App\Domains\MasterData\Models\Company;

class PortalSelection
{
    public const BRANCH_KEY = 'portal.selected_branch_id';

    public const COMPANY_KEY = 'portal.selected_company_id';

    public static function setBranch(Branch $branch): void
    {
        session([self::BRANCH_KEY => $branch->id]);
        session()->forget(self::COMPANY_KEY);
    }

    public static function setCompany(Company $company): void
    {
        session([self::COMPANY_KEY => $company->id]);
    }

    public static function clear(): void
    {
        session()->forget([self::BRANCH_KEY, self::COMPANY_KEY]);
    }

    public static function branchId(): ?int
    {
        $id = session(self::BRANCH_KEY);

        return $id ? (int) $id : null;
    }

    public static function companyId(): ?int
    {
        $id = session(self::COMPANY_KEY);

        return $id ? (int) $id : null;
    }

    public static function branch(): ?Branch
    {
        $id = self::branchId();

        return $id ? Branch::query()->find($id) : null;
    }

    public static function company(): ?Company
    {
        $id = self::companyId();

        return $id ? Company::query()->find($id) : null;
    }

    public static function isComplete(): bool
    {
        return self::branchId() !== null && self::companyId() !== null;
    }
}
