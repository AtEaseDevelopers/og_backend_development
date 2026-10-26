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

    /**
     * Next CSN number, the client's format (user, 26 Oct 2026): XX-YYMM#####, e.g. KL-261000001.
     * XX = the location code (the branch's CSN code: KL, SA, MK, KG, PK, PP, PA, JB; else the company code),
     * YYMM = the year and month the order was placed, ##### = a 5-digit running number that starts again each month
     * for each location code.
     */
    public function nextCsn(Branch|int $branch, ?string $locationCode = null, ?\DateTimeInterface $orderedAt = null): string
    {
        $branchId = $branch instanceof Branch ? $branch->id : $branch;
        // no branch given (a CSN made by hand): the company's first branch code, else the company code
        $code = strtoupper(trim((string) $locationCode))
            ?: strtoupper((string) \App\Domains\MasterData\Models\SaLocation::query()->where('branch_id', $branchId)->where('is_active', true)->orderBy('id')->value('csn_prefix'))
            ?: (string) ($branch instanceof Branch ? $branch->code : Branch::query()->whereKey($branchId)->value('code'));
        $period = ($orderedAt ? \Illuminate\Support\Carbon::instance($orderedAt) : now())->format('ym');
        // a counter per location code and month (the type key carries the code; the period is 4 characters, apart
        // from the other documents' YYYYMM)
        $type = DocumentType::Csn->value.':'.$code;

        return DB::transaction(function () use ($branchId, $type, $period, $code) {
            $sequence = DocumentNumberSequence::query()
                ->where('branch_id', $branchId)
                ->where('document_type', $type)
                ->where('period', $period)
                ->lockForUpdate()
                ->first()
                ?? DocumentNumberSequence::query()->create(['branch_id' => $branchId, 'document_type' => $type, 'period' => $period, 'last_number' => 0]);

            $sequence->last_number++;
            $sequence->save();

            return sprintf('%s-%s%05d', $code, $period, $sequence->last_number);
        });
    }
}
