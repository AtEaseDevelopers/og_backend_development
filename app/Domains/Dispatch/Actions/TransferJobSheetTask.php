<?php

namespace App\Domains\Dispatch\Actions;

use App\Domains\Dispatch\Models\DeliveryOrder;
use App\Domains\Dispatch\Models\JobSheet;
use App\Domains\Dispatch\Models\JobSheetTask;
use App\Domains\Dispatch\Models\JobSheetTransfer;
use App\Enums\DeliveryOrderStatus;
use App\Enums\DocumentType;
use App\Enums\JobSheetStatus;
use App\Models\User;
use App\Services\DocumentNumberingService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class TransferJobSheetTask
{
    public function __construct(private DocumentNumberingService $numbering) {}

    public function execute(
        DeliveryOrder $do,
        JobSheet $toJobSheet,
        User $actor,
        string $reason,
    ): JobSheetTransfer {
        $do->loadMissing('jobSheet');

        if (! $do->job_sheet_id) {
            throw new InvalidArgumentException('Delivery order is not on a Job Sheet.');
        }

        if ($do->job_sheet_id === $toJobSheet->id) {
            throw new InvalidArgumentException('Delivery order is already on the target Job Sheet.');
        }

        if ($do->status === DeliveryOrderStatus::Delivered) {
            throw new InvalidArgumentException('Cannot transfer a delivered order.');
        }

        $from = $do->jobSheet;

        if ($from->status === JobSheetStatus::InTransit && blank($reason)) {
            throw new InvalidArgumentException('In-transit transfers require a reason.');
        }

        return DB::transaction(function () use ($do, $from, $toJobSheet, $actor, $reason) {
            JobSheetTask::query()
                ->where('job_sheet_id', $from->id)
                ->where('delivery_order_id', $do->id)
                ->delete();

            $sequence = (int) $toJobSheet->tasks()->max('sequence') + 1;
            JobSheetTask::query()->create([
                'job_sheet_id' => $toJobSheet->id,
                'delivery_order_id' => $do->id,
                'sequence' => $sequence,
            ]);

            $do->update([
                'job_sheet_id' => $toJobSheet->id,
                'lorry_id' => $toJobSheet->lorry_id,
                'driver_id' => $toJobSheet->driver_id,
                'status' => DeliveryOrderStatus::Transferred,
            ]);

            // After transfer, active status for the receiving sheet
            if ($toJobSheet->status === JobSheetStatus::InTransit) {
                $do->update(['status' => DeliveryOrderStatus::InTransit]);
            } elseif ($toJobSheet->status === JobSheetStatus::Draft) {
                $do->update(['status' => DeliveryOrderStatus::Assigned]);
            }

            $transfer = JobSheetTransfer::query()->create([
                'delivery_order_id' => $do->id,
                'from_job_sheet_id' => $from->id,
                'to_job_sheet_id' => $toJobSheet->id,
                'reason' => $reason,
                'transferred_by' => $actor->id,
            ]);

            $this->refreshJobSheetCompletion($from);
            $this->refreshJobSheetCompletion($toJobSheet);

            return $transfer->load(['fromJobSheet', 'toJobSheet', 'deliveryOrder']);
        });
    }

    public function transferToLorry(
        DeliveryOrder $do,
        \App\Domains\MasterData\Models\Lorry $lorry,
        User $actor,
        string $reason,
        ?string $operatingDate = null,
    ): JobSheetTransfer {
        $date = $operatingDate
            ? \Illuminate\Support\Carbon::parse($operatingDate)->toDateString()
            : ($do->jobSheet?->operating_date?->toDateString() ?? now()->toDateString());

        $lorry->load('branch', 'defaultDriver');

        $jobSheet = JobSheet::query()->firstOrCreate(
            [
                'lorry_id' => $lorry->id,
                'operating_date' => $date,
            ],
            [
                'number' => $this->numbering->next($lorry->branch, DocumentType::JobSheet),
                'operating_branch_id' => $lorry->branch_id,
                'driver_id' => $lorry->default_driver_id,
                'status' => JobSheetStatus::Draft,
                'is_shared_dispatch' => $do->source_branch_id !== $lorry->branch_id,
            ]
        );

        if ($do->source_branch_id !== $lorry->branch_id) {
            $jobSheet->update(['is_shared_dispatch' => true]);
        }

        return $this->execute($do, $jobSheet, $actor, $reason);
    }

    private function refreshJobSheetCompletion(JobSheet $jobSheet): void
    {
        $pending = $jobSheet->deliveryOrders()
            ->whereNotIn('status', [
                DeliveryOrderStatus::Delivered->value,
                DeliveryOrderStatus::Failed->value,
                DeliveryOrderStatus::Cancelled->value,
                DeliveryOrderStatus::Transferred->value,
            ])
            ->exists();

        if (! $pending && $jobSheet->status === JobSheetStatus::InTransit) {
            $jobSheet->update(['status' => JobSheetStatus::Completed]);
        }
    }
}
