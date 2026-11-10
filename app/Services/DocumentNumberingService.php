<?php

namespace App\Services;

use App\Domains\MasterData\Models\Branch;
use App\Domains\MasterData\Models\DocumentNumberSequence;
use App\Domains\MasterData\Models\SaLocation;
use App\Enums\DocumentType;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Document numbers in O&G's format (client's list, user 11 Nov 2026). XX = the location code (Master Data → Branches:
 * KL, SA, MK, KG, PK, PP, PA, JB, MR), YYMM = the year and month the number is issued, then a running number that
 * keeps growing (it never restarts with the month; past 9999 / 99999 it simply gets longer):
 *
 *   Job sheet          XX####            KL0001
 *   Order quotation    QXX########       QKL00000001
 *   Order form         OFXXYYMM#####     OFKL261200001
 *   Consignment note   XXYYMM#####       KL261200001       (nextCsn)
 *   Proforma invoice   PIXXYYMM#####     PIKL261200001
 *   Cash bill          CBXXYYMM#####     CBKL261200001
 *   Invoice            IVYYMM#####       IV261200001       (per company: the letterhead tells them apart)
 *   COD invoice        CODXXYYMM#####    CODKL261200001
 *   COD payment voucher PVXXYYMMDD###    PVKL261231001
 *   Official receipt   ORYYMM#####       OR261200001       (per company)
 *   Refund note        RN########        RN00000001        (per company)
 *
 * The quotation with exclusive prices (QXX/DebtorCode/YYMM) and the commission purchase order (YYMM/XX/lorry no) carry
 * no running number (Quote::nextNumber, GenerateCommissionPurchaseOrders). The documents not on the client's list
 * (subsheet, delivery order, break bulk, commission batch / slip / PI) keep ZZ-EE-YYMM#####.
 *
 * Each company keeps its own counters. A new counter starts after the highest number its document type reached
 * before (the earlier monthly counters), so a new number never repeats an older one.
 */
class DocumentNumberingService
{
    /**
     * The O&G formats: [prefix, with the location code, date part, running number width].
     *
     * @var array<string, array{0: string, 1: bool, 2: string, 3: int}>
     */
    public const FORMATS = [
        'JS' => ['', true, '', 4],
        'QT' => ['Q', true, '', 8],
        'ORD' => ['OF', true, 'ym', 5],
        'PI' => ['PI', true, 'ym', 5],
        'CBL' => ['CB', true, 'ym', 5],
        'INV' => ['IV', false, 'ym', 5],
        'CIV' => ['COD', true, 'ym', 5],
        'PV' => ['PV', true, 'ymd', 3],
        'OR' => ['OR', false, 'ym', 5],
        'RN' => ['RN', false, '', 8],
    ];

    /** The counters that never restart (one row per company and type, this period value). */
    public const RUNNING = 'all';

    /**
     * Next document number. $prefixOverride replaces the location code; $date is the date in the number (default today).
     */
    public function next(Branch|int $branch, DocumentType $type, ?string $prefixOverride = null, ?\DateTimeInterface $date = null): string
    {
        $branchId = $branch instanceof Branch ? $branch->id : $branch;
        $date = $date ? Carbon::instance($date) : now();

        if (! isset(self::FORMATS[$type->value])) {
            return $this->legacy($branch, $branchId, $type, $prefixOverride);
        }

        [$prefix, $withLocation, $datePart, $width] = self::FORMATS[$type->value];
        $location = $withLocation ? (filled($prefixOverride) ? strtoupper(trim($prefixOverride)) : static::locationCode($branchId)) : '';
        // a counter per company, type and (where the number has one) location code
        $key = $type->value.($withLocation ? ':'.$location : '');
        $running = $this->increment($branchId, $key, $type->value);

        return $prefix.$location.($datePart !== '' ? $date->format($datePart) : '').str_pad((string) $running, $width, '0', STR_PAD_LEFT);
    }

    /**
     * Next CSN number: XXYYMM#####, e.g. KL261200001. XX = the branch's location code, YYMM = the year and month the
     * order was placed, ##### = a running number per location code that keeps growing. CSN numbers are unique across
     * companies: the counter continues after the highest number in use for the code (any company, either format).
     */
    public function nextCsn(Branch|int $branch, ?string $locationCode = null, ?\DateTimeInterface $orderedAt = null): string
    {
        $branchId = $branch instanceof Branch ? $branch->id : $branch;
        $code = strtoupper(trim((string) $locationCode)) ?: static::locationCode($branchId);
        $period = ($orderedAt ? Carbon::instance($orderedAt) : now())->format('ym');
        $type = DocumentType::Csn->value.':'.$code;

        return DB::transaction(function () use ($branchId, $type, $period, $code) {
            $sequence = $this->sequence($branchId, $type, $type);
            // the highest running number in use for the code: XX-YYMM##### (before) and XXYYMM#####
            $inUse = (int) DB::table('consignment_notes')->where('number', 'like', $code.'%')->pluck('number')
                ->map(fn ($n) => preg_match('/^'.preg_quote($code, '/').'-?\d{4}(\d+)$/', (string) $n, $m) ? (int) $m[1] : 0)
                ->max();
            $sequence->last_number = max((int) $sequence->last_number, $inUse) + 1;
            $sequence->save();

            return $code.$period.str_pad((string) $sequence->last_number, 5, '0', STR_PAD_LEFT);
        });
    }

    /**
     * The location code of a company: its first active branch's (Master Data → Branches: KL, JB, KG …), else the
     * company code.
     */
    public static function locationCode(int $branchId): string
    {
        static $codes = [];

        return $codes[$branchId] ??= strtoupper(trim((string) (
            SaLocation::query()->where('branch_id', $branchId)->where('is_active', true)->orderBy('id')->value('csn_prefix')
            ?: Branch::query()->whereKey($branchId)->value('code')
        )));
    }

    /** The next running number of a counter that never restarts. */
    private function increment(int $branchId, string $key, string $baseType): int
    {
        return DB::transaction(function () use ($branchId, $key, $baseType) {
            $sequence = $this->sequence($branchId, $key, $baseType);
            $sequence->last_number++;
            $sequence->save();

            return (int) $sequence->last_number;
        });
    }

    /**
     * The counter row (locked); a new one starts at the highest number the type reached in its earlier monthly
     * counters of the company, so the numbers keep growing.
     */
    private function sequence(int $branchId, string $key, string $baseType): DocumentNumberSequence
    {
        $sequence = DocumentNumberSequence::query()
            ->where('branch_id', $branchId)
            ->where('document_type', $key)
            ->where('period', self::RUNNING)
            ->lockForUpdate()
            ->first();

        if ($sequence) {
            return $sequence;
        }

        $start = (int) DocumentNumberSequence::query()
            ->where('branch_id', $branchId)
            ->whereIn('document_type', array_unique([$baseType, $key]))
            ->where('period', '!=', self::RUNNING)
            ->max('last_number');

        return DocumentNumberSequence::query()->create([
            'branch_id' => $branchId,
            'document_type' => $key,
            'period' => self::RUNNING,
            'last_number' => $start,
        ]);
    }

    /**
     * The documents not on the client's list keep ZZ-EE-YYMM##### (e.g. SS-KL-261000001): a 5-digit running number
     * per company, type and month.
     */
    private function legacy(Branch|int $branch, int $branchId, DocumentType $type, ?string $prefixOverride): string
    {
        $branchCode = $branch instanceof Branch ? $branch->code : Branch::query()->whereKey($branchId)->value('code');
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

            return sprintf('%s-%s-%s%05d', $type->value, $prefix, $period, $sequence->last_number);
        });
    }
}
