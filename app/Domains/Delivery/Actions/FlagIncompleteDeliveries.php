<?php

namespace App\Domains\Delivery\Actions;

use App\Domains\Dispatch\Models\DeliveryOrder;
use App\Enums\DeliveryOrderStatus;
use App\Models\User;
use App\Notifications\IncompleteDeliveriesAlert;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class FlagIncompleteDeliveries
{
    /**
     * Flag DOs scheduled for $date that are still incomplete after 4pm.
     *
     * @return Collection<int, object>
     */
    public function execute(?Carbon $date = null, bool $notify = true): Collection
    {
        $date = ($date ?? now())->copy()->startOfDay();

        $incomplete = DeliveryOrder::query()
            ->with(['jobSheet', 'sourceBranch', 'consignmentNote', 'driver'])
            ->whereHas('jobSheet', fn ($q) => $q->whereDate('operating_date', $date))
            ->whereNotIn('status', [
                DeliveryOrderStatus::Delivered->value,
                DeliveryOrderStatus::Cancelled->value,
            ])
            ->get();

        $alerts = collect();

        foreach ($incomplete as $do) {
            $id = DB::table('incomplete_delivery_alerts')->updateOrInsert(
                [
                    'alert_date' => $date->toDateString(),
                    'delivery_order_id' => $do->id,
                ],
                [
                    'job_sheet_id' => $do->job_sheet_id,
                    'branch_id' => $do->source_branch_id,
                    'status' => 'open',
                    'notified_at' => now(),
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );

            $alerts->push((object) [
                'delivery_order' => $do,
                'alert_date' => $date->toDateString(),
            ]);
        }

        if ($notify && $alerts->isNotEmpty()) {
            $recipients = User::query()
                ->where('is_active', true)
                ->where(function ($q) {
                    $q->where('is_hq', true)
                        ->orWhereHas('roles', fn ($r) => $r->whereIn('name', [
                            'hq_admin', 'branch_manager', 'dispatcher',
                        ]));
                })
                ->get();

            Notification::send($recipients, new IncompleteDeliveriesAlert($date, $alerts));
        }

        return $alerts;
    }
}
