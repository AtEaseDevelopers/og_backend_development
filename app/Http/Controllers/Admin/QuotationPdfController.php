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
                ];
            }

            if ($destIndex !== false) {
                $rows[$rowKey]['prices'][$destIndex] = $line->unit_price;
            } elseif ($destinations->isEmpty()) {
                $rows[$rowKey]['prices'][0] = $line->unit_price;
            }
        }

        if ($destinations->isEmpty() && $rows === []) {
            foreach ($quotation->lines as $line) {
                $rows[] = [
                    'label' => $line->item_name,
                    'sub' => collect([$line->dimensions, $line->handling_notes])->filter()->implode(' · '),
                    'prices' => [$line->unit_price],
                ];
            }
            $destinationLabels = ['Rate'];
        }

        return [
            'destinations' => $destinationLabels,
            'rows' => array_values($rows),
        ];
    }
}
