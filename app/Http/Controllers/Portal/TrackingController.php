<?php

namespace App\Http\Controllers\Portal;

use App\Domains\Dispatch\Models\DeliveryOrder;
use App\Http\Controllers\Controller;
use Illuminate\View\View;

class TrackingController extends Controller
{
    public function __invoke(string $token): View
    {
        $deliveryOrder = DeliveryOrder::query()
            ->with(['consignmentNote', 'proofOfDelivery', 'failedDelivery', 'sourceBranch', 'driver'])
            ->where('tracking_token', $token)
            ->firstOrFail();

        return view('tracking.show', compact('deliveryOrder'));
    }
}
