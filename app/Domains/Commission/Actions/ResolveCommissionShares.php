<?php

namespace App\Domains\Commission\Actions;

use App\Domains\Dispatch\Models\DeliveryOrder;
use App\Domains\Dispatch\Models\Subsheet;
use App\Domains\MasterData\Models\CommissionRule;
use App\Enums\DeliveryOrderStatus;
use Illuminate\Support\Collection;

/**
 * Driver commission of a delivery order (user, 15 and 20 Oct 2026). One commission per CSN: the rule's rate of the
 * CSN total (80%; the other 20% is O&G's). A Pickup leg's driver is paid the fixed amount entered when the subsheet
 * was put on a lorry (deducted first, never more than what is left); the rest is shared equally by the main driver
 * and every other leg with a driver (80% / 2 each for two drivers, / 3 for three …). Each delivery order pays its
 * own driver, so no driver is paid twice for one CSN.
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
            'notes' => match (true) {
                $split['pickup'] => sprintf('Pickup · fixed RM %s of the RM %s commission', number_format($split['amount'], 2), number_format($split['base'], 2)),
                (bool) $do->is_duplicate => 'Duplicate reassignment — dual commission eligible',
                default => sprintf(
                    '%s · %s%% of RM %s = RM %s%s · shared by %d driver(s)',
                    $split['rule'] ?? 'Default rate',
                    $this->number($split['rate']),
                    number_format($split['freight'], 2),
                    number_format($split['base'], 2),
                    $split['pickup_total'] > 0 ? ' less pickup RM '.number_format($split['pickup_total'], 2) : '',
                    $split['drivers'],
                ),
            },
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

        return ['rate' => $split['rate'], 'rule' => $split['rule'], 'share' => $split['share'], 'drivers' => $split['drivers'], 'pickup' => $split['pickup']];
    }

    /**
     * The CSN's commission and this delivery order's part of it.
     *
     * @return array{rate: float, rule: ?string, freight: float, base: float, drivers: int, share: float, amount: float, pickup: bool, pickup_total: float, remaining: float}
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
                ->get(['id', 'delivery_order_id', 'task_type', 'psi_amount'])
            : collect();

        // Pickup legs are paid their fixed amount; the main delivery and the other legs share the rest equally
        $pickups = $legs->where('task_type', 'incoming_psi');
        $sharing = $legs->reject(fn (Subsheet $leg) => $leg->task_type === 'incoming_psi');
        $drivers = 1 + $sharing->count();

        $rule = $this->matchRule($do, $drivers);
        $rate = (float) ($rule?->rate_percent ?? config('og.default_commission_rate', 80));
        $freight = (float) ($csn?->total_amount ?? 0);
        $base = round($freight * ($rate / 100), 2);

        $left = $base;
        $pickupAmounts = [];

        foreach ($pickups as $leg) {
            $amount = round(min(max(0, (float) $leg->psi_amount), $left), 2);
            $pickupAmounts[(int) $leg->delivery_order_id] = $amount;
            $left = round($left - $amount, 2);
        }

        $pickupTotal = round(array_sum($pickupAmounts), 2);
        $remaining = max(0, $left);
        $isPickup = array_key_exists((int) $do->id, $pickupAmounts)
            || ($do->subsheet_id !== null && Subsheet::query()->whereKey($do->subsheet_id)->value('task_type') === 'incoming_psi');
        $amount = $isPickup ? (float) ($pickupAmounts[(int) $do->id] ?? 0) : round($remaining / $drivers, 2);

        return [
            'rate' => $rate,
            'rule' => $rule?->name,
            'freight' => $freight,
            'base' => $base,
            'drivers' => $drivers,
            'share' => $base > 0 ? round($amount / $base * 100, 2) : 0.0,
            'amount' => $amount,
            'pickup' => $isPickup,
            'pickup_total' => $pickupTotal,
            'remaining' => $remaining,
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
