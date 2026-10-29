<?php

namespace App\Support;

use App\Domains\MasterData\Models\Branch;
use App\Domains\MasterData\Models\Company;
use App\Domains\MasterData\Models\Customer;

/**
 * The customer account (BRN) a portal user is working in. Picking an account also sets its branch and company,
 * which the order form and the header read.
 */
class PortalSelection
{
    public const BRANCH_KEY = 'portal.selected_branch_id';

    public const COMPANY_KEY = 'portal.selected_company_id';

    public const CUSTOMER_KEY = 'portal.selected_customer_id';

    public static function setCustomer(Customer $customer): void
    {
        $companyId = $customer->company_id
            ?? Company::query()->where('branch_id', $customer->branch_id)->where('is_active', true)->orderBy('id')->value('id');

        session([
            self::CUSTOMER_KEY => $customer->id,
            self::BRANCH_KEY => $customer->branch_id,
            self::COMPANY_KEY => $companyId,
        ]);
    }

    public static function clear(): void
    {
        session()->forget([self::BRANCH_KEY, self::COMPANY_KEY, self::CUSTOMER_KEY]);
    }

    public static function customerId(): ?int
    {
        $id = session(self::CUSTOMER_KEY);

        return $id ? (int) $id : null;
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

    public static function customer(): ?Customer
    {
        $id = self::customerId();

        return $id ? Customer::query()->find($id) : null;
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
        return self::customerId() !== null;
    }
}
