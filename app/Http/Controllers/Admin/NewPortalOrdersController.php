<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Quotation\Models\PortalEnquiry;
use App\Filament\Pages\Orders;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/** Polled by the admin pop-up (filament.hooks.new-order-alert): customer portal orders waiting for review. */
class NewPortalOrdersController extends Controller
{
    public function __invoke(): JsonResponse
    {
        abort_unless(Orders::canAccess(), 403);

        $latest = Orders::newPortalOrdersQuery()
            ->with('customer:id,company_name')
            ->latest('id')
            ->limit(5)
            ->get(['id', 'customer_id', 'order_number', 'reference_no', 'created_at']);

        return response()->json([
            'count' => Orders::newPortalOrdersQuery()->count(),
            'latest_id' => (int) ($latest->first()?->id ?? 0),
            'orders' => $latest->map(fn (PortalEnquiry $e) => [
                'id' => $e->id,
                'number' => $e->order_number ?: $e->reference_no,
                'customer' => $e->customer?->company_name ?? 'Customer',
                'submitted' => $e->created_at?->format('d/m/Y H:i'),
            ])->values(),
        ]);
    }
}
