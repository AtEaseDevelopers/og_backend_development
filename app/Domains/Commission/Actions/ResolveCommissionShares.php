<?php

namespace App\Domains\Commission\Actions;

use App\Domains\Dispatch\Models\DeliveryOrder;
use App\Domains\Dispatch\Models\Subsheet;
use App\Domains\MasterData\Models\CommissionRule;
use App\Enums\DeliveryOrderStatus;
use Illuminate\Support\Collection;

/**
 * Driver commission of a delivery order. One commission per CSN: the rule's rate % of the CSN total, shared
 * between the drivers of the CSN — its own delivery (main) and every subsheet leg (Pickup / Transfer / Break bulk)
 * that has a driver — by the rule for that number of drivers (1 = Single, 2 = Split 2, 3 = Split 3, 4+ = Split 4;
 * its share % per driver, else an equal split). Each delivery order pays its own driver's share (main first, then
 * the legs in the order they were created), so no driver is paid twice for one CSN (user, 15 Oct 2026).
 */
class ResolveCommissionShares
{
    /**
     * Build per-driver commission share rows for a delivery order.
     *
     * @return Collection<int, array{
     *   driver_id:int|null,
     *   lorry_id:int|null,
     *   amount:float,
     *   split_percent:float,
     *   line_type:string,
     *   is_eligible:bool,
     *   notes:?string
     * }>
     */
    public function execute(DeliveryOrder $do): Collection
    {
        $do->loadMissing([
            'consignmentNote.returnedCsn',
            'lorry',
            'driver',
            'failedDelivery',
            'parent.failedDelivery',
        ]);

        $csn = $do->consignmentNote;
        $eligible = (bool) ($csn?->isOriginalReturned());
        $rows = collect();

        // Failed DO (not a successful duplicate replacement)
        if ($do->status === DeliveryOrderStatus::Failed) {
            $option = $do->failedDelivery?->reassignment_option;
            $rows->push([
                'driver_id' => $do->driver_id,
                'lorry_id' => $do->lorry_id,
                'amount' => 0.0,
                'split_percent' => 100.0,
                'line_type' => 'failed',
                'is_eligible' => $eligible,
                'notes' => $option === 'standard'
                    ? 'Failed (standard reassignment) — original driver 0'
                    : 'Failed delivery — 0 commission (visible on slip)',
            ]);

            return $rows;
        }

        // Standard reassignment: original failing driver stays visible at 0; current driver earns.
        if ($do->failedDelivery?->reassignment_option === 'standard') {
            $originalDriverId = $do->failedDelivery->driver_id;
            if ($originalDriverId && $originalDriverId !== $do->driver_id) {
                $rows->push([
                    'driver_id' => $originalDriverId,
                    'lorry_id' => $do->lorry_id,
                    'amount' => 0.0,
                    'split_percent' => 100.0,
                    'line_type' => 'failed',
                    'is_eligible' => $eligible,
                    'notes' => 'Failed (standard reassignment) — original driver 0',
                ]);
            }
        }

        if (! $do->driver_id) {
            return $rows->values();
        }

        $split = $this->splitFor($do);

        $rows->push([
            'driver_id' => $do->driver_id,
            'lorry_id' => $do->lorry_id,
            'amount' => $split['amount'],
            'split_percent' => $split['share'],
            'line_type' => $do->is_duplicate ? 'duplicate' : 'delivery',
            'is_eligible' => $eligible,
            'notes' => $do->is_duplicate
                ? 'Duplicate reassignment — dual commission eligible'
                : sprintf(
                    '%s · %s%% of RM %s · share %s%% of %d driver(s)',
                    $split['rule'] ?? 'Default rate',
                    $this->number($split['rate']),
                    number_format($split['freight'], 2),
                    $this->number($split['share']),
                    $split['drivers'],
                ),
        ]);

        return $rows->values();
    }

    /**
     * The commission rate of a delivery order (its rule for the CSN's number of drivers, else the default rate)
     * and the share of it this delivery order's driver earns.
     *
     * @return array{rate: float, rule: ?string, share: float, drivers: int}
     */
    public function rateFor(DeliveryOrder $do): array
    {
        $split = $this->splitFor($do);

        return ['rate' => $split['rate'], 'rule' => $split['rule'], 'share' => $split['share'], 'drivers' => $split['drivers']];
    }

    /**
     * The CSN's commission and this delivery order's share of it.
     *
     * @return array{rate: float, rule: ?string, freight: float, base: float, drivers: int, share: float, amount: float}
     */
    public function splitFor(DeliveryOrder $do): array
    {
        $do->loadMissing('consignmentNote');
        $csn = $do->consignmentNote;

        // the subsheet legs of the CSN that have a driver (not cancelled), in the order they were created
        $legs = $csn
            ? Subsheet::query()
                ->where('consignment_note_id', $csn->id)
                ->whereNotNull('delivery_order_id')
                ->whereHas('deliveryOrder', fn ($q) => $q->whereNotNull('driver_id')->where('status', '!=', DeliveryOrderStatus::Cancelled->value))
                ->orderBy('id')
                ->pluck('delivery_order_id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all()
            : [];

        // the CSN's own delivery (main, or its re-delivery) is the first driver; each leg one more
        $drivers = 1 + count($legs);
        $legIndex = array_search((int) $do->id, $legs, true);
        $position = $legIndex !== false
            ? $legIndex + 1
            : ($do->subsheet_id !== null ? $drivers - 1 : 0);

        $rule = $this->matchRule($do, $drivers);
        $rate = (float) ($rule?->rate_percent ?? config('og.default_commission_rate', 10));
        $freight = (float) ($csn?->total_amount ?? 0);
        $base = round($freight * ($rate / 100), 2);

        $shares = $rule?->shares() ?? [];
        if (count($shares) !== $drivers) {
            $shares = array_fill(0, $drivers, 100 / $drivers);
        }

        $share = (float) ($shares[$position] ?? (100 / $drivers));

        return [
            'rate' => $rate,
            'rule' => $rule?->name,
            'freight' => $freight,
            'base' => $base,
            'drivers' => $drivers,
            'share' => round($share, 2),
            'amount' => round($base * ($share / 100), 2),
        ];
    }

    /**
     * Active rule for the number of drivers (Single / Split 2 / Split 3 / Split 4), the branch's own before one for
     * all branches; without one for that number, the branch's first rule (its rate, shared equally).
     */
    private function matchRule(DeliveryOrder $do, int $drivers): ?CommissionRule
    {
        $splitType = match (true) {
            $drivers <= 1 => 'single',
            $drivers === 2 => 'split_2',
            $drivers === 3 => 'split_3',
            default => 'split_4',
        };

        $rules = CommissionRule::query()
            ->where('is_active', true)
            ->where(function ($q) use ($do) {
                $q->whereNull('source_branch_id')
                    ->orWhere('source_branch_id', $do->source_branch_id);
            })
            ->orderByRaw('source_branch_id is null')
            ->orderBy('id')
            ->get();

        return $rules->firstWhere('split_type', $splitType) ?? $rules->first();
    }

    private function number(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}
