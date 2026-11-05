<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Consignment\Models\ConsignmentNote;
use App\Support\CsnDocumentData;
use App\Support\CurrentCompany;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Facades\Filament;
use Symfony\Component\HttpFoundation\Response;

class ConsignmentNotePdfController
{
    public function __invoke(string $tenant, int|string $consignmentNote): Response
    {
        $user = Filament::auth()->user();
        abort_unless($user, 403);

        $record = ConsignmentNote::query()->findOrFail($consignmentNote);

        $currentTenant = Filament::getTenant() ?? CurrentCompany::get();
        if ($currentTenant && (int) $record->company_id !== (int) $currentTenant->getKey()) {
            abort(404);
        }

        if (method_exists($user, 'canAccessTenant') && $record->company) {
            abort_unless($user->canAccessTenant($record->company), 403);
        }

        $document = app(CsnDocumentData::class)->fromConsignmentNote($record);

        $filename = ($record->number ?: 'consignment-note').'.pdf';

        // two layouts (client, 6 Nov 2026): the consignees at the bottom, or beside the consignor (?layout=side)
        $document['layout'] = request()->query('layout') === 'side' ? 'side' : 'stack';

        $pdf = Pdf::loadView('pdf.consignment-note', [
            'document' => $document,
            'meta' => $document['meta'],
        ])->setPaper('a4');

        \App\Support\PdfPageNumbers::stamp($pdf); // Page X of Y bottom right (client, 5 Nov 2026)
        return $pdf->stream($filename);
    }
}
