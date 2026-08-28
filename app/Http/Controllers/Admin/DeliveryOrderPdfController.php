<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Dispatch\Models\DeliveryOrder;
use App\Support\CurrentCompany;
use App\Support\DeliveryOrderDocumentData;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Facades\Filament;
use Symfony\Component\HttpFoundation\Response;

class DeliveryOrderPdfController
{
    public function __invoke(string $tenant, int|string $deliveryOrder): Response
    {
        $user = Filament::auth()->user();
        abort_unless($user, 403);

        $record = DeliveryOrder::query()->findOrFail($deliveryOrder);

        $currentTenant = Filament::getTenant() ?? CurrentCompany::get();
        if ($currentTenant && (int) $record->company_id !== (int) $currentTenant->getKey()) {
            abort(404);
        }

        if (method_exists($user, 'canAccessTenant') && $record->company) {
            abort_unless($user->canAccessTenant($record->company), 403);
        }

        $document = app(DeliveryOrderDocumentData::class)->fromDeliveryOrder($record);

        $filename = ($record->number ?: 'delivery-order').'.pdf';

        $pdf = Pdf::loadView('pdf.delivery-order', [
            'document' => $document,
            'meta' => $document['meta'],
        ])->setPaper('a4');

        return $pdf->stream($filename);
    }
}
