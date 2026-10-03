<?php

namespace App\Domains\Dispatch\Actions;

use App\Domains\Dispatch\Models\JobSheet;
use App\Domains\MasterData\Models\Lorry;
use App\Enums\DocumentType;
use App\Enums\JobSheetStatus;
use App\Services\DocumentNumberingService;

/**
 * Finds the job sheet (trip) a lorry is working on for a date, or opens the next trip.
 *
 * Section I: a driver may run Trip 1, Trip 2, … on the same day. Each trip is its own
 * Job Sheet with its own number, tasks and lorry information. Completed trips are never
 * reopened: the next assignment starts a new trip.
 */
class ResolveJobSheet
{
    public function __construct(private DocumentNumberingService $numbering) {}

    public function forLorry(
        Lorry $lorry,
        string $date,
        ?int $driverId = null,
        ?int $companyId = null,
        bool $shared = false,
        bool $newTrip = false,
    ): JobSheet {
        $lorry->loadMissing(['branch', 'defaultDriver']);
        $resolvedDriverId = $driverId ?: $lorry->default_driver_id;

        $base = JobSheet::query()
            ->where('lorry_id', $lorry->id)
            ->whereDate('operating_date', $date);

        if (! $newTrip) {
            $open = (clone $base)
                ->where('status', '!=', JobSheetStatus::Completed->value)
                ->orderByDesc('trip_no')
                ->first();

            if ($open) {
                $updates = [];

                if ($resolvedDriverId && (int) $open->driver_id !== (int) $resolvedDriverId) {
                    $updates['driver_id'] = $resolvedDriverId;
                }

                if ($shared && ! $open->is_shared_dispatch) {
                    $updates['is_shared_dispatch'] = true;
                }

                if ($updates !== []) {
                    $open->update($updates);
                }

                return $open;
            }
        }

        $tripNo = (int) (clone $base)->max('trip_no') + 1;

        return JobSheet::query()->create([
            'number' => $this->numbering->next($lorry->branch, DocumentType::JobSheet),
            'company_id' => $lorry->company_id ?? $companyId,
            'operating_branch_id' => $lorry->branch_id,
            'lorry_id' => $lorry->id,
            'driver_id' => $resolvedDriverId,
            'operating_date' => $date,
            'trip_no' => $tripNo,
            'status' => JobSheetStatus::Draft,
            'is_shared_dispatch' => $shared,
        ]);
    }
}
