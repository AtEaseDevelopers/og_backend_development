<?php

namespace App\Http\Controllers\Portal;

use App\Domains\Quotation\Models\Quotation;
use App\Http\Controllers\Controller;
use App\Support\PortalSelection;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $branch = PortalSelection::branch();
        $company = PortalSelection::company();
        $customerIds = $user->approvedCustomerIds();

        if ($company) {
            $customerIds = $user->customers()
                ->wherePivot('status', 'approved')
                ->where('customers.company_id', $company->id)
                ->pluck('customers.id');

            if ($customerIds->isEmpty() && $user->customer?->company_id === $company->id) {
                $customerIds = collect([$user->customer_id]);
            }
        }

        $quotations = Quotation::query()
            ->with('branch')
            ->whereIn('customer_id', $customerIds)
            ->when($branch, fn ($query) => $query->where('branch_id', $branch->id))
            ->when($company, fn ($query) => $query->where('company_id', $company->id))
            ->latest()
            ->limit(20)
            ->get();

        return view('portal.dashboard', compact('quotations', 'branch', 'company'));
    }
}
