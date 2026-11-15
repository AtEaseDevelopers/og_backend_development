<?php

namespace App\Filament\Resources\OtherItemResource\Pages;

use App\Filament\Concerns\HasExcelColumnFilters;
use App\Filament\Resources\OtherItemResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListOtherItems extends ListRecords
{
    use \App\Filament\Concerns\MasterDataBreadcrumbs;
    use HasExcelColumnFilters;

    protected static string $resource = OtherItemResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()->label('New other item')];
    }
}
