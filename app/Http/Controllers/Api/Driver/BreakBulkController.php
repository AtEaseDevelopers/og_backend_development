<?php

namespace App\Http\Controllers\Api\Driver;

use App\Domains\Delivery\Actions\CreateBreakBulk;
use App\Domains\Dispatch\Models\DeliveryOrder;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BreakBulkController extends Controller
{
    public function store(Request $request, DeliveryOrder $deliveryOrder, CreateBreakBulk $action): JsonResponse
    {
        $driver = $request->user()->driver;
        abort_unless($driver, 403, 'Driver profile required.');

        if ($deliveryOrder->driver_id && $deliveryOrder->driver_id !== $driver->id) {
            abort(403, 'This delivery is not assigned to you.');
        }

        $data = $request->validate([
            'reason' => ['required', 'string'],
            'location' => ['nullable', 'string'],
            'photo_paths' => ['nullable', 'array'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
        ]);

        $breakBulk = $action->execute($deliveryOrder, $data, $driver, $request->user());

        return response()->json(['data' => $breakBulk], 201);
    }
}
