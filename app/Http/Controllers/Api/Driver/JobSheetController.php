<?php

namespace App\Http\Controllers\Api\Driver;

use App\Domains\Dispatch\Actions\DriverCheckIn;
use App\Domains\Dispatch\Models\JobSheet;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JobSheetController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $driverId = $request->user()->driver_id;
        $date = $request->query('date', now()->toDateString());

        $jobSheet = JobSheet::query()
            ->with(['lorry', 'deliveryOrders.consignmentNote.lines', 'deliveryOrders.sourceBranch', 'tasks'])
            ->where('driver_id', $driverId)
            ->whereDate('operating_date', $date)
            ->latest('id')
            ->first();

        if (! $jobSheet) {
            return response()->json(['message' => 'No job sheet for this date', 'data' => null], 404);
        }

        return response()->json(['data' => $this->transform($jobSheet)]);
    }

    public function checkIn(Request $request, DriverCheckIn $action): JsonResponse
    {
        $data = $request->validate([
            'job_sheet_id' => ['required', 'exists:job_sheets,id'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
        ]);

        $jobSheet = JobSheet::query()->findOrFail($data['job_sheet_id']);
        $driver = $request->user()->driver;

        $jobSheet = $action->execute(
            $driver,
            $jobSheet,
            isset($data['latitude']) ? (float) $data['latitude'] : null,
            isset($data['longitude']) ? (float) $data['longitude'] : null
        );

        return response()->json(['data' => $this->transform($jobSheet)]);
    }

    private function transform(JobSheet $jobSheet): array
    {
        return [
            'id' => $jobSheet->id,
            'number' => $jobSheet->number,
            'status' => $jobSheet->status->value,
            'operating_date' => $jobSheet->operating_date?->toDateString(),
            'checked_in_at' => $jobSheet->checked_in_at,
            'lorry' => [
                'id' => $jobSheet->lorry?->id,
                'registration_no' => $jobSheet->lorry?->registration_no,
            ],
            'tasks' => $jobSheet->deliveryOrders->map(fn ($do) => [
                'delivery_order_id' => $do->id,
                'do_number' => $do->number,
                'csn_number' => $do->consignmentNote?->number,
                'status' => $do->status->value,
                'source_branch' => $do->sourceBranch?->code,
                'consignee_name' => $do->consignmentNote?->consignee_name,
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
