<?php

namespace App\Domains\Dispatch\Actions;

use App\Domains\Dispatch\Models\JobSheet;
use App\Domains\MasterData\Models\Lorry;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Section I: Admin manually creates the next Job Sheet (Trip 2, 3, …) for the same driver
 * and lorry on the same day. The previous trip is never overwritten.
 */
class CreateNextTrip
{
    public function __construct(private ResolveJobSheet $resolveJobSheet) {}

    public function execute(JobSheet $previous, User $actor, ?string $plannedDepartureAt = null, ?int $driverId = null, bool $allowOverlap = false): JobSheet
    {
        $previous->loadMissing(['lorry', 'driver']);

        /** @var Lorry $lorry */
        $lorry = $previous->lorry;

        // Flowchart: no overlapping active trip unless explicitly allowed
        if (! $allowOverlap) {
            $inTransit = JobSheet::query()
                ->where('lorry_id', $lorry->id)
                ->whereDate('operating_date', $previous->operating_date)
                ->where('status', \App\Enums\JobSheetStatus::InTransit->value)
                ->first();

            if ($inTransit) {
                throw new \InvalidArgumentException(
                    $inTransit->number.' ('.$inTransit->tripLabel().') is still in transit on '.$lorry->registration_no.'. Complete it first, or tick "Allow overlapping trip".'
                );
            }
        }

        $next = $this->resolveJobSheet->forLorry(
            lorry: $lorry,
            date: $previous->operating_date->toDateString(),
            driverId: $driverId ?? $previous->driver_id,
            companyId: $previous->company_id,
            shared: (bool) $previous->is_shared_dispatch,
            newTrip: true,
        );

        if ($plannedDepartureAt) {
            $next->update(['planned_departure_at' => Carbon::parse($plannedDepartureAt)]);
        }

        activity()
            ->performedOn($next)
            ->causedBy($actor)
            ->withProperties(['previous_job_sheet' => $previous->number])
            ->log('Trip '.$next->trip_no.' created after '.$previous->number);

        return $next;
    }
}
