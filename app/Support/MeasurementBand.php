<?php

namespace App\Support;

use App\Domains\MasterData\Models\Uom;
use Illuminate\Support\Collection;

/**
 * The size band of goods (user, 2 Nov 2026): the product of a Measurement sub-category (Carton, Pallet, …) or of an
 * Item (Cable Drum, Coil, Drum, …) whose limits the goods' L, W, H and weight fit — L+W+H, length, width, height and
 * weight ranges of the price list. Create order and the customer portal enter one value each (only the ones the
 * item's bands use); Create quotation enters min – max ranges, and the whole range must lie inside the band. The
 * tightest band wins when several fit; a per-kg band only when no per-piece band fits.
 */
class MeasurementBand
{
    public const DIMS = ['length', 'width', 'height', 'weight'];

    /**
     * @param  array{l: ?array{float, float}, w: ?array{float, float}, h: ?array{float, float}, kg: ?float}  $size  each as [min, max]; null = not entered
     * @param  Collection<int, Uom>  $candidates  the products (bands) to choose from
     */
    public static function pick(array $size, Collection $candidates): ?Uom
    {
        // a limit the band has must be met by a value entered; a band without that limit ignores the value
        $within = function (?array $range, $min, $max): bool {
            if ($min === null && $max === null) {
                return true;
            }

            if ($range === null) {
                return false;
            }

            return ($min === null || min($range) >= (float) $min - 0.0001) && ($max === null || max($range) <= (float) $max + 0.0001);
        };
        $lwh = ($size['l'] ?? null) !== null && ($size['w'] ?? null) !== null && ($size['h'] ?? null) !== null
            ? [$size['l'][0] + $size['w'][0] + $size['h'][0], $size['l'][1] + $size['w'][1] + $size['h'][1]]
            : null;
        $kg = ($size['kg'] ?? null) !== null ? [$size['kg'], $size['kg']] : null;

        return $candidates
            ->filter(fn (Uom $uom) => $within($lwh, $uom->min_lwh, $uom->max_lwh)
                && $within($size['l'] ?? null, $uom->min_length, $uom->max_length)
                && $within($size['w'] ?? null, null, $uom->max_width)
                && $within($size['h'] ?? null, null, $uom->max_height)
                && $within($kg, $uom->min_weight, $uom->max_weight))
            ->sortBy([
                fn (Uom $a, Uom $b) => (int) $a->priced_per_kg <=> (int) $b->priced_per_kg,
                fn (Uom $a, Uom $b) => ($a->max_lwh === null ? INF : (float) $a->max_lwh) <=> ($b->max_lwh === null ? INF : (float) $b->max_lwh),
                fn (Uom $a, Uom $b) => ($a->max_weight === null ? INF : (float) $a->max_weight) <=> ($b->max_weight === null ? INF : (float) $b->max_weight),
            ])
            ->first();
    }

    /**
     * The sizes an item's bands use (length, width, height, weight), so the form asks only for those.
     *
     * @param  Collection<int, Uom>  $bands
     * @return list<string>
     */
    public static function limitsUsed(Collection $bands): array
    {
        $used = [];

        foreach ($bands as $uom) {
            $lwh = $uom->min_lwh !== null || $uom->max_lwh !== null;

            if ($lwh || $uom->min_length !== null || $uom->max_length !== null) {
                $used['length'] = true;
            }

            if ($lwh || $uom->max_width !== null) {
                $used['width'] = true;
            }

            if ($lwh || $uom->max_height !== null) {
                $used['height'] = true;
            }

            if ($uom->min_weight !== null || $uom->max_weight !== null) {
                $used['weight'] = true;
            }
        }

        return array_values(array_filter(self::DIMS, fn (string $dim) => isset($used[$dim])));
    }

    /**
     * The band of goods from single values, by their type: a Measurement packing (sub-category) or an Item (sub-category
     * and base item), among a company's active products (the portal, where no company is current). A product without
     * any limit (e.g. an air compressor model) is the band itself.
     *
     * @param  array{length?: mixed, width?: mixed, height?: mixed, weight?: mixed}  $values
     */
    public static function forGoods(?int $branchId, string $category, string $subCategory, ?string $baseItem, array $values): ?Uom
    {
        $candidates = Uom::query()->withoutGlobalScopes()
            ->where('is_active', true)
            ->where('category', $category)
            ->whereRaw('LOWER(sub_category) = ?', [mb_strtolower(trim($subCategory))])
            ->when($category === Uom::ITEM && filled($baseItem), fn ($q) => $q->whereRaw('LOWER(base_item) = ?', [mb_strtolower(trim((string) $baseItem))]))
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->get();

        return static::pickFromValues($values, $candidates);
    }

    /**
     * The band among candidates from single values (null where not entered); every size the bands use must be entered,
     * else nothing is picked — except a lone product without limits.
     *
     * @param  array{length?: mixed, width?: mixed, height?: mixed, weight?: mixed}  $values
     * @param  Collection<int, Uom>  $candidates
     */
    public static function pickFromValues(array $values, Collection $candidates): ?Uom
    {
        if ($candidates->isEmpty()) {
            return null;
        }

        $limits = static::limitsUsed($candidates);

        if ($limits === []) {
            return $candidates->count() === 1 ? $candidates->first() : null;
        }

        $num = fn (string $key) => is_numeric($values[$key] ?? null) ? (float) $values[$key] : null;

        foreach ($limits as $dim) {
            if ($num($dim) === null) {
                return null;
            }
        }

        $range = fn (string $key) => $num($key) === null ? null : [$num($key), $num($key)];

        return static::pick(['l' => $range('length'), 'w' => $range('width'), 'h' => $range('height'), 'kg' => $num('weight')], $candidates);
    }

    /**
     * The goods the portal offers, every company: packing types (Measurement sub-categories) and items (Item sub-category
     * + base item), each with the sizes it needs.
     *
     * @return array{packing: list<array{value: string, label: string, dims: list<string>}>, items: list<array{value: string, label: string, dims: list<string>}>}
     */
    public static function portalGoods(?int $branchId = null): array
    {
        $rows = Uom::query()->withoutGlobalScopes()
            ->where('is_active', true)
            ->whereIn('category', [Uom::MEASUREMENT, Uom::ITEM])
            ->whereNotNull('sub_category')
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->get();

        $packing = $rows->where('category', Uom::MEASUREMENT)->groupBy('sub_category')->sortKeys()
            ->map(fn (Collection $bands, string $sub) => ['value' => Uom::MEASUREMENT.':'.$sub, 'label' => $sub, 'dims' => self::DIMS])
            ->values()->all();
        $items = $rows->where('category', Uom::ITEM)->groupBy(fn (Uom $u) => $u->sub_category.'|'.($u->base_item ?: $u->sub_category))->sortKeys()
            ->map(function (Collection $bands, string $key) {
                [$sub, $base] = explode('|', $key, 2);

                return ['value' => Uom::ITEM.':'.$sub.'|'.$base, 'label' => $base === $sub ? $base : $sub.' · '.$base, 'dims' => static::limitsUsed($bands)];
            })
            ->values()->all();

        return ['packing' => $packing, 'items' => $items];
    }

    /**
     * A portal goods value ("measurement:Carton", "item:Cable|Cable Drum") as [category, sub-category, base item].
     *
     * @return array{0: string, 1: string, 2: ?string}|null
     */
    public static function parseGoods(?string $value): ?array
    {
        if (! $value || ! str_contains($value, ':')) {
            return null;
        }

        [$category, $rest] = explode(':', $value, 2);

        if (! in_array($category, [Uom::MEASUREMENT, Uom::ITEM], true) || trim($rest) === '') {
            return null;
        }

        [$sub, $base] = array_pad(explode('|', $rest, 2), 2, null);

        return [$category, trim($sub), $base !== null ? trim($base) : null];
    }
}
