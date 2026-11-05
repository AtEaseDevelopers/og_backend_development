<?php

namespace App\Support;

use App\Domains\Delivery\Actions\CompleteDelivery;
use App\Domains\Delivery\Models\BreakBulk;
use App\Domains\Delivery\Models\DriverTaskProof;
use App\Domains\Dispatch\Models\DeliveryOrder;
use App\Domains\Dispatch\Models\JobSheet;
use App\Domains\Dispatch\Models\JobSheetTask;
use App\Domains\MasterData\Models\TransferCode;
use App\Enums\CsnBillingType;
use Illuminate\Support\Facades\Storage;

/**
 * Driver app (section O) view of a delivery order on a job sheet: the delivery information, its sequence and
 * route group, and what kind of task it is (own delivery, subsheet transfer, additional pickup / PSI task,
 * Break-Bulk continuation, re-delivery of a failed one).
 */
class DriverTaskPayload
{
    public const RELATIONS = [
        'consignmentNote.lines', 'consignmentNote.transferCode.destinationBranch', 'sourceBranch', 'parent',
        'subsheet.mainDriver', 'subsheet.mainLorry', 'subsheet.subDriver', 'subsheet.subLorry',
        'breakBulks.originalDriver', 'breakBulks.originalLorry', 'breakBulks.replacementDriver', 'breakBulks.replacementLorry',
        'failedDelivery', 'proofOfDelivery', 'taskProofs',
    ];

    public static function forJobSheet(JobSheet $jobSheet, ?int $driverId): array
    {
        $tasks = $jobSheet->tasks->keyBy('delivery_order_id');

        return $jobSheet->deliveryOrders
            ->sortBy(fn (DeliveryOrder $do) => $tasks->get($do->id)?->sequence ?? 999)
            ->values()
            ->map(fn (DeliveryOrder $do) => self::task($do, $tasks->get($do->id), $driverId))
            ->all();
    }

    public static function task(DeliveryOrder $do, ?JobSheetTask $task, ?int $driverId): array
    {
        $csn = $do->consignmentNote;
        $subsheet = $do->subsheet;
        $breakBulk = $do->breakBulks->sortByDesc('id')->first();
        $kind = self::kind($do, $breakBulk, $driverId);
        $transferCode = $csn?->transferCode
            ?? ($subsheet?->transfer_code ? TransferCode::query()->with('destinationBranch')->where('code', $subsheet->transfer_code)->first() : null);
        $isCod = $csn?->billing_type === CsnBillingType::Cod;
        $lines = $csn?->lines ?? collect();
        $pickup = $do->taskProofs->where('type', DriverTaskProof::TYPE_PICKUP)->sortByDesc('id')->first();
        $handover = $do->taskProofs->where('type', DriverTaskProof::TYPE_HANDOVER)->sortByDesc('id')->first();

        return [
            'delivery_order_id' => $do->id,
            'do_number' => $do->number,
            'csn_number' => $csn?->number,
            'csn_qr_token' => $csn?->qr_token,
            'csn_status' => $csn?->status?->value,
            'claim_pending' => (bool) $csn?->transfer_claim_pending,
            'customer_do_number' => $csn?->customer_do_number,
            'order_type' => $csn?->order_type?->value,
            'service_type' => $csn?->service_type?->value,
            'drop_off_type' => $csn?->drop_off_type,
            'status' => $do->status->value,
            'status_label' => $do->status->getLabel(),
            'sequence' => $task?->sequence,
            'route_group' => $task?->route_group ?: ($csn?->delivery_state ?: 'Unrouted'),
            'task_kind' => $kind,
            'task_label' => self::kindLabel($kind),
            'source_branch' => $do->sourceBranch ? ['code' => $do->sourceBranch->code, 'name' => $do->sourceBranch->name] : null,
            'customer_name' => $csn?->customer_name,
            'customer_phone' => $csn?->customer_phone,
            'consignor_name' => $csn?->consignor_name,
            'consignor_address' => $csn?->consignor_address,
            'consignee_name' => $csn?->consignee_name,
            'consignee_pic' => $csn?->consignee_pic,
            'consignee_phone' => $csn?->consignee_phone,
            'address' => $csn?->delivery_address,
            'postcode' => $csn?->delivery_postcode,
            'city' => $csn?->delivery_city,
            'state' => $csn?->delivery_state,
            'remarks' => $csn?->remarks,
            'billing_type' => $csn?->billing_type?->value,
            'is_cod' => $isCod,
            'cod_amount' => $isCod && $csn ? app(CompleteDelivery::class)->codDue($csn) : null,
            'items' => $lines->map(fn ($l) => [
                'item_name' => $l->item_name,
                'quantity' => (float) $l->quantity,
                'uom' => $l->uom,
                'handling_notes' => $l->handling_notes,
            ])->values(),
            'total_quantity' => (float) $lines->sum('quantity'),
            'item_summary' => $lines->map(fn ($l) => rtrim(rtrim(number_format((float) $l->quantity, 2, '.', ''), '0'), '.').' '.($l->uom ?: 'unit').' '.$l->item_name)->implode(', '),
            'scan_codes' => array_values(array_filter([$do->number, $csn?->number, $csn?->qr_token, $csn?->customer_do_number])),
            'transfer_code' => $transferCode ? [
                'code' => $transferCode->code,
                'name' => $transferCode->name,
                'destination_branch' => $transferCode->destinationBranch
                    ? ['code' => $transferCode->destinationBranch->code, 'name' => $transferCode->destinationBranch->name]
                    : null,
            ] : ($subsheet?->transfer_code ? ['code' => $subsheet->transfer_code, 'name' => null, 'destination_branch' => null] : null),
            'subsheet' => $subsheet ? [
                'id' => $subsheet->id,
                'number' => $subsheet->number,
                'type' => $subsheet->task_type,
                'type_label' => $subsheet->typeLabel(),
                'handover_status' => $subsheet->handover_status,
                'segment_route' => $subsheet->segment_route,
                'notes' => $subsheet->notes,
                'main_driver' => $subsheet->mainDriver?->name,
                'main_lorry' => $subsheet->mainLorry?->registration_no,
                'psi_amount' => (float) $subsheet->psi_amount,
            ] : null,
            'parent_do_number' => $do->parent?->number,
            'is_duplicate' => (bool) $do->is_duplicate,
            'break_bulk' => $breakBulk ? self::breakBulk($breakBulk) : null,
            'failed' => $do->failedDelivery ? [
                'reason' => $do->failedDelivery->reason,
                'remarks' => $do->failedDelivery->remarks,
                'failed_at' => $do->failedDelivery->failed_at,
                'reassignment_option' => $do->failedDelivery->reassignment_option,
            ] : null,
            'pickup_recorded_at' => $pickup?->recorded_at,
            'handover_recorded_at' => $handover?->recorded_at,
            'delivered_at' => $do->delivered_at,
            'recipient_name' => $do->proofOfDelivery?->recipient_name,
        ];
    }

    public static function breakBulk(BreakBulk $breakBulk): array
    {
        return [
            'id' => $breakBulk->id,
            'number' => $breakBulk->number,
            'status' => $breakBulk->status,
            'handover_status' => $breakBulk->handover_status,
            'reason' => $breakBulk->reason,
            'location' => $breakBulk->location,
            'latitude' => $breakBulk->latitude,
            'longitude' => $breakBulk->longitude,
            'reported_at' => $breakBulk->reported_at ?? $breakBulk->created_at,
            'photo_urls' => collect($breakBulk->photo_paths ?? [])->map(fn ($p) => self::url($p))->values(),
            'original_driver' => $breakBulk->originalDriver?->name,
            'original_lorry' => $breakBulk->originalLorry?->registration_no,
            'replacement_driver' => $breakBulk->replacementDriver?->name,
            'replacement_lorry' => $breakBulk->replacementLorry?->registration_no,
            'released_at' => $breakBulk->released_at,
        ];
    }

    public static function url(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        return str_starts_with($path, 'http') ? $path : Storage::disk('public')->url($path);
    }

    private static function kind(DeliveryOrder $do, ?BreakBulk $breakBulk, ?int $driverId): string
    {
        if ($breakBulk && $breakBulk->status !== 'revoked' && $breakBulk->replacement_driver_id
            && (int) $breakBulk->original_driver_id !== (int) $driverId) {
            return 'break_bulk_continuation';
        }

        if ($do->subsheet) {
            return match ($do->subsheet->task_type) {
                'incoming_psi' => 'additional_pickup',
                'break_bulk' => 'break_bulk_continuation',
                default => 'subsheet_transfer',
            };
        }

        if ($do->is_duplicate) {
            return 'redelivery_duplicate';
        }

        if ($do->failedDelivery?->reassignment_option === 'standard') {
            return 'reassigned';
        }

        return 'delivery';
    }

    private static function kindLabel(string $kind): string
    {
        return match ($kind) {
            'break_bulk_continuation' => 'Break-Bulk continuation',
            'additional_pickup' => 'Additional task (PSI pickup)',
            'subsheet_transfer' => 'Subsheet transfer',
            'redelivery_duplicate' => 'Re-delivery (duplicated)',
            'reassigned' => 'Reassigned delivery',
            default => 'Delivery',
        };
    }
}
