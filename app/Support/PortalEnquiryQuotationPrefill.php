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

        $state = [
            'company_id' => (string) ($enquiry->company_id ?? CurrentCompany::id()),
            'branch_id' => (string) ($enquiry->branch_id ?? CurrentCompany::branchId()),
            'customer_id' => $customerId,
            'salesperson_id' => auth()->id() ? (string) auth()->id() : null,
            'pricing_source' => 'portal',
            'expected_delivery_date' => $enquiry->preferred_delivery_date?->toDateString(),
            'pickup_location' => $enquiry->pickup_address,
            'consignee_name' => $firstDestination['consignee_name'] ?? $firstLabel,
            'drop_off_location' => $this->formatDropOff($firstDestination),
            'consignee_address' => $enquiry->customer?->address,
            'notes' => $notes !== '' ? $notes : null,
            'matrix_columns' => $matrixColumns,
            'matrix_rows' => $this->matrixRows($items, $matrixColumns),
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
        foreach (['company_id', 'branch_id', 'customer_id', 'salesperson_id', 'from_location_id'] as $key) {
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
    private function matrixRows($items, array $matrixColumns): array
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

        return $items->map(function (array $item) use ($matrixColumns, $lookup): array {
            $itemName = trim((string) ($item['item_name'] ?? 'Transport item'));
            $uom = strtoupper(trim((string) ($item['uom'] ?? '')));
            $lineType = $uom !== '' ? 'uom' : 'item';
            $catalogKey = $lookup->resolveCatalogKey($itemName);

            if ($lineType === 'uom' && ! $catalogKey) {
                $catalogKey = $lookup->resolveCatalogKey($uom) ?: null;
            }

            return [
                'line_type' => $lineType,
                'item_name' => $itemName,
                'catalog_key' => $catalogKey,
                'quantity' => max(0.01, (float) ($item['quantity'] ?? 1)),
                'prices' => array_fill_keys($matrixColumns, null),
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
