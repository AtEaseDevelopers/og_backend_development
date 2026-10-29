<?php

namespace App\Support;

use App\Domains\MasterData\Models\Uom;
use App\Domains\MasterData\Models\UomRateTier;
use App\Enums\DropOffType;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Price rows as the client's price list template lays them out (user, 21 Oct 2026): one row per condition (branch,
 * pickup location, drop-off location and type, the three minimum charges, effective and expiry date) with up to five
 * quantity ranges A–E (quantity from, quantity to, price per unit, and since 29 Oct 2026 an optional fixed charge on
 * top: "(250+4.0X)"). Stored as one uom_rate_tiers row per range.
 */
class UomPriceRows
{
    public const RANGES = ['a', 'b', 'c', 'd', 'e'];

    /**
     * The tiers (of one or several products) as template rows, by product, drop-off location, drop-off type and date.
     *
     * @param  Collection<int, UomRateTier>  $tiers
     * @return list<array<string, mixed>>
     */
    public static function fromTiers(Collection $tiers): array
    {
        return $tiers
            ->groupBy(fn (UomRateTier $tier) => implode('|', [
                $tier->uom_id,
                $tier->customer_code,
                $tier->branch_id,
                $tier->location_id,
                $tier->drop_off_type ?: DropOffType::Default->value,
                $tier->pickup_location,
                $tier->pickup_min_charge,
                $tier->construction_min_charge,
                $tier->drop_off_min_charge,
                $tier->effective_from?->toDateString(),
                $tier->expires_on?->toDateString(),
            ]))
            ->map(function (Collection $group): array {
                $first = $group->first();
                $ranges = $group->sortBy(fn (UomRateTier $tier) => (float) $tier->min_qty)->values();
                $row = [
                    'uom_id' => $first->uom_id,
                    'customer_code' => $first->customer_code,
                    'branch_id' => $first->branch_id,
                    'pickup_location' => $first->pickup_location,
                    'location_id' => $first->location_id,
                    'drop_off_type' => $first->drop_off_type ?: DropOffType::Default->value,
                    'pickup_min_charge' => self::money($first->pickup_min_charge),
                    'construction_min_charge' => self::money($first->construction_min_charge),
                    'drop_off_min_charge' => self::money($first->drop_off_min_charge),
                    'effective_from' => $first->effective_from?->toDateString(),
                    'expires_on' => $first->expires_on?->toDateString(),
                    'source' => $first->source,
                    // shown only (not saved)
                    'product' => $first->relationLoaded('uom') ? $first->uom?->name : null,
                    'location' => $first->relationLoaded('location') ? $first->location?->name : null,
                ];

                foreach (self::RANGES as $i => $letter) {
                    $tier = $ranges->get($i);
                    $row["range_{$letter}_from"] = $tier ? self::quantity($tier->min_qty) : null;
                    $row["range_{$letter}_to"] = $tier && $tier->max_qty !== null ? self::quantity($tier->max_qty) : null;
                    $row["range_{$letter}_price"] = $tier ? self::money($tier->price) : null;
                    $row["range_{$letter}_fixed"] = $tier && $tier->fixed_charge !== null && (float) $tier->fixed_charge > 0 ? self::money($tier->fixed_charge) : null;
                }

                return $row;
            })
            ->sortBy([
                fn (array $a, array $b) => strcmp((string) $a['product'], (string) $b['product']),
                fn (array $a, array $b) => strcmp((string) $a['location'], (string) $b['location']),
                fn (array $a, array $b) => strcmp((string) $a['drop_off_type'], (string) $b['drop_off_type']),
                fn (array $a, array $b) => strcmp((string) $a['effective_from'], (string) $b['effective_from']),
            ])
            ->values()
            ->all();
    }

    /**
     * The filled ranges of a row: [from, to (null = and above), price per unit, fixed charge (null = none)].
     *
     * @return list<array{0: float, 1: ?float, 2: float, 3: ?float}>
     */
    public static function ranges(array $row): array
    {
        $ranges = [];

        foreach (self::RANGES as $letter) {
            $from = $row["range_{$letter}_from"] ?? null;
            $price = $row["range_{$letter}_price"] ?? null;

            if (! is_numeric($from) || ! is_numeric($price)) {
                continue;
            }

            $to = $row["range_{$letter}_to"] ?? null;
            $fixed = $row["range_{$letter}_fixed"] ?? null;
            $ranges[] = [(float) $from, is_numeric($to) ? (float) $to : null, round((float) $price, 2), is_numeric($fixed) && (float) $fixed > 0 ? round((float) $fixed, 2) : null];
        }

        return $ranges;
    }

    /** A row with every template column empty: the condition fields and the five ranges. */
    public static function blankRow(): array
    {
        return array_fill_keys(array_merge(
            ['pickup_location', 'pickup_min_charge', 'construction_min_charge', 'drop_off_min_charge', 'effective_from', 'expires_on'],
            ...array_map(fn (string $l) => ["range_{$l}_from", "range_{$l}_to", "range_{$l}_price", "range_{$l}_fixed"], self::RANGES),
        ), null);
    }

    /**
     * The default price row in force on a date for the product, destination and drop-off type (else the Default
     * type's), the branch's own first and the latest effective date first: the start of a customer's special price.
     */
    public static function defaultRow(int $uomId, int $locationId, string $dropOffType, ?int $branchId, string $date): ?array
    {
        return collect(self::fromTiers(
            UomRateTier::query()
                ->whereNull('customer_code')
                ->where('uom_id', $uomId)
                ->where('location_id', $locationId)
                ->where(fn ($q) => $q->whereNull('effective_from')->orWhereDate('effective_from', '<=', $date))
                ->where(fn ($q) => $q->whereNull('expires_on')->orWhereDate('expires_on', '>=', $date))
                ->get()
        ))
            ->sortBy([
                fn (array $a, array $b) => [$a['drop_off_type'] !== $dropOffType, $a['branch_id'] !== $branchId] <=> [$b['drop_off_type'] !== $dropOffType, $b['branch_id'] !== $branchId],
                fn (array $a, array $b) => strcmp((string) $b['effective_from'], (string) $a['effective_from']),
            ])
            ->first(fn (array $row) => in_array($row['drop_off_type'], [$dropOffType, DropOffType::Default->value], true));
    }

    /** Replaces the product's default price rows (customer special prices stay as they are). */
    public static function sync(Uom $uom, array $rows): void
    {
        DB::transaction(function () use ($uom, $rows): void {
            $uom->rateTiers()->whereNull('customer_code')->delete();

            foreach ($rows as $row) {
                static::create($uom->id, $row, null);
            }
        });
    }

    /** Saves one price row (a condition with its ranges) of the product: default (no customer) or a customer's special. */
    public static function create(int $uomId, array $row, ?string $customerCode): void
    {
        $condition = [
            'uom_id' => $uomId,
            'customer_code' => $customerCode,
            'branch_id' => filled($row['branch_id'] ?? null) ? (int) $row['branch_id'] : null,
            'pickup_location' => filled($row['pickup_location'] ?? null) ? trim((string) $row['pickup_location']) : null,
            'location_id' => (int) $row['location_id'],
            'drop_off_type' => ($row['drop_off_type'] ?? '') ?: DropOffType::Default->value,
            'pickup_min_charge' => is_numeric($row['pickup_min_charge'] ?? null) ? (float) $row['pickup_min_charge'] : null,
            'construction_min_charge' => is_numeric($row['construction_min_charge'] ?? null) ? (float) $row['construction_min_charge'] : null,
            'drop_off_min_charge' => is_numeric($row['drop_off_min_charge'] ?? null) ? (float) $row['drop_off_min_charge'] : null,
            'effective_from' => filled($row['effective_from'] ?? null) ? Carbon::parse($row['effective_from'])->toDateString() : null,
            'expires_on' => filled($row['expires_on'] ?? null) ? Carbon::parse($row['expires_on'])->toDateString() : null,
            'source' => filled($row['source'] ?? null) ? $row['source'] : 'UOM page',
        ];

        foreach (static::ranges($row) as [$from, $to, $price, $fixed]) {
            UomRateTier::query()->create($condition + ['min_qty' => $from, 'max_qty' => $to, 'price' => $price, 'fixed_charge' => $fixed]);
        }
    }

    /**
     * The row with the price of the range that holds the quantity set (a quotation's price for that quantity); a row
     * without ranges gets one, 1 and above.
     */
    public static function withPrice(array $row, float $quantity, float $price): array
    {
        $filled = array_values(array_filter(self::RANGES, fn (string $l) => is_numeric($row["range_{$l}_from"] ?? null)));

        if ($filled === []) {
            return array_merge($row, ['range_a_from' => '1', 'range_a_to' => null, 'range_a_price' => number_format($price, 2, '.', '')]);
        }

        $letter = collect($filled)->first(fn (string $l) => (float) $row["range_{$l}_from"] <= $quantity + 0.0001
            && (! is_numeric($row["range_{$l}_to"] ?? null) || $quantity <= (float) $row["range_{$l}_to"] + 0.0001)) ?? $filled[0];

        $row["range_{$letter}_price"] = number_format($price, 2, '.', '');

        return $row;
    }

    /** "1 → RM 14.00", "2–9 → RM 5.50", "40+ → RM 3.50"; with a fixed charge "50+ → RM 250.00 + RM 4.00/unit" */
    public static function rangeLines(array $row): array
    {
        return array_map(function (array $range): string {
            [$from, $to, $price, $fixed] = $range;
            $qty = match (true) {
                $to === null => self::quantity($from).'+',
                $to === $from => self::quantity($from),
                default => self::quantity($from).'–'.self::quantity($to),
            };

            return $qty.' → '.($fixed !== null ? 'RM '.number_format($fixed, 2).' + RM '.number_format($price, 2).'/unit' : 'RM '.number_format($price, 2));
        }, self::ranges($row));
    }

    private static function quantity(mixed $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
    }

    private static function money(mixed $value): ?string
    {
        return $value === null ? null : number_format((float) $value, 2, '.', '');
    }
}
