<?php

namespace App\Domains\Delivery\Actions;

use App\Domains\Delivery\Models\FailedDelivery;
use App\Domains\Dispatch\Actions\TransferJobSheetTask;
use App\Domains\Dispatch\Models\DeliveryOrder;
use App\Domains\Dispatch\Models\JobSheet;
use App\Domains\Dispatch\Models\JobSheetTask;
use App\Domains\MasterData\Models\Lorry;
use App\Enums\DeliveryOrderStatus;
use App\Enums\DocumentType;
use App\Enums\JobSheetStatus;
use App\Models\User;
use App\Services\DocumentNumberingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ReassignFailedDelivery
{
    public function __construct(
        private DocumentNumberingService $numbering,
        private TransferJobSheetTask $transfer,
    ) {}

    /**
     * @param  string  $option  standard|duplicate
     */
    public function execute(
        FailedDelivery $failed,
        string $option,
        Lorry $lorry,
        User $actor,
        ?string $operatingDate = null,
        ?int $driverId = null,
    ): DeliveryOrder {
        if (! in_array($option, ['standard', 'duplicate'], true)) {
            throw new InvalidArgumentException('Reassignment option must be standard or duplicate.');
        }

        $failed->loadMissing('deliveryOrder.consignmentNote', 'deliveryOrder.sourceBranch');
        $original = $failed->deliveryOrder;

        if ($original->status !== DeliveryOrderStatus::Failed) {
            throw new InvalidArgumentException('Only failed delivery orders can be reassigned.');
        }

        if ($failed->replacement_do_id) {
            throw new InvalidArgumentException('This failed delivery was already reassigned.');
        }

        return DB::transaction(function () use ($failed, $option, $lorry, $actor, $operatingDate, $driverId, $original) {
            $lorry->load('branch', 'defaultDriver');
            $date = $operatingDate
                ? \Illuminate\Support\Carbon::parse($operatingDate)->toDateString()
                : now()->toDateString();

            $jobSheet = JobSheet::query()->firstOrCreate(
                [
                    'lorry_id' => $lorry->id,
                    'operating_date' => $date,
                ],
                [
                    'number' => $this->numbering->next($lorry->branch, DocumentType::JobSheet),
                    'operating_branch_id' => $lorry->branch_id,
                    'driver_id' => $driverId ?? $lorry->default_driver_id,
                    'status' => JobSheetStatus::Draft,
                    'is_shared_dispatch' => $original->source_branch_id !== $lorry->branch_id,
                ]
            );

            if ($option === 'standard') {
                // Move the same DO to the replacement job sheet; original driver gets no commission later.
                $failed->update(['reassignment_option' => 'standard']);

                if ($original->job_sheet_id && $original->job_sheet_id !== $jobSheet->id) {
                    $this->transfer->execute(
                        $original,
                        $jobSheet,
                        $actor,
                        'Standard failed-delivery reassignment'
                    );
                } else {
                    $original->update([
                        'job_sheet_id' => $jobSheet->id,
                        'lorry_id' => $lorry->id,
                        'driver_id' => $driverId ?? $lorry->default_driver_id,
                        'status' => DeliveryOrderStatus::Reassigned,
                    ]);
                    JobSheetTask::query()->firstOrCreate(
                        [
                            'job_sheet_id' => $jobSheet->id,
                            'delivery_order_id' => $original->id,
                        ],
                        ['sequence' => (int) $jobSheet->tasks()->max('sequence') + 1]
                    );
                }

                $failed->update(['replacement_do_id' => $original->id]);

                return $original->fresh(['jobSheet', 'lorry', 'driver']);
            }

            // Duplicate & reassign — keep failed DO, create linked new DO eligible for dual commission.
            $newDo = DeliveryOrder::query()->create([
                'number' => $this->numbering->next($original->sourceBranch, DocumentType::Do),
                'consignment_note_id' => $original->consignment_note_id,
                'source_branch_id' => $original->source_branch_id,
                'job_sheet_id' => $jobSheet->id,
                'lorry_id' => $lorry->id,
                'driver_id' => $driverId ?? $lorry->default_driver_id,
                'status' => DeliveryOrderStatus::Assigned,
                'tracking_token' => Str::random(40),
                'parent_do_id' => $original->id,
                'is_duplicate' => true,
            ]);

            JobSheetTask::query()->create([
                'job_sheet_id' => $jobSheet->id,
                'delivery_order_id' => $newDo->id,
                'sequence' => (int) $jobSheet->tasks()->max('sequence') + 1,
            ]);

            $failed->update([
                'reassignment_option' => 'duplicate',
                'replacement_do_id' => $newDo->id,
            ]);

            return $newDo->load(['jobSheet', 'lorry', 'driver', 'consignmentNote']);
        });
    }
}
