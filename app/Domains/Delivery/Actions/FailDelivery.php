<?php

namespace App\Domains\Delivery\Actions;

use App\Domains\Delivery\Models\FailedDelivery;
use App\Domains\Dispatch\Models\DeliveryOrder;
use App\Domains\MasterData\Models\Driver;
use App\Enums\DeliveryOrderStatus;
use App\Enums\JobSheetStatus;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class FailDelivery
{
    public function execute(DeliveryOrder $do, Driver $driver, array $data): FailedDelivery
    {
        if (! empty($data['client_uuid'])) {
            $existing = FailedDelivery::query()->where('client_uuid', $data['client_uuid'])->first();
            if ($existing) {
                return $existing;
            }
        }

        if ($do->status === DeliveryOrderStatus::Delivered) {
            throw new InvalidArgumentException('Cannot fail a delivered order.');
        }

        if ($do->status === DeliveryOrderStatus::Failed) {
            throw new InvalidArgumentException('Delivery order already marked as failed.');
        }

        return DB::transaction(function () use ($do, $driver, $data) {
            $failed = FailedDelivery::query()->create([
                'delivery_order_id' => $do->id,
                'driver_id' => $driver->id,
                'reason' => $data['reason'],
                'remarks' => $data['remarks'] ?? null,
                'photo_paths' => $data['photo_paths'] ?? null,
                'latitude' => $data['latitude'] ?? null,
                'longitude' => $data['longitude'] ?? null,
                'client_uuid' => $data['client_uuid'] ?? null,
                'failed_at' => $data['failed_at'] ?? now(),
                'synced_at' => now(),
            ]);

            $do->update([
                'status' => DeliveryOrderStatus::Failed,
                'failed_at' => $failed->failed_at,
            ]);

            $jobSheet = $do->jobSheet;
            if ($jobSheet && ! $jobSheet->deliveryOrders()
                ->whereNotIn('status', [
                    DeliveryOrderStatus::Delivered->value,
                    DeliveryOrderStatus::Failed->value,
                    DeliveryOrderStatus::Cancelled->value,
                ])->exists()) {
                $jobSheet->update(['status' => JobSheetStatus::Completed, 'completed_at' => now()]);
            }

            return $failed;
        });
    }
}
