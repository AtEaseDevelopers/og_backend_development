<?php

namespace App\Filament\Resources\OtherInvoiceResource\Pages;

use App\Filament\Resources\OtherInvoiceResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditOtherInvoice extends EditRecord
{
    use \App\Filament\Concerns\FormActionsInHeader;

    protected static string $resource = OtherInvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
            Actions\Action::make('pdf')->label('PDF')->icon('heroicon-o-document-arrow-down')->color('gray')
                ->url(fn () => OtherInvoiceResource::pdfUrl($this->getRecord()))->openUrlInNewTab(),
        ];
    }

    protected function afterSave(): void
    {
        $this->getRecord()->recalculate();
    }
}
