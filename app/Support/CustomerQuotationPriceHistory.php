<?php

namespace App\Support;

use App\Domains\MasterData\Models\CustomerPricing;
use App\Domains\Quotation\Models\QuotationLine;

class CustomerQuotationPriceHistory
{
    /**
     * @return array{special: list<array<string, mixed>>, previous: list<array<string, mixed>>}
     */
    public function panel(?int $customerId): array
    {
        if (! $customerId) {
            return ['special' => [], 'previous' => []];
        }

        return [
            'special' => $this->specialPrices($customerId),
            'previous' => $this->previousPrices($customerId),
        ];
    }

    public function resolvePrice(?int $customerId, string $itemName, string $location): ?float
    {
        return $this->resolveSpecialPrice($customerId, $itemName, $location)
            ?? $this->resolvePreviousPrice($customerId, $itemName, $location);
    }

    public function resolveSpecialPrice(?int $customerId, string $itemName, string $location): ?float
    {
        if (! $customerId) {
            return null;
        }

        $special = CustomerPricing::query()
            ->where('customer_id', $customerId)
            ->where('is_active', true)
            ->where('item_name', $itemName)
            ->where(function ($query) use ($location) {
                $query->where('destination', $location)
                    ->orWhereNull('destination')
                    ->orWhere('destination', '');
            })
            ->orderByRaw('CASE WHEN destination = ? THEN 0 ELSE 1 END', [$location])
            ->first();

        if (! $special) {
            return null;
        }

        $price = $special->unit_rate ?? $special->base_price;

        return $price !== null ? (float) $price : null;
    }

    public function resolvePreviousPrice(?int $customerId, string $itemName, string $location): ?float
    {
        if (! $customerId) {
            return null;
        }

        $previous = QuotationLine::query()
            ->where('item_name', $itemName)
            ->whereHas('quotation', fn ($query) => $query->where('customer_id', $customerId))
            ->whereHas('destination', function ($query) use ($location) {
                $query->where('city', $location)
                    ->orWhere('consignee_name', $location)
                    ->orWhere('address', $location);
            })
            ->latest('id')
            ->first();

        return $previous ? (float) $previous->unit_price : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function specialPrices(?int $customerId, ?string $destination = null): array
    {
        if (! $customerId) {
            return [];
        }

        return CustomerPricing::query()
            ->where('customer_id', $customerId)
            ->where('is_active', true)
            ->when($destination, fn ($query) => $query->where(function ($inner) use ($destination) {
                $inner->where('destination', $destination)
                    ->orWhereNull('destination')
                    ->orWhere('destination', '');
            }))
            ->orderBy('item_name')
            ->orderBy('destination')
            ->limit(50)
            ->get()
            ->map(fn (CustomerPricing $row) => [
                'measurement' => $row->item_name ?: '—',
                'destination' => $row->destination ?: 'All destinations',
                'uom' => $row->uom ?: '—',
                'route' => $row->route ?: '—',
                'qty' => 1,
                'price' => (float) ($row->unit_rate ?? $row->base_price),
                'min_charge' => $row->min_charge ? (float) $row->min_charge : null,
                'prices' => $this->specialPriceMatrix($customerId, $row->item_name ?: ''),
            ])
            ->all();
    }

    /** @return array<string, float|null> */
    private function specialPriceMatrix(int $customerId, string $itemName): array
    {
        if ($itemName === '') {
            return [];
        }

        return CustomerPricing::query()
            ->where('customer_id', $customerId)
            ->where('is_active', true)
            ->where('item_name', $itemName)
            ->whereNotNull('destination')
            ->where('destination', '!=', '')
            ->get()
            ->mapWithKeys(fn (CustomerPricing $row) => [
                $row->destination => (float) ($row->unit_rate ?? $row->base_price),
            ])
            ->all();
    }

    /** @return list<array<string, mixed>> */
    public function previousPrices(?int $customerId): array
    {
        if (! $customerId) {
            return [];
        }

        return $this->previousPricesQuery($customerId)->all();
    }

    /**
     * @return list<array{date: ?string, measurement: string, destination: string, qty: float, price: float, quotation_id: int, quote: string, view_url: ?string}>
     */
    public function previousQuotationPrices(
        ?int $customerId,
        ?string $search = null,
        ?string $measurement = null,
        ?string $destination = null,
        ?string $tenantSlug = null,
    ): array {
        if (! $customerId) {
            return [];
        }

        return collect($this->previousPricesQuery($customerId))
            ->filter(function (array $row) use ($search, $measurement, $destination) {
                if ($destination && stripos($row['destination'], $destination) === false) {
                    return false;
                }

                if ($measurement && stripos($row['measurement'], $measurement) === false) {
                    return false;
                }

                if ($search) {
                    $haystack = strtolower(implode(' ', [
                        $row['measurement'],
                        $row['quote'],
                        $row['destination'],
                    ]));

                    return str_contains($haystack, strtolower($search));
                }

                return true;
            })
            ->take(30)
            ->map(function (array $row) use ($tenantSlug) {
                $row['view_url'] = $tenantSlug && isset($row['quotation_id'])
                    ? route('filament.admin.resources.quotations.view', [
                        'tenant' => $tenantSlug,
                        'record' => $row['quotation_id'],
                    ])
                    : null;

                return $row;
            })
            ->values()
            ->all();
    }

    /** @return \Illuminate\Support\Collection<int, array{date: ?string, measurement: string, destination: string, qty: float, price: float, quotation_id: int, quote: string}> */
    private function previousPricesQuery(int $customerId): \Illuminate\Support\Collection
    {
        return QuotationLine::query()
            ->whereHas('quotation', fn ($query) => $query->where('customer_id', $customerId))
            ->with([
                'quotation:id,number,quoted_at,created_at',
                'destination:id,city,consignee_name',
            ])
            ->latest('id')
            ->limit(100)
            ->get()
            ->unique(fn (QuotationLine $line) => implode('|', [
                $line->item_name,
                $line->destination?->city ?: $line->destination?->consignee_name,
            ]))
            ->map(fn (QuotationLine $line) => [
                'date' => optional($line->quotation?->quoted_at ?? $line->quotation?->created_at)?->format('d/m/Y'),
                'measurement' => $line->item_name,
                'destination' => $line->destination?->city
                    ?: $line->destination?->consignee_name
                    ?: '—',
                'qty' => (float) $line->quantity,
                'price' => (float) $line->unit_price,
                'quotation_id' => (int) $line->quotation_id,
                'quote' => $line->quotation?->number ?? '—',
            ])
            ->values();
    }
}
