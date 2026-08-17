<?php

namespace App\Support;

use App\Domains\Consignment\Models\ConsignmentNote;
use App\Domains\Quotation\Models\Quotation;

class CsnTransportMatrix
{
    /**
     * @return array{matrix_columns: list<string>, matrix_rows: list<array<string, mixed>>, charge_column: ?string}
     */
    public function toFormState(ConsignmentNote $csn): array
    {
        $csn->loadMissing(['lines']);

        $column = $csn->delivery_city ?: $csn->consignee_name ?: 'Rate';
        $columns = [$column];

        $rows = $csn->lines->map(fn ($line) => [
            'catalog_key' => null,
            'item_name' => $line->item_name,
            'quantity' => (float) $line->quantity,
            'uom' => $line->uom,
            'prices' => [
                $column => (float) $line->unit_price,
            ],
        ])->values()->all();

        if ($rows === []) {
            $rows = [[
                'catalog_key' => null,
                'item_name' => '',
                'quantity' => 1,
                'uom' => null,
                'prices' => [$column => null],
            ]];
        }

        return [
            'matrix_columns' => $columns,
            'matrix_rows' => $rows,
            'charge_column' => $column,
        ];
    }

    /**
     * @return array{matrix_columns: list<string>, matrix_rows: list<array<string, mixed>>, charge_column: ?string}
     */
    public function fromQuotation(Quotation $quotation): array
    {
        $matrix = app(QuotationMatrix::class)->toFormState($quotation);

        return $matrix + [
            'charge_column' => collect($matrix['matrix_columns'])->first(),
        ];
    }

    /**
     * @param  list<string>  $columns
     * @param  list<array<string, mixed>>  $rows
     */
    public function sumForColumn(array $columns, array $rows, ?string $column): float
    {
        $column = $column ?: ($columns[0] ?? null);

        if (! $column) {
            return 0;
        }

        return round(collect($rows)->sum(function (array $row) use ($column) {
            $price = $row['prices'][$column] ?? null;
            $qty = (float) ($row['quantity'] ?? 1);

            return $price !== null && $price !== '' ? (float) $price * $qty : 0;
        }), 2);
    }

    /**
     * @param  list<string>  $columns
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    public function linesFromMatrix(array $columns, array $rows, ?string $column): array
    {
        $column = $column ?: ($columns[0] ?? null);

        if (! $column) {
            return [];
        }

        return collect($rows)
            ->filter(fn (array $row) => filled($row['item_name'] ?? null))
            ->map(function (array $row) use ($column) {
                $qty = (float) ($row['quantity'] ?? 1);
                $unitPrice = (float) ($row['prices'][$column] ?? 0);

                return [
                    'item_name' => $row['item_name'],
                    'uom' => $row['uom'] ?? null,
                    'quantity' => $qty,
                    'unit_price' => $unitPrice,
                    'line_total' => round($qty * $unitPrice, 2),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $columns
     * @param  list<array<string, mixed>>  $rows
     * @return array{destinations: list<string>, rows: list<array<string, mixed>>}
     */
    public function preview(array $columns, array $rows): array
    {
        return app(QuotationMatrix::class)->preview($columns, $rows);
    }
}
