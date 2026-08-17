<?php

namespace App\Domains\Dispatch\Actions;

use App\Domains\Consignment\Models\ConsignmentNote;
use App\Domains\Dispatch\Models\DeliveryOrder;
use App\Domains\Dispatch\Models\JobSheet;
use App\Domains\Dispatch\Models\JobSheetTask;
use App\Domains\Dispatch\Models\Subsheet;
use App\Domains\MasterData\Models\Lorry;
use App\Enums\CsnStatus;
use App\Enums\DeliveryOrderStatus;
use App\Enums\DocumentType;
use App\Enums\JobSheetStatus;
use App\Services\DocumentNumberingService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AssignDeliveryOrderToLorry
{
    public function __construct(private DocumentNumberingService $numbering) {}

    public function execute(
        DeliveryOrder $do,
        Lorry $lorry,
        ?string $operatingDate = null,
        ?int $driverId = null,
    ): DeliveryOrder {
        $do->loadMissing(['jobSheet', 'consignmentNote', 'sourceBranch']);

        if ($do->status === DeliveryOrderStatus::Delivered) {
            throw new InvalidArgumentException('Delivered delivery orders cannot be reassigned.');
        }

        if ($do->status === DeliveryOrderStatus::Cancelled) {
            throw new InvalidArgumentException('Cancelled delivery orders cannot be reassigned.');
        }

        $csn = $do->consignmentNote;
        if ($csn?->status === CsnStatus::Cancelled) {
            throw new InvalidArgumentException('Cancelled CSN cannot be assigned.');
        }

        if ($csn && ! $csn->canAssignToLorry()) {
            throw new InvalidArgumentException(
                'Cash Bill CSN requires full payment before assignment / printing.'
            );
        }

        $date = $operatingDate
            ? \Illuminate\Support\Carbon::parse($operatingDate)->toDateString()
            : ($do->jobSheet?->operating_date?->toDateString() ?? now()->toDateString());

        return DB::transaction(function () use ($do, $lorry, $date, $driverId, $csn) {
            $lorry->load('defaultDriver', 'branch');
            $resolvedDriverId = $driverId ?: $lorry->default_driver_id;
            $isShared = $do->source_branch_id !== $lorry->branch_id;

            $jobSheet = JobSheet::query()->firstOrCreate(
                [
                    'lorry_id' => $lorry->id,
                    'operating_date' => $date,
                ],
                [
                    'number' => $this->numbering->next($lorry->branch, DocumentType::JobSheet),
                    'company_id' => $lorry->company_id ?? $do->company_id,
                    'operating_branch_id' => $lorry->branch_id,
                    'driver_id' => $resolvedDriverId,
                    'status' => JobSheetStatus::Draft,
                    'is_shared_dispatch' => $isShared,
                ]
            );

            if ($resolvedDriverId && (int) $jobSheet->driver_id !== (int) $resolvedDriverId) {
                $jobSheet->update(['driver_id' => $resolvedDriverId]);
            }

            if ($isShared && ! $jobSheet->is_shared_dispatch) {
                $jobSheet->update(['is_shared_dispatch' => true]);
            }

            $previousJobSheetId = $do->job_sheet_id;

            if ($previousJobSheetId && $previousJobSheetId !== $jobSheet->id) {
                JobSheetTask::query()
                    ->where('job_sheet_id', $previousJobSheetId)
                    ->where('delivery_order_id', $do->id)
                    ->delete();
            }

            $do->update([
                'job_sheet_id' => $jobSheet->id,
                'lorry_id' => $lorry->id,
                'driver_id' => $resolvedDriverId ?? $jobSheet->driver_id,
                'status' => DeliveryOrderStatus::Assigned,
            ]);

            JobSheetTask::query()->firstOrCreate(
                [
                    'job_sheet_id' => $jobSheet->id,
                    'delivery_order_id' => $do->id,
                ],
                [
                    'sequence' => (int) $jobSheet->tasks()->max('sequence') + 1,
                    'route_group' => $csn?->delivery_state,
                ]
            );

            if ($do->parent_do_id) {
                Subsheet::query()
                    ->where('delivery_order_id', $do->id)
                    ->update([
                        'job_sheet_id' => $jobSheet->id,
                        'sub_lorry_id' => $lorry->id,
                        'sub_driver_id' => $resolvedDriverId,
                    ]);
            }

            if ($csn && ! $do->parent_do_id) {
                $csn->update(['status' => CsnStatus::Assigned]);
            }

            return $do->fresh(['jobSheet', 'lorry', 'driver', 'consignmentNote']);
        });
    }
}
