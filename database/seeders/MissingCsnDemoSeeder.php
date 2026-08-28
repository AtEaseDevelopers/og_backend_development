<?php

namespace Database\Seeders;

use App\Domains\Consignment\Models\ConsignmentNote;
use App\Domains\Delivery\Actions\FlagMissingCsns;
use App\Domains\Delivery\Models\MissingCsnLog;
use App\Enums\CsnStatus;
use Illuminate\Database\Seeder;

class MissingCsnDemoSeeder extends Seeder
{
    public function run(): void
    {
        $graceDays = (int) config('og.missing_csn_days', 7);

        $logs = MissingCsnLog::query()
            ->where('status', 'pending_return')
            ->with('deliveryOrder', 'consignmentNote')
            ->orderBy('id')
            ->limit(5)
            ->get();

        $backdated = 0;

        foreach ($logs as $index => $log) {
            $do = $log->deliveryOrder;

            if (! $do || ! $do->delivered_at) {
                continue;
            }

            if ($index >= 1) {
                $do->update([
                    'delivered_at' => now()->subDays($graceDays + 1 + $index),
                ]);

                $log->consignmentNote?->update([
                    'status' => CsnStatus::Delivered,
                    'return_status' => 'pending_return',
                ]);

                $backdated++;
            }
        }

        // Ensure KL demo CSNs without open logs get tracked again.
        ConsignmentNote::query()
            ->where('company_id', 1)
            ->where('status', CsnStatus::Delivered)
            ->whereIn('return_status', ['pending_return', 'missing'])
            ->whereDoesntHave('returnedCsn')
            ->whereDoesntHave('missingCsnLogs', fn ($q) => $q->whereIn('status', ['pending_return', 'missing']))
            ->with('deliveryOrders')
            ->limit(2)
            ->get()
            ->each(function (ConsignmentNote $csn): void {
                app(FlagMissingCsns::class)->ensurePendingReturn($csn);
            });

        $flagged = app(FlagMissingCsns::class)->execute();

        MissingCsnLog::query()
            ->whereNull('company_id')
            ->with(['consignmentNote', 'deliveryOrder'])
            ->get()
            ->each(function (MissingCsnLog $log): void {
                $companyId = $log->consignmentNote?->company_id ?? $log->deliveryOrder?->company_id;

                if ($companyId) {
                    $log->update(['company_id' => $companyId]);
                }
            });

        $this->command?->info("Missing CSN demo: {$backdated} delivery date(s) backdated, {$flagged->count()} CSN(s) marked missing.");
        $this->command?->info('Pending return: '.MissingCsnLog::query()->where('status', 'pending_return')->count());
        $this->command?->info('Missing: '.MissingCsnLog::query()->where('status', 'missing')->count());
    }
}
