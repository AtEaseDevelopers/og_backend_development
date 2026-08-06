<?php

namespace App\Domains\Commission\Actions;

use App\Domains\Commission\Models\CommissionBatch;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ConfirmCommissionBatch
{
    public function __construct(private AdjustCommissionSlip $adjuster) {}

    public function execute(CommissionBatch $batch, User $actor): CommissionBatch
    {
        if ($batch->status === 'confirmed') {
            throw new InvalidArgumentException('Batch already confirmed.');
        }

        if ($batch->slips()->count() === 0) {
            throw new InvalidArgumentException('Cannot confirm an empty commission batch.');
        }

        return DB::transaction(function () use ($batch, $actor) {
            foreach ($batch->slips as $slip) {
                $this->adjuster->recalculate($slip);

                // Ineligible lines remain on slip (visible) and stay carry-forward for next month.
                $slip->lines()
                    ->where('is_eligible', false)
                    ->where('is_hidden', false)
                    ->update(['is_carry_forward' => true]);

                $slip->update(['status' => 'confirmed']);
            }

            $batch->update([
                'status' => 'confirmed',
                'confirmed_by' => $actor->id,
                'confirmed_at' => now(),
            ]);

            return $batch->fresh(['slips.lines', 'confirmedBy']);
        });
    }
}
