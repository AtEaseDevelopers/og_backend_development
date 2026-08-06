<?php

namespace App\Domains\MasterData\Actions;

use App\Domains\MasterData\Models\VehicleMaintenanceRecord;
use App\Models\User;
use App\Notifications\VehicleMaintenanceDueAlert;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

class FlagVehicleMaintenanceDue
{
    /** @return Collection<int, VehicleMaintenanceRecord> */
    public function execute(bool $notify = true): Collection
    {
        $days = (int) config('og.vehicle.expiry_alert_days', 30);
        $until = now()->addDays($days)->toDateString();

        $due = VehicleMaintenanceRecord::query()
            ->with('lorry.branch')
            ->where('status', 'active')
            ->where(function ($q) use ($until) {
                $q->whereDate('expiry_date', '<=', $until)
                    ->orWhereDate('next_service_date', '<=', $until);
            })
            ->get();

        foreach ($due as $record) {
            $record->update(['alerted_at' => now()]);
        }

        if ($notify && $due->isNotEmpty()) {
            $recipients = User::query()
                ->where('is_active', true)
                ->where(function ($q) {
                    $q->where('is_hq', true)
                        ->orWhereHas('roles', fn ($r) => $r->whereIn('name', [
                            'hq_admin', 'branch_manager', 'dispatcher',
                        ]));
                })
                ->get();

            Notification::send($recipients, new VehicleMaintenanceDueAlert($due));
        }

        return $due;
    }
}
