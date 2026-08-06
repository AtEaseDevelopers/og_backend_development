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

        $pod = $action->execute($deliveryOrder, $request->user()->driver, $data);

        return response()->json(['data' => $pod]);
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

        $failed = $action->execute($deliveryOrder, $request->user()->driver, $data);

        return response()->json(['data' => $failed]);
    }
}
