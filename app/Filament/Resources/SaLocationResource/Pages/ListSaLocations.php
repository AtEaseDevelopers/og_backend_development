<?php

namespace App\Filament\Resources\SaLocationResource\Pages;

use App\Filament\Resources\SaLocationResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use App\Filament\Concerns\HasExcelColumnFilters;

class ListSaLocations extends ListRecords
{
    use HasExcelColumnFilters;

    protected static string $resource = SaLocationResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
