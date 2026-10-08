<?php

namespace App\Support;

use App\Domains\Quotation\Actions\UpdateOrderRecords;
use App\Domains\Quotation\Models\Quotation;

class QuotationMatrix
{
    /**
     * @return array{matrix_columns: list<string>, matrix_rows: list<array{item_name: string, catalog_key: ?string, line_type: string, quantity: float, prices: array<string, float|null>}>}
     */
    public function toFormState(Quotation $quotation): array
    {
        $quotation->loadMissing(['destinations', 'lines']);

        $destinations = $quotation->destinations->sortBy('sequence')->values();
        $columns = $destinations->map(fn ($destination) => $this->columnLabel($destination))->filter()->values()->all();

        if ($columns === []) {
            $columns = ['Seremban', 'Melaka', 'Johor'];
        }

        $rows = [];

        foreach ($quotation->lines->sortBy('id') as $line) {
            $destination = $destinations->firstWhere('id', $line->quotation_destination_id);
            $column = $destination ? $this->columnLabel($destination) : $columns[0] ?? 'Rate';
            $rowKey = $line->item_name;
            $lookup = app(QuotationPricingLookup::class);
            $catalogKey = $lookup->resolveCatalogKey($line->item_name);
            $lineType = $lookup->inferLineType($catalogKey, $line->item_name);

            if (! isset($rows[$rowKey])) {
                $rows[$rowKey] = [
                    'line_type' => $lineType,
                    'item_name' => $line->item_name,
                    'catalog_key' => $catalogKey,
                    'quantity' => (float) ($line->quantity ?: 1),
                    'prices' => array_fill_keys($columns, null),
                ];
            }

            if (in_array($column, $columns, true)) {
                // a line without a price yet stays without one
                $rows[$rowKey]['prices'][$column] = $line->unit_price !== null ? (float) $line->unit_price : null;
            }
        }

        return [
            'matrix_columns' => $columns,
            'matrix_rows' => array_values($rows),
        ];
    }

    /** No price entered (a 0 is a price). */
    public static function isBlankPrice(mixed $price): bool
    {
        return $price === null || (is_string($price) && trim($price) === '');
    }

    /**
     * Products of a record kept as a line without a price yet (they are left out of its total): a quotation
     * is not sent or confirmed while one is left.
     *
     * @return list<string>
     */
    public static function unpricedItems(Quotation $quotation): array
    {
        $names = $quotation->lines()->whereNull('unit_price')->orderBy('id')->pluck('item_name');

        // products of older records that only come back from the order form (no line yet) are unpriced too
        if ($quotation->portal_enquiry_id && ($enquiry = $quotation->portalEnquiry)) {
            $names = $names->merge(collect(UpdateOrderRecords::payloadItemsWithoutLine($quotation, $enquiry))
                ->map(fn (array $item) => trim((string) ($item['item_name'] ?? '')))
                ->filter());
        }

        return $names->unique()->values()->all();
    }

    /**
     * Section D: rates that differ from the standard price list (or the customer's special price)
     * are overrides. They need a reason and HQ Admin / Branch Manager permission.
     *
     * @param  list<string>  $columns
     * @param  list<array{item_name: string, line_type?: string, quantity?: mixed, prices: array<string, mixed>}>  $rows
     * @return list<array{item: string, destination: string, standard: float, entered: float}>
     */
    public function detectOverrides(?int $customerId, array $columns, array $rows): array
    {
        $lookup = app(QuotationPricingLookup::class);
        $overrides = [];

        foreach ($rows as $row) {
            $itemName = trim((string) ($row['item_name'] ?? ''));

            if ($itemName === '') {
                continue;
            }

            $lineType = $row['line_type'] ?? $lookup->inferLineType($row['catalog_key'] ?? null, $itemName);
            $quantity = $lineType === 'uom' ? max(0.01, (float) ($row['quantity'] ?? 1)) : 1.0;

            foreach (array_filter($columns) as $column) {
                $price = $row['prices'][$column] ?? null;

                if ($price === null || $price === '') {
                    continue;
                }

                $standard = $lookup->lookupForCustomer($customerId, $itemName, $column, $quantity)['price'] ?? null;

                if ($standard === null) {
                    continue; // nothing to compare against: manual pricing, not an override
                }

                if (abs(round((float) $price, 2) - round((float) $standard, 2)) >= 0.005) {
                    $overrides[] = [
                        'item' => $itemName,
                        'destination' => $column,
                        'standard' => round((float) $standard, 2),
                        'entered' => round((float) $price, 2),
                    ];
                }
            }
        }

        return $overrides;
    }

    /**
     * @param  list<string>  $columns
     * @param  list<array{item_name: string, line_type?: string, quantity?: mixed, prices: array<string, mixed>}>  $rows
     */
    public function sync(Quotation $quotation, array $columns, array $rows): void
    {
        $quotation->destinations()->delete();
        $quotation->lines()->delete();

        $columns = collect($columns)->filter()->values()->all();

        if ($columns === []) {
            $columns = ['Seremban', 'Melaka', 'Johor'];
        }

        $destinations = [];
        $destinationTypes = collect($quotation->destination_types ?? [])->keyBy('column');

        foreach ($columns as $index => $column) {
            $typeSetting = $destinationTypes->get($column, []);

            $destinations[$column] = $quotation->destinations()->create([
                'sequence' => $index + 1,
                'consignee_name' => $column,
                'address' => $column,
                'city' => $column,
                'state' => '',
                'drop_off_type' => $typeSetting['drop_off_type'] ?? null,
                'service_type' => $typeSetting['service_type'] ?? $quotation->service_type?->value,
            ]);
        }

        $lookup = app(QuotationPricingLookup::class);
        $subtotal = 0;

        foreach ($rows as $row) {
            $itemName = trim((string) ($row['item_name'] ?? ''));

            if ($itemName === '') {
                continue;
            }

            $lineType = $row['line_type'] ?? $lookup->inferLineType($row['catalog_key'] ?? null, $itemName);
            $quantity = $lineType === 'uom'
                ? max(0.01, (float) ($row['quantity'] ?? 1))
                : 1.0;
            $uom = $lineType === 'uom'
                ? $lookup->resolveUomCode($row['catalog_key'] ?? null, $itemName)
                : null;

            $priced = array_values(array_filter($columns, fn (string $column) => ! static::isBlankPrice($row['prices'][$column] ?? null)));

            // a product without any price yet is still kept: one line on the first column with no unit price
            // (line total 0, so the totals leave it out) until a price is entered
            foreach ($priced !== [] ? $priced : [$columns[0]] as $column) {
                $price = $row['prices'][$column] ?? null;
                $unitPrice = static::isBlankPrice($price) ? null : round((float) $price, 2);
                $lineTotal = $unitPrice !== null ? round($quantity * $unitPrice, 2) : 0.0;
                $subtotal += $lineTotal;

                $quotation->lines()->create([
                    'quotation_destination_id' => $destinations[$column]->id,
                    'item_name' => $itemName,
                    'uom' => $uom,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'line_total' => $lineTotal,
                ]);
            }
        }

        $quotation->update([
            'subtotal' => $subtotal,
            'total_amount' => $subtotal + (float) $quotation->tax_amount,
        ]);
    }

    /**
     * @param  list<string>  $columns
     * @param  list<array{item_name: string, line_type?: string, quantity?: mixed, prices: array<string, mixed>}>  $rows
     * @return array{destinations: list<string>, rows: list<array{label: string, sub: ?string, prices: list<mixed>}>}
     */
    public function preview(array $columns, array $rows): array
    {
        $columns = collect($columns)->filter()->values()->all();

        if ($columns === []) {
            $columns = ['Seremban', 'Melaka', 'Johor'];
        }

        $matrixRows = [];

        foreach ($rows as $row) {
            $label = trim((string) ($row['item_name'] ?? ''));

            if ($label === '') {
                continue;
            }

            $lineType = $row['line_type'] ?? 'item';
            $quantity = $lineType === 'uom' ? max(0.01, (float) ($row['quantity'] ?? 1)) : 1.0;
            $prices = [];

            foreach ($columns as $column) {
                $unitPrice = $row['prices'][$column] ?? null;

                if (! filled($unitPrice)) {
                    $prices[] = null;

                    continue;
                }

                $prices[] = $lineType === 'uom' && $quantity !== 1.0
                    ? round((float) $unitPrice * $quantity, 2)
                    : $unitPrice;
            }

            $sub = null;

            if ($lineType === 'uom' && $quantity !== 1.0) {
                $sub = 'Qty '.number_format($quantity, 0).' @ range tier rate';
            }

            $matrixRows[] = [
                'label' => $label,
                'sub' => $sub,
                'prices' => $prices,
            ];
        }

        return [
            'destinations' => $columns,
            'rows' => $matrixRows,
        ];
    }

    private function columnLabel($destination): string
    {
        return trim((string) ($destination->city ?: $destination->consignee_name ?: $destination->address));
    }
}
