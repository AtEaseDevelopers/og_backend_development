<?php

namespace App\Support;

use App\Domains\Consignment\Models\ConsignmentNote;
use App\Domains\MasterData\Models\Lorry;
use App\Domains\MasterData\Models\Uom;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;

/**
 * The note on "Assign to lorry" for an order with a chartered lorry (user, 13 Nov 2026): the size the customer
 * chartered ("12 FT Lorry") and the fleet vehicles of that size (Master Data → Fleet Vehicles, Type), so the user
 * picks a lorry that fits. It only informs: any lorry can still be picked.
 */
class CharteredLorryFit
{
    /** "12 FT Lorry", "24FT", "10-ton" → ['value' => 12.0, 'unit' => 'FT', 'label' => '12 FT']; null when no size is read. */
    public static function size(?string $text): ?array
    {
        if (! preg_match('/(\d+(?:\.\d+)?)\s*-?\s*(ft|feet|foot|tonne|tons|ton|t)\b/i', (string) $text, $m)) {
            return null;
        }

        $unit = in_array(strtolower($m[2]), ['ft', 'feet', 'foot'], true) ? 'FT' : 'TON';
        $value = (float) $m[1];

        return ['value' => $value, 'unit' => $unit, 'label' => rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.').' '.($unit === 'FT' ? 'FT' : 'Ton')];
    }

    /**
     * The chartered lorries on the CSNs: one row per CSN and product.
     *
     * @param  Collection<int, ConsignmentNote|null>  $csns
     * @return list<array{csn: string, item: string, size: ?array}>
     */
    public static function requirements(Collection $csns): array
    {
        $csns = $csns->filter()->unique('id')->values();

        if ($csns->isEmpty()) {
            return [];
        }

        $csns->each(fn (ConsignmentNote $csn) => $csn->loadMissing('lines'));
        $codes = $csns->flatMap(fn (ConsignmentNote $csn) => $csn->lines->pluck('uom'))->filter()->unique()->values();
        $lorryCodes = $codes->isEmpty() ? collect() : Uom::query()->withoutGlobalScopes()->where('category', Uom::LORRY)->whereIn('code', $codes)->pluck('code')->flip();
        $rows = [];

        foreach ($csns as $csn) {
            foreach ($csn->lines as $line) {
                if (! $lorryCodes->has((string) $line->uom)) {
                    continue;
                }

                $rows[$csn->id.'|'.$line->item_name] = ['csn' => (string) $csn->number, 'item' => (string) $line->item_name, 'size' => static::size($line->item_name)];
            }
        }

        return array_values($rows);
    }

    /**
     * The active fleet vehicles of the company against a size: the same size, and the larger ones of the same unit.
     *
     * @return array{same: list<string>, larger: list<string>}
     */
    public static function lorriesFor(array $size): array
    {
        $lorries = Lorry::query()->where('is_active', true)
            ->when(CurrentCompany::id(), fn ($q, $id) => $q->where(fn ($w) => $w->where('company_id', $id)->orWhereNull('company_id')))
            ->orderBy('registration_no')->get(['registration_no', 'type']);
        $fit = ['same' => [], 'larger' => []];

        foreach ($lorries as $lorry) {
            $own = static::size($lorry->type);

            if (! $own || $own['unit'] !== $size['unit']) {
                continue;
            }

            if (abs($own['value'] - $size['value']) < 0.001) {
                $fit['same'][] = (string) $lorry->registration_no;
            } elseif ($own['value'] > $size['value']) {
                $fit['larger'][] = $lorry->registration_no.' ('.$own['label'].')';
            }
        }

        return $fit;
    }

    /** The banner, or null when no CSN has a chartered lorry. */
    public static function banner(Collection $csns): ?HtmlString
    {
        $requirements = static::requirements($csns);

        if ($requirements === []) {
            return null;
        }

        $many = $csns->filter()->unique('id')->count() > 1;
        $lines = [];

        foreach (collect($requirements)->groupBy(fn (array $r) => $r['size']['label'] ?? $r['item']) as $rows) {
            $first = $rows->first();
            $what = '<strong>'.e($first['item']).'</strong>'.($many ? ' <span style="opacity:.8">('.e($rows->pluck('csn')->unique()->implode(', ')).')</span>' : '');

            if (! $first['size']) {
                $lines[] = $what.' — pick a lorry that fits it.';

                continue;
            }

            $fit = static::lorriesFor($first['size']);
            $text = $what.' — ';
            $text .= $fit['same'] !== []
                ? 'suitable '.e($first['size']['label']).' lorries: <strong>'.e(implode(', ', $fit['same'])).'</strong>.'
                : 'no active '.e($first['size']['label']).' lorry in Lorry Database.';
            if ($fit['larger'] !== []) {
                $text .= ' Larger: '.e(implode(', ', $fit['larger'])).'.';
            }
            $lines[] = $text;
        }

        return new HtmlString('<div style="border:1px solid rgb(253 186 116);background:rgb(255 247 237);color:rgb(124 45 18);border-radius:.6rem;padding:.6rem .8rem;font-size:.85rem;line-height:1.5">'
            .'<div style="font-weight:700;margin-bottom:.15rem">Chartered lorry on this order</div>'
            .collect($lines)->map(fn (string $line) => '<div>'.$line.'</div>')->implode('')
            .'</div>');
    }
}
