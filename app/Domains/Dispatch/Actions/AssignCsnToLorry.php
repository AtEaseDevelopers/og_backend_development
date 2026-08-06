<?php

namespace App\Domains\Dispatch\Actions;

use App\Domains\Consignment\Models\ConsignmentNote;
use App\Domains\Dispatch\Models\DeliveryOrder;
use App\Domains\Dispatch\Models\JobSheet;
use App\Domains\Dispatch\Models\JobSheetTask;
use App\Domains\MasterData\Models\Lorry;
use App\Enums\CsnStatus;
use App\Enums\DeliveryOrderStatus;
use App\Enums\DocumentType;
use App\Enums\JobSheetStatus;
use App\Services\DocumentNumberingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class AssignCsnToLorry
{
    public function __construct(private DocumentNumberingService $numbering) {}

    public function execute(
        ConsignmentNote $csn,
        Lorry $lorry,
        ?string $operatingDate = null,
        ?int $driverId = null,
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

        $date = $operatingDate
            ? \Illuminate\Support\Carbon::parse($operatingDate)->toDateString()
            : now()->toDateString();

        return DB::transaction(function () use ($csn, $lorry, $date, $driverId) {
            $lorry->load('defaultDriver', 'branch');
            $resolvedDriverId = $driverId ?: $lorry->default_driver_id;

            $isShared = $csn->source_branch_id !== $lorry->branch_id;

            $jobSheet = JobSheet::query()->firstOrCreate(
                [
                    'lorry_id' => $lorry->id,
                    'operating_date' => $date,
                ],
                [
                    'number' => $this->numbering->next($lorry->branch, DocumentType::JobSheet),
                    'company_id' => $lorry->company_id ?? $csn->company_id,
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

            $csn->update(['status' => CsnStatus::Assigned]);

            return $do->load(['consignmentNote', 'jobSheet', 'lorry', 'driver']);
        });
    }
}
