<?php

namespace App\Domains\Delivery\Actions;

use App\Domains\Delivery\Models\BreakBulk;
use App\Domains\Dispatch\Actions\TransferJobSheetTask;
use App\Domains\MasterData\Models\Driver;
use App\Domains\MasterData\Models\Lorry;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AssignBreakBulkContinuation
{
    public function __construct(private TransferJobSheetTask $transfer) {}

    public function execute(BreakBulk $breakBulk, array $data, User $actor): BreakBulk
    {
        if ($breakBulk->status !== 'active') {
            throw new InvalidArgumentException('Only active Break-Bulk records can be assigned.');
        }

        $replacementDriverId = $data['replacement_driver_id'] ?? null;
        $replacementLorryId = $data['replacement_lorry_id'] ?? null;

        if (! $replacementDriverId && ! $replacementLorryId) {
            throw new InvalidArgumentException('Assign a replacement driver or lorry.');
        }

        return DB::transaction(function () use ($breakBulk, $data, $actor, $replacementDriverId, $replacementLorryId) {
            $breakBulk->update([
                'replacement_driver_id' => $replacementDriverId,
                'replacement_lorry_id' => $replacementLorryId,
                'subcontractor_id' => $data['subcontractor_id'] ?? null,
                'handover_status' => 'released',
                'released_at' => now(),
            ]);

            if ($replacementLorryId) {
                $lorry = Lorry::query()->findOrFail($replacementLorryId);
                $this->transfer->transferToLorry(
                    $breakBulk->deliveryOrder,
                    $lorry,
                    $actor,
                    'Break-Bulk continuation: '.$breakBulk->number,
                    $data['operating_date'] ?? null
                );

                if ($replacementDriverId) {
                    $breakBulk->deliveryOrder->update(['driver_id' => $replacementDriverId]);
                    $breakBulk->deliveryOrder->jobSheet?->update(['driver_id' => $replacementDriverId]);
                }
            } elseif ($replacementDriverId) {
                $breakBulk->deliveryOrder->update(['driver_id' => $replacementDriverId]);
                $breakBulk->deliveryOrder->jobSheet?->update(['driver_id' => $replacementDriverId]);
            }

            return $breakBulk->fresh([
                'deliveryOrder', 'originalDriver', 'replacementDriver', 'originalLorry', 'replacementLorry',
            ]);
        });
    }

    public function updateHandover(BreakBulk $breakBulk, string $status): BreakBulk
    {
        if (! in_array($status, ['pending', 'released', 'collected', 'completed'], true)) {
            throw new InvalidArgumentException('Invalid handover status.');
        }

        $breakBulk->update([
            'handover_status' => $status,
            'collected_at' => $status === 'collected' ? now() : $breakBulk->collected_at,
            'completed_at' => $status === 'completed' ? now() : $breakBulk->completed_at,
            'status' => $status === 'completed' ? 'completed' : $breakBulk->status,
        ]);

        return $breakBulk->fresh();
    }

    public function revoke(BreakBulk $breakBulk, string $reason): BreakBulk
    {
        if ($breakBulk->status !== 'active') {
            throw new InvalidArgumentException('Only active Break-Bulk can be revoked.');
        }

        $breakBulk->update([
            'status' => 'revoked',
            'revoke_reason' => $reason,
            'handover_status' => 'pending',
        ]);

        return $breakBulk->fresh();
    }
}
