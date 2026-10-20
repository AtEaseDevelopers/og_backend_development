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
    private PriceListRates $rates;

    /** (created with "new" by older code too: the price list rates come from the container then) */
    public function __construct(?PriceListRates $rates = null)
    {
        $this->rates = $rates ?? app(PriceListRates::class);
    }

    /**
     * The price of a product for the customer (user, 8 and 19 Oct 2026): the customer's special price when it has
     * one for the product and destination, otherwise the default price; neither = null (keyed in by hand).
     * A price for the drop-off type comes before the Default type's.
     *
     * @return array{price: ?float, source: ?string}  source special | master
     */
    public function lookupForCustomer(?int $customerId, string $itemName, string $locationName, float $quantity = 1, ?string $dropOffType = null): array
    {
        $locationId = $this->locationId($locationName);
        $uom = Uom::query()->where('name', $itemName)->first();

        if ($uom) {
            if (! $locationId) {
                return ['price' => null, 'source' => null];
            }

            $set = $this->rates->uomTiers($uom, $locationId, $customerId, $dropOffType);
            $tier = $this->rates->tierFor($set['tiers'], $quantity);

            return $tier
                ? ['price' => (float) $tier->price, 'source' => $set['special'] ? 'special' : 'master']
                : ['price' => null, 'source' => null];
        }

        // a transport item: its customer special price, else its default rate
        $specialPrice = app(CustomerQuotationPriceHistory::class)->resolveSpecialPrice($customerId, $itemName, $locationName);

        if ($specialPrice !== null) {
            return ['price' => $specialPrice, 'source' => 'special'];
        }

        $masterPrice = $this->lookup($itemName, $locationName, $quantity, $dropOffType);

        return [
            'price' => $masterPrice,
            'source' => $masterPrice !== null ? 'master' : null,
        ];
    }

    /** The default price (no customer). */
    public function lookup(string $itemName, string $locationName, float $quantity = 1, ?string $dropOffType = null): ?float
    {
        $locationId = $this->locationId($locationName);

        if (! $locationId) {
            return null;
        }

        $item = Item::query()->where('name', $itemName)->first();

        if ($item) {
            $price = $item->rates()->where('location_id', $locationId)->value('price');

            return $price !== null ? (float) $price : null;
        }

        $uom = Uom::query()->where('name', $itemName)->first();

        if ($uom) {
            $tier = $this->rates->uomTier($uom, $locationId, $quantity, null, $dropOffType);

            return $tier ? (float) $tier->price : null;
        }

        return null;
    }

    /**
     * The rate tier behind a UOM product's price (the customer's special set, else the default set), with its
     * minimum charges.
     */
    public function rateTierFor(?int $customerId, string $itemName, string $locationName, float $quantity, ?string $dropOffType = null): ?UomRateTier
    {
        $locationId = $this->locationId($locationName);
        $uom = $locationId ? Uom::query()->where('name', $itemName)->first() : null;

        return $uom ? $this->rates->uomTier($uom, $locationId, $quantity, $customerId, $dropOffType) : null;
    }

    private function locationId(string $locationName): ?int
    {
        $id = Location::query()->where('name', $locationName)->value('id');

        return $id ? (int) $id : null;
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

        return $options;
    }

    /**
     * Products to pick on the order form. A product priced only for other customers (their special price list) is
     * left out; one with a default price, a special price of this customer or no price at all is offered.
     *
     * @return array<string, string>
     */
    public function catalogOptionsForType(string $type, ?int $customerId = null): array
    {
        $code = $this->rates->customerCode($customerId);
        $offered = fn ($query) => $query->where(fn ($q) => $q->whereNull('customer_code')->when($code, fn ($q) => $q->orWhere('customer_code', $code)));

        // chartered lorries are UOM products of the Chartered Lorry category (user, 21 Oct 2026)
        return match ($type) {
            'lorry' => Uom::query()
                ->where('is_active', true)
                ->where('category', Uom::LORRY)
                ->where(fn ($q) => $q->whereDoesntHave('rateTiers')->orWhereHas('rateTiers', $offered))
                ->orderBy('name')
                ->get()
                ->mapWithKeys(fn (Uom $uom) => [
                    'uom:'.$uom->id => $uom->name,
                ])
                ->all(),
            'uom' => Uom::query()
                ->where('is_active', true)
                ->where(fn ($q) => $q->whereNull('category')->orWhere('category', '!=', Uom::LORRY))
                ->where(fn ($q) => $q->whereDoesntHave('rateTiers')->orWhereHas('rateTiers', $offered))
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
            // an older order's chartered lorry (before lorries were UOM products)
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

    public function matchedUomTier(string $itemName, string $locationName, float $quantity, ?int $customerId = null, ?string $dropOffType = null): ?UomRateTier
    {
        return $this->rateTierFor($customerId, $itemName, $locationName, $quantity, $dropOffType);
    }

    public function formatUomTier(UomRateTier $tier, float $quantity): string
    {
        $range = $tier->max_qty
            ? number_format((float) $tier->min_qty, 0).'–'.number_format((float) $tier->max_qty, 0)
            : number_format((float) $tier->min_qty, 0).'+';

        return "Qty {$range} @ RM ".number_format((float) $tier->price, 2).' each (entered: '.number_format($quantity, 0).')';
    }

    /** @return list<array{range: string, price: float}> */
    public function uomTierBreakdown(string $itemName, string $locationName, ?int $customerId = null, ?string $dropOffType = null): array
    {
        $locationId = $this->locationId($locationName);
        $uom = Uom::query()->where('name', $itemName)->first();

        if (! $locationId || ! $uom) {
            return [];
        }

        return $this->rates->uomTiers($uom, $locationId, $customerId, $dropOffType)['tiers']
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
    public function uomTierSummary(string $itemName, array $destinations, float $quantity, ?int $customerId = null, ?string $dropOffType = null): array
    {
        $quantity = max(0.01, $quantity);

        return [
            'quantity' => $quantity,
            'destinations' => collect($destinations)
                ->filter()
                ->values()
                ->map(function (string $destination) use ($itemName, $quantity, $customerId, $dropOffType) {
                    $matched = $this->matchedUomTier($itemName, $destination, $quantity, $customerId, $dropOffType);
                    $locationId = $this->locationId($destination);
                    $uom = Uom::query()->where('name', $itemName)->first();

                    $tiers = [];

                    if ($locationId && $uom) {
                        $tiers = $this->rates->uomTiers($uom, $locationId, $customerId, $dropOffType)['tiers']
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
