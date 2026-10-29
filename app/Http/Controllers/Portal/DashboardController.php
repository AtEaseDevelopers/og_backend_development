<?php

namespace App\Http\Controllers\Portal;

use App\Domains\Quotation\Models\PortalEnquiry;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Portal\Concerns\UsesPortalCustomer;
use Illuminate\View\View;

class DashboardController extends Controller
{
    use UsesPortalCustomer;

    public function __invoke(): View
    {
        $customer = $this->portalCustomer();

        return view('portal.dashboard', [
            'customer' => $customer,
            'enquiries' => PortalEnquiry::query()->where('customer_id', $customer->id)->latest()->limit(20)->get(),
        ]);
    }
}
