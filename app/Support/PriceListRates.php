<?php

namespace App\Support;

use App\Domains\MasterData\Models\CharteredLorry;
use App\Domains\MasterData\Models\CharteredLorryRate;
use App\Domains\MasterData\Models\Customer;
use App\Domains\MasterData\Models\Uom;
use App\Domains\MasterData\Models\UomRateTier;
use App\Enums\DropOffType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Picks the rate of the client's price list for a product (UOM band or chartered lorry) to a destination.
 *
 * The pricing rule (user, 8 and 19 Oct 2026): when a customer is selected and has a special price for the product
 * and destination, use it; otherwise use the default price. A price for the order's drop-off type comes before
 * the Default type's (a special price listed only for another type, e.g. Own Company, is still the customer's
 * price), and the order's branch before one for every branch. Only rates in force today count.
 */
class PriceListRates
{
    /** @var array<int, ?string> */
    private array $customerCodes = [];

    /** AutoCount debtor code of the customer (the key of its special prices). */
    public function customerCode(?int $customerId): ?string
    {
        if (! $customerId) {
            return null;
        }

        if (! array_key_exists($customerId, $this->customerCodes)) {
            $code = trim((string) Customer::query()->whereKey($customerId)->value('code'));
            $this->customerCodes[$customerId] = $code !== '' ? $code : null;
        }

        return $this->customerCodes[$customerId];
    }

    /**
     * The tiers in force for a product to a destination: the customer's special set when there is one, else the
     * default set.
     *
     * @return array{tiers: Collection<int, UomRateTier>, special: bool}
     */
    public function uomTiers(Uom $uom, int $locationId, ?int $customerId, ?string $dropOffType = null, ?int $branchId = null): array
    {
        $rows = $this->inForce(UomRateTier::query(), $branchId)
            ->where('uom_id', $uom->id)
            ->where('location_id', $locationId)
            ->orderBy('min_qty')
            ->get();

        return $this->pick($rows, $customerId, $dropOffType, $branchId);
    }

    /** The tier for the quantity (null when the chosen set has no range for it). */
    public function uomTier(Uom $uom, int $locationId, float $quantity, ?int $customerId, ?string $dropOffType = null, ?int $branchId = null): ?UomRateTier
    {
        return $this->tierFor($this->uomTiers($uom, $locationId, $customerId, $dropOffType, $branchId)['tiers'], $quantity);
    }

    /**
     * Every destination the product has a price to for the quantity (the customer's special price where it has one,
     * else the default), in one query: location id → tier.
     *
     * @return Collection<int, UomRateTier>
     */
    public function uomTiersByLocation(Uom $uom, float $quantity, ?int $customerId, ?string $dropOffType = null, ?int $branchId = null): Collection
    {
        return $this->inForce(UomRateTier::query(), $branchId)
            ->where('uom_id', $uom->id)
            ->orderBy('min_qty')
            ->get()
            ->groupBy('location_id')
            ->map(fn (Collection $rows) => $this->tierFor($this->pick($rows, $customerId, $dropOffType, $branchId)['tiers'], $quantity))
            ->filter();
    }

    /** @param  Collection<int, UomRateTier>  $tiers */
    public function tierFor(Collection $tiers, float $quantity): ?UomRateTier
    {
        return $tiers
            ->filter(fn (UomRateTier $tier) => (float) $tier->min_qty <= $quantity + 0.0001
                && ($tier->max_qty === null || (float) $tier->max_qty + 0.0001 >= $quantity))
            ->sortByDesc(fn (UomRateTier $tier) => (float) $tier->min_qty)
            ->first();
    }

    /** @return array{rate: ?CharteredLorryRate, special: bool} */
    public function lorryRate(CharteredLorry $lorry, int $locationId, ?int $customerId, ?string $dropOffType = null, ?int $branchId = null): array
    {
        $rows = $this->inForce(CharteredLorryRate::query(), $branchId)
            ->where('chartered_lorry_id', $lorry->id)
            ->where('location_id', $locationId)
            ->get();

        $picked = $this->pick($rows, $customerId, $dropOffType, $branchId);

        return ['rate' => $picked['tiers']->first(), 'special' => $picked['special']];
    }

    /** Rates in force today for the branch (the branch's own and those for every branch). */
    private function inForce(Builder $query, ?int $branchId): Builder
    {
        $today = now()->toDateString();
        $branchId ??= CurrentCompany::branchId();

        return $query
            ->where(fn (Builder $q) => $q->whereNull('effective_from')->orWhereDate('effective_from', '<=', $today))
            ->where(fn (Builder $q) => $q->whereNull('expires_on')->orWhereDate('expires_on', '>=', $today))
            ->when($branchId, fn (Builder $q) => $q->where(fn (Builder $inner) => $inner->whereNull('branch_id')->orWhere('branch_id', $branchId)));
    }

    /**
     * @template T of UomRateTier|CharteredLorryRate
     *
     * @param  Collection<int, T>  $rows
     * @return array{tiers: Collection<int, T>, special: bool}
     */
    private function pick(Collection $rows, ?int $customerId, ?string $dropOffType, ?int $branchId): array
    {
        $code = $this->customerCode($customerId);
        $type = DropOffType::tryFrom((string) $dropOffType)?->value ?? DropOffType::Default->value;
        $types = array_values(array_unique([$type, DropOffType::Default->value]));
        $branchId ??= CurrentCompany::branchId();

        foreach ($code !== null ? [$code, null] : [null] as $customerCode) {
            // a customer with a special price for the product keeps it whatever drop-off type it is listed for
            // (the order's type first, then Default); a default price is only used for the type or Default
            $candidates = $customerCode === null
                ? $types
                : array_values(array_unique(array_merge($types, $rows
                    ->filter(fn ($row) => strcasecmp(trim((string) $row->customer_code), $customerCode) === 0)
                    ->map(fn ($row) => $row->drop_off_type ?: DropOffType::Default->value)
                    ->unique()->sort()->values()->all())));

            foreach ($candidates as $candidate) {
                $set = $rows->filter(fn ($row) => ($customerCode === null
                        ? blank($row->customer_code)
                        : strcasecmp(trim((string) $row->customer_code), $customerCode) === 0)
                    && ($row->drop_off_type ?: DropOffType::Default->value) === $candidate);

                if ($set->isEmpty()) {
                    continue;
                }

                // the branch's own rates before those for every branch (no branch known: all of them)
                $own = $branchId ? $set->where('branch_id', $branchId) : collect();
                $shared = $set->whereNull('branch_id');
                $chosen = $own->isNotEmpty() ? $own : ($shared->isNotEmpty() ? $shared : $set);

                return ['tiers' => $chosen->values(), 'special' => $customerCode !== null];
            }
        }

        return ['tiers' => collect(), 'special' => false];
    }
}
