<?php

namespace App\Domains\Dispatch\Actions;

use App\Domains\Dispatch\Models\DeliveryOrder;
use App\Domains\Dispatch\Models\ProfitSharingTransaction;
use App\Domains\Dispatch\Models\Subsheet;
use App\Domains\MasterData\Models\TransferCode;
use App\Enums\DocumentType;
use App\Services\DocumentNumberingService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CreateSubsheet
{
    public function __construct(private DocumentNumberingService $numbering) {}

    public function execute(DeliveryOrder $do, array $data): Subsheet
    {
        $do->loadMissing(['jobSheet', 'consignmentNote', 'sourceBranch']);

        if (! $do->job_sheet_id) {
            throw new InvalidArgumentException('Delivery order must be on a Job Sheet before creating a subsheet.');
        }

        return DB::transaction(function () use ($do, $data) {
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

            $profit = null;
            if ($psi > 0 || $pso > 0 || $taskType === 'incoming_psi') {
                $profit = ProfitSharingTransaction::query()->create([
                    'source_branch_id' => $do->source_branch_id,
                    'delivery_order_id' => $do->id,
                    'consignment_note_id' => $do->consignment_note_id,
                    'assisting_driver_id' => $data['sub_driver_id'] ?? null,
                    'main_driver_id' => $data['main_driver_id'] ?? $do->driver_id,
                    'psi_amount' => $psi,
                    'pso_amount' => $pso,
                    'status' => 'pending',
                ]);
            }

            $branch = $do->jobSheet?->operatingBranch ?? $do->sourceBranch;

            return Subsheet::query()->create([
                'number' => $this->numbering->next($branch, DocumentType::Subsheet),
                'job_sheet_id' => $do->job_sheet_id,
                'delivery_order_id' => $do->id,
                'consignment_note_id' => $do->consignment_note_id,
                'transfer_code' => $transferCode,
                'task_type' => $taskType,
                'notes' => $data['notes'] ?? null,
                'main_driver_id' => $data['main_driver_id'] ?? $do->driver_id,
                'main_lorry_id' => $data['main_lorry_id'] ?? $do->lorry_id,
                'sub_driver_id' => $data['sub_driver_id'] ?? null,
                'sub_lorry_id' => $data['sub_lorry_id'] ?? null,
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
