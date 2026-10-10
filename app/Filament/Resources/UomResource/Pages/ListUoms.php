<?php

namespace App\Filament\Resources\UomResource\Pages;

use App\Filament\Resources\UomResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use App\Filament\Concerns\HasExcelColumnFilters;

class ListUoms extends ListRecords
{
    use HasExcelColumnFilters;

    protected static string $resource = UomResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
