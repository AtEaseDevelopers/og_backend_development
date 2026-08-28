<?php

namespace Database\Seeders;

use App\Domains\Consignment\Models\ConsignmentNote;
use App\Domains\Delivery\Actions\CompleteDelivery;
use App\Domains\Delivery\Actions\RecordReturnedCsn;
use App\Domains\Dispatch\Models\DeliveryOrder;
use App\Enums\DeliveryOrderStatus;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Throwable;

class ReturnedCsnDemoSeeder extends Seeder
{
    public function run(): void
    {
        $complete = app(CompleteDelivery::class);
        $recordReturn = app(RecordReturnedCsn::class);

        $receiver = User::query()
            ->where('email', 'counter.kl@og.local')
            ->first()
            ?? User::query()->where('is_hq', true)->first()
            ?? User::query()->firstOrFail();

        $deliveryOrders = DeliveryOrder::query()
            ->whereNotNull('consignment_note_id')
            ->whereNotNull('driver_id')
            ->whereNotIn('status', [
                DeliveryOrderStatus::Delivered->value,
                DeliveryOrderStatus::Failed->value,
                DeliveryOrderStatus::Cancelled->value,
            ])
            ->with(['consignmentNote', 'driver', 'jobSheet'])
            ->orderBy('id')
            ->limit(8)
            ->get();

        if ($deliveryOrders->isEmpty()) {
            $this->command?->warn('No eligible delivery orders found. Run DemoOperationsSeeder first.');

            return;
        }

        $returnProfiles = [
            ['is_signed' => true, 'is_stamped' => true, 'record_return' => false, 'remarks' => null],
            ['is_signed' => true, 'is_stamped' => true, 'record_return' => true, 'remarks' => 'Original signed CSN received at counter.'],
            ['is_signed' => true, 'is_stamped' => false, 'record_return' => true, 'remarks' => 'Signed copy returned; customer stamp not present.'],
            ['is_signed' => true, 'is_stamped' => false, 'record_return' => false, 'remarks' => null],
            ['is_signed' => false, 'is_stamped' => false, 'record_return' => false, 'remarks' => null],
            ['is_signed' => true, 'is_stamped' => true, 'record_return' => false, 'remarks' => null],
            ['is_signed' => true, 'is_stamped' => false, 'record_return' => true, 'remarks' => 'Late return after driver handover.'],
            ['is_signed' => true, 'is_stamped' => true, 'record_return' => false, 'remarks' => null],
        ];

        $delivered = 0;
        $returned = 0;
        $pending = 0;
        $pendingNumbers = [];
        $returnedNumbers = [];

        foreach ($deliveryOrders as $index => $do) {
            $driver = $do->driver;

            if (! $driver || ! $do->consignmentNote) {
                continue;
            }

            $profile = $returnProfiles[$index % count($returnProfiles)];

            try {
                if ($do->status !== DeliveryOrderStatus::Delivered) {
                    $complete->execute(
                        $do,
                        $driver,
                        [
                            'recipient_name' => 'Demo Receiver '.($index + 1),
                            'latitude' => 3.139 + ($index * 0.002),
                            'longitude' => 101.686 + ($index * 0.002),
                            'delivered_at' => now()->subDays(max(1, 3 - ($index % 3)))->subHours($index),
                            'client_uuid' => (string) Str::uuid(),
                        ],
                        $receiver
                    );
                    $delivered++;
                }

                $csn = $do->consignmentNote->fresh();

                if ($index === 0 && $csn->return_status !== 'returned' && ! $csn->returnedCsn()->exists()) {
                    $this->applyDemoScanAliases($csn);
                    $csn = $csn->fresh();
                }

                if ($profile['record_return'] && ! $csn->returnedCsn()->exists()) {
                    $recordReturn->execute($csn, [
                        'returned_by_driver_id' => $driver->id,
                        'is_signed' => $profile['is_signed'],
                        'is_stamped' => $profile['is_stamped'],
                        'remarks' => $profile['remarks'],
                        'returned_at' => now()->subHours(max(1, 6 - $index)),
                        'scan_method' => $index === 0 ? 'qr' : 'manual',
                    ], $receiver);

                    $returned++;
                    $returnedNumbers[] = $csn->number;
                } elseif ($csn->return_status === 'pending_return' && ! $csn->returnedCsn()->exists()) {
                    $pending++;
                    $pendingNumbers[] = $csn->number;
                }
            } catch (Throwable $exception) {
                $this->command?->warn("Skipped DO {$do->number}: {$exception->getMessage()}");
            }
        }

        $this->command?->info("Returned CSN demo: {$delivered} delivered, {$returned} recorded as returned, {$pending} pending return.");

        if ($pendingNumbers !== []) {
            $this->command?->info('Pending return CSNs (scan on Returned CSN Reconciliation):');
            foreach ($pendingNumbers as $number) {
                $this->command?->line("  - {$number}");
            }
            $this->command?->info('Try scanning: CSN-2608-1208 (alias for first demo CSN).');
        }

        if ($returnedNumbers !== []) {
            $this->command?->info('Already returned CSNs:');
            foreach ($returnedNumbers as $number) {
                $this->command?->line("  - {$number}");
            }
        }
    }

    private function applyDemoScanAliases(ConsignmentNote $csn): void
    {
        $csn->update([
            'number' => 'CSN-2608-1208',
            'qr_token' => 'csn-demo-2608-1208',
            'do_number' => $csn->do_number ?: 'DO-2608-1208',
            'job_no' => $csn->job_no ?: 'JS-2608-018',
            'customer_name' => $csn->customer_name ?: 'Mega Industrial Sdn Bhd',
        ]);

        $do = $csn->deliveryOrders()->latest('id')->first();
        $do?->update(['number' => 'DO-2608-1208']);

        $jobSheet = $do?->jobSheet;
        $jobSheet?->update(['number' => 'JS-2608-018']);
    }
}
