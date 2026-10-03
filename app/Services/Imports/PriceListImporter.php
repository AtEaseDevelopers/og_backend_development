<?php

namespace App\Services\Imports;

use App\Domains\MasterData\Models\Item;
use App\Domains\MasterData\Models\Location;
use App\Domains\MasterData\Models\Uom;
use App\Domains\MasterData\Models\UomRateTier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Imports the general price list workbook ("Pricing list General.xlsx") into UOM rate tiers.
 *
 * Sheet layout (first sheet):
 *   row 1        B.. = destination columns, e.g. "Seremban/Melaka" | "Johor" ("/" = same price for each)
 *   header rows  A only                         → section (e.g. "Cartons", "Guni Bags")
 *                A only, followed by tier rows   → one UOM with quantity tiers (e.g. Carton up to 30KG)
 *   tier rows    "1 Ctn", "2 - 9 Ctn", "20 Ctn & Above", "1 pail", "10 - 19 plt" …
 *   price rows   A + prices                      → one UOM with a single tier (qty 1 and above)
 *   special      "… Over 10 drums"               → extra tier on the base UOM
 *                "1 - 5 pallets [size]"          → tier on the UOM named by [size]
 *                "(1-1000) kg" + D "per ton"     → per-ton UOM, tiers in tons
 *                "Max(Weight * 0.14,30)"         → per-kg UOM (minimum floor reported, not enforced)
 *                "Minimum" rows                  → reported (no minimum-charge field yet)
 *
 * Every UOM's tiers for the sheet's locations are replaced on each run, so the command is re-runnable.
 */
class PriceListImporter
{
    private const COUNT_UNITS = 'ctns?|cartons?|pails?|plts?|pallets?|drums?|pcs|units?|bags?';

    public function __construct(private SimpleXlsxReader $reader) {}

    /**
     * @return array{
     *     locations: list<string>,
     *     uoms: array<string, array{name: string, section: string, basis: string, tiers: list<array{location: string, min: float, max: ?float, price: float}>}>,
     *     skipped: list<string>,
     *     warnings: list<string>
     * }
     */
    public function parse(string $path): array
    {
        $sheets = $this->reader->readSheets($path);
        $rows = reset($sheets) ?: [];

        if ($rows === []) {
            throw new RuntimeException('The workbook has no rows.');
        }

        // ---- destination columns --------------------------------------------------------
        $columns = [];
        foreach (array_shift($rows) as $col => $header) {
            if ($col === 'A' || blank($header)) {
                continue;
            }

            $columns[$col] = array_values(array_filter(array_map('trim', explode('/', (string) $header))));
        }

        if ($columns === []) {
            throw new RuntimeException('Row 1 must list the destination columns (e.g. Seremban/Melaka, Johor).');
        }

        $locations = array_values(array_unique(array_merge(...array_values($columns))));

        $uoms = [];
        $skipped = [];
        $warnings = [];
        $section = 'General';
        $group = null;          // UOM name that tier rows attach to
        $minimumMode = false;   // inside a "Minimum" block

        $rowCount = count($rows);

        for ($i = 0; $i < $rowCount; $i++) {
            $row = $rows[$i];
            $label = trim((string) ($row['A'] ?? ''));
            $prices = $this->pricesFor($row, $columns);
            $note = strtolower(trim((string) ($row['D'] ?? '')));

            if ($label === '' && $prices === []) {
                continue;
            }

            if ($label === '') {
                $skipped[] = sprintf('Unlabelled price row under "%s": %s', $section, $this->describePrices($prices));

                continue;
            }

            // -- header rows (no prices) -------------------------------------------------
            if ($prices === []) {
                $next = $this->nextLabel($rows, $i);

                if (strcasecmp($label, 'Minimum') === 0) {
                    $minimumMode = true;
                    $group = null;

                    continue;
                }

                $minimumMode = false;

                if ($next !== null && $this->isTierLabel($next)) {
                    $group = $label;
                    $uoms[$group] ??= $this->newUom($group, $section, 'per unit');
                } else {
                    $section = $label;
                    $group = null;
                }

                continue;
            }

            // -- minimum charges (no field yet) ------------------------------------------
            if ($minimumMode || strcasecmp($label, 'Minimum') === 0) {
                $skipped[] = sprintf('Minimum charge "%s" under "%s": %s (no minimum-charge field yet)', $label, $section, $this->describePrices($prices));

                continue;
            }

            // -- per-ton weight bands, e.g. "(1-1000) kg" with D = per ton ---------------
            if (str_contains($note, 'ton') && ($band = $this->weightBandInTons($label)) !== null) {
                $name = $this->sectionShort($section).' (per ton)';
                $uoms[$name] ??= $this->newUom($name, $section, 'per ton');
                $this->addTiers($uoms[$name], $prices, $band[0], $band[1]);

                continue;
            }

            // -- "Per tons" single row ----------------------------------------------------
            if (preg_match('/^per\s+tons?$/i', $label)) {
                $name = $this->sectionShort($section).' (per ton)';
                $uoms[$name] ??= $this->newUom($name, $section, 'per ton');
                $this->addTiers($uoms[$name], $prices, 0, null);

                continue;
            }

            // -- tier rows attached to the current group --------------------------------
            if ($group !== null && ($tier = $this->parseTier($label)) !== null) {
                $this->addTiers($uoms[$group], $prices, $tier[0], $tier[1]);

                continue;
            }

            $group = null;

            // -- "1 - 5 pallets [size]" / "6 pallet and above [size]" ------------------
            if (preg_match('/^(\d+)\s*-\s*(\d+)\s+(?:'.self::COUNT_UNITS.')\s+(.+)$/i', $label, $m)
                || preg_match('/^(\d+)\s+(?:'.self::COUNT_UNITS.')\s+(?:and|&)\s+above\s+(.+)$/i', $label, $m)) {
                $isRange = count($m) === 4;
                $name = $this->prefixed($section, $isRange ? $m[3] : $m[2]);
                $uoms[$name] ??= $this->newUom($name, $section, 'per unit');
                $this->addTiers($uoms[$name], $prices, (float) $m[1], $isRange ? (float) $m[2] : null);

                continue;
            }

            // -- "<base> Over 10 drums" → extra tier on the base UOM ----------------------
            if (preg_match('/^(.*?)\s+over\s+(\d+)\s+\w+$/i', $label, $m)) {
                $base = $this->prefixed($section, trim($m[1]));
                $threshold = (float) $m[2];

                if (isset($uoms[$base])) {
                    foreach ($uoms[$base]['tiers'] as &$tier) {
                        if ($tier['max'] === null && $tier['min'] <= $threshold) {
                            $tier['max'] = $threshold;
                        }
                    }
                    unset($tier);

                    $this->addTiers($uoms[$base], $prices, $threshold + 1, null);

                    continue;
                }

                $warnings[] = sprintf('"%s": base item "%s" not found, imported as its own UOM.', $label, $base);
            }

            // -- weight formula "Max(Weight * 0.14,30)" → per-kg UOM ---------------------
            if ($this->hasFormula($prices)) {
                $name = $this->prefixed($section, $label).' (per kg)';
                $uoms[$name] ??= $this->newUom($name, $section, 'per kg');
                $floors = [];

                foreach ($prices as $location => $price) {
                    if (is_array($price)) {
                        $uoms[$name]['tiers'][] = ['location' => $location, 'min' => 0, 'max' => null, 'price' => $price['rate']];
                        $floors[] = $location.' min RM'.$price['minimum'];
                    } else {
                        $uoms[$name]['tiers'][] = ['location' => $location, 'min' => 0, 'max' => null, 'price' => $price];
                    }
                }

                if ($floors !== []) {
                    $warnings[] = sprintf('%s: imported as a per-kg rate; minimum NOT enforced yet (%s).', $name, implode(', ', array_unique($floors)));
                }

                continue;
            }

            // -- ordinary priced row → single-tier UOM ------------------------------------
            $name = $this->prefixed($section, $label);
            $uoms[$name] ??= $this->newUom($name, $section, 'per unit');
            $this->addTiers($uoms[$name], $prices, 1, null);
        }

        // report destinations with no price, grouped by which destinations are missing
        $missingGroups = [];

        foreach ($uoms as $name => $uom) {
            $priced = array_unique(array_column($uom['tiers'], 'location'));
            $missing = array_values(array_diff($locations, $priced));

            if ($missing !== [] && $uom['tiers'] !== []) {
                $missingGroups[implode(', ', $missing)][] = $name;
            }
        }

        foreach ($missingGroups as $missing => $names) {
            $warnings[] = sprintf('No price for %s on %d UOM(s): %s', $missing, count($names), implode('; ', $names));
        }

        $uoms = array_filter($uoms, fn (array $uom) => $uom['tiers'] !== []);

        return compact('locations', 'uoms', 'skipped', 'warnings');
    }

    /**
     * Writes the parsed price list. Replaces every imported UOM's tiers for the sheet locations.
     *
     * @param  array{locations: list<string>, uoms: array<string, array>}  $data
     * @return array{locations: int, uoms_created: int, uoms_updated: int, tiers: int, reset_uoms: int}
     */
    public function import(array $data, bool $reset = false): array
    {
        return DB::transaction(function () use ($data, $reset) {
            $stats = ['locations' => 0, 'uoms_created' => 0, 'uoms_updated' => 0, 'tiers' => 0, 'reset_uoms' => 0];

            if ($reset) {
                $stats['reset_uoms'] = Uom::query()->count();
                UomRateTier::query()->delete();
                Uom::query()->delete();
                Item::query()->whereNotNull('default_uom')->update(['default_uom' => null]);
            }

            $locationIds = [];

            foreach ($data['locations'] as $name) {
                $location = Location::query()->updateOrCreate(
                    ['name' => $name],
                    [
                        'code' => Str::upper(Str::slug($name, '_')),
                        'type' => 'delivery',
                        'state' => $this->stateFor($name),
                        'city' => $name,
                        'is_active' => true,
                    ],
                );
                $locationIds[$name] = $location->id;
                $stats['locations']++;
            }

            $usedCodes = Uom::query()->pluck('code')->flip()->all();

            foreach ($data['uoms'] as $name => $row) {
                $uom = Uom::query()->where('name', $name)->first();

                if ($uom) {
                    $uom->update(['is_active' => true]);
                    $stats['uoms_updated']++;
                } else {
                    $code = $this->uniqueCode($name, $usedCodes);
                    $usedCodes[$code] = true;
                    $uom = Uom::query()->create(['code' => $code, 'name' => $name, 'is_active' => true]);
                    $stats['uoms_created']++;
                }

                UomRateTier::query()
                    ->where('uom_id', $uom->id)
                    ->whereIn('location_id', array_values($locationIds))
                    ->delete();

                foreach ($row['tiers'] as $tier) {
                    UomRateTier::query()->create([
                        'uom_id' => $uom->id,
                        'location_id' => $locationIds[$tier['location']],
                        'min_qty' => $tier['min'],
                        'max_qty' => $tier['max'],
                        'price' => $tier['price'],
                    ]);
                    $stats['tiers']++;
                }
            }

            return $stats;
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Parsing helpers
    |--------------------------------------------------------------------------
    */

    /**
     * @param  array<string, string|null>  $row
     * @param  array<string, list<string>>  $columns
     * @return array<string, float|array{rate: float, minimum: float}>
     */
    private function pricesFor(array $row, array $columns): array
    {
        $prices = [];

        foreach ($columns as $col => $locations) {
            $raw = trim((string) ($row[$col] ?? ''));

            if ($raw === '') {
                continue;
            }

            if (preg_match('/^max\(\s*weight\s*\*\s*([\d.]+)\s*,\s*([\d.]+)\s*\)$/i', $raw, $m)) {
                $value = ['rate' => (float) $m[1], 'minimum' => (float) $m[2]];
            } elseif (is_numeric($raw)) {
                $value = (float) $raw;
            } else {
                continue;
            }

            foreach ($locations as $location) {
                $prices[$location] = $value;
            }
        }

        return $prices;
    }

    /** @param  array<string, mixed>  $prices */
    private function hasFormula(array $prices): bool
    {
        foreach ($prices as $price) {
            if (is_array($price)) {
                return true;
            }
        }

        return false;
    }

    /** @param  list<array<string, string|null>>  $rows */
    private function nextLabel(array $rows, int $i): ?string
    {
        for ($j = $i + 1; $j < count($rows); $j++) {
            $row = $rows[$j];
            $label = trim((string) ($row['A'] ?? ''));
            $hasValues = collect($row)->except('A')->filter(fn ($v) => filled($v))->isNotEmpty();

            if ($label === '' && ! $hasValues) {
                continue;
            }

            return $label === '' ? null : $label;
        }

        return null;
    }

    private function isTierLabel(string $label): bool
    {
        return $this->parseTier($label) !== null;
    }

    /** "1 Ctn" → [1,1], "2 - 9 Ctn" → [2,9], "20 Ctn & Above" → [20,null]. */
    private function parseTier(string $label): ?array
    {
        $units = self::COUNT_UNITS;

        if (preg_match('/^(\d+)\s*-\s*(\d+)\s*(?:'.$units.')$/i', $label, $m)) {
            return [(float) $m[1], (float) $m[2]];
        }

        if (preg_match('/^(\d+)\s*(?:'.$units.')\s*(?:&|and)\s*above$/i', $label, $m)) {
            return [(float) $m[1], null];
        }

        if (preg_match('/^(\d+)\s*(?:'.$units.')$/i', $label, $m)) {
            return [(float) $m[1], (float) $m[1]];
        }

        return null;
    }

    /** "(1-1000) kg" → [0, 1] tons, "1001kgs and above" → [1.01, null]. */
    private function weightBandInTons(string $label): ?array
    {
        if (preg_match('/^\(?\s*(\d+)\s*-\s*(\d+)\s*\)?\s*kgs?$/i', $label, $m)) {
            return [(int) $m[1] <= 1 ? 0.0 : round((float) $m[1] / 1000, 2), round((float) $m[2] / 1000, 2)];
        }

        if (preg_match('/^(\d+)\s*kgs?\s*(?:&|and)\s*above$/i', $label, $m)) {
            return [max(0.01, round(ceil((float) $m[1] / 10) / 100, 2)), null];
        }

        return null;
    }

    /** "Cable (Diameter)" → "Cable", "Pallets/Case (LxWxH) (Below 1 ton)" → "Pallets/Case". */
    private function sectionShort(string $section): string
    {
        return trim(preg_replace('/\s*\(.*$/', '', $section)) ?: $section;
    }

    /** Short / numeric labels get the section name in front ("Guni Bags 20kgs", "Air Compressor 2 HP"). */
    private function prefixed(string $section, string $label): string
    {
        $label = trim(preg_replace('/\s+/', ' ', $label));
        $sectionShort = $this->sectionShort($section);

        $startsNumeric = (bool) preg_match('/^[\d\[\(\'"]/', $label);

        if (! $startsNumeric) {
            return $label;
        }

        $keywords = collect(preg_split('/[\s\/]+/', strtolower($sectionShort)))
            ->map(fn ($w) => rtrim($w, 's'))
            ->filter(fn ($w) => strlen($w) >= 3);

        $haystack = strtolower($label);

        if ($keywords->contains(fn ($w) => str_contains($haystack, $w))) {
            return $label;
        }

        return $sectionShort.' '.$label;
    }

    /** @return array{name: string, section: string, basis: string, tiers: list<array>} */
    private function newUom(string $name, string $section, string $basis): array
    {
        return ['name' => $name, 'section' => $section, 'basis' => $basis, 'tiers' => []];
    }

    /** @param  array<string, float|array>  $prices */
    private function addTiers(array &$uom, array $prices, float $min, ?float $max): void
    {
        foreach ($prices as $location => $price) {
            if (is_array($price)) {
                continue;
            }

            $uom['tiers'][] = ['location' => $location, 'min' => $min, 'max' => $max, 'price' => $price];
        }
    }

    /** @param  array<string, float|array>  $prices */
    private function describePrices(array $prices): string
    {
        return collect($prices)
            ->map(fn ($p, $loc) => $loc.' '.(is_array($p) ? 'RM '.$p['rate'].'/kg min '.$p['minimum'] : 'RM '.number_format((float) $p, 2)))
            ->implode(', ');
    }

    /** @param  array<string, bool>  $used */
    private function uniqueCode(string $name, array $used): string
    {
        $base = Str::upper(Str::limit(Str::slug($name, '_'), 44, ''));
        $base = rtrim($base, '_') ?: 'UOM';
        $code = $base;
        $n = 2;

        while (isset($used[$code])) {
            $code = $base.'_'.$n++;
        }

        return $code;
    }

    private function stateFor(string $location): ?string
    {
        return match (strtolower($location)) {
            'seremban' => 'Negeri Sembilan',
            'melaka', 'malacca' => 'Melaka',
            'johor', 'johor bahru' => 'Johor',
            'penang', 'george town' => 'Pulau Pinang',
            'kuala lumpur' => 'Kuala Lumpur',
            'klang' => 'Selangor',
            default => null,
        };
    }
}
