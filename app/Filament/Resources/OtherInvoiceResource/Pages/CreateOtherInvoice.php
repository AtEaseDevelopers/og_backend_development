<?php

namespace App\Filament\Resources\OtherInvoiceResource\Pages;

use App\Filament\Resources\OtherInvoiceResource;
use Filament\Resources\Pages\CreateRecord;

class CreateOtherInvoice extends CreateRecord
{
    use \App\Filament\Concerns\FormActionsInHeader;

    protected static string $resource = OtherInvoiceResource::class;

    protected static bool $canCreateAnother = false;

    // the lines are saved after the invoice: the totals are taken from them then
    protected function afterCreate(): void
    {
        $this->getRecord()->recalculate();
    }

    protected function getRedirectUrl(): string
    {
        return OtherInvoiceResource::getUrl('index');
    }
}
