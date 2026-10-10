<?php

namespace App\Filament\Resources\DropOffMinChargeResource\Pages;

use App\Filament\Resources\DropOffMinChargeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use App\Filament\Concerns\HasExcelColumnFilters;

class ListDropOffMinCharges extends ListRecords
{
    use HasExcelColumnFilters;

    protected static string $resource = DropOffMinChargeResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
