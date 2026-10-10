<?php

namespace App\Domains\Dispatch\Actions;

use App\Domains\Consignment\Models\ConsignmentNote;
use App\Domains\Dispatch\Models\DeliveryOrder;
use App\Domains\Dispatch\Models\JobSheetTask;
use App\Domains\Dispatch\Models\ProfitSharingTransaction;
use App\Domains\Dispatch\Models\Subsheet;
use App\Domains\MasterData\Models\Lorry;
use App\Domains\MasterData\Models\TransferCode;
use App\Enums\DeliveryOrderStatus;
use App\Enums\DocumentType;
use App\Services\DocumentNumberingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Subsheets of a CSN: a record of its own under the CSN (type, transfer code), created with or without a lorry
 * (forCsn) and put on a lorry now or later together with CSNs (assignToLorry: the lorry's trip of the day, its own
 * delivery order and job sheet task, profit sharing). The CSN does not need a lorry first; its subsheet delivery
 * orders hang under its main delivery order once that exists (AssignCsnToLorry).
 */
class CreateSubsheet
{
    public function __construct(
        private DocumentNumberingService $numbering,
        private ResolveJobSheet $resolveJobSheet,
    ) {}

    /** A subsheet of a CSN's main delivery order on the given lorry (sub_lorry_id) at once (older callers). */
    public function execute(DeliveryOrder $parentDo, array $data): Subsheet
    {
        $parentDo->loadMissing(['jobSheet', 'consignmentNote']);

        if ($parentDo->isSubDo()) {
            throw new InvalidArgumentException('Subsheets must be created from the main delivery order.');
        }

        $subLorryId = $data['sub_lorry_id'] ?? null;
        if (! $subLorryId || ! $parentDo->consignmentNote) {
            throw new InvalidArgumentException('Assisting lorry is required for a subsheet.');
        }

        return DB::transaction(fn () => $this->assignToLorry(
            $this->forCsn($parentDo->consignmentNote, $data),
            Lorry::query()->findOrFail($subLorryId),
            $data + ['operating_date' => $parentDo->jobSheet?->operating_date?->toDateString()],
        ));
    }

    /**
     * A subsheet of the CSN without a lorry yet. Type: Subsheet (incoming_psi), Transfer (transfer, the default)
     * or Break bulk (break_bulk, set from the driver app).
     */
    public function forCsn(ConsignmentNote $csn, array $data): Subsheet
    {
        $csn->loadMissing(['deliveryOrder', 'sourceBranch']);
        $main = $csn->deliveryOrder;
        $transferCode = filled($data['transfer_code'] ?? null) ? (string) $data['transfer_code'] : null;

        return Subsheet::query()->create([
            'number' => $this->numbering->next($csn->sourceBranch, DocumentType::Subsheet),
            'consignment_note_id' => $csn->id,
            'transfer_code' => $transferCode,
            'task_type' => $this->taskType($data, $transferCode),
            'notes' => $data['notes'] ?? null,
            'main_driver_id' => $data['main_driver_id'] ?? $main?->driver_id,
            'main_lorry_id' => $data['main_lorry_id'] ?? $main?->lorry_id,
            'subcontractor_id' => $data['subcontractor_id'] ?? null,
            'segment_route' => $data['segment_route'] ?? null,
            'handover_status' => $data['handover_status'] ?? 'pending',
            'psi_amount' => (float) ($data['psi_amount'] ?? 0),
            'pso_amount' => (float) ($data['pso_amount'] ?? ($data['psi_amount'] ?? 0)),
        ]);
    }

    /**
     * Puts a subsheet on a lorry (driver: the lorry's default driver unless given): the lorry's open trip of the
     * day, the subsheet's own delivery order (under the CSN's main delivery order when it has one) and job sheet
     * task, and the profit sharing of the leg. $data may change the type / transfer code on the way.
     */
    public function assignToLorry(Subsheet $subsheet, Lorry $lorry, array $data = []): Subsheet
    {
        if ($subsheet->delivery_order_id) {
            throw new InvalidArgumentException('Subsheet '.$subsheet->number.' already has a lorry.');
        }

        if (! $lorry->is_active) {
            throw new InvalidArgumentException('Lorry '.$lorry->registration_no.' is not active.');
        }

        $csn = $subsheet->consignmentNote()->with(['deliveryOrder.jobSheet', 'sourceBranch'])->first();

        if (! $csn) {
            throw new InvalidArgumentException('Subsheet '.$subsheet->number.' has no CSN.');
        }

        $main = $csn->deliveryOrder;

        if ($main && (int) $main->lorry_id === (int) $lorry->id) {
            throw new InvalidArgumentException('Lorry '.$lorry->registration_no.' already carries CSN '.$csn->number.' (main lorry).');
        }

        return DB::transaction(function () use ($subsheet, $lorry, $data, $csn, $main) {
            $lorry->loadMissing(['branch', 'defaultDriver']);
            $date = filled($data['operating_date'] ?? null)
                ? (string) $data['operating_date']
                : ($main?->jobSheet?->operating_date?->toDateString() ?? now()->toDateString());
            $driverId = ($data['sub_driver_id'] ?? null) ?: $lorry->default_driver_id;
            $isShared = (int) $csn->source_branch_id !== (int) $lorry->branch_id;

            if (array_key_exists('transfer_code', $data) || array_key_exists('task_type', $data)) {
                $transferCode = array_key_exists('transfer_code', $data) ? (filled($data['transfer_code']) ? (string) $data['transfer_code'] : null) : $subsheet->transfer_code;
                $subsheet->fill(['transfer_code' => $transferCode, 'task_type' => $this->taskType($data + ['task_type' => $subsheet->task_type], $transferCode)]);
            }

            $jobSheet = $this->resolveJobSheet->forLorry($lorry, $date, $driverId, $lorry->company_id ?? $csn->company_id, $isShared);

            $subDo = DeliveryOrder::query()->create([
                'number' => $this->numbering->next($csn->sourceBranch, DocumentType::Do),
                'company_id' => $csn->company_id,
                'consignment_note_id' => $csn->id,
                'source_branch_id' => $csn->source_branch_id,
                'job_sheet_id' => $jobSheet->id,
                'lorry_id' => $lorry->id,
                'driver_id' => $driverId ?? $jobSheet->driver_id,
                'status' => DeliveryOrderStatus::Assigned,
                'tracking_token' => Str::random(40),
                'parent_do_id' => $main?->id,
                'subsheet_id' => $subsheet->id,
            ]);

            JobSheetTask::query()->create([
                'job_sheet_id' => $jobSheet->id,
                'delivery_order_id' => $subDo->id,
                'sequence' => (int) $jobSheet->tasks()->max('sequence') + 1,
                'route_group' => $csn->delivery_state,
            ]);

            $psi = (float) ($data['psi_amount'] ?? $subsheet->psi_amount ?? 0);
            $pso = (float) ($data['pso_amount'] ?? $subsheet->pso_amount ?? $psi);
            $profit = null;

            if ($psi > 0 || $pso > 0 || $subsheet->task_type === 'incoming_psi') {
                $profit = ProfitSharingTransaction::query()->create([
                    'source_branch_id' => $csn->source_branch_id,
                    'delivery_order_id' => $subDo->id,
                    'consignment_note_id' => $csn->id,
                    'assisting_driver_id' => $driverId,
                    'main_driver_id' => $subsheet->main_driver_id ?? $main?->driver_id,
                    'psi_amount' => $psi,
                    'pso_amount' => $pso,
                    'status' => 'pending',
                ]);
            }

            $others = $csn->other_do_numbers ?? [];
            if (! in_array($subDo->number, $others, true)) {
                $others[] = $subDo->number;
                $csn->update(['other_do_numbers' => $others]);
            }

            $subsheet->fill([
                'job_sheet_id' => $jobSheet->id,
                'delivery_order_id' => $subDo->id,
                'sub_driver_id' => $driverId,
                'sub_lorry_id' => $lorry->id,
                'main_driver_id' => $subsheet->main_driver_id ?? $main?->driver_id,
                'main_lorry_id' => $subsheet->main_lorry_id ?? $main?->lorry_id,
                'psi_amount' => $psi,
                'pso_amount' => $pso,
                'profit_sharing_transaction_id' => $profit?->id,
            ])->save();

            return $subsheet->fresh();
        });
    }

    /** The type asked for (Transfer when none); an older caller without one: Subsheet for an incoming transfer code. */
    private function taskType(array $data, ?string $transferCode): string
    {
        if (filled($data['task_type'] ?? null) && array_key_exists((string) $data['task_type'], Subsheet::TYPES)) {
            return (string) $data['task_type'];
        }

        $incoming = $transferCode && TransferCode::query()->where('code', $transferCode)->where('type', 'incoming')->exists();

        return $incoming ? 'incoming_psi' : 'transfer';
    }
}
