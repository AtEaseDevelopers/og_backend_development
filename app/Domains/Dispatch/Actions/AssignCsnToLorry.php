<?php

namespace App\Domains\Dispatch\Actions;

use App\Domains\Consignment\Models\ConsignmentNote;
use App\Domains\Dispatch\Models\DeliveryOrder;
use App\Domains\Dispatch\Models\JobSheet;
use App\Domains\Dispatch\Models\JobSheetTask;
use App\Domains\MasterData\Models\Driver;
use App\Domains\MasterData\Models\Lorry;
use App\Domains\Notification\Actions\SendNotification;
use App\Domains\Notification\Models\NotificationLog;
use App\Enums\CsnStatus;
use App\Enums\DeliveryOrderStatus;
use App\Enums\DocumentType;
use App\Models\User;
use App\Services\DocumentNumberingService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class AssignCsnToLorry
{
    public function __construct(
        private DocumentNumberingService $numbering,
        private ResolveJobSheet $resolveJobSheet,
        private SendNotification $notify,
    ) {}

    /**
     * Manual assignment (admin) or assignment following a driver claim.
     *
     * @param  JobSheet|null  $jobSheet  target trip; when null the lorry's open trip for the date is used
     */
    public function execute(
        ConsignmentNote $csn,
        Lorry $lorry,
        ?string $operatingDate = null,
        ?int $driverId = null,
        ?User $actor = null,
        ?JobSheet $jobSheet = null,
    ): DeliveryOrder {
        if ($csn->deliveryOrder()->exists()) {
            throw new InvalidArgumentException('CSN already has a Delivery Order.');
        }

        if ($csn->status === CsnStatus::Cancelled) {
            throw new InvalidArgumentException('Cancelled CSN cannot be assigned.');
        }

        if (! $csn->canAssignToLorry()) {
            throw new InvalidArgumentException(
                'Cash Bill CSN requires full payment before assignment / printing.'
            );
        }

        if (! $lorry->is_active) {
            throw new InvalidArgumentException('Lorry '.$lorry->registration_no.' is not active.');
        }

        $date = $operatingDate
            ? Carbon::parse($operatingDate)->toDateString()
            : ($jobSheet?->operating_date?->toDateString() ?? now()->toDateString());

        return DB::transaction(function () use ($csn, $lorry, $date, $driverId, $actor, $jobSheet) {
            $lorry->load('defaultDriver', 'branch');
            $resolvedDriverId = $driverId ?: $lorry->default_driver_id;

            if ($resolvedDriverId) {
                $driver = Driver::query()->find($resolvedDriverId);

                if ($driver && ! $driver->is_active) {
                    throw new InvalidArgumentException('Driver '.$driver->name.' is not active.');
                }
            }

            $isShared = $csn->source_branch_id !== $lorry->branch_id;

            $jobSheet ??= $this->resolveJobSheet->forLorry(
                $lorry,
                $date,
                $resolvedDriverId,
                $csn->company_id,
                $isShared,
            );

            $do = DeliveryOrder::query()->create([
                'number' => $this->numbering->next($csn->sourceBranch, DocumentType::Do),
                'company_id' => $csn->company_id,
                'consignment_note_id' => $csn->id,
                'source_branch_id' => $csn->source_branch_id,
                'job_sheet_id' => $jobSheet->id,
                'lorry_id' => $lorry->id,
                'driver_id' => $resolvedDriverId ?? $jobSheet->driver_id,
                'status' => DeliveryOrderStatus::Assigned,
                'tracking_token' => Str::random(40),
            ]);

            $sequence = (int) $jobSheet->tasks()->max('sequence') + 1;

            JobSheetTask::query()->create([
                'job_sheet_id' => $jobSheet->id,
                'delivery_order_id' => $do->id,
                'sequence' => $sequence,
                'route_group' => $csn->delivery_state,
            ]);

            $csn->update([
                'status' => CsnStatus::Assigned,
                'assigned_by' => $actor?->id,
                'assigned_at' => now(),
                'transfer_claim_pending' => false,
            ]);

            // subsheets put on a lorry before the CSN itself: their legs now hang under this main delivery order
            DeliveryOrder::query()
                ->where('consignment_note_id', $csn->id)
                ->whereNotNull('subsheet_id')
                ->whereNull('parent_do_id')
                ->update(['parent_do_id' => $do->id]);
            $csn->subsheets()->whereNull('main_lorry_id')->update(['main_lorry_id' => $lorry->id, 'main_driver_id' => $do->driver_id]);

            $this->notifyDriver($do->load(['consignmentNote', 'jobSheet', 'lorry', 'driver']));

            return $do;
        });
    }

    private function notifyDriver(DeliveryOrder $do): void
    {
        $driver = $do->driver;

        if (! $driver) {
            return;
        }

        $csn = $do->consignmentNote;

        $this->notify->execute(
            event: 'csn_assigned',
            recipient: ['type' => 'driver', 'name' => $driver->name, 'phone' => $driver->phone, 'email' => $driver->user?->email],
            subject: 'New delivery assigned: '.$csn->number,
            message: sprintf(
                "CSN %s (DO %s) has been assigned to lorry %s on %s (%s).\nDeliver to: %s, %s",
                $csn->number,
                $do->number,
                $do->lorry?->registration_no,
                $do->jobSheet?->operating_date?->format('d/m/Y'),
                $do->jobSheet?->tripLabel(),
                $csn->consignee_name,
                $csn->delivery_address,
            ),
            related: $csn,
            channels: [NotificationLog::CHANNEL_WHATSAPP, NotificationLog::CHANNEL_SYSTEM],
        );
    }
}
