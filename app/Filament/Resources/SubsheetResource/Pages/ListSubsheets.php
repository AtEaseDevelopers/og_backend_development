<?php

namespace App\Filament\Resources\SubsheetResource\Pages;

use App\Filament\Resources\SubsheetResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use App\Filament\Concerns\HasExcelColumnFilters;

class ListSubsheets extends ListRecords
{
    use HasExcelColumnFilters;

    protected static string $resource = SubsheetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
