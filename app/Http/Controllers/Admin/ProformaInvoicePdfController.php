<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Billing\Models\ProformaInvoice;
use App\Support\CurrentCompany;
use App\Support\QuantityLabel;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Facades\Filament;
use Symfony\Component\HttpFoundation\Response;

/** Printable proforma invoice issued when the customer confirms an order. */
class ProformaInvoicePdfController
{
    public function __invoke(string $tenant, int|string $proformaInvoice): Response
    {
        $user = Filament::auth()->user();
        abort_unless($user, 403);

        $record = ProformaInvoice::query()
            ->with(['quotation.lines', 'quotation.destinations', 'quotation.portalEnquiry', 'quotation.salesperson', 'quotation.fromLocation', 'quotation.toLocation', 'customer', 'company', 'consignmentNote'])
            ->findOrFail($proformaInvoice);

        $currentTenant = Filament::getTenant() ?? CurrentCompany::get();
        if ($currentTenant && (int) $record->company_id !== (int) $currentTenant->getKey()) {
            abort(404);
        }

        if (method_exists($user, 'canAccessTenant') && $record->company) {
            abort_unless($user->canAccessTenant($record->company), 403);
        }

        $quotation = $record->quotation;
        $branch = $quotation?->branch ?? CurrentCompany::branch();
        $destinations = $quotation?->destinations->keyBy('id') ?? collect();

        $lines = ($quotation?->lines ?? collect())->map(fn ($line) => [
            'item' => $line->item_name,
            // the measurement the customer keyed and the handling note, as the order form (client, 6 Nov 2026); no destination
            'measure' => trim(implode(' · ', array_filter([$line->dimensions, $line->weight ? $line->weight.' kg' : null]))) ?: null,
            'handling' => $line->handling_notes ?: null,
            'qty' => rtrim(rtrim(number_format((float) $line->quantity, 3, '.', ','), '0'), '.'),
            'uom' => QuantityLabel::unit($line->uom) ?? 'Unit',
            'unit' => number_format((float) $line->unit_price, 2),
            'total' => number_format((float) $line->line_total, 2),
        ])->values()->all();

        $pdf = Pdf::loadView('pdf.proforma-invoice', [
            'proforma' => $record,
            'quotation' => $quotation,
            'branch' => $branch,
            'lines' => $lines,
            'outstanding' => max(0, (float) $record->total_amount - (float) $record->paid_amount),
        ])->setPaper('a4');

        \App\Support\PdfPageNumbers::stamp($pdf); // Page X of Y bottom right (client, 5 Nov 2026)
        return $pdf->stream(($record->number ?: 'proforma').'.pdf');
    }
}
