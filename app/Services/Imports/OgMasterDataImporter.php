<?php

namespace App\Services\Imports;

use App\Domains\MasterData\Models\CharteredLorry;
use App\Domains\MasterData\Models\CharteredLorryRate;
use App\Domains\MasterData\Models\Item;
use App\Domains\MasterData\Models\ItemCategory;
use App\Domains\MasterData\Models\ItemRate;
use App\Domains\MasterData\Models\Location;
use App\Domains\MasterData\Models\Uom;
use App\Domains\MasterData\Models\UomRateTier;
use Illuminate\Support\Str;

class OgMasterDataImporter
{
    public function __construct(
        private readonly SimpleXlsxReader $reader = new SimpleXlsxReader,
    ) {}

    /** @return array{transport_items: int, chartered_lorries: int, uoms: int, locations: int, item_rates: int, chartered_lorry_rates: int, uom_rate_tiers: int} */
    public function importFromXlsx(string $path): array
    {
        $sheets = $this->reader->readSheets($path);

        return $this->import([
            'items' => $this->mapItemRows($sheets['Items'] ?? []),
            'lorries' => $this->mapItemRows($sheets['Lorries'] ?? []),
            'uoms' => $this->mapUomRows($sheets['UOM'] ?? []),
        ]);
    }

    /**
     * @param  array{items: list<array{name: string, location: string, price: string|null}>, lorries: list<array{name: string, location: string, price: string|null}>, uoms: list<array{name: string, min_qty: string|null, max_qty: string|null, location: string, price: string|null}>}  $data
     * @return array{transport_items: int, chartered_lorries: int, uoms: int, locations: int, item_rates: int, chartered_lorry_rates: int, uom_rate_tiers: int}
     */
    public function import(array $data): array
    {
        $transportCategory = ItemCategory::query()->firstOrCreate(
            ['name' => 'Transport Items'],
            ['is_active' => true],
        );

        $counts = [
            'transport_items' => 0,
            'chartered_lorries' => 0,
            'uoms' => 0,
            'locations' => count($this->uniqueLocations($data)),
            'item_rates' => 0,
            'chartered_lorry_rates' => 0,
            'uom_rate_tiers' => 0,
        ];

        $locationIds = [];

        foreach ($this->uniqueLocations($data) as $locationName) {
            $location = Location::query()->updateOrCreate(
                ['name' => $locationName],
                [
                    'code' => $this->locationCode($locationName),
                    'type' => 'delivery',
                    'is_active' => true,
                ],
            );

            $locationIds[$locationName] = $location->id;
        }

        $transportItemNames = [];
        $charteredLorryNames = [];

        foreach ($data['items'] as $row) {
            $item = $this->upsertItem($row['name'], $transportCategory->id);
            $transportItemNames[$item->name] = true;

            ItemRate::query()->updateOrCreate(
                [
                    'item_id' => $item->id,
                    'location_id' => $locationIds[$row['location']],
                ],
                ['price' => $this->parsePrice($row['price'])],
            );
            $counts['item_rates']++;
        }

        foreach ($data['lorries'] as $row) {
            $charteredLorry = $this->upsertCharteredLorry($row['name']);
            $charteredLorryNames[$charteredLorry->name] = true;

            CharteredLorryRate::query()->updateOrCreate(
                [
                    'chartered_lorry_id' => $charteredLorry->id,
                    'location_id' => $locationIds[$row['location']],
                ],
                ['price' => $this->parsePrice($row['price'])],
            );
            $counts['chartered_lorry_rates']++;
        }

        $counts['transport_items'] = count($transportItemNames);
        $counts['chartered_lorries'] = count($charteredLorryNames);

        $importedUomIds = [];

        foreach ($data['uoms'] as $row) {
            $uom = Uom::query()->updateOrCreate(
                ['code' => $this->uomCode($row['name'])],
                [
                    'name' => $row['name'],
                    'is_active' => true,
                ],
            );

            $importedUomIds[$uom->id] = true;

            UomRateTier::query()->updateOrCreate(
                [
                    'uom_id' => $uom->id,
                    'location_id' => $locationIds[$row['location']],
                    'min_qty' => $this->parseQuantity($row['min_qty']),
                ],
                [
                    'max_qty' => $row['max_qty'] !== null && $row['max_qty'] !== ''
                        ? $this->parseQuantity($row['max_qty'])
                        : null,
                    'price' => $this->parsePrice($row['price']),
                ],
            );
            $counts['uom_rate_tiers']++;
        }

        $counts['uoms'] = count($importedUomIds);

        return $counts;
    }

    /** @param  list<array<string, string|null>>  $rows */
    private function mapItemRows(array $rows): array
    {
        $mapped = [];

        foreach (array_slice($rows, 1) as $row) {
            $name = trim((string) ($row['A'] ?? ''));

            if ($name === '') {
                continue;
            }

            $mapped[] = [
                'name' => $name,
                'location' => $this->normalizeLocation((string) ($row['B'] ?? '')),
                'price' => $row['C'] ?? null,
            ];
        }

        return $mapped;
    }

    /** @param  list<array<string, string|null>>  $rows */
    private function mapUomRows(array $rows): array
    {
        $mapped = [];

        foreach (array_slice($rows, 1) as $row) {
            $name = trim((string) ($row['A'] ?? ''));

            if ($name === '') {
                continue;
            }

            $mapped[] = [
                'name' => $name,
                'min_qty' => $row['B'] ?? '1',
                'max_qty' => $row['C'] ?? null,
                'location' => $this->normalizeLocation((string) ($row['D'] ?? '')),
                'price' => $row['E'] ?? null,
            ];
        }

        return $mapped;
    }

    /** @param  array{items: list<array{location: string}>, lorries: list<array{location: string}>, uoms: list<array{location: string}>}  $data */
    private function uniqueLocations(array $data): array
    {
        return collect($data['items'])
            ->pluck('location')
            ->merge(collect($data['lorries'])->pluck('location'))
            ->merge(collect($data['uoms'])->pluck('location'))
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    private function upsertCharteredLorry(string $name): CharteredLorry
    {
        return CharteredLorry::query()->updateOrCreate(
            ['name' => $name],
            [
                'code' => $this->itemCode($name),
                'is_active' => true,
            ],
        );
    }

    private function upsertItem(string $name, int $categoryId): Item
    {
        $code = $this->itemCode($name);

        return Item::query()->updateOrCreate(
            ['name' => $name],
            [
                'item_category_id' => $categoryId,
                'code' => $code,
                'is_active' => true,
            ],
        );
    }

    private function normalizeLocation(string $location): string
    {
        return trim(preg_replace('/\s+/', ' ', $location) ?? $location);
    }

    private function locationCode(string $location): string
    {
        return Str::upper(Str::slug($this->normalizeLocation($location), '_'));
    }

    private function itemCode(string $name): string
    {
        $slug = Str::upper(Str::slug($name, '-'));

        return Str::limit($slug, 50, '');
    }

    private function uomCode(string $name): string
    {
        $slug = Str::upper(Str::slug($name, '-'));

        return Str::limit($slug, 50, '');
    }

    private function parsePrice(?string $value): float
    {
        if ($value === null || trim($value) === '') {
            return 0;
        }

        $normalized = preg_replace('/[^0-9.]/', '', str_replace(',', '', $value));

        return round((float) $normalized, 2);
    }

    private function parseQuantity(?string $value): float
    {
        if ($value === null || trim($value) === '') {
            return 1;
        }

        return round((float) $value, 2);
    }
}
