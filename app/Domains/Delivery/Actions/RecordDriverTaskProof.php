<?php

namespace App\Domains\Delivery\Actions;

use App\Domains\Delivery\Models\DriverTaskProof;
use App\Domains\Dispatch\Models\DeliveryOrder;
use App\Domains\Dispatch\Models\ProfitSharingTransaction;
use App\Domains\MasterData\Models\Driver;
use App\Enums\DeliveryOrderStatus;
use App\Enums\JobSheetStatus;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Section O items 22, 34–38: pickup proof (photos, GPS, time) of a delivery or an additional task, and the
 * assisting driver's handover of a subsheet leg to the actual delivery driver. The handover completes the
 * leg and makes the assisting driver's profit sharing (PSI) eligible.
 */
class RecordDriverTaskProof
{
    public function pickup(DeliveryOrder $do, Driver $driver, array $data): DriverTaskProof
    {
        if ($existing = $this->existing($data)) {
            return $existing;
        }

        if (in_array($do->status, [DeliveryOrderStatus::Cancelled, DeliveryOrderStatus::Delivered], true)) {
            throw new InvalidArgumentException('Pickup cannot be recorded on a '.$do->status->getLabel().' delivery order.');
        }

        return DB::transaction(function () use ($do, $driver, $data) {
            $proof = $this->create($do, $driver, DriverTaskProof::TYPE_PICKUP, $data);

            $do->subsheet?->update(['handover_status' => 'collected']);

            return $proof;
        });
    }

    public function handover(DeliveryOrder $do, Driver $driver, array $data): DriverTaskProof
    {
        if ($existing = $this->existing($data)) {
            return $existing;
        }

        $subsheet = $do->subsheet;

        if (! $subsheet) {
            throw new InvalidArgumentException('Handover applies to additional (subsheet) tasks only.');
        }

        if (in_array($do->status, [DeliveryOrderStatus::Cancelled, DeliveryOrderStatus::Delivered], true)) {
            throw new InvalidArgumentException('This task is already '.$do->status->getLabel().'.');
        }

        if (empty($data['photo_paths'])) {
            throw new InvalidArgumentException('Upload at least one handover photo.');
        }

        return DB::transaction(function () use ($do, $driver, $data, $subsheet) {
            $proof = $this->create($do, $driver, DriverTaskProof::TYPE_HANDOVER, $data);

            $subsheet->update(['handover_status' => 'completed']);

            $do->update([
                'status' => DeliveryOrderStatus::Delivered,
                'delivered_at' => $proof->recorded_at,
            ]);

            ProfitSharingTransaction::query()
                ->where(fn ($q) => $q->where('delivery_order_id', $do->id)
                    ->when($subsheet->profit_sharing_transaction_id, fn ($q) => $q->orWhere('id', $subsheet->profit_sharing_transaction_id)))
                ->where('status', 'pending')
                ->update(['status' => 'eligible']);

            $jobSheet = $do->jobSheet;
            if ($jobSheet && ! $jobSheet->deliveryOrders()
                ->whereNotIn('status', [
                    DeliveryOrderStatus::Delivered->value,
                    DeliveryOrderStatus::Failed->value,
                    DeliveryOrderStatus::Cancelled->value,
                ])->exists()) {
                $jobSheet->update(['status' => JobSheetStatus::Completed, 'completed_at' => now()]);
            }

            return $proof;
        });
    }

    private function existing(array $data): ?DriverTaskProof
    {
        return empty($data['client_uuid'])
            ? null
            : DriverTaskProof::query()->where('client_uuid', $data['client_uuid'])->first();
    }

    private function create(DeliveryOrder $do, Driver $driver, string $type, array $data): DriverTaskProof
    {
        return DriverTaskProof::query()->create([
            'delivery_order_id' => $do->id,
            'subsheet_id' => $do->subsheet_id,
            'driver_id' => $driver->id,
            'type' => $type,
            'photo_paths' => $data['photo_paths'] ?? null,
            'handover_to' => $data['handover_to'] ?? null,
            'remarks' => $data['remarks'] ?? null,
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'client_uuid' => $data['client_uuid'] ?? null,
            'recorded_at' => $data['recorded_at'] ?? now(),
        ]);
    }
}
