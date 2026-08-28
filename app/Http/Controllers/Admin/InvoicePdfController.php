<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Billing\Models\Invoice;
use App\Support\CurrentCompany;
use App\Support\InvoiceDocumentData;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Facades\Filament;
use Symfony\Component\HttpFoundation\Response;

class InvoicePdfController
{
    public function __invoke(string $tenant, int|string $invoice): Response
    {
        $user = Filament::auth()->user();
        abort_unless($user, 403);

        $record = Invoice::query()->findOrFail($invoice);

        $currentTenant = Filament::getTenant() ?? CurrentCompany::get();
        if ($currentTenant && (int) $record->company_id !== (int) $currentTenant->getKey()) {
            abort(404);
        }

        if (method_exists($user, 'canAccessTenant') && $record->company) {
            abort_unless($user->canAccessTenant($record->company), 403);
        }

        $document = app(InvoiceDocumentData::class)->fromInvoice($record);

        $filename = ($record->number ?: 'invoice').'.pdf';

        $pdf = Pdf::loadView('pdf.invoice', [
            'document' => $document,
            'meta' => $document['meta'],
        ])->setPaper('a4');

        return $pdf->stream($filename);
    }
}
