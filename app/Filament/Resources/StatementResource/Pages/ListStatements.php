<?php

namespace App\Filament\Resources\StatementResource\Pages;

use App\Filament\Resources\StatementResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use App\Filament\Concerns\HasExcelColumnFilters;

class ListStatements extends ListRecords
{
    use HasExcelColumnFilters;

    protected static string $resource = StatementResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
