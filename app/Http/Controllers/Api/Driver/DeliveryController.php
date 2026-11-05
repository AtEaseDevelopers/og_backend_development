<?php

namespace App\Http\Controllers\Api\Driver;

use App\Domains\Delivery\Actions\CompleteDelivery;
use App\Domains\Delivery\Actions\FailDelivery;
use App\Domains\Delivery\Actions\RecordDriverTaskProof;
use App\Domains\Delivery\Models\FailedDelivery;
use App\Domains\Dispatch\Models\DeliveryOrder;
use App\Enums\CsnBillingType;
use App\Http\Controllers\Api\Driver\Concerns\NormalizesDeviceTimes;
use App\Http\Controllers\Controller;
use App\Support\DriverTaskPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class DeliveryController extends Controller
{
    use NormalizesDeviceTimes;

    /** Section O items 22–33: the DO is delivered once photo, recipient, signature (and COD when due) are recorded. */
    public function complete(Request $request, DeliveryOrder $deliveryOrder, CompleteDelivery $action): JsonResponse
    {
        $isCod = $deliveryOrder->consignmentNote?->billing_type === CsnBillingType::Cod;

        $data = $request->validate([
            'recipient_name' => ['required', 'string', 'max:255'],
            'signature_path' => ['required_without:pod_document_path', 'nullable', 'string'],
            'photo_paths' => ['required', 'array', 'min:1'],
            'photo_paths.*' => ['string'],
            'pod_document_path' => ['nullable', 'string'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'cod_amount_collected' => [$isCod ? 'required' : 'nullable', 'numeric', 'min:0'],
            'cod_payment_method' => [$isCod ? 'required' : 'nullable', 'string', Rule::in(array_keys(MetaController::COD_PAYMENT_METHODS))],
            'remarks' => ['nullable', 'string'],
            'client_uuid' => ['nullable', 'uuid'],
            'delivered_at' => ['nullable', 'date', 'before_or_equal:'.now()->addMinutes(10)->toDateTimeString()],
        ], [
            'photo_paths.required' => 'Take at least one delivery photo.',
            'signature_path.required_without' => 'Capture the recipient signature or upload the signed POD.',
        ]);

        $data = $this->deviceTimes($data, 'delivered_at');
        $this->assertOwnership($request, $deliveryOrder);

        try {
            $pod = $action->execute($deliveryOrder, $request->user()->driver, $data);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $pod]);
    }

    /** Section O items 46–51: reason from the predefined list, remarks, proof photos, GPS and time. */
    public function fail(Request $request, DeliveryOrder $deliveryOrder, FailDelivery $action): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', Rule::in([...array_keys(FailedDelivery::REASONS), ...array_values(FailedDelivery::REASONS)])],
            'remarks' => ['nullable', 'string', 'required_if:reason,other,Other'],
            'photo_paths' => ['required', 'array', 'min:1'],
            'photo_paths.*' => ['string'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'client_uuid' => ['nullable', 'uuid'],
            'failed_at' => ['nullable', 'date', 'before_or_equal:'.now()->addMinutes(10)->toDateTimeString()],
        ], [
            'photo_paths.required' => 'Supporting proof photos are required for a failed delivery.',
            'remarks.required_if' => 'Describe the reason in the remarks.',
        ]);

        $data['reason'] = FailedDelivery::REASONS[$data['reason']] ?? $data['reason'];
        $data = $this->deviceTimes($data, 'failed_at');

        $this->assertOwnership($request, $deliveryOrder);

        try {
            $failed = $action->execute($deliveryOrder, $request->user()->driver, $data);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $failed]);
    }

    /** Section O items 22, 35: pickup-location photos with GPS and time. */
    public function pickup(Request $request, DeliveryOrder $deliveryOrder, RecordDriverTaskProof $action): JsonResponse
    {
        $data = $this->validateProof($request);
        $this->assertOwnership($request, $deliveryOrder);

        try {
            $proof = $action->pickup($deliveryOrder->load('subsheet'), $request->user()->driver, $data);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $proof]);
    }

    /** Section O items 36–38: the assisting driver hands the goods over to the actual delivery driver. */
    public function handover(Request $request, DeliveryOrder $deliveryOrder, RecordDriverTaskProof $action): JsonResponse
    {
        $data = $this->validateProof($request, true);
        $this->assertOwnership($request, $deliveryOrder);

        try {
            $proof = $action->handover($deliveryOrder->load(['subsheet', 'jobSheet']), $request->user()->driver, $data);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $proof]);
    }

    /** Section O item 54: the driver's failed deliveries and how Admin reassigned them. */
    public function failures(Request $request): JsonResponse
    {
        $driverId = $request->user()->driver_id;
        abort_unless($driverId, 403, 'No driver profile linked to this account.');

        $failures = FailedDelivery::query()
            ->with(['deliveryOrder.consignmentNote', 'replacementDeliveryOrder.lorry', 'replacementDeliveryOrder.driver', 'replacementDeliveryOrder.jobSheet'])
            ->where('driver_id', $driverId)
            ->latest('failed_at')
            ->limit(50)
            ->get()
            ->map(fn (FailedDelivery $f) => [
                'id' => $f->id,
                'do_number' => $f->deliveryOrder?->number,
                'csn_number' => $f->deliveryOrder?->consignmentNote?->number,
                'consignee_name' => $f->deliveryOrder?->consignmentNote?->consignee_name,
                'reason' => $f->reason,
                'remarks' => $f->remarks,
                'failed_at' => $f->failed_at,
                'photo_urls' => collect($f->photo_paths ?? [])->map(fn ($p) => DriverTaskPayload::url($p))->values(),
                'reassignment_option' => $f->reassignment_option,
                'reassignment_label' => match ($f->reassignment_option) {
                    'standard' => 'Reassigned',
                    'duplicate' => 'Duplicated & reassigned',
                    default => 'Awaiting Admin',
                },
                'replacement' => $f->replacementDeliveryOrder ? [
                    'do_number' => $f->replacementDeliveryOrder->number,
                    'status' => $f->replacementDeliveryOrder->status?->getLabel(),
                    'lorry' => $f->replacementDeliveryOrder->lorry?->registration_no,
                    'driver' => $f->replacementDeliveryOrder->driver?->name,
                    'job_sheet' => $f->replacementDeliveryOrder->jobSheet?->number,
                    'operating_date' => $f->replacementDeliveryOrder->jobSheet?->operating_date?->toDateString(),
                    'assigned_to_me' => (int) $f->replacementDeliveryOrder->driver_id === (int) $driverId,
                ] : null,
            ]);

        return response()->json(['data' => $failures]);
    }

    private function validateProof(Request $request, bool $handover = false): array
    {
        return $this->deviceTimes($request->validate([
            'photo_paths' => [$handover ? 'required' : 'nullable', 'array', $handover ? 'min:1' : 'min:0'],
            'photo_paths.*' => ['string'],
            'handover_to' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'client_uuid' => ['nullable', 'uuid'],
            'recorded_at' => ['nullable', 'date', 'before_or_equal:'.now()->addMinutes(10)->toDateTimeString()],
        ], [
            'photo_paths.required' => 'Upload at least one handover photo.',
        ]), 'recorded_at');
    }

    /** Only the driver currently holding the DO may update it (transferred jobs follow the new driver). */
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
}
