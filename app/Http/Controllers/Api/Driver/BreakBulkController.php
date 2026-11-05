<?php

namespace App\Http\Controllers\Api\Driver;

use App\Domains\Delivery\Actions\CreateBreakBulk;
use App\Domains\Delivery\Models\BreakBulk;
use App\Domains\Dispatch\Models\DeliveryOrder;
use App\Http\Controllers\Api\Driver\Concerns\NormalizesDeviceTimes;
use App\Http\Controllers\Controller;
use App\Support\DriverTaskPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class BreakBulkController extends Controller
{
    use NormalizesDeviceTimes;

    /** Section O items 39–44: reason, intermediate location, photo, GPS and time of the offload. */
    public function store(Request $request, DeliveryOrder $deliveryOrder, CreateBreakBulk $action): JsonResponse
    {
        $driver = $request->user()->driver;
        abort_unless($driver, 403, 'Driver profile required.');

        if ($deliveryOrder->driver_id && (int) $deliveryOrder->driver_id !== (int) $driver->id) {
            abort(403, 'This delivery is not assigned to you.');
        }

        $data = $request->validate([
            'reason' => ['required', 'string'],
            'location' => ['required', 'string'],
            'photo_paths' => ['required', 'array', 'min:1'],
            'photo_paths.*' => ['string'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'client_uuid' => ['nullable', 'uuid'],
            'reported_at' => ['nullable', 'date', 'before_or_equal:'.now()->addMinutes(10)->toDateTimeString()],
        ], [
            'location.required' => 'Record where the goods are offloaded.',
            'photo_paths.required' => 'Upload a photo of the goods at the intermediate location.',
        ]);

        $data['reason'] = MetaController::BREAK_BULK_REASONS[$data['reason']] ?? $data['reason'];
        $data = $this->deviceTimes($data, 'reported_at');

        try {
            $breakBulk = $action->execute($deliveryOrder, $data, $driver, $request->user());
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $breakBulk], 201);
    }

    /** Section O item 45: the driver's Break-Bulk requests with the replacement driver / lorry once Admin assigns. */
    public function index(Request $request): JsonResponse
    {
        $driverId = $request->user()->driver_id;
        abort_unless($driverId, 403, 'Driver profile required.');

        $breakBulks = BreakBulk::query()
            ->with(['deliveryOrder', 'consignmentNote', 'originalDriver', 'originalLorry', 'replacementDriver', 'replacementLorry'])
            ->where(fn ($q) => $q->where('requested_by_driver_id', $driverId)
                ->orWhere('original_driver_id', $driverId)
                ->orWhere('replacement_driver_id', $driverId))
            ->latest('id')
            ->limit(50)
            ->get()
            ->map(fn (BreakBulk $b) => DriverTaskPayload::breakBulk($b) + [
                'do_number' => $b->deliveryOrder?->number,
                'csn_number' => $b->consignmentNote?->number,
                'consignee_name' => $b->consignmentNote?->consignee_name,
                'is_mine_to_continue' => (int) $b->replacement_driver_id === (int) $driverId,
            ]);

        return response()->json(['data' => $breakBulks]);
    }
}
