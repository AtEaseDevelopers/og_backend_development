<?php

namespace App\Http\Controllers\Api\Driver;

use App\Domains\Dispatch\Actions\DriverCheckIn;
use App\Domains\Dispatch\Models\JobSheet;
use App\Enums\JobSheetStatus;
use App\Http\Controllers\Controller;
use App\Support\DriverTaskPayload;
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
            'data' => $this->transform($jobSheet, $driverId),
            'trips' => $trips->map(fn (JobSheet $sheet) => $this->tripSummary($sheet))->values(),
        ]);
    }

    /** Every trip (job sheet) of the driver for the date, section I. */
    public function index(Request $request): JsonResponse
    {
        $driverId = $request->user()->driver_id;
        $date = $request->query('date', now()->toDateString());

        return response()->json([
            'data' => $this->tripsQuery($driverId, $date)->get()->map(fn (JobSheet $sheet) => $this->transform($sheet, $driverId))->values(),
        ]);
    }

    public function detail(Request $request, JobSheet $jobSheet): JsonResponse
    {
        $driverId = $request->user()->driver_id;
        abort_unless((int) $jobSheet->driver_id === (int) $driverId, 403, 'This job sheet is not assigned to you.');

        return response()->json(['data' => $this->transform($this->tripsQuery($driverId, null)->findOrFail($jobSheet->id), $driverId)]);
    }

    /** Check-in confirms the driver, the lorry, its operating branch and the time; a mismatch blocks the start. */
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
        abort_unless($driver, 403, 'No driver profile linked to this account.');

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

        return response()->json([
            'message' => 'Checked in. '.$jobSheet->number.' is now In Transit.',
            'data' => $this->transform($this->tripsQuery($driver->id, null)->findOrFail($jobSheet->id), $driver->id),
        ]);
    }

    private function tripsQuery(?int $driverId, ?string $date)
    {
        return JobSheet::query()
            ->with([
                'lorry.branch', 'operatingBranch', 'tasks',
                'scanValidations' => fn ($q) => $q->latest('id')->limit(1),
                ...collect(DriverTaskPayload::RELATIONS)->map(fn ($r) => 'deliveryOrders.'.$r)->all(),
            ])
            ->where('driver_id', $driverId)
            ->when($date, fn ($q) => $q->whereDate('operating_date', $date))
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

    private function transform(JobSheet $jobSheet, ?int $driverId): array
    {
        $validation = $jobSheet->scanValidations->first();
        $tasks = DriverTaskPayload::forJobSheet($jobSheet, $driverId);

        return [
            'id' => $jobSheet->id,
            'number' => $jobSheet->number,
            'trip_no' => $jobSheet->trip_no,
            'trip_label' => $jobSheet->tripLabel(),
            'status' => $jobSheet->status->value,
            'status_label' => $jobSheet->status->getLabel(),
            'operating_date' => $jobSheet->operating_date?->toDateString(),
            'is_shared_dispatch' => (bool) $jobSheet->is_shared_dispatch,
            'checked_in_at' => $jobSheet->checked_in_at,
            'planned_departure_at' => $jobSheet->planned_departure_at,
            'departed_at' => $jobSheet->departed_at,
            'completed_at' => $jobSheet->completed_at,
            'operating_branch' => $jobSheet->operatingBranch
                ? ['code' => $jobSheet->operatingBranch->code, 'name' => $jobSheet->operatingBranch->name]
                : null,
            'lorry' => [
                'id' => $jobSheet->lorry?->id,
                'registration_no' => $jobSheet->lorry?->registration_no,
                'branch' => $jobSheet->lorry?->branch?->code,
            ],
            'route_groups' => collect($tasks)->pluck('route_group')->unique()->values(),
            'scan_validation' => $validation ? [
                'is_complete' => $validation->is_complete,
                'continued_incomplete' => $validation->continued_incomplete,
                'missing_count' => count($validation->missing ?? []),
                'extra_count' => count($validation->extra ?? []),
                'unauthorized_count' => count($validation->unauthorized ?? []),
                'validated_at' => $validation->validated_at,
            ] : null,
            'tasks' => $tasks,
        ];
    }
}
