<?php

namespace App\Filament\Resources\QuotationResource\Pages;

use App\Filament\Resources\QuotationResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditQuotation extends EditRecord
{
    protected static string $resource = QuotationResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\ViewAction::make(), Actions\DeleteAction::make()];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $lines = $this->data['lines'] ?? [];
        $subtotal = collect($lines)->sum(fn ($l) => (float) ($l['line_total'] ?? 0));
        $data['subtotal'] = $subtotal;
        $data['total_amount'] = $subtotal + (float) ($data['tax_amount'] ?? 0);

        return $data;
    }
}
