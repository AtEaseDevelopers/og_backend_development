<?php

namespace App\Domains\Commission\Actions;

use App\Domains\Dispatch\Models\DeliveryOrder;
use App\Domains\Dispatch\Models\ProfitSharingTransaction;
use App\Domains\MasterData\Models\CommissionRule;
use App\Enums\DeliveryOrderStatus;
use Illuminate\Support\Collection;

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
            'subsheets',
            'parent.failedDelivery',
        ]);

        $csn = $do->consignmentNote;
        $eligible = (bool) ($csn?->isOriginalReturned());
        $rule = $this->matchRule($do);
        $rate = (float) ($rule?->rate_percent ?? config('og.default_commission_rate', 10));
        $freight = (float) ($csn?->total_amount ?? 0);
        $base = round($freight * ($rate / 100), 2);

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

        $psiRows = ProfitSharingTransaction::query()
            ->where('delivery_order_id', $do->id)
            ->get();

        if ($psiRows->isNotEmpty()) {
            foreach ($psiRows as $psi) {
                if ($psi->assisting_driver_id && (float) $psi->psi_amount > 0) {
                    $rows->push([
                        'driver_id' => $psi->assisting_driver_id,
                        'lorry_id' => $do->lorry_id,
                        'amount' => (float) $psi->psi_amount,
                        'split_percent' => 0,
                        'line_type' => 'psi',
                        'is_eligible' => $eligible,
                        'notes' => 'PSI allocation',
                    ]);
                }

                $mainAmount = max(0, $base - (float) $psi->psi_amount);
                if ($psi->main_driver_id) {
                    $rows->push([
                        'driver_id' => $psi->main_driver_id,
                        'lorry_id' => $do->lorry_id,
                        'amount' => $mainAmount,
                        'split_percent' => 100,
                        'line_type' => 'pso',
                        'is_eligible' => $eligible,
                        'notes' => 'Main/PSO share after PSI',
                    ]);
                }
            }

            return $rows->filter(fn ($r) => $r['driver_id'])->values();
        }

        $shares = $rule?->shares() ?? [100.0];
        $drivers = $this->collectDrivers($do);

        foreach ($shares as $index => $percent) {
            $driverId = $drivers[$index] ?? $drivers[0] ?? $do->driver_id;
            if (! $driverId) {
                continue;
            }

            $amount = round($base * ((float) $percent / 100), 2);
            $rows->push([
                'driver_id' => $driverId,
                'lorry_id' => $do->lorry_id,
                'amount' => $amount,
                'split_percent' => (float) $percent,
                'line_type' => $do->is_duplicate ? 'duplicate' : 'delivery',
                'is_eligible' => $eligible,
                'notes' => $do->is_duplicate
                    ? 'Duplicate reassignment — dual commission eligible'
                    : ($rule ? 'Rule: '.$rule->name : 'Default rate '.$rate.'%'),
            ]);
        }

        return $rows->values();
    }

    private function matchRule(DeliveryOrder $do): ?CommissionRule
    {
        $lorryType = $do->lorry?->type;

        return CommissionRule::query()
            ->where('is_active', true)
            ->where(function ($q) use ($do) {
                $q->whereNull('source_branch_id')
                    ->orWhere('source_branch_id', $do->source_branch_id);
            })
            ->when($lorryType, function ($q) use ($lorryType) {
                $q->where(function ($inner) use ($lorryType) {
                    $inner->whereNull('lorry_type')->orWhere('lorry_type', $lorryType);
                });
            })
            ->orderByRaw('source_branch_id is null')
            ->orderByRaw('lorry_type is null')
            ->first();
    }

    /** @return array<int, int|null> */
    private function collectDrivers(DeliveryOrder $do): array
    {
        $drivers = [$do->driver_id];

        foreach ($do->subsheets as $sub) {
            if ($sub->sub_driver_id) {
                $drivers[] = $sub->sub_driver_id;
            }
        }

        return array_values(array_unique(array_filter($drivers)));
    }
}
