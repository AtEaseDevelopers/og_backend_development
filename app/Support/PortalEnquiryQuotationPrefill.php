<?php

namespace App\Support;

use App\Domains\MasterData\Models\Branch;
use App\Domains\MasterData\Models\Location;
use App\Domains\Quotation\Models\PortalEnquiry;
use App\Filament\Resources\QuotationResource\Schemas\QuotationForm;

class PortalEnquiryQuotationPrefill
{
    /**
     * @return array<string, mixed>
     */
    public function formState(PortalEnquiry $enquiry): array
    {
        $enquiry->loadMissing(['customer.pics', 'customer.addresses', 'branch']);
        $payload = $enquiry->payload ?? [];
        $destinations = collect($payload['destinations'] ?? []);
        $items = collect($payload['items'] ?? []);

        $matrixColumns = $destinations
            ->values()
            ->map(fn (array $destination, int $index): string => $this->destinationLabel($destination, $index))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($matrixColumns === []) {
            $matrixColumns = ['Destination 1'];
        }

        $firstDestination = $destinations->first() ?? [];
        $firstLabel = $matrixColumns[0] ?? 'Destination 1';

        $notes = collect([
            filled($enquiry->reference_no) ? 'Portal enquiry '.$enquiry->reference_no.'.' : null,
            filled($enquiry->special_requirements) ? 'Customer request: '.$enquiry->special_requirements : null,
        ])->filter()->implode("\n");

        $customerId = $enquiry->customer_id ? (string) $enquiry->customer_id : null;

        // Section A: every order from the enquiry stays under the enquiry's salesperson
        $salespersonId = $enquiry->salesperson_id ?? auth()->id();
        $saLocationId = $enquiry->sa_location_id ?? $enquiry->salesperson?->sa_location_id ?? auth()->user()?->sa_location_id;

        $state = [
            'company_id' => (string) ($enquiry->company_id ?? CurrentCompany::id()),
            'branch_id' => (string) ($enquiry->branch_id ?? CurrentCompany::branchId()),
            'customer_id' => $customerId,
            'salesperson_id' => $salespersonId ? (string) $salespersonId : null,
            'salesperson_locked' => $enquiry->salesperson_id !== null,
            'sa_location_id' => $saLocationId ? (string) $saLocationId : null,
            'order_type' => $enquiry->order_type?->value ?? $enquiry->customer?->default_order_type,
            'service_type' => $enquiry->service_type?->value,
            'payment_method' => $enquiry->payment_method,
            'customer_do_number' => $enquiry->customer_do_number,
            'attachments' => collect($enquiry->attachments ?? [])->pluck('path')->filter()->values()->all(),
            'destination_types' => $destinations->values()->map(fn (array $destination, int $index): array => [
                'column' => $this->destinationLabel($destination, $index),
                'drop_off_type' => $destination['drop_off_type'] ?? null,
                'service_type' => $destination['service_type'] ?? null,
            ])->all(),
            'pricing_source' => 'portal',
            'expected_delivery_date' => $enquiry->preferred_delivery_date?->toDateString(),
            'pickup_location' => $enquiry->pickup_address,
            'consignee_name' => $firstDestination['consignee_name'] ?? $firstLabel,
            'drop_off_location' => $this->formatDropOff($firstDestination),
            'consignee_address' => $enquiry->customer?->address,
            'notes' => $notes !== '' ? $notes : null,
            'matrix_columns' => $matrixColumns,
            'matrix_rows' => $this->matrixRows($items, $matrixColumns, $enquiry->customer_id ? (int) $enquiry->customer_id : null),
            'history_destination' => $firstLabel,
        ];

        if ($customerId) {
            $state = array_merge(
                QuotationForm::consignorStateForCustomer($customerId, withPickupPreset: false),
                $state,
            );
        }

        $fromLocationId = $this->fromLocationIdForBranch($enquiry->branch_id);

        if ($fromLocationId) {
            $state['from_location_id'] = (string) $fromLocationId;
        }

        return $this->stringifySelectValues($state);
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    private function stringifySelectValues(array $state): array
    {
        foreach (['company_id', 'branch_id', 'customer_id', 'salesperson_id', 'sa_location_id', 'from_location_id'] as $key) {
            if (filled($state[$key] ?? null)) {
                $state[$key] = (string) $state[$key];
            }
        }

        return $state;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $items
     * @param  list<string>  $matrixColumns
     * @return list<array<string, mixed>>
     */
    private function matrixRows($items, array $matrixColumns, ?int $customerId = null): array
    {
        if ($items->isEmpty()) {
            return [[
                'line_type' => 'item',
                'item_name' => 'Transport charges',
                'catalog_key' => null,
                'quantity' => 1,
                'prices' => array_fill_keys($matrixColumns, null),
            ]];
        }

        $lookup = app(QuotationPricingLookup::class);

        return $items->map(function (array $item) use ($matrixColumns, $lookup, $customerId): array {
            $itemName = trim((string) ($item['item_name'] ?? 'Transport item'));
            $uom = strtoupper(trim((string) ($item['uom'] ?? '')));
            $lineType = $uom !== '' ? 'uom' : 'item';
            $catalogKey = $lookup->resolveCatalogKey($itemName);

            if ($lineType === 'uom' && ! $catalogKey) {
                $catalogKey = $lookup->resolveCatalogKey($uom) ?: null;
            }

            $quantity = max(0.01, (float) ($item['quantity'] ?? 1));
            $prices = array_fill_keys($matrixColumns, null);

            // UOM rows are priced from the master price list (qty range tiers per location),
            // exactly as the form does when a UOM/qty is changed by hand.
            if ($lineType === 'uom') {
                foreach ($matrixColumns as $column) {
                    $prices[$column] = $lookup->lookupForCustomer($customerId, $itemName, $column, $quantity)['price'] ?? null;
                }
            }

            return [
                'line_type' => $lineType,
                'item_name' => $itemName,
                'catalog_key' => $catalogKey,
                'quantity' => $quantity,
                'prices' => $prices,
            ];
        })->values()->all();
    }

    /** @param  array<string, mixed>  $destination */
    private function formatDropOff(array $destination): ?string
    {
        $formatted = collect([
            $destination['consignee_name'] ?? null,
            $destination['address'] ?? null,
            collect([
                $destination['postcode'] ?? null,
                $destination['state'] ?? null,
            ])->filter()->implode(', '),
        ])->filter()->implode(', ');

        return $formatted !== '' ? $formatted : null;
    }

    /** @param  array<string, mixed>  $destination */
    private function destinationLabel(array $destination, int $index): string
    {
        // Prefer a price-list location (UOM rate tiers are keyed by Location) so UOM
        // rows auto-price; the matrix column label must equal the Location name.
        if ($location = $this->matchPriceListLocation($destination)) {
            return $location;
        }

        if (filled($destination['consignee_name'] ?? null)) {
            return (string) $destination['consignee_name'];
        }

        if (filled($destination['city'] ?? null)) {
            return (string) $destination['city'];
        }

        if (filled($destination['state'] ?? null)) {
            return (string) $destination['state'];
        }

        return 'Destination '.($index + 1);
    }

    /** @param  array<string, mixed>  $destination */
    private function matchPriceListLocation(array $destination): ?string
    {
        $candidates = collect([
            $destination['city'] ?? null,
            $destination['state'] ?? null,
            $destination['address'] ?? null,
            $destination['consignee_name'] ?? null,
        ])->filter()->map(fn ($value) => mb_strtolower(trim((string) $value)))->values();

        if ($candidates->isEmpty()) {
            return null;
        }

        $locations = Location::query()
            ->where('is_active', true)
            ->whereHas('uomRateTiers')
            ->get(['id', 'code', 'name']);

        foreach ($candidates as $candidate) {
            foreach ($locations as $location) {
                $name = mb_strtolower($location->name);

                if ($candidate === $name || str_contains($candidate, $name)) {
                    return $location->name;
                }
            }
        }

        return null;
    }

    private function fromLocationIdForBranch(?int $branchId): ?int
    {
        if (! $branchId) {
            return null;
        }

        $branch = Branch::query()->find($branchId);

        if (! $branch) {
            return null;
        }

        return Location::query()
            ->where('is_active', true)
            ->where(function ($query) use ($branch) {
                $query->where('code', $branch->code)
                    ->orWhere('name', 'like', '%'.$branch->name.'%');
            })
            ->value('id');
    }
}
