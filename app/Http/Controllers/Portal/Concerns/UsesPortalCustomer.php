<?php

namespace App\Http\Controllers\Portal\Concerns;

use App\Domains\MasterData\Models\Customer;
use App\Support\PortalSelection;

/** Portal pages work inside the selected customer account (checked by the portal.context middleware). */
trait UsesPortalCustomer
{
    protected function portalCustomer(): Customer
    {
        $customer = PortalSelection::customer();
        abort_unless($customer, 403);

        return $customer;
    }

    protected function authorizeCustomerRecord(int|string|null $customerId): void
    {
        abort_unless((int) $customerId === PortalSelection::customerId(), 404);
    }
}
