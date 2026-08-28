<?php

namespace App\Support;

use App\Domains\Dispatch\Models\DeliveryOrder;

class DeliveryOrderDocumentData
{
    /**
     * @return array<string, mixed>
     */
    public function fromDeliveryOrder(DeliveryOrder $deliveryOrder): array
    {
        $deliveryOrder->loadMissing([
            'consignmentNote',
            'lorry',
            'driver',
        ]);

        $csn = $deliveryOrder->consignmentNote;

        if (! $csn) {
            throw new \InvalidArgumentException('Delivery order has no linked consignment note.');
        }

        $document = app(CsnDocumentData::class)->fromConsignmentNote($csn);

        $document['document_title'] = 'Delivery Order';
        $document['hide_pricing'] = true;
        $document['meta']['number'] = $deliveryOrder->number;
        $document['meta']['reference_label'] = 'CSN No';
        $document['meta']['reference_number'] = $csn->number;
        $document['meta']['lorry_number'] = $deliveryOrder->lorry?->registration_no
            ?: ($document['meta']['lorry_number'] ?? '—');

        if ($deliveryOrder->driver?->name) {
            $document['footer']['remarks'] = trim(collect([
                $document['footer']['remarks'] ?? null,
                'Driver: '.$deliveryOrder->driver->name,
            ])->filter()->implode("\n"));
        }

        return $document;
    }
}
