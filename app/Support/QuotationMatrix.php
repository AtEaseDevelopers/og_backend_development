<?php

namespace App\Support;

use App\Domains\Quotation\Models\Quotation;

class QuotationMatrix
{
    /**
     * @return array{matrix_columns: list<string>, matrix_rows: list<array{item_name: string, catalog_key: ?string, prices: array<string, float|null>}>}
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

            if (! isset($rows[$rowKey])) {
                $rows[$rowKey] = [
                    'item_name' => $line->item_name,
                    'catalog_key' => null,
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
     * @param  list<array{item_name: string, prices: array<string, mixed>}>  $rows
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

        $subtotal = 0;

        foreach ($rows as $row) {
            $itemName = trim((string) ($row['item_name'] ?? ''));

            if ($itemName === '') {
                continue;
            }

            foreach ($columns as $column) {
                $price = $row['prices'][$column] ?? null;

                if ($price === null || $price === '') {
                    continue;
                }

                $price = round((float) $price, 2);
                $subtotal += $price;

                $quotation->lines()->create([
                    'quotation_destination_id' => $destinations[$column]->id,
                    'item_name' => $itemName,
                    'quantity' => 1,
                    'unit_price' => $price,
                    'line_total' => $price,
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
     * @param  list<array{item_name: string, prices: array<string, mixed>}>  $rows
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

            $prices = [];

            foreach ($columns as $column) {
                $prices[] = filled($row['prices'][$column] ?? null) ? $row['prices'][$column] : null;
            }

            $matrixRows[] = [
                'label' => $label,
                'sub' => null,
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
