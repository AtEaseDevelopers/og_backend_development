<?php

namespace App\Http\Controllers\Api\Driver;

use App\Domains\Delivery\Actions\CompleteDelivery;
use App\Domains\Delivery\Actions\FailDelivery;
use App\Domains\Dispatch\Models\DeliveryOrder;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeliveryController extends Controller
{
    public function complete(Request $request, DeliveryOrder $deliveryOrder, CompleteDelivery $action): JsonResponse
    {
        $data = $request->validate([
            'recipient_name' => ['nullable', 'string'],
            'signature_path' => ['nullable', 'string'],
            'photo_paths' => ['nullable', 'array'],
            'pod_document_path' => ['nullable', 'string'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'cod_amount_collected' => ['nullable', 'numeric'],
            'cod_payment_method' => ['nullable', 'string'],
            'remarks' => ['nullable', 'string'],
            'client_uuid' => ['nullable', 'uuid'],
        ]);

        $this->assertOwnership($request, $deliveryOrder);

        $pod = $action->execute($deliveryOrder, $request->user()->driver, $data);

        return response()->json(['data' => $pod]);
    }

    /** Only the driver currently holding the DO may complete or fail it (transferred jobs follow the new driver). */
    private function assertOwnership(Request $request, DeliveryOrder $deliveryOrder): void
    {
        $driverId = $request->user()->driver_id;

        abort_unless($driverId, 403, 'No driver profile linked to this account.');

        if ($deliveryOrder->driver_id && (int) $deliveryOrder->driver_id !== (int) $driverId) {
            abort(403, 'This delivery is not assigned to you.');
        }

        if ($deliveryOrder->consignmentNote?->transfer_claim_pending) {
            abort(422, 'Scan the CSN to claim this transferred job before updating it.');
        }
    }

    public function fail(Request $request, DeliveryOrder $deliveryOrder, FailDelivery $action): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string'],
            'remarks' => ['nullable', 'string'],
            'photo_paths' => ['nullable', 'array'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'client_uuid' => ['nullable', 'uuid'],
        ]);

        $this->assertOwnership($request, $deliveryOrder);

        $failed = $action->execute($deliveryOrder, $request->user()->driver, $data);

        return response()->json(['data' => $failed]);
    }
}
