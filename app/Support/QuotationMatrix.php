<?php

namespace App\Support;

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
                $rows[$rowKey]['prices'][$column] = (float) $line->unit_price;
            }
        }

        return [
            'matrix_columns' => $columns,
            'matrix_rows' => array_values($rows),
        ];
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

        foreach ($columns as $index => $column) {
            $destinations[$column] = $quotation->destinations()->create([
                'sequence' => $index + 1,
                'consignee_name' => $column,
                'address' => $column,
                'city' => $column,
                'state' => '',
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

            foreach ($columns as $column) {
                $price = $row['prices'][$column] ?? null;

                if ($price === null || $price === '') {
                    continue;
                }

                $unitPrice = round((float) $price, 2);
                $lineTotal = round($quantity * $unitPrice, 2);
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
