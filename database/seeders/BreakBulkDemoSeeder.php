<?php

namespace Database\Seeders;

use App\Domains\Delivery\Actions\CreateBreakBulk;
use App\Domains\Dispatch\Models\DeliveryOrder;
use App\Models\User;
use Illuminate\Database\Seeder;
use Throwable;

class BreakBulkDemoSeeder extends Seeder
{
    public function run(): void
    {
        $actor = User::query()->where('email', 'manager.kl@og.local')->first()
            ?? User::query()->where('email', 'admin@og.local')->firstOrFail();

        $create = app(CreateBreakBulk::class);

        $reasons = [
            'Unexpected Delivery Change',
            'Vehicle Breakdown',
            'Operational Transfer',
            'Recipient Unavailable',
            'Route Reassignment',
        ];

        $locations = [
            'Ipoh Transit Hub',
            'Penang Hub',
            'KL Central Depot',
            'Shah Alam Warehouse',
            'Seremban Transit Point',
        ];

        $deliveryOrders = DeliveryOrder::query()
            ->whereNotNull('job_sheet_id')
            ->whereNotNull('driver_id')
            ->with(['driver', 'jobSheet'])
            ->orderByDesc('id')
            ->limit(8)
            ->get();

        $created = 0;

        foreach ($deliveryOrders as $index => $do) {
            try {
                $create->execute($do, [
                    'location' => $locations[$index % count($locations)],
                    'reason' => $reasons[$index % count($reasons)],
                    'photo_paths' => [],
                ], $index % 2 === 0 ? $do->driver : null, $actor);

                $created++;
            } catch (Throwable $exception) {
                $this->command?->warn("Skipped DO {$do->number}: {$exception->getMessage()}");
            }
        }

        $this->command?->info("Created {$created} break-bulk demo record(s).");
    }
}
