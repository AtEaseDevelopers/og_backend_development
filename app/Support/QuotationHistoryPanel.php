<?php

namespace App\Support;

use App\Domains\MasterData\Models\Customer;
use App\Domains\MasterData\Models\CustomerPricing;
use App\Domains\Quotation\Models\Quotation;
use App\Domains\Quotation\Models\QuotationLine;

class QuotationHistoryPanel
{
    public function __construct(
        private readonly QuotationPricingLookup $lookup = new QuotationPricingLookup,
    ) {}

    /**
     * @param  list<string>  $destinations
     * @return list<array{measurement: string, qty: float, price: ?float, prices: array<string, float|null>}>
     */
    public function defaultPricesForAllMeasurements(array $destinations): array
    {
        $destinations = $this->normalizeDestinations($destinations);

        $items = collect(array_keys($this->lookup->catalogOptions()))
            ->map(fn (string $key) => $this->lookup->resolveCatalogName($key))
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();

        return collect($items)->map(function (string $measurement) use ($destinations) {
            $prices = collect($destinations)->mapWithKeys(
                fn (string $destination) => [$destination => $this->lookup->lookup($measurement, $destination)]
            )->all();

            $firstPrice = collect($prices)->first(fn ($price) => $price !== null);

            return [
                'measurement' => $measurement,
                'qty' => 1,
                'price' => $firstPrice,
                'prices' => $prices,
            ];
        })->values()->all();
    }

    /**
     * @param  list<string>  $destinations
     * @param  list<string>  $seedItems
     * @return list<array{measurement: string, qty: float, price: ?float, prices: array<string, float|null>}>
     */
    public function masterToAllConsignees(array $destinations, array $seedItems = []): array
    {
        $destinations = $this->normalizeDestinations($destinations);
        $items = $this->resolveMeasurementList($seedItems);

        return collect($items)->map(function (string $measurement) use ($destinations) {
            $prices = collect($destinations)->mapWithKeys(
                fn (string $destination) => [$destination => $this->lookup->lookup($measurement, $destination)]
            )->all();

            $firstPrice = collect($prices)->first(fn ($price) => $price !== null);

            return [
                'measurement' => $measurement,
                'qty' => 1,
                'price' => $firstPrice,
                'prices' => $prices,
            ];
        })->values()->all();
    }

    /**
     * @param  list<string>  $destinations
     * @return list<array{measurement: string, qty: float, price: ?float, bill_to: string, prices: array<string, float|null>}>
     */
    public function customerToConsignee(?int $customerId, ?string $destination, array $destinations): array
    {
        if (! $customerId) {
            return [];
        }

        $customer = Customer::query()->find($customerId);
        $destinations = $this->normalizeDestinations($destinations);
        $targetDestination = $destination ?: ($destinations[0] ?? null);

        if (! $targetDestination) {
            return [];
        }

        $items = $this->resolveMeasurementList(
            $this->measurementsForCustomerDestination($customerId, $targetDestination),
        );

        return collect($items)->map(function (string $measurement) use ($customerId, $targetDestination, $customer, $destinations) {
            $resolved = $this->lookup->lookupForCustomer($customerId, $measurement, $targetDestination);
            $prices = collect($destinations)->mapWithKeys(function (string $dest) use ($customerId, $measurement) {
                $match = $this->lookup->lookupForCustomer($customerId, $measurement, $dest);

                return [$dest => $match['price']];
            })->all();
            $sources = collect($destinations)->mapWithKeys(function (string $dest) use ($customerId, $measurement) {
                $match = $this->lookup->lookupForCustomer($customerId, $measurement, $dest);

                return [$dest => $match['source']];
            })->all();

            return [
                'measurement' => $measurement,
                'qty' => 1,
                'price' => $resolved['price'],
                'source' => $resolved['source'],
                'sources' => $sources,
                'bill_to' => $customer?->company_name ?? '—',
                'prices' => $prices,
                'destination' => $targetDestination,
            ];
        })->values()->all();
    }

    /**
     * @return list<array{measurement: string, destination: string, uom: string, route: string, qty: float, price: float, min_charge: ?float, prices: array<string, float|null>}>
     */
    public function specialPrices(?int $customerId, ?string $destination = null): array
    {
        return app(CustomerQuotationPriceHistory::class)->specialPrices($customerId, $destination);
    }

    /**
     * @param  list<string>  $destinations
     * @return list<array{measurement: string, qty: float, price: ?float, prices: array<string, float|null>}>
     */
    public function specialPricesForMatrix(?int $customerId, array $destinations): array
    {
        if (! $customerId) {
            return [];
        }

        $destinations = $this->normalizeDestinations($destinations);

        return collect($this->specialPrices($customerId))
            ->unique('measurement')
            ->map(function (array $row) use ($destinations) {
                $prices = collect($destinations)->mapWithKeys(function (string $destination) use ($row) {
                    $price = $row['prices'][$destination] ?? null;

                    if ($price === null && ($row['destination'] === 'All destinations' || $row['destination'] === $destination)) {
                        $price = $row['price'];
                    }

                    return [$destination => $price];
                })->all();

                return [
                    'measurement' => $row['measurement'],
                    'qty' => $row['qty'],
                    'price' => collect($prices)->first(fn ($price) => $price !== null) ?? $row['price'],
                    'prices' => $prices,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return list<array{date: ?string, measurement: string, qty: float, price: float, quotation_id: int, quote: string, destination: string, view_url: ?string}>
     */
    public function otherNonDefault(
        ?int $customerId,
        ?string $search = null,
        ?string $measurement = null,
        ?string $tenantSlug = null,
    ): array {
        if (! $customerId) {
            return [];
        }

        $lines = QuotationLine::query()
            ->whereHas('quotation', fn ($query) => $query->where('customer_id', $customerId))
            ->with([
                'quotation:id,number,quoted_at,created_at',
                'destination:id,city,consignee_name',
            ])
            ->latest('id')
            ->limit(100)
            ->get();

        return $lines
            ->filter(function (QuotationLine $line) use ($search, $measurement) {
                $destination = $line->destination?->city ?: $line->destination?->consignee_name ?: '';
                $master = app(QuotationPricingLookup::class)->lookup(
                    $line->item_name,
                    $destination,
                    (float) $line->quantity,
                );
                $isNonDefault = $master === null || round((float) $line->unit_price, 2) !== round((float) $master, 2);

                if (! $isNonDefault) {
                    return false;
                }

                if ($measurement && stripos($line->item_name, $measurement) === false) {
                    return false;
                }

                if ($search) {
                    $haystack = strtolower(implode(' ', [
                        $line->item_name,
                        $line->quotation?->number,
                        $destination,
                    ]));

                    return str_contains($haystack, strtolower($search));
                }

                return true;
            })
            ->take(30)
            ->map(function (QuotationLine $line) use ($tenantSlug) {
                $destination = $line->destination?->city ?: $line->destination?->consignee_name ?: '—';

                return [
                    'date' => optional($line->quotation?->quoted_at ?? $line->quotation?->created_at)?->format('d/m/Y'),
                    'measurement' => $line->item_name,
                    'qty' => (float) $line->quantity,
                    'price' => (float) $line->unit_price,
                    'quotation_id' => (int) $line->quotation_id,
                    'quote' => $line->quotation?->number ?? '—',
                    'destination' => $destination,
                    'view_url' => $tenantSlug
                        ? route('filament.admin.resources.quotations.view', [
                            'tenant' => $tenantSlug,
                            'record' => $line->quotation_id,
                        ])
                        : null,
                ];
            })
            ->values()
            ->all();
    }

    /** @return list<string> */
    public function measurementOptions(?int $customerId): array
    {
        if (! $customerId) {
            return [];
        }

        $fromQuotes = QuotationLine::query()
            ->whereHas('quotation', fn ($query) => $query->where('customer_id', $customerId))
            ->distinct()
            ->orderBy('item_name')
            ->pluck('item_name');

        $fromSpecial = CustomerPricing::query()
            ->where('customer_id', $customerId)
            ->where('is_active', true)
            ->distinct()
            ->orderBy('item_name')
            ->pluck('item_name');

        return $fromQuotes->merge($fromSpecial)->filter()->unique()->sort()->values()->all();
    }

    /**
     * @param  list<array{measurement: string, qty: float, prices: array<string, float|null>}>  $rows
     * @return list<array{item_name: string, catalog_key: null, prices: array<string, float|null>}>
     */
    public function toMatrixRows(array $rows): array
    {
        return collect($rows)
            ->filter(fn (array $row) => filled($row['measurement'] ?? null))
            ->map(fn (array $row) => [
                'item_name' => $row['measurement'],
                'catalog_key' => null,
                'prices' => $row['prices'] ?? [],
            ])
            ->values()
            ->all();
    }

    /** @param  list<string>  $destinations */
    private function normalizeDestinations(array $destinations): array
    {
        $destinations = collect($destinations)->filter()->values()->all();

        return $destinations !== [] ? $destinations : ['Seremban', 'Melaka', 'Johor'];
    }

    /** @param  list<string>  $seedItems
     * @return list<string>
     */
    private function resolveMeasurementList(array $seedItems): array
    {
        $items = collect($seedItems)->filter()->unique()->values();

        if ($items->isEmpty()) {
            $items = collect(array_keys($this->lookup->catalogOptions()))
                ->map(fn (string $key) => $this->lookup->resolveCatalogName($key))
                ->filter()
                ->take(8);
        }

        return $items->take(12)->values()->all();
    }

    /** @return list<string> */
    private function measurementsForCustomerDestination(int $customerId, string $destination): array
    {
        $fromQuotes = QuotationLine::query()
            ->whereHas('quotation', fn ($query) => $query->where('customer_id', $customerId))
            ->whereHas('destination', function ($query) use ($destination) {
                $query->where('city', $destination)
                    ->orWhere('consignee_name', $destination);
            })
            ->distinct()
            ->orderBy('item_name')
            ->pluck('item_name');

        $fromSpecial = CustomerPricing::query()
            ->where('customer_id', $customerId)
            ->where('is_active', true)
            ->where(function ($query) use ($destination) {
                $query->where('destination', $destination)
                    ->orWhereNull('destination')
                    ->orWhere('destination', '');
            })
            ->distinct()
            ->orderBy('item_name')
            ->pluck('item_name');

        return $fromQuotes->merge($fromSpecial)->filter()->unique()->sort()->values()->all();
    }
}
