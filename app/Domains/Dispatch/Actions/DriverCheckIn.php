<?php

namespace App\Domains\Dispatch\Actions;

use App\Domains\Dispatch\Models\JobSheet;
use App\Domains\MasterData\Models\Driver;
use App\Enums\DeliveryOrderStatus;
use App\Enums\JobSheetStatus;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class DriverCheckIn
{
    public function execute(Driver $driver, JobSheet $jobSheet, ?float $lat = null, ?float $lng = null): JobSheet
    {
        if ($jobSheet->driver_id && $jobSheet->driver_id !== $driver->id) {
            throw new InvalidArgumentException('Job sheet is assigned to another driver.');
        }

        return DB::transaction(function () use ($driver, $jobSheet, $lat, $lng) {
            if (! $jobSheet->driver_id) {
                $jobSheet->driver_id = $driver->id;
            }

            $jobSheet->status = JobSheetStatus::InTransit;
            $jobSheet->checked_in_at = now();
            $jobSheet->save();

            $jobSheet->deliveryOrders()
                ->where('status', DeliveryOrderStatus::Assigned)
                ->update(['status' => DeliveryOrderStatus::InTransit]);

            DB::table('driver_check_ins')->insert([
                'driver_id' => $driver->id,
                'lorry_id' => $jobSheet->lorry_id,
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
