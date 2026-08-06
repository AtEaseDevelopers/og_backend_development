<?php

namespace App\Filament\Resources\QuotationResource\Pages;

use App\Enums\DocumentType;
use App\Filament\Resources\QuotationResource;
use App\Services\DocumentNumberingService;
use Filament\Resources\Pages\CreateRecord;

class CreateQuotation extends CreateRecord
{
    protected static string $resource = QuotationResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['number'] = app(DocumentNumberingService::class)->next(
            (int) $data['branch_id'],
            DocumentType::Quotation
        );
        $data['created_by'] = auth()->id();

        $lines = $this->data['lines'] ?? [];
        $subtotal = collect($lines)->sum(fn ($l) => (float) ($l['line_total'] ?? 0));
        $data['subtotal'] = $subtotal;
        $data['total_amount'] = $subtotal + (float) ($data['tax_amount'] ?? 0);

        return $data;
    }
}
