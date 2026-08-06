<?php

namespace App\Http\Controllers\Portal;

use App\Domains\Quotation\Models\Quotation;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $customerIds = $user->customers()->wherePivot('status', 'approved')->pluck('customers.id');

        if ($customerIds->isEmpty() && $user->customer_id) {
            $customerIds = collect([$user->customer_id]);
        }

        $quotations = Quotation::query()
            ->with('branch')
            ->whereIn('customer_id', $customerIds)
            ->latest()
            ->limit(20)
            ->get();

        return view('portal.dashboard', compact('quotations'));
    }
}
