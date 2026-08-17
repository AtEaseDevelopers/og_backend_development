<?php

namespace App\Filament\Resources\FleetVehicleResource\Pages;

use App\Filament\Resources\FleetVehicleResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFleetVehicles extends ListRecords
{
    protected static string $resource = FleetVehicleResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
