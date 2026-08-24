<?php

namespace App\Filament\Resources\QuotationResource\Pages;

use App\Domains\Quotation\Models\Quotation;
use App\Filament\Resources\QuotationResource;
use App\Support\QuotationViewData;
use Filament\Actions;
use Filament\Facades\Filament;
use Filament\Infolists;
use Filament\Infolists\Infolist;
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

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\ViewEntry::make('quotation_view')
                    ->hiddenLabel()
                    ->view('filament.infolists.quotation-view')
                    ->viewData(fn (Quotation $record): array => app(QuotationViewData::class)->for($record)),
            ])
            ->columns(1);
    }
}
