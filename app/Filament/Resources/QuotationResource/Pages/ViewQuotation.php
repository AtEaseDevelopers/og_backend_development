<?php

namespace App\Filament\Resources\QuotationResource\Pages;

use App\Domains\Quotation\Models\Quotation;
use App\Filament\Resources\QuotationResource;
use Filament\Actions;
use Filament\Facades\Filament;
use Filament\Resources\Pages\ViewRecord;

class ViewQuotation extends ViewRecord
{
    protected static string $resource = QuotationResource::class;

    protected function getHeaderActions(): array
    {
        /** @var Quotation $record */
        $record = $this->getRecord();

        return [
            Actions\Action::make('previewPdf')
                ->label('Preview PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->url(fn (): string => route('filament.admin.quotations.pdf', [
                    'tenant' => Filament::getTenant(),
                    'quotation' => $record,
                ]))
                ->openUrlInNewTab(),
            Actions\EditAction::make(),
        ];
    }
}
