<?php

namespace Database\Seeders;

use App\Domains\Delivery\Actions\FailDelivery;
use App\Domains\Dispatch\Models\DeliveryOrder;
use App\Enums\DeliveryOrderStatus;
use Illuminate\Database\Seeder;
use Throwable;

class FailedDeliveryDemoSeeder extends Seeder
{
    public function run(): void
    {
        $fail = app(FailDelivery::class);

        $reasons = [
            'Recipient Unavailable',
            'Wrong Address',
            'Lazy',
            'Premise Closed',
        ];

        $deliveryOrders = DeliveryOrder::query()
            ->whereNotNull('job_sheet_id')
            ->whereNotNull('driver_id')
            ->whereNotIn('status', [
                DeliveryOrderStatus::Delivered->value,
                DeliveryOrderStatus::Failed->value,
                DeliveryOrderStatus::Cancelled->value,
            ])
            ->with(['driver', 'jobSheet'])
            ->orderByDesc('id')
            ->limit(4)
            ->get();

        $created = 0;

        foreach ($deliveryOrders as $index => $do) {
            if (! $do->driver) {
                continue;
            }

            try {
                $fail->execute($do, $do->driver, [
                    'reason' => $reasons[$index % count($reasons)],
                    'remarks' => 'Recorded via demo seeder',
                    'failed_at' => now()->subHours(2 - $index),
                ]);

                $created++;
            } catch (Throwable $exception) {
                $this->command?->warn("Skipped DO {$do->number}: {$exception->getMessage()}");
            }
        }

        $this->command?->info("Created {$created} failed delivery demo record(s).");
    }
}
