<?php

namespace App\Filament\Resources\LocationResource\Pages;

use App\Filament\Resources\LocationResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListLocations extends ListRecords
{
    use \App\Filament\Concerns\MasterDataBreadcrumbs;

    protected static string $resource = LocationResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
