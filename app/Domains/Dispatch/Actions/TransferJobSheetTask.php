<?php

namespace App\Domains\Dispatch\Actions;

use App\Domains\Dispatch\Models\DeliveryOrder;
use App\Domains\Dispatch\Models\JobSheet;
use App\Domains\Dispatch\Models\JobSheetTask;
use App\Domains\Dispatch\Models\JobSheetTransfer;
use App\Domains\MasterData\Models\Lorry;
use App\Domains\Notification\Actions\SendNotification;
use App\Domains\Notification\Models\NotificationLog;
use App\Enums\CsnStatus;
use App\Enums\DeliveryOrderStatus;
use App\Enums\JobSheetStatus;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Moves a delivery task to another job sheet / lorry, including while already in route
 * (section J). Records original + new lorry and driver, time, place and reason; notifies
 * both drivers and the customer; the new driver scans the CSN to claim the transferred job.
 */
class TransferJobSheetTask
{
    public function __construct(
        private ResolveJobSheet $resolveJobSheet,
        private SendNotification $notify,
    ) {}

    public function execute(
        DeliveryOrder $do,
        JobSheet $toJobSheet,
        User $actor,
        string $reason,
        ?string $handoverAt = null,
        ?string $handoverLocation = null,
    ): JobSheetTransfer {
        self::assertAuthorised($actor);

        $do->loadMissing(['jobSheet.lorry', 'jobSheet.driver', 'consignmentNote', 'driver', 'lorry']);

        if (! $do->job_sheet_id) {
            throw new InvalidArgumentException('Delivery order is not on a Job Sheet.');
        }

        if ($do->job_sheet_id === $toJobSheet->id) {
            throw new InvalidArgumentException('Delivery order is already on the target Job Sheet.');
        }

        if ($do->status === DeliveryOrderStatus::Delivered) {
            throw new InvalidArgumentException('Cannot transfer a delivered order.');
        }

        if ($toJobSheet->status === JobSheetStatus::Completed) {
            throw new InvalidArgumentException('Cannot transfer into a completed trip.');
        }

        $from = $do->jobSheet;
        $inRoute = $from->status === JobSheetStatus::InTransit || $do->status === DeliveryOrderStatus::InTransit;

        if (blank($reason)) {
            throw new InvalidArgumentException('A reason is required for every lorry / driver transfer.');
        }

        return DB::transaction(function () use ($do, $from, $toJobSheet, $actor, $reason, $inRoute, $handoverAt, $handoverLocation) {
            $toJobSheet->loadMissing(['lorry', 'driver']);

            $fromLorryId = $do->lorry_id ?? $from->lorry_id;
            $fromDriverId = $do->driver_id ?? $from->driver_id;

            JobSheetTask::query()
                ->where('job_sheet_id', $from->id)
                ->where('delivery_order_id', $do->id)
                ->delete();

            $sequence = (int) $toJobSheet->tasks()->max('sequence') + 1;
            JobSheetTask::query()->create([
                'job_sheet_id' => $toJobSheet->id,
                'delivery_order_id' => $do->id,
                'sequence' => $sequence,
                'route_group' => $do->consignmentNote?->delivery_state,
            ]);

            $do->update([
                'job_sheet_id' => $toJobSheet->id,
                'lorry_id' => $toJobSheet->lorry_id,
                'driver_id' => $toJobSheet->driver_id,
                'status' => DeliveryOrderStatus::Transferred,
            ]);

            // Active status on the receiving trip
            if ($toJobSheet->status === JobSheetStatus::InTransit) {
                $do->update(['status' => DeliveryOrderStatus::InTransit]);
            } elseif ($toJobSheet->status === JobSheetStatus::Draft) {
                $do->update(['status' => DeliveryOrderStatus::Assigned]);
            }

            $transfer = JobSheetTransfer::query()->create([
                'delivery_order_id' => $do->id,
                'consignment_note_id' => $do->consignment_note_id,
                'from_job_sheet_id' => $from->id,
                'to_job_sheet_id' => $toJobSheet->id,
                'from_lorry_id' => $fromLorryId,
                'to_lorry_id' => $toJobSheet->lorry_id,
                'from_driver_id' => $fromDriverId,
                'to_driver_id' => $toJobSheet->driver_id,
                'in_route' => $inRoute,
                'handover_at' => $handoverAt ? Carbon::parse($handoverAt) : now(),
                'handover_location' => $handoverLocation,
                'reason' => $reason,
                'transferred_by' => $actor->id,
            ]);

            $csn = $do->consignmentNote;

            if ($csn && ! $do->isSubDo()) {
                $driverChanged = (int) $fromDriverId !== (int) $toJobSheet->driver_id;

                $csn->update([
                    'status' => $toJobSheet->status === JobSheetStatus::InTransit
                        ? CsnStatus::InTransit
                        : CsnStatus::Assigned,
                    // the receiving driver must scan the CSN to claim the transferred job
                    'transfer_claim_pending' => $driverChanged,
                    'claimed_by' => $driverChanged ? null : $csn->claimed_by,
                    'claimed_at' => $driverChanged ? null : $csn->claimed_at,
                ]);
            }

            $this->refreshJobSheetCompletion($from);
            $this->refreshJobSheetCompletion($toJobSheet);

            $transfer->load(['fromJobSheet', 'toJobSheet', 'deliveryOrder', 'fromLorry', 'toLorry', 'fromDriver', 'toDriver']);

            $this->sendNotifications($transfer, $inRoute);

            return $transfer;
        });
    }

    public function transferToLorry(
        DeliveryOrder $do,
        Lorry $lorry,
        User $actor,
        string $reason,
        ?string $operatingDate = null,
        ?int $driverId = null,
        ?string $handoverAt = null,
        ?string $handoverLocation = null,
    ): JobSheetTransfer {
        $do->loadMissing('jobSheet');

        $date = $operatingDate
            ? Carbon::parse($operatingDate)->toDateString()
            : ($do->jobSheet?->operating_date?->toDateString() ?? now()->toDateString());

        if (! $lorry->is_active) {
            throw new InvalidArgumentException('Lorry '.$lorry->registration_no.' is not active.');
        }

        $jobSheet = $this->resolveJobSheet->forLorry(
            $lorry,
            $date,
            $driverId,
            $do->company_id,
            $do->source_branch_id !== $lorry->branch_id,
        );

        return $this->execute($do, $jobSheet, $actor, $reason, $handoverAt, $handoverLocation);
    }

    /** Flowchart: lorry / driver changes while in route are allowed only to authorised Admin. */
    public static function canTransfer(?User $user): bool
    {
        return (bool) ($user?->is_hq || $user?->hasAnyRole(['hq_admin', 'branch_manager', 'dispatcher']));
    }

    private static function assertAuthorised(User $actor): void
    {
        if (! self::canTransfer($actor)) {
            throw new InvalidArgumentException('Only HQ Admin, Branch Manager or Dispatcher may transfer a delivery to another lorry / driver.');
        }
    }

    private function sendNotifications(JobSheetTransfer $transfer, bool $inRoute): void
    {
        $do = $transfer->deliveryOrder;
        $csn = $do?->consignmentNote;
        $csnNumber = $csn?->number ?? $do?->number;

        $summary = sprintf(
            "Delivery %s has been transferred from lorry %s (%s) to lorry %s (%s).\nReason: %s\nHandover: %s%s",
            $csnNumber,
            $transfer->fromLorry?->registration_no ?? '—',
            $transfer->fromDriver?->name ?? 'no driver',
            $transfer->toLorry?->registration_no ?? '—',
            $transfer->toDriver?->name ?? 'no driver',
            $transfer->reason,
            $transfer->handover_at?->format('d/m/Y H:i'),
            $transfer->handover_location ? ' at '.$transfer->handover_location : '',
        );

        foreach ([$transfer->fromDriver, $transfer->toDriver] as $driver) {
            if (! $driver) {
                continue;
            }

            $this->notify->execute(
                event: 'lorry_transferred',
                recipient: ['type' => 'driver', 'name' => $driver->name, 'phone' => $driver->phone, 'email' => $driver->user?->email],
                subject: 'Delivery transferred: '.$csnNumber,
                message: $summary.($driver->id === $transfer->to_driver_id ? "\nPlease scan the CSN to claim this job." : ''),
                related: $csn ?? $do,
                channels: [NotificationLog::CHANNEL_WHATSAPP, NotificationLog::CHANNEL_SYSTEM],
            );
        }

        if ($inRoute && $csn) {
            $this->notify->execute(
                event: 'delivery_lorry_changed',
                recipient: [
                    'type' => 'customer',
                    'name' => $csn->consignee_name ?? $csn->customer_name,
                    'phone' => $csn->consignee_phone ?? $csn->customer_phone,
                    'email' => $csn->customer?->email,
                ],
                subject: 'Delivery update for '.$csnNumber,
                message: sprintf(
                    'Your delivery %s is now being handled by lorry %s. It remains in transit and will be delivered as planned.',
                    $csnNumber,
                    $transfer->toLorry?->registration_no ?? '—',
                ),
                related: $csn,
            );
        }
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
            $jobSheet->update(['status' => JobSheetStatus::Completed, 'completed_at' => now()]);
        }
    }
}
