<?php

namespace App\Http\Controllers\Api\Driver;

use App\Domains\Dispatch\Actions\DriverCheckIn;
use App\Domains\Dispatch\Models\JobSheet;
use App\Enums\JobSheetStatus;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class JobSheetController extends Controller
{
    /** Current trip for the date: the in-transit trip first, otherwise the latest open one, otherwise the latest. */
    public function show(Request $request): JsonResponse
    {
        $driverId = $request->user()->driver_id;
        $date = $request->query('date', now()->toDateString());

        $trips = $this->tripsQuery($driverId, $date)->get();

        $jobSheet = $trips->firstWhere('status', JobSheetStatus::InTransit)
            ?? $trips->first(fn (JobSheet $sheet) => $sheet->status === JobSheetStatus::Draft)
            ?? $trips->first();

        if (! $jobSheet) {
            return response()->json(['message' => 'No job sheet for this date', 'data' => null, 'trips' => []], 404);
        }

        return response()->json([
            'data' => $this->transform($jobSheet),
            'trips' => $trips->map(fn (JobSheet $sheet) => $this->tripSummary($sheet))->values(),
        ]);
    }

    /** Every trip (job sheet) of the driver for the date, section I. */
    public function index(Request $request): JsonResponse
    {
        $driverId = $request->user()->driver_id;
        $date = $request->query('date', now()->toDateString());

        return response()->json([
            'data' => $this->tripsQuery($driverId, $date)->get()->map(fn (JobSheet $sheet) => $this->transform($sheet))->values(),
        ]);
    }

    /** Check-in confirms the driver, the lorry and the time; a lorry mismatch blocks the start. */
    public function checkIn(Request $request, DriverCheckIn $action): JsonResponse
    {
        $data = $request->validate([
            'job_sheet_id' => ['required', 'exists:job_sheets,id'],
            'lorry_id' => ['nullable', 'exists:lorries,id'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
        ]);

        $jobSheet = JobSheet::query()->with('lorry')->findOrFail($data['job_sheet_id']);
        $driver = $request->user()->driver;

        try {
            $jobSheet = $action->execute(
                $driver,
                $jobSheet,
                isset($data['latitude']) ? (float) $data['latitude'] : null,
                isset($data['longitude']) ? (float) $data['longitude'] : null,
                isset($data['lorry_id']) ? (int) $data['lorry_id'] : null,
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $this->transform($jobSheet)]);
    }

    private function tripsQuery(?int $driverId, string $date)
    {
        return JobSheet::query()
            ->with(['lorry', 'deliveryOrders.consignmentNote.lines', 'deliveryOrders.sourceBranch', 'tasks'])
            ->where('driver_id', $driverId)
            ->whereDate('operating_date', $date)
            ->orderBy('trip_no')
            ->orderBy('id');
    }

    private function tripSummary(JobSheet $jobSheet): array
    {
        return [
            'id' => $jobSheet->id,
            'number' => $jobSheet->number,
            'trip_no' => $jobSheet->trip_no,
            'trip_label' => $jobSheet->tripLabel(),
            'status' => $jobSheet->status->value,
            'lorry' => $jobSheet->lorry?->registration_no,
            'task_count' => $jobSheet->deliveryOrders->count(),
            'checked_in_at' => $jobSheet->checked_in_at,
            'completed_at' => $jobSheet->completed_at,
        ];
    }

    private function transform(JobSheet $jobSheet): array
    {
        return [
            'id' => $jobSheet->id,
            'number' => $jobSheet->number,
            'trip_no' => $jobSheet->trip_no,
            'trip_label' => $jobSheet->tripLabel(),
            'status' => $jobSheet->status->value,
            'operating_date' => $jobSheet->operating_date?->toDateString(),
            'checked_in_at' => $jobSheet->checked_in_at,
            'planned_departure_at' => $jobSheet->planned_departure_at,
            'departed_at' => $jobSheet->departed_at,
            'completed_at' => $jobSheet->completed_at,
            'lorry' => [
                'id' => $jobSheet->lorry?->id,
                'registration_no' => $jobSheet->lorry?->registration_no,
            ],
            'tasks' => $jobSheet->deliveryOrders
                ->sortBy(fn ($do) => $jobSheet->tasks->firstWhere('delivery_order_id', $do->id)?->sequence ?? 999)
                ->values()
                ->map(fn ($do) => [
                    'delivery_order_id' => $do->id,
                    'do_number' => $do->number,
                    'csn_number' => $do->consignmentNote?->number,
                    'csn_qr_token' => $do->consignmentNote?->qr_token,
                    'csn_status' => $do->consignmentNote?->status?->value,
                    'claim_pending' => (bool) $do->consignmentNote?->transfer_claim_pending,
                    'customer_do_number' => $do->consignmentNote?->customer_do_number,
                    'order_type' => $do->consignmentNote?->order_type?->value,
                    'service_type' => $do->consignmentNote?->service_type?->value,
                    'drop_off_type' => $do->consignmentNote?->drop_off_type,
                    'status' => $do->status->value,
                    'source_branch' => $do->sourceBranch?->code,
                    'consignee_name' => $do->consignmentNote?->consignee_name,
                    'consignee_phone' => $do->consignmentNote?->consignee_phone,
                    'address' => $do->consignmentNote?->delivery_address,
                    'postcode' => $do->consignmentNote?->delivery_postcode,
                    'state' => $do->consignmentNote?->delivery_state,
                    'cod_amount' => $do->consignmentNote?->billing_type?->value === 'cod'
                        ? $do->consignmentNote?->total_amount
                        : null,
                    'items' => $do->consignmentNote?->lines?->map(fn ($l) => [
                        'item_name' => $l->item_name,
                        'quantity' => $l->quantity,
                        'uom' => $l->uom,
                    ]),
                ]),
        ];
    }
}
