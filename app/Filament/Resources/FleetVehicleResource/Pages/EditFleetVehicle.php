<?php

namespace App\Filament\Resources\FleetVehicleResource\Pages;

use App\Filament\Resources\FleetVehicleResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditFleetVehicle extends EditRecord
{
    use \App\Filament\Concerns\MasterDataBreadcrumbs;

    protected static string $resource = FleetVehicleResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
