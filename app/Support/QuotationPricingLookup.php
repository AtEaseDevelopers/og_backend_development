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
}
