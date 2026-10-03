<?php

namespace App\Services;

use App\Domains\MasterData\Models\Branch;
use App\Domains\MasterData\Models\DocumentNumberSequence;
use App\Enums\DocumentType;
use Illuminate\Support\Facades\DB;

class DocumentNumberingService
{
    /**
     * Next document number for a branch: {PREFIX}-{TYPE}-{Ym}-{0001}.
     *
     * The running sequence is always kept per branch / type / month. $prefixOverride replaces
     * the branch code in the printed number (used for the SA-location CSN prefix, section H)
     * without starting a separate counter, so numbers stay unique within the branch.
     */
    public function next(Branch|int $branch, DocumentType $type, ?string $prefixOverride = null): string
    {
        $branchId = $branch instanceof Branch ? $branch->id : $branch;
        $branchCode = $branch instanceof Branch
            ? $branch->code
            : Branch::query()->whereKey($branchId)->value('code');

        $prefix = filled($prefixOverride) ? strtoupper(trim($prefixOverride)) : $branchCode;
        $period = now()->format('Ym');

        return DB::transaction(function () use ($branchId, $prefix, $type, $period) {
            $sequence = DocumentNumberSequence::query()
                ->where('branch_id', $branchId)
                ->where('document_type', $type->value)
                ->where('period', $period)
                ->lockForUpdate()
                ->first();

            if (! $sequence) {
                $sequence = DocumentNumberSequence::query()->create([
                    'branch_id' => $branchId,
                    'document_type' => $type->value,
                    'period' => $period,
                    'last_number' => 0,
                ]);
            }

            $sequence->last_number++;
            $sequence->save();

            return sprintf(
                '%s-%s-%s-%04d',
                $prefix,
                $type->value,
                $period,
                $sequence->last_number
            );
        });
    }
}
