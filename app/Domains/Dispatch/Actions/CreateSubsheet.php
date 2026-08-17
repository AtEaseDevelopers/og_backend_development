<?php

namespace App\Domains\Dispatch\Actions;

use App\Domains\Dispatch\Models\DeliveryOrder;
use App\Domains\Dispatch\Models\JobSheet;
use App\Domains\Dispatch\Models\JobSheetTask;
use App\Domains\Dispatch\Models\ProfitSharingTransaction;
use App\Domains\Dispatch\Models\Subsheet;
use App\Domains\MasterData\Models\Lorry;
use App\Domains\MasterData\Models\TransferCode;
use App\Enums\DeliveryOrderStatus;
use App\Enums\DocumentType;
use App\Enums\JobSheetStatus;
use App\Services\DocumentNumberingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class CreateSubsheet
{
    public function __construct(private DocumentNumberingService $numbering) {}

    public function execute(DeliveryOrder $parentDo, array $data): Subsheet
    {
        $parentDo->loadMissing(['jobSheet', 'consignmentNote', 'sourceBranch']);

        if (! $parentDo->job_sheet_id) {
            throw new InvalidArgumentException('Delivery order must be on a Job Sheet before creating a subsheet.');
        }

        if ($parentDo->parent_do_id) {
            throw new InvalidArgumentException('Subsheets must be created from the main delivery order.');
        }

        $subLorryId = $data['sub_lorry_id'] ?? null;
        if (! $subLorryId) {
            throw new InvalidArgumentException('Assisting lorry is required for a subsheet.');
        }

        return DB::transaction(function () use ($parentDo, $data, $subLorryId) {
            $transferCode = $data['transfer_code'] ?? null;
            $taskType = $data['task_type'] ?? 'transfer';

            if ($transferCode) {
                $code = TransferCode::query()->where('code', $transferCode)->where('is_active', true)->first();
                if ($code && $code->type === 'incoming') {
                    $taskType = 'incoming_psi';
                }
            }

            $psi = (float) ($data['psi_amount'] ?? 0);
            $pso = (float) ($data['pso_amount'] ?? $psi);

            $lorry = Lorry::query()->with(['branch', 'defaultDriver'])->findOrFail($subLorryId);
            $date = $parentDo->jobSheet->operating_date;
            $driverId = $data['sub_driver_id'] ?? $lorry->default_driver_id;
            $isShared = $parentDo->source_branch_id !== $lorry->branch_id;

            $jobSheet = JobSheet::query()->firstOrCreate(
                [
                    'lorry_id' => $lorry->id,
                    'operating_date' => $date,
                ],
                [
                    'number' => $this->numbering->next($lorry->branch, DocumentType::JobSheet),
                    'company_id' => $lorry->company_id ?? $parentDo->company_id,
                    'operating_branch_id' => $lorry->branch_id,
                    'driver_id' => $driverId,
                    'status' => JobSheetStatus::Draft,
                    'is_shared_dispatch' => $isShared,
                ]
            );

            if ($driverId && (int) $jobSheet->driver_id !== (int) $driverId) {
                $jobSheet->update(['driver_id' => $driverId]);
            }

            if ($isShared && ! $jobSheet->is_shared_dispatch) {
                $jobSheet->update(['is_shared_dispatch' => true]);
            }

            $subDo = DeliveryOrder::query()->create([
                'number' => $this->numbering->next($parentDo->sourceBranch, DocumentType::Do),
                'company_id' => $parentDo->company_id,
                'consignment_note_id' => $parentDo->consignment_note_id,
                'source_branch_id' => $parentDo->source_branch_id,
                'job_sheet_id' => $jobSheet->id,
                'lorry_id' => $lorry->id,
                'driver_id' => $driverId,
                'status' => DeliveryOrderStatus::Assigned,
                'tracking_token' => Str::random(40),
                'parent_do_id' => $parentDo->id,
            ]);

            JobSheetTask::query()->create([
                'job_sheet_id' => $jobSheet->id,
                'delivery_order_id' => $subDo->id,
                'sequence' => (int) $jobSheet->tasks()->max('sequence') + 1,
                'route_group' => $parentDo->consignmentNote?->delivery_state,
            ]);

            $profit = null;
            if ($psi > 0 || $pso > 0 || $taskType === 'incoming_psi') {
                $profit = ProfitSharingTransaction::query()->create([
                    'source_branch_id' => $parentDo->source_branch_id,
                    'delivery_order_id' => $subDo->id,
                    'consignment_note_id' => $parentDo->consignment_note_id,
                    'assisting_driver_id' => $driverId,
                    'main_driver_id' => $data['main_driver_id'] ?? $parentDo->driver_id,
                    'psi_amount' => $psi,
                    'pso_amount' => $pso,
                    'status' => 'pending',
                ]);
            }

            $csn = $parentDo->consignmentNote;
            if ($csn) {
                $others = $csn->other_do_numbers ?? [];
                if (! in_array($subDo->number, $others, true)) {
                    $others[] = $subDo->number;
                    $csn->update(['other_do_numbers' => $others]);
                }
            }

            $branch = $jobSheet->operatingBranch ?? $parentDo->sourceBranch;

            return Subsheet::query()->create([
                'number' => $this->numbering->next($branch, DocumentType::Subsheet),
                'job_sheet_id' => $jobSheet->id,
                'delivery_order_id' => $subDo->id,
                'consignment_note_id' => $parentDo->consignment_note_id,
                'transfer_code' => $transferCode,
                'task_type' => $taskType,
                'notes' => $data['notes'] ?? null,
                'main_driver_id' => $data['main_driver_id'] ?? $parentDo->driver_id,
                'main_lorry_id' => $data['main_lorry_id'] ?? $parentDo->lorry_id,
                'sub_driver_id' => $driverId,
                'sub_lorry_id' => $lorry->id,
                'subcontractor_id' => $data['subcontractor_id'] ?? null,
                'segment_route' => $data['segment_route'] ?? null,
                'handover_status' => $data['handover_status'] ?? 'pending',
                'psi_amount' => $psi,
                'pso_amount' => $pso,
                'profit_sharing_transaction_id' => $profit?->id,
            ]);
        });
    }
}
