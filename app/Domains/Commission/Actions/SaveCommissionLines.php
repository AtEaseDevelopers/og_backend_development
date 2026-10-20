<?php

namespace App\Domains\Commission\Actions;

use App\Domains\Commission\Models\CommissionBatch;
use App\Domains\Commission\Models\CommissionBatchCsn;
use App\Domains\Commission\Models\CommissionLineItem;
use App\Domains\Commission\Models\CommissionSlip;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Save on a commission batch's lorry / job sheet page (user, 21 Oct 2026): admin's amount per CSN line is kept, and
 * each driver whose lines were saved gets their commission in Purchase Order (PO) (one per driver per batch: the
 * batch's slip, recalculated and generated).
 */
class SaveCommissionLines
{
    public function __construct(private AdjustCommissionSlip $adjuster) {}

    /**
     * @param  array<int|string, mixed>  $amounts  line id → RM
     * @return Collection<int, CommissionSlip> the drivers' POs generated / updated
     */
    public function execute(CommissionBatch $batch, array $amounts): Collection
    {
        if ($batch->status !== 'draft') {
            throw new InvalidArgumentException('Batch '.$batch->number.' is confirmed: its commission is locked.');
        }

        return DB::transaction(function () use ($batch, $amounts) {
            $lines = CommissionLineItem::query()
                ->whereIn('id', array_map('intval', array_keys($amounts)))
                ->whereHas('slip', fn ($q) => $q->where('commission_batch_id', $batch->id))
                ->get();

            foreach ($lines as $line) {
                $value = $amounts[$line->id] ?? $amounts[(string) $line->id] ?? null;

                if (! is_numeric($value) || (float) $value < 0) {
                    throw new InvalidArgumentException('Enter a commission of RM 0 or more for every CSN.');
                }

                $line->update(['amount' => round((float) $value, 2)]);
            }

            // each CSN's commission in the batch's Returned CSNs table
            foreach ($lines->pluck('consignment_note_id')->filter()->unique() as $csnId) {
                CommissionBatchCsn::query()->where('commission_batch_id', $batch->id)->where('consignment_note_id', $csnId)->update([
                    'amount' => round((float) CommissionLineItem::query()
                        ->where('consignment_note_id', $csnId)
                        ->whereHas('slip', fn ($q) => $q->where('commission_batch_id', $batch->id))
                        ->where('is_hidden', false)
                        ->sum('amount'), 2),
                ]);
            }

            $slips = CommissionSlip::query()->whereIn('id', $lines->pluck('commission_slip_id')->unique())->get();

            foreach ($slips as $slip) {
                $this->adjuster->recalculate($slip);

                if ($slip->status === 'draft') {
                    $slip->update(['status' => 'generated']);
                }
            }

            return $slips->map->fresh();
        });
    }
}
