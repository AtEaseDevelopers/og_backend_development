<?php

namespace App\Domains\Dispatch\Actions;

use App\Domains\Consignment\Models\ConsignmentNote;
use App\Domains\Dispatch\Models\JobSheet;
use App\Domains\MasterData\Models\Driver;
use App\Enums\CsnStatus;
use App\Enums\DeliveryOrderStatus;
use App\Enums\JobSheetStatus;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Section I: the driver checks in before the job becomes active, confirming the driver,
 * the lorry and the check-in time. A lorry mismatch blocks the start.
 */
class DriverCheckIn
{
    public function execute(
        Driver $driver,
        JobSheet $jobSheet,
        ?float $lat = null,
        ?float $lng = null,
        ?int $confirmedLorryId = null,
    ): JobSheet {
        if ($jobSheet->driver_id && $jobSheet->driver_id !== $driver->id) {
            throw new InvalidArgumentException('Job sheet is assigned to another driver.');
        }

        if ($jobSheet->status === JobSheetStatus::Completed) {
            throw new InvalidArgumentException('This trip is already completed.');
        }

        // Flowchart step 16: no overlapping active trip for the same driver on the same day
        $activeTrip = JobSheet::query()
            ->where('driver_id', $driver->id)
            ->whereKeyNot($jobSheet->id)
            ->whereDate('operating_date', $jobSheet->operating_date)
            ->where('status', JobSheetStatus::InTransit->value)
            ->first();

        if ($activeTrip) {
            throw new InvalidArgumentException(
                'You still have '.$activeTrip->number.' ('.$activeTrip->tripLabel().') in transit. Complete it before checking in to the next trip.'
            );
        }

        if ($confirmedLorryId !== null && (int) $confirmedLorryId !== (int) $jobSheet->lorry_id) {
            throw new InvalidArgumentException(
                'Lorry mismatch: this trip is assigned to '.($jobSheet->lorry?->registration_no ?? 'another lorry').'. Contact Admin to correct the assignment.'
            );
        }

        return DB::transaction(function () use ($driver, $jobSheet, $lat, $lng, $confirmedLorryId) {
            if (! $jobSheet->driver_id) {
                $jobSheet->driver_id = $driver->id;
            }

            $jobSheet->status = JobSheetStatus::InTransit;
            $jobSheet->checked_in_at = now();
            $jobSheet->checked_in_lorry_id = $confirmedLorryId ?? $jobSheet->lorry_id;
            $jobSheet->departed_at ??= now();
            $jobSheet->save();

            $jobSheet->deliveryOrders()
                ->where('status', DeliveryOrderStatus::Assigned)
                ->update(['status' => DeliveryOrderStatus::InTransit]);

            // main-DO CSNs move to In Transit (operational sequence, flowchart step 17)
            $csnIds = $jobSheet->deliveryOrders()
                ->whereNull('parent_do_id')
                ->pluck('consignment_note_id');

            ConsignmentNote::query()
                ->whereIn('id', $csnIds)
                ->where('status', CsnStatus::Assigned->value)
                ->update(['status' => CsnStatus::InTransit->value]);

            DB::table('driver_check_ins')->insert([
                'driver_id' => $driver->id,
                'lorry_id' => $jobSheet->lorry_id,
                'lorry_confirmed' => $confirmedLorryId === null || (int) $confirmedLorryId === (int) $jobSheet->lorry_id,
                'job_sheet_id' => $jobSheet->id,
                'checked_in_at' => now(),
                'latitude' => $lat,
                'longitude' => $lng,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $jobSheet->fresh(['deliveryOrders.consignmentNote', 'lorry', 'driver']);
        });
    }
}
