<?php

namespace App\Services;

use App\Domains\MasterData\Models\Branch;
use App\Domains\MasterData\Models\DocumentNumberSequence;
use App\Enums\DocumentType;
use Illuminate\Support\Facades\DB;

class DocumentNumberingService
{
    /**
     * The document types numbered without hyphens, with their own type code (user, 28 Oct 2026): an invoice
     * IVKL261000001, a cash bill CBKL261000001 (ZZXXYYMM#####).
     */
    public const COMPACT = [DocumentType::Invoice->value => 'IV', DocumentType::CashBill->value => 'CB'];

    /**
     * Next document number (user, 28 Oct 2026): ZZ-EE-YYMM#####, e.g. OR-KL-261000001. ZZ = the document type code,
     * EE = the company code (replaced by $prefixOverride when given), YYMM = this month, ##### = a 5-digit running
     * number kept per company, type and month. An invoice or cash bill is ZZXXYYMM##### (COMPACT).
     */
    public function next(Branch|int $branch, DocumentType $type, ?string $prefixOverride = null): string
    {
        $branchId = $branch instanceof Branch ? $branch->id : $branch;
        $branchCode = $branch instanceof Branch
            ? $branch->code
            : Branch::query()->whereKey($branchId)->value('code');

        $prefix = strtoupper(filled($prefixOverride) ? trim($prefixOverride) : (string) $branchCode);
        $period = now()->format('ym');

        return DB::transaction(function () use ($branchId, $prefix, $type, $period) {
            $sequence = DocumentNumberSequence::query()
                ->where('branch_id', $branchId)
                ->where('document_type', $type->value)
                ->where('period', $period)
                ->lockForUpdate()
                ->first()
                ?? DocumentNumberSequence::query()->create(['branch_id' => $branchId, 'document_type' => $type->value, 'period' => $period, 'last_number' => 0]);

            $sequence->last_number++;
            $sequence->save();

            return isset(self::COMPACT[$type->value])
                ? sprintf('%s%s%s%05d', self::COMPACT[$type->value], $prefix, $period, $sequence->last_number)
                : sprintf('%s-%s-%s%05d', $type->value, $prefix, $period, $sequence->last_number);
        });
    }

    /**
     * Next CSN number (user, 28 Oct 2026): CSN-EE-XX-YYMM#####, e.g. CSN-KL-JB-261000001. EE = the company code,
     * XX = the branch's CSN code (KL, SA, MK, KG, PK, PP, PA, JB; else the company code), YYMM = the year and month
     * the order was placed, ##### = a 5-digit running number that starts again each month for each company and code.
     */
    public function nextCsn(Branch|int $branch, ?string $locationCode = null, ?\DateTimeInterface $orderedAt = null): string
    {
        $branchId = $branch instanceof Branch ? $branch->id : $branch;
        $company = strtoupper((string) ($branch instanceof Branch ? $branch->code : Branch::query()->whereKey($branchId)->value('code')));
        // no branch given (a CSN made by hand): the company's first branch code, else the company code
        $code = strtoupper(trim((string) $locationCode))
            ?: strtoupper((string) \App\Domains\MasterData\Models\SaLocation::query()->where('branch_id', $branchId)->where('is_active', true)->orderBy('id')->value('csn_prefix'))
            ?: $company;
        $period = ($orderedAt ? \Illuminate\Support\Carbon::instance($orderedAt) : now())->format('ym');
        // a counter per company, location code and month (the type key carries the code)
        $type = DocumentType::Csn->value.':'.$code;

        return DB::transaction(function () use ($branchId, $type, $period, $company, $code) {
            $sequence = DocumentNumberSequence::query()
                ->where('branch_id', $branchId)
                ->where('document_type', $type)
                ->where('period', $period)
                ->lockForUpdate()
                ->first()
                ?? DocumentNumberSequence::query()->create(['branch_id' => $branchId, 'document_type' => $type, 'period' => $period, 'last_number' => 0]);

            // continue after the highest number in use (renumbered CSNs, a counter out of step)
            $inUse = (int) substr((string) DB::table('consignment_notes')->where('number', 'like', 'CSN-'.$company.'-'.$code.'-'.$period.'%')->max('number'), -5);
            $sequence->last_number = max((int) $sequence->last_number, $inUse) + 1;
            $sequence->save();

            return sprintf('CSN-%s-%s-%s%05d', $company, $code, $period, $sequence->last_number);
        });
    }
}
