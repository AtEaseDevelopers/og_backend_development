<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Quotation\Models\Quotation;
use App\Support\CurrentCompany;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Facades\Filament;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class QuotationPdfController
{
    public function __invoke(string $tenant, int|string $quotation): Response
    {
        $user = Filament::auth()->user();
        abort_unless($user, 403);

        $record = Quotation::query()->findOrFail($quotation);

        $record->loadMissing([
            'customer.pics',
            'branch',
            'company',
            'salesperson',
            'destinations',
            'lines',
        ]);

        $currentTenant = Filament::getTenant() ?? CurrentCompany::get();
        if ($currentTenant && (int) $record->company_id !== (int) $currentTenant->getKey()) {
            abort(404);
        }

        if (method_exists($user, 'canAccessTenant') && $record->company) {
            abort_unless($user->canAccessTenant($record->company), 403);
        }

        $pdf = Pdf::loadView('pdf.quotation', [
            'quotation' => $record,
            'rateMatrix' => $this->buildRateMatrix($record),
        ])->setPaper('a4');

        return $pdf->stream($record->number.'.pdf');
    }

    /**
     * @return array{destinations: array<int, string>, rows: array<int, array{label: string, sub: ?string, prices: array<int, mixed>}>}
     */
    private function buildRateMatrix(Quotation $quotation): array
    {
        $destinations = $quotation->destinations->sortBy('sequence')->values();

        $destinationLabels = $destinations->map(function ($destination) {
            $label = collect([
                $destination->city && $destination->state
                    ? $destination->city.' / '.$destination->state
                    : null,
                $destination->city,
                $destination->state,
                $destination->consignee_name,
            ])->filter()->first();

            return $label ?: Str::limit($destination->address ?? 'Destination', 28);
        })->all();

        $rows = [];
        foreach ($quotation->lines->sortBy('id') as $line) {
            $destIndex = $destinations->search(fn ($d) => (int) $d->id === (int) $line->quotation_destination_id);
            $rowKey = implode('|', [
                $line->item_name,
                $line->handling_notes ?? '',
                $line->dimensions ?? '',
                $line->weight ?? '',
            ]);

            if (! isset($rows[$rowKey])) {
                $rows[$rowKey] = [
                    'label' => $line->item_name,
                    'sub' => collect([
                        $line->dimensions,
                        $line->weight ? 'Weight: '.$line->weight : null,
                        $line->handling_notes,
                    ])->filter()->implode(' · '),
                    'prices' => array_fill(0, max(1, $destinations->count()), null),
                    'amounts' => array_fill(0, max(1, $destinations->count()), null),
                    'qty' => (float) $line->quantity,
                ];
            }

            $column = $destIndex !== false ? $destIndex : ($destinations->isEmpty() ? 0 : null);

            if ($column !== null) {
                $rows[$rowKey]['prices'][$column] = $line->unit_price;
                // a product without a price yet shows no amount (not RM 0.00)
                $rows[$rowKey]['amounts'][$column] = $line->unit_price !== null ? $line->line_total : null;
            }
        }

        if ($destinations->isEmpty() && $rows === []) {
            foreach ($quotation->lines as $line) {
                $rows[] = [
                    'label' => $line->item_name,
                    'sub' => collect([$line->dimensions, $line->handling_notes])->filter()->implode(' · '),
                    'prices' => [$line->unit_price],
                    'amounts' => [$line->unit_price !== null ? $line->line_total : null],
                    'qty' => (float) $line->quantity,
                ];
            }
            $destinationLabels = ['Rate'];
        }

        $rows = array_values($rows);
        $totals = [];

        foreach (array_keys($destinationLabels) as $index) {
            $totals[$index] = round(collect($rows)->sum(fn (array $row) => (float) ($row['amounts'][$index] ?? 0)), 2);
        }

        return [
            'destinations' => $destinationLabels,
            'rows' => $rows,
            'totals' => $totals,
            'grand_total' => (float) $quotation->total_amount,
        ];
    }
}
