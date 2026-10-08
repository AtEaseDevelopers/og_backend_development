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
            ->with(['quotation.lines', 'quotation.destinations', 'quotation.portalEnquiry', 'quotation.salesperson', 'quotation.fromLocation', 'quotation.toLocation', 'customer', 'company'])
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
            'route' => $destinations->get($line->quotation_destination_id)?->consignee_name,
            'qty' => QuantityLabel::format($line->quantity, $line->uom),
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

        return $pdf->stream(($record->number ?: 'proforma').'.pdf');
    }
}
