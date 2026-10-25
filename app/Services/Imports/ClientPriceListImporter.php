<?php

namespace App\Services\Imports;

use App\Domains\MasterData\Models\Branch;
use App\Domains\MasterData\Models\Customer;
use App\Domains\MasterData\Models\Location;
use App\Domains\MasterData\Models\Uom;
use App\Domains\MasterData\Models\UomRateTier;
use App\Enums\DropOffType;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Imports the client's "O&G Customer Pricing Setup Template" workbooks (19 Oct 2026): the Default Price file
 * (sheets Measurement / Items / Chartered Lorry / Special Area Pricing Setup) and the Special Price file (one sheet
 * per customer, named by its AutoCount debtor code). Every sheet has its column titles on the row that starts with
 * "Record No."; each row below it is one item to one drop-off location with up to five quantity ranges (A–E).
 *
 *  - Measurement / Item rows → a UOM product per size / weight band ("Carton · L+W+H ≤ 149 cm · L ≤ 60 cm · up to
 *    30 kg") with its rate tiers; Chartered Lorry rows → a UOM product of the Chartered Lorry category ("10 FT
 *    Lorry", user 21 Oct 2026) with its rate tiers, like the others.
 *  - A row with a Customer Code is that customer's special price; without one it is the default price.
 *
 * Re-runnable: the default prices of the file's branches (and the old price list, which had no branch) are replaced
 * when the file has default rows; a customer's special prices are replaced when the file has rows for that customer.
 * UOM products left without any price afterwards (the old price list) are deactivated: past orders keep their names.
 */
class ClientPriceListImporter
{
    private const RANGES = ['A', 'B', 'C', 'D', 'E'];

    /** column title (letters and digits only, lower case) → field */
    private const COLUMNS = [
        'branch' => 'branch',
        'customercode' => 'customer_code',
        'customername' => 'customer_name',
        'category' => 'category',
        'subcategory' => 'sub_category',
        'item' => 'item',
        'minlwhcm' => 'min_lwh',
        'maxlwhcm' => 'max_lwh',
        'minlengthlcm' => 'min_length',
        'minlengthcm' => 'min_length',
        'maxlengthlcm' => 'max_length',
        'maxlengthcm' => 'max_length',
        'maxwidthwcm' => 'max_width',
        'maxsides1cm' => 'max_width',
        'maxheighthcm' => 'max_height',
        'maxsides2cm' => 'max_height',
        'minweightkg' => 'min_weight',
        'maxweightkg' => 'max_weight',
        'pickuplocation' => 'pickup_location',
        'dropofflocation' => 'drop_off_location',
        'dropofftype' => 'drop_off_type',
        'pickupminimumchargerm' => 'pickup_min_charge',
        'constructionsitedropoffminimumchargerm' => 'construction_min_charge',
        'dropoffminimumchargerm' => 'drop_off_min_charge',
        'effectivedate' => 'effective_from',
        'expirydate' => 'expires_on',
    ];

    private const BANDS = ['min_lwh', 'max_lwh', 'min_length', 'max_length', 'max_width', 'max_height', 'min_weight', 'max_weight'];

    public function __construct(private SimpleXlsxReader $reader) {}

    /**
     * Read a workbook into price rows (nothing is written).
     *
     * @return array{
     *     file: string,
     *     products: array<string, array<string, mixed>>,
     *     lorries: array<string, array{name: string, max_weight: ?float}>,
     *     rates: list<array<string, mixed>>,
     *     customers: array<string, string>,
     *     branches: list<string>,
     *     locations: list<string>,
     *     warnings: list<string>,
     *     rows: int
     * }
     */
    public function parse(string $path, ?string $fileName = null): array
    {
        $fileName ??= basename($path);
        $sheets = $this->reader->readSheetsByRow($path);
        $result = [
            'file' => $fileName, 'products' => [], 'lorries' => [], 'rates' => [], 'customers' => [],
            'branches' => [], 'locations' => [], 'warnings' => [], 'rows' => 0,
        ];

        foreach ($sheets as $sheetName => $rows) {
            $columns = $this->headerColumns($rows);

            if ($columns === null) {
                continue; // the Lists sheet and any other sheet without the pricing columns
            }

            [$headerRow, $map] = $columns;

            foreach ($rows as $rowNumber => $cells) {
                if ($rowNumber <= $headerRow) {
                    continue;
                }

                $row = $this->rowValues($cells, $map);

                if (blank($row['item'] ?? null)) {
                    continue; // empty template rows (only the Record No. formula)
                }

                $result['rows']++;
                $where = "{$sheetName} row {$rowNumber}";
                $this->parseRow($row, $sheetName, $where, $fileName, $result);
            }
        }

        if ($result['rows'] === 0) {
            throw new RuntimeException('No price rows found. Is this the O&G Customer Pricing Setup Template?');
        }

        $result['branches'] = array_values(array_unique($result['branches']));
        $result['locations'] = array_values(array_unique($result['locations']));

        return $result;
    }

    /**
     * Write the parsed rows (one transaction).
     *
     * @param  array<string, mixed>  $data  from parse()
     * @return array{products: int, products_new: int, lorries: int, locations_new: list<string>, default_rates: int, special_rates: array<string, int>, unknown_customers: list<string>, deactivated: int, warnings: list<string>}
     */
    public function import(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $warnings = $data['warnings'];
            $branches = $this->resolveBranches($data['branches'], $warnings);
            $branchIds = array_values(array_filter($branches));
            $locations = $this->resolveLocations($data['locations']);
            $locationsNew = array_values(array_filter(array_keys($locations), fn ($name) => $locations[$name]['new']));

            // products and lorries
            $usedCodes = Uom::query()->pluck('code')->mapWithKeys(fn ($code) => [strtoupper((string) $code) => true])->all();
            $uomIds = [];
            $productsNew = 0;

            foreach ($data['products'] as $key => $product) {
                $uom = Uom::query()->where('name', $product['name'])->first();

                if (! $uom) {
                    $code = $this->uniqueCode($product['name'], $usedCodes);
                    $usedCodes[$code] = true;
                    $uom = new Uom(['code' => $code]);
                    $productsNew++;
                }

                $uom->fill([
                    'name' => $product['name'],
                    'category' => $product['category'],
                    'sub_category' => $product['sub_category'],
                    'base_item' => $product['base_item'],
                    'is_active' => true,
                ] + array_intersect_key($product, array_flip(self::BANDS)))->save();

                $uomIds[$key] = $uom->id;
            }

            // replace the prices this file holds: the default prices of its branches (plus those for every branch,
            // i.e. the old price list) and the special prices of its customers
            $hasDefault = collect($data['rates'])->contains(fn ($rate) => $rate['customer_code'] === null);
            $customerCodes = collect($data['rates'])->pluck('customer_code')->filter()->unique()->values()->all();
            $scope = fn ($query) => $query->where(fn ($q) => $q->whereNull('branch_id')->when($branchIds !== [], fn ($q) => $q->orWhereIn('branch_id', $branchIds)));

            if ($hasDefault) {
                UomRateTier::query()->whereNull('customer_code')->where($scope)->delete();
            }

            // a customer's price agreed in an accepted quotation stays: the file does not replace it
            $quoted = [];

            if ($customerCodes !== []) {
                $fromQuotation = fn ($query) => $query->where('source', 'like', 'Quotation %');

                foreach (UomRateTier::query()->whereIn('customer_code', $customerCodes)->where($fromQuotation)->get(['uom_id', 'location_id', 'customer_code']) as $row) {
                    $quoted[$row->uom_id.'|'.$row->location_id.'|'.strtoupper((string) $row->customer_code)] = true;
                }

                UomRateTier::query()->whereIn('customer_code', $customerCodes)->where($scope)
                    ->where(fn ($q) => $q->whereNull('source')->orWhereNot($fromQuotation))
                    ->delete();
            }

            $defaultRates = 0;
            $specialRates = [];
            $now = now();
            $tierRows = [];

            foreach ($data['rates'] as $rate) {
                if ($rate['customer_code'] !== null && isset($quoted[$uomIds[$rate['product']].'|'.$locations[$rate['location']]['id'].'|'.strtoupper($rate['customer_code'])])) {
                    continue;
                }

                $common = [
                    'location_id' => $locations[$rate['location']]['id'],
                    'customer_code' => $rate['customer_code'],
                    'branch_id' => $branches[$rate['branch']] ?? null,
                    'drop_off_type' => $rate['drop_off_type'],
                    'pickup_location' => $rate['pickup_location'],
                    'effective_from' => $rate['effective_from'],
                    'expires_on' => $rate['expires_on'],
                    'source' => Str::limit($rate['source'], 150, ''),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                foreach ($rate['tiers'] as $tier) {
                    $tierRows[] = $common + [
                        'uom_id' => $uomIds[$rate['product']],
                        'min_qty' => $tier['min'],
                        'max_qty' => $tier['max'],
                        'price' => $tier['price'],
                        'pickup_min_charge' => $rate['pickup_min_charge'],
                        'construction_min_charge' => $rate['construction_min_charge'],
                        'drop_off_min_charge' => $rate['drop_off_min_charge'],
                    ];
                }

                if ($rate['customer_code'] === null) {
                    $defaultRates++;
                } else {
                    $specialRates[$rate['customer_code']] = ($specialRates[$rate['customer_code']] ?? 0) + 1;
                }
            }

            foreach (array_chunk($tierRows, 500) as $chunk) {
                UomRateTier::query()->insert($chunk);
            }

            // the old price list: products left without any price are no longer offered
            $deactivated = $hasDefault
                ? Uom::query()->where('is_active', true)->whereNotIn('id', array_values($uomIds))->whereDoesntHave('rateTiers')->update(['is_active' => false])
                : 0;

            $known = Customer::query()->whereIn('code', $customerCodes)->pluck('code')->map(fn ($code) => strtoupper((string) $code))->all();
            $unknown = array_values(array_filter($customerCodes, fn ($code) => ! in_array(strtoupper($code), $known, true)));

            return [
                'products' => count($uomIds),
                'products_new' => $productsNew,
                'lorries' => collect($data['products'])->where('category', Uom::LORRY)->count(),
                'locations_new' => $locationsNew,
                'default_rates' => $defaultRates,
                'special_rates' => $specialRates,
                'unknown_customers' => array_map(fn ($code) => $code.' '.($data['customers'][$code] ?? ''), $unknown),
                'deactivated' => $deactivated,
                'warnings' => $warnings,
            ];
        });
    }

    /** The bundled copies of the client's workbooks (database/data), default price first. */
    public const BUNDLED = ['client-price-list-default.xlsx', 'client-price-list-special.xlsx'];

    /** @return array<string, mixed> the import() report */
    public function importFile(string $path, ?string $fileName = null): array
    {
        return $this->import($this->parse($path, $fileName));
    }

    /**
     * The report as short lines for a notification / the console.
     *
     * @param  array<string, mixed>  $report
     * @return list<string>
     */
    public static function summaryLines(array $report): array
    {
        $special = $report['special_rates'] ?? [];
        $lines = [
            sprintf('%d product(s) (%d new), %d lorry type(s)', $report['products'], $report['products_new'], $report['lorries']),
            sprintf('%d default price row(s), %d special price row(s) for %d customer(s)', $report['default_rates'], array_sum($special), count($special)),
        ];

        if ($report['locations_new'] !== []) {
            $lines[] = count($report['locations_new']).' new destination(s): '.implode(', ', array_slice($report['locations_new'], 0, 12)).(count($report['locations_new']) > 12 ? '…' : '');
        }

        if ($report['deactivated'] > 0) {
            $lines[] = $report['deactivated'].' old price-list product(s) no longer offered';
        }

        if ($report['unknown_customers'] !== []) {
            $lines[] = 'Not a customer yet (their special prices apply once a customer has this code): '.implode('; ', $report['unknown_customers']);
        }

        foreach ($report['warnings'] as $warning) {
            $lines[] = 'Note: '.$warning;
        }

        return $lines;
    }

    /** @param  array<string, mixed>  $result */
    private function parseRow(array $row, string $sheetName, string $where, string $fileName, array &$result): void
    {
        $item = $this->text($row['item']);
        $location = $this->text($row['drop_off_location'] ?? null);

        if ($location === '') {
            $result['warnings'][] = "{$where}: {$item} has no Drop-Off Location · skipped";

            return;
        }

        // the customer: a code is a special price; "Default Price" / blank is the default price
        $customerCode = strtoupper($this->text($row['customer_code'] ?? null));
        $customerName = $this->text($row['customer_name'] ?? null);

        if ($customerCode === '' && $customerName !== '' && strcasecmp($customerName, 'Default Price') !== 0) {
            $result['warnings'][] = "{$where}: customer \"{$customerName}\" has no Customer Code · skipped";

            return;
        }

        if ($customerCode !== '') {
            $result['customers'][$customerCode] = $customerName;
        }

        $category = strtolower($this->text($row['category'] ?? null));
        $kind = str_contains($category, 'lorry') || (! array_key_exists('category', $row) && str_contains(strtolower($sheetName), 'lorry'))
            ? 'lorry'
            : ($category === 'item' ? 'item' : 'measurement');

        $type = DropOffType::fromLabel($row['drop_off_type'] ?? null);

        if (! $type) {
            $result['warnings'][] = "{$where}: Drop-Off Type \"{$row['drop_off_type']}\" is not a known type · priced as Default";
            $type = DropOffType::Default;
        }

        [$tiers, $perKg] = $this->tiers($row, $where, $result['warnings']);

        if ($tiers === []) {
            $result['warnings'][] = "{$where}: {$item} to {$location} has no price · skipped";

            return;
        }

        $bands = $this->bands($row);

        if ($kind === 'lorry') {
            // a chartered lorry is a UOM product of the Chartered Lorry category, named as the sheet names it
            $name = $item;
            $key = strtolower($name);
            $result['products'][$key] ??= [
                'name' => $name,
                'category' => Uom::LORRY,
                'sub_category' => 'Chartered Lorry',
                'base_item' => $item,
            ] + $bands;
        } else {
            // "Weight * 0.14": priced per kg, the quantity is the weight in kg
            $name = $this->productName($item, $bands).($perKg ? ' (per kg)' : '');
            $key = strtolower($name);
            $result['products'][$key] ??= [
                'name' => $name,
                'category' => $kind,
                'sub_category' => $this->text($row['sub_category'] ?? null) ?: null,
                'base_item' => $item,
            ] + $bands;
        }

        $branch = $this->text($row['branch'] ?? null);
        $result['branches'][] = $branch;
        $result['locations'][] = $location;

        $rate = [
            'kind' => $kind,
            'product' => $key,
            'location' => $location,
            'customer_code' => $customerCode !== '' ? $customerCode : null,
            'branch' => $branch,
            'drop_off_type' => $type->value,
            'pickup_location' => $this->text($row['pickup_location'] ?? null) ?: null,
            'pickup_min_charge' => $this->number($row['pickup_min_charge'] ?? null),
            'construction_min_charge' => $this->number($row['construction_min_charge'] ?? null),
            'drop_off_min_charge' => $this->number($row['drop_off_min_charge'] ?? null),
            'effective_from' => $this->date($row['effective_from'] ?? null),
            'expires_on' => $this->date($row['expires_on'] ?? null),
            'tiers' => $tiers,
            'source' => "{$fileName} · {$where}",
        ];

        // the same product, route, type and customer twice: the later row wins
        $identity = implode('|', [$kind, $key, strtolower($location), $rate['customer_code'], strtolower($branch), $rate['drop_off_type']]);

        foreach ($result['rates'] as $index => $existing) {
            if ($existing['identity'] === $identity) {
                $result['warnings'][] = "{$where}: same item, location, type and customer as ".Str::after($existing['source'], ' · ').' · this row is used';
                unset($result['rates'][$index]);
                $result['rates'] = array_values($result['rates']);

                break;
            }
        }

        $result['rates'][] = $rate + ['identity' => $identity];
    }

    /**
     * Quantity ranges A–E of a row (a price without a "from" starts at 1 for range A). A price written
     * "Weight * 0.14" is RM 0.14 per kg: one range from 1 (kg) up.
     *
     * @param  list<string>  $warnings
     * @return array{0: list<array{min: float, max: ?float, price: float}>, 1: bool}
     */
    private function tiers(array $row, string $where, array &$warnings): array
    {
        if (preg_match('/^\s*weight\s*[*x×]\s*([\d.]+)\s*$/iu', (string) ($row['price_A'] ?? ''), $m)) {
            foreach (array_slice(self::RANGES, 1) as $range) {
                if (filled($row['price_'.$range] ?? null)) {
                    $warnings[] = "{$where}: priced per kg (Weight * {$m[1]}) · range {$range} ignored";
                }
            }

            return [[['min' => 1.0, 'max' => null, 'price' => round((float) $m[1], 2)]], true];
        }

        $tiers = [];

        foreach (self::RANGES as $range) {
            $price = $this->number($row['price_'.$range] ?? null);

            if ($price === null) {
                continue;
            }

            $min = $this->number($row['from_'.$range] ?? null) ?? ($range === 'A' ? 1.0 : null);
            $max = $this->number($row['to_'.$range] ?? null);

            if ($min === null || ($max !== null && $max < $min)) {
                $warnings[] = "{$where}: quantity range {$range} is not valid · skipped";

                continue;
            }

            $tiers[] = ['min' => $min, 'max' => $max, 'price' => $price];
        }

        usort($tiers, fn ($a, $b) => $a['min'] <=> $b['min']);

        for ($i = 1; $i < count($tiers); $i++) {
            $previousMax = $tiers[$i - 1]['max'];

            if ($previousMax === null || $previousMax >= $tiers[$i]['min']) {
                $warnings[] = "{$where}: quantity ranges overlap (from {$this->format($tiers[$i]['min'])})";
            }
        }

        return [$tiers, false];
    }

    /** @return array<string, ?float> */
    private function bands(array $row): array
    {
        $bands = [];

        foreach (self::BANDS as $field) {
            $bands[$field] = $this->number($row[$field] ?? null);
        }

        // a blank minimum next to a maximum is 0 (the same band as one written 0–30)
        foreach (['lwh', 'length', 'weight'] as $dimension) {
            if ($bands['min_'.$dimension] === null && $bands['max_'.$dimension] !== null) {
                $bands['min_'.$dimension] = 0.0;
            }
        }

        return $bands;
    }

    /** "Carton · L+W+H ≤ 149 cm · L ≤ 60 cm · up to 30 kg" (also the UOM form's name for a product left unnamed) */
    public function productName(string $item, array $bands): string
    {
        $parts = [$item];
        $range = function (?float $min, ?float $max, string $prefix, string $unit): ?string {
            if ($max === null) {
                return $min !== null && $min > 0 ? "{$prefix}≥ {$this->format($min)} {$unit}" : null;
            }

            return $min !== null && $min > 0
                ? "{$prefix}{$this->format($min)}–{$this->format($max)} {$unit}"
                : "{$prefix}≤ {$this->format($max)} {$unit}";
        };

        $parts[] = $range($bands['min_lwh'], $bands['max_lwh'], 'L+W+H ', 'cm');
        $parts[] = $range($bands['min_length'], $bands['max_length'], 'L ', 'cm');

        $width = $bands['max_width'];
        $height = $bands['max_height'];

        if ($width !== null && $width === $height) {
            $parts[] = "W, H ≤ {$this->format($width)} cm";
        } else {
            $parts[] = $width !== null ? "W ≤ {$this->format($width)} cm" : null;
            $parts[] = $height !== null ? "H ≤ {$this->format($height)} cm" : null;
        }

        $min = $bands['min_weight'];
        $max = $bands['max_weight'];
        $parts[] = match (true) {
            $max !== null && ($min === null || $min <= 0) => "up to {$this->format($max)} kg",
            $max !== null => "{$this->format($min)}–{$this->format($max)} kg",
            $min !== null && $min > 0 => "from {$this->format($min)} kg",
            default => null,
        };

        return implode(' · ', array_filter($parts));
    }

    /**
     * The header row (its "Record No." title) and the column letter of every field.
     *
     * @param  array<int, array<string, ?string>>  $rows
     * @return array{0: int, 1: array<string, string>}|null
     */
    private function headerColumns(array $rows): ?array
    {
        foreach ($rows as $rowNumber => $cells) {
            if ($rowNumber > 20) {
                break;
            }

            if (strtolower(preg_replace('/[^a-z0-9]/i', '', (string) ($cells['A'] ?? ''))) !== 'recordno') {
                continue;
            }

            $map = [];

            foreach ($cells as $column => $title) {
                $key = strtolower(preg_replace('/[^a-z0-9]/i', '', (string) $title));

                if (isset(self::COLUMNS[$key])) {
                    $map[self::COLUMNS[$key]] ??= $column;
                } elseif (preg_match('/^quantity(from|to)([a-e])$/', $key, $m)) {
                    $map[$m[1].'_'.strtoupper($m[2])] = $column;
                } elseif (preg_match('/^priceforthisrangerm([a-e])$/', $key, $m)) {
                    $map['price_'.strtoupper($m[1])] = $column;
                }
            }

            return isset($map['item'], $map['drop_off_location'], $map['price_A']) ? [$rowNumber, $map] : null;
        }

        return null;
    }

    /** @return array<string, ?string> */
    private function rowValues(array $cells, array $map): array
    {
        $row = [];

        foreach ($map as $field => $column) {
            $value = $cells[$column] ?? null;
            $row[$field] = $value !== null && trim($value) !== '' ? $value : null;
        }

        return $row;
    }

    /**
     * Branch names of the file ("O & G KL") → branch id (null = not found: the prices are for every branch).
     *
     * @param  list<string>  $names
     * @param  list<string>  $warnings
     * @return array<string, ?int>
     */
    private function resolveBranches(array $names, array &$warnings): array
    {
        $branches = Branch::query()->get(['id', 'code', 'name']);
        $resolved = [];

        foreach ($names as $name) {
            $short = trim(preg_replace('/^o\s*&\s*g\b/i', '', $name));
            $branch = $branches->first(fn (Branch $b) => strcasecmp((string) $b->code, $short) === 0
                || strcasecmp((string) $b->name, $name) === 0
                || strcasecmp((string) $b->name, $short.' Branch') === 0);

            if (! $branch && $name !== '') {
                $warnings[] = "Company \"{$name}\" is not a company in the system · its prices apply to every company";
            }

            $resolved[$name] = $branch?->id;
        }

        return $resolved;
    }

    /**
     * Destination names → location (created as a delivery location when missing).
     *
     * @param  list<string>  $names
     * @return array<string, array{id: int, new: bool}>
     */
    private function resolveLocations(array $names): array
    {
        $existing = Location::query()->get(['id', 'name', 'code']);
        $codes = $existing->pluck('code')->filter()->map(fn ($code) => strtoupper((string) $code))->flip()->all();
        $resolved = [];

        foreach ($names as $name) {
            $location = $existing->first(fn (Location $l) => strcasecmp(trim($l->name), $name) === 0);
            $new = false;

            if (! $location) {
                $code = $this->uniqueCode($name, $codes, 20);
                $codes[$code] = true;
                $location = Location::query()->create([
                    'code' => $code,
                    'name' => $name,
                    'type' => 'delivery',
                    'is_active' => true,
                ]);
                $existing->push($location);
                $new = true;
            }

            $resolved[$name] = ['id' => $location->id, 'new' => $new];
        }

        return $resolved;
    }

    /** @param  array<string, bool>  $used */
    private function uniqueCode(string $name, array $used, int $length = 60): string
    {
        $base = rtrim(Str::upper(Str::limit(Str::slug(str_replace(['+', '≤', '≥'], [' ', 'max ', 'min '], $name), '_'), $length - 3, '')), '_') ?: 'UOM';
        $code = $base;
        $n = 2;

        while (isset($used[$code])) {
            $code = $base.'_'.$n++;
        }

        return $code;
    }

    private function text(mixed $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', (string) $value));
    }

    private function number(mixed $value): ?float
    {
        $value = str_replace([',', 'RM', ' '], '', (string) $value);

        return is_numeric($value) ? round((float) $value, 2) : null;
    }

    /** An Excel date serial (46143 = 1 May 2026) or a written date → Y-m-d. */
    private function date(mixed $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return Carbon::create(1899, 12, 30)->addDays((int) $value)->toDateString();
        }

        foreach (['d/m/Y', 'd-m-Y', 'Y-m-d', 'd/m/y'] as $format) {
            try {
                return Carbon::createFromFormat($format, $value)->toDateString();
            } catch (\Throwable) {
            }
        }

        return null;
    }

    private function format(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}
