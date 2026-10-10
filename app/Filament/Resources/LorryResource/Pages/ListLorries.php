<?php

namespace App\Filament\Resources\LorryResource\Pages;

use App\Filament\Resources\LorryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use App\Filament\Concerns\HasExcelColumnFilters;

class ListLorries extends ListRecords
{
    use HasExcelColumnFilters;

    protected static string $resource = LorryResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
