<?php

namespace App\Filament\Resources\StoreResource\Pages;

use App\Filament\Concerns\HasExcelColumnFilters;
use App\Filament\Resources\StoreResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListStores extends ListRecords
{
    use HasExcelColumnFilters;

    protected static string $resource = StoreResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()->label('New store')];
    }
}
