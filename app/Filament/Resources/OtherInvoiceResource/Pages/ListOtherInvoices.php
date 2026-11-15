<?php

namespace App\Filament\Resources\OtherInvoiceResource\Pages;

use App\Filament\Concerns\HasExcelColumnFilters;
use App\Filament\Resources\OtherInvoiceResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListOtherInvoices extends ListRecords
{
    // a filter on every column, as the other lists
    use HasExcelColumnFilters;

    protected static string $resource = OtherInvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()->label('New other invoice')];
    }
}
