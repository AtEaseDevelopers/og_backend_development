<?php

namespace App\Support;

use App\Domains\MasterData\Models\CharteredLorry;
use App\Domains\MasterData\Models\Item;
use App\Domains\MasterData\Models\Location;
use App\Domains\MasterData\Models\Uom;
use App\Domains\MasterData\Models\UomRateTier;
use App\Domains\Quotation\Models\Quotation;

class QuotationPricingLookup
{
    public function lookupForCustomer(?int $customerId, string $itemName, string $locationName, float $quantity = 1): array
    {
        if (Uom::query()->where('name', $itemName)->exists()) {
            $masterPrice = $this->lookup($itemName, $locationName, $quantity);

            return [
                'price' => $masterPrice,
                'source' => $masterPrice !== null ? 'master' : null,
            ];
        }

        $history = app(CustomerQuotationPriceHistory::class);

        $specialPrice = $history->resolveSpecialPrice($customerId, $itemName, $locationName);

        if ($specialPrice !== null) {
            return ['price' => $specialPrice, 'source' => 'special'];
        }

        $previousPrice = $history->resolvePreviousPrice($customerId, $itemName, $locationName);

        if ($previousPrice !== null) {
            return ['price' => $previousPrice, 'source' => 'previous'];
        }

        $masterPrice = $this->lookup($itemName, $locationName, $quantity);

        return [
            'price' => $masterPrice,
            'source' => $masterPrice !== null ? 'master' : null,
        ];
    }

    public function lookup(string $itemName, string $locationName, float $quantity = 1): ?float
    {
        $locationId = Location::query()->where('name', $locationName)->value('id');

        if (! $locationId) {
            return null;
        }

        $item = Item::query()->where('name', $itemName)->first();

        if ($item) {
            $price = $item->rates()->where('location_id', $locationId)->value('price');

            return $price !== null ? (float) $price : null;
        }

        $chartered = CharteredLorry::query()->where('name', $itemName)->first();

        if ($chartered) {
            $price = $chartered->rates()->where('location_id', $locationId)->value('price');

            return $price !== null ? (float) $price : null;
        }

        $uom = Uom::query()->where('name', $itemName)->first();

        if ($uom) {
            $tier = UomRateTier::query()
                ->where('uom_id', $uom->id)
                ->where('location_id', $locationId)
                ->where('min_qty', '<=', $quantity)
                ->where(function ($query) use ($quantity) {
                    $query->whereNull('max_qty')->orWhere('max_qty', '>=', $quantity);
                })
                ->orderByDesc('min_qty')
                ->first();

            return $tier ? (float) $tier->price : null;
        }

        return null;
    }

    /** @return array<string, string> */
    public function catalogOptions(): array
    {
        $options = [];

        Item::query()->orderBy('name')->each(function (Item $item) use (&$options) {
            $options['item:'.$item->id] = '[Item] '.$item->name;
        });

        Uom::query()->orderBy('name')->each(function (Uom $uom) use (&$options) {
            $options['uom:'.$uom->id] = '[UOM] '.$uom->name;
        });

        CharteredLorry::query()->orderBy('name')->each(function (CharteredLorry $lorry) use (&$options) {
            $options['chartered:'.$lorry->id] = '[Lorry] '.$lorry->name;
        });

        return $options;
    }

    /** @return array<string, string> */
    public function catalogOptionsForType(string $type): array
    {
        return match ($type) {
            'lorry' => CharteredLorry::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get()
                ->mapWithKeys(fn (CharteredLorry $lorry) => [
                    'chartered:'.$lorry->id => $lorry->name,
                ])
                ->all(),
            'uom' => Uom::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get()
                ->mapWithKeys(fn (Uom $uom) => [
                    'uom:'.$uom->id => $uom->name,
                ])
                ->all(),
            default => Item::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get()
                ->mapWithKeys(fn (Item $item) => [
                    'item:'.$item->id => $item->name,
                ])
                ->all(),
        };
    }

    public function inferLineType(?string $catalogKey, ?string $itemName): string
    {
        if ($catalogKey) {
            return match (explode(':', $catalogKey, 2)[0] ?? '') {
                'chartered' => 'lorry',
                'uom' => 'uom',
                default => 'item',
            };
        }

        if ($itemName && CharteredLorry::query()->where('name', $itemName)->exists()) {
            return 'lorry';
        }

        if ($itemName && Uom::query()->where('name', $itemName)->exists()) {
            return 'uom';
        }

        return 'item';
    }

    public function resolveCatalogKey(?string $itemName): ?string
    {
        if (! $itemName) {
            return null;
        }

        $chartered = CharteredLorry::query()->where('name', $itemName)->first();

        if ($chartered) {
            return 'chartered:'.$chartered->id;
        }

        $uom = Uom::query()->where('name', $itemName)->first();

        if ($uom) {
            return 'uom:'.$uom->id;
        }

        $item = Item::query()->where('name', $itemName)->first();

        if ($item) {
            return 'item:'.$item->id;
        }

        return null;
    }

    public function resolveCatalogName(?string $catalogKey): ?string
    {
        if (! $catalogKey) {
            return null;
        }

        [$type, $id] = array_pad(explode(':', $catalogKey, 2), 2, null);

        return match ($type) {
            'item' => Item::query()->find($id)?->name,
            'uom' => Uom::query()->find($id)?->name,
            'chartered' => CharteredLorry::query()->find($id)?->name,
            default => null,
        };
    }

    public function resolveUomCode(?string $catalogKey, ?string $itemName = null): ?string
    {
        if ($catalogKey && str_starts_with($catalogKey, 'uom:')) {
            return Uom::query()->find(explode(':', $catalogKey, 2)[1])?->code;
        }

        if ($itemName) {
            return Uom::query()->where('name', $itemName)->value('code');
        }

        return null;
    }

    public function matchedUomTier(string $itemName, string $locationName, float $quantity): ?UomRateTier
    {
        $locationId = Location::query()->where('name', $locationName)->value('id');
        $uom = Uom::query()->where('name', $itemName)->first();

        if (! $locationId || ! $uom) {
            return null;
        }

        return UomRateTier::query()
            ->where('uom_id', $uom->id)
            ->where('location_id', $locationId)
            ->where('min_qty', '<=', $quantity)
            ->where(function ($query) use ($quantity) {
                $query->whereNull('max_qty')->orWhere('max_qty', '>=', $quantity);
            })
            ->orderByDesc('min_qty')
            ->first();
    }

    public function formatUomTier(UomRateTier $tier, float $quantity): string
    {
        $range = $tier->max_qty
            ? number_format((float) $tier->min_qty, 0).'–'.number_format((float) $tier->max_qty, 0)
            : number_format((float) $tier->min_qty, 0).'+';

        return "Qty {$range} @ RM ".number_format((float) $tier->price, 2).' each (entered: '.number_format($quantity, 0).')';
    }

    /** @return list<array{range: string, price: float}> */
    public function uomTierBreakdown(string $itemName, string $locationName): array
    {
        $locationId = Location::query()->where('name', $locationName)->value('id');
        $uom = Uom::query()->where('name', $itemName)->first();

        if (! $locationId || ! $uom) {
            return [];
        }

        return UomRateTier::query()
            ->where('uom_id', $uom->id)
            ->where('location_id', $locationId)
            ->orderBy('min_qty')
            ->get()
            ->map(fn (UomRateTier $tier) => [
                'range' => $tier->max_qty
                    ? number_format((float) $tier->min_qty, 0).'–'.number_format((float) $tier->max_qty, 0)
                    : number_format((float) $tier->min_qty, 0).'+',
                'price' => (float) $tier->price,
            ])
            ->all();
    }

    /**
     * @param  list<string>  $destinations
     * @return array{quantity: float, destinations: list<array{name: string, unit_price: ?float, active_range: ?string, line_total: ?float, tiers: list<array{range: string, price: float, active: bool}>}>}
     */
    public function uomTierSummary(string $itemName, array $destinations, float $quantity): array
    {
        $quantity = max(0.01, $quantity);

        return [
            'quantity' => $quantity,
            'destinations' => collect($destinations)
                ->filter()
                ->values()
                ->map(function (string $destination) use ($itemName, $quantity) {
                    $matched = $this->matchedUomTier($itemName, $destination, $quantity);
                    $locationId = Location::query()->where('name', $destination)->value('id');
                    $uom = Uom::query()->where('name', $itemName)->first();

                    $tiers = [];

                    if ($locationId && $uom) {
                        $tiers = UomRateTier::query()
                            ->where('uom_id', $uom->id)
                            ->where('location_id', $locationId)
                            ->orderBy('min_qty')
                            ->get()
                            ->map(fn (UomRateTier $tier) => [
                                'range' => $tier->max_qty
                                    ? number_format((float) $tier->min_qty, 0).'–'.number_format((float) $tier->max_qty, 0)
                                    : number_format((float) $tier->min_qty, 0).'+',
                                'price' => (float) $tier->price,
                                'active' => $matched?->id === $tier->id,
                            ])
                            ->all();
                    }

                    $unitPrice = $matched ? (float) $matched->price : null;
                    $activeRange = collect($tiers)->firstWhere('active', true)['range'] ?? null;

                    return [
                        'name' => $destination,
                        'unit_price' => $unitPrice,
                        'active_range' => $activeRange,
                        'line_total' => $unitPrice !== null ? round($quantity * $unitPrice, 2) : null,
                        'tiers' => $tiers,
                    ];
                })
                ->all(),
        ];
    }
}
