<?php

namespace App\Filament\Resources\UomResource\Pages;

use App\Filament\Actions\ImportPriceListAction;
use App\Filament\Concerns\HasExcelColumnFilters;
use App\Filament\Resources\UomResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListUoms extends ListRecords
{
    use \App\Filament\Concerns\MasterDataBreadcrumbs;

    use HasExcelColumnFilters;

    protected static string $resource = UomResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // the client's "O&G Customer Pricing Setup Template" workbooks: the default price file and/or the special
            // price file (one sheet per customer); a preview, then replace the price list or add / update only
            ImportPriceListAction::make()
                // one import for every category (it fills UOM, Items and Lorries)
                ->visible(fn () => static::getResource() === UomResource::class && UomResource::canCreate()),
            Actions\CreateAction::make(),
        ];
    }
}
