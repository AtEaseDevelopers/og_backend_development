<?php

namespace App\Http\Controllers\Api\Driver;

use App\Domains\Dispatch\Actions\ValidateJobSheetScans;
use App\Domains\Dispatch\Models\JobSheet;
use App\Http\Controllers\Api\Driver\Concerns\NormalizesDeviceTimes;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Section O items 16–21: barcode validation of the job sheet's consignments before delivery. */
class ScanValidationController extends Controller
{
    use NormalizesDeviceTimes;

    /** One scanned code: matched, extra or unauthorized (used live while online). */
    public function check(Request $request, JobSheet $jobSheet, ValidateJobSheetScans $action): JsonResponse
    {
        $this->assertOwnership($request, $jobSheet);

        $data = $request->validate(['code' => ['required', 'string', 'max:255']]);

        return response()->json(['data' => $action->classify($jobSheet, $data['code'])]);
    }

    /** The finished scan session; an incomplete validation is recorded and the driver may continue. */
    public function store(Request $request, JobSheet $jobSheet, ValidateJobSheetScans $action): JsonResponse
    {
        $this->assertOwnership($request, $jobSheet);

        $data = $request->validate([
            'scanned_codes' => ['present', 'array'],
            'scanned_codes.*' => ['string', 'max:255'],
            'continue_incomplete' => ['nullable', 'boolean'],
            'remarks' => ['nullable', 'string'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'client_uuid' => ['nullable', 'uuid'],
            'validated_at' => ['nullable', 'date'],
        ]);

        $validation = $action->record($jobSheet, $request->user()->driver, $this->deviceTimes($data, 'validated_at'));

        return response()->json([
            'message' => $validation->is_complete
                ? 'All consignments validated.'
                : 'Validation incomplete — recorded for Admin review.',
            'data' => $validation,
        ], 201);
    }

    private function assertOwnership(Request $request, JobSheet $jobSheet): void
    {
        $driverId = $request->user()->driver_id;
        abort_unless($driverId, 403, 'No driver profile linked to this account.');
        abort_if($jobSheet->driver_id && (int) $jobSheet->driver_id !== (int) $driverId, 403, 'This job sheet is not assigned to you.');
    }
}
