<?php

namespace App\Domains\Commission\Actions;

use App\Domains\Commission\Models\CommissionAdjustment;
use App\Domains\Commission\Models\CommissionLineItem;
use App\Domains\Commission\Models\CommissionSlip;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AdjustCommissionSlip
{
    public function adjustAmount(CommissionSlip $slip, float $amount, string $reason, User $actor): CommissionAdjustment
    {
        $this->assertDraft($slip);

        if (blank($reason)) {
            throw new InvalidArgumentException('Adjustment reason is required.');
        }

        return DB::transaction(function () use ($slip, $amount, $reason, $actor) {
            $adjustment = CommissionAdjustment::query()->create([
                'commission_slip_id' => $slip->id,
                'amount' => $amount,
                'reason' => $reason,
                'adjusted_by' => $actor->id,
            ]);

            $this->recalculate($slip);

            return $adjustment;
        });
    }

    public function hideLine(CommissionLineItem $line, string $reason): CommissionLineItem
    {
        $this->assertDraft($line->slip);

        if (blank($reason)) {
            throw new InvalidArgumentException('Hide reason is required.');
        }

        $line->update([
            'is_hidden' => true,
            'hidden_reason' => $reason,
        ]);

        $this->recalculate($line->slip);

        return $line->fresh();
    }

    public function unhideLine(CommissionLineItem $line): CommissionLineItem
    {
        $this->assertDraft($line->slip);

        $line->update([
            'is_hidden' => false,
            'hidden_reason' => null,
        ]);

        $this->recalculate($line->slip);

        return $line->fresh();
    }

    public function recalculate(CommissionSlip $slip): void
    {
        $system = (float) $slip->lines()->where('is_hidden', false)->sum('amount');
        $eligible = (float) $slip->lines()
            ->where('is_hidden', false)
            ->where('is_eligible', true)
            ->sum('amount');
        $adjustments = (float) $slip->adjustments()->sum('amount');
        $deductions = abs(min(0, $adjustments));

        $slip->update([
            'system_amount' => $system,
            'deductions' => $deductions,
            'final_amount' => max(0, $eligible + $adjustments),
        ]);
    }

    private function assertDraft(CommissionSlip $slip): void
    {
        $slip->loadMissing('batch');
        if ($slip->batch?->status === 'confirmed' || $slip->status === 'confirmed') {
            throw new InvalidArgumentException('Confirmed commission slips are locked.');
        }
    }
}
