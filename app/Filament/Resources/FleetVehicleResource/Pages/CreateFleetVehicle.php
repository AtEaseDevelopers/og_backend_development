<?php

namespace App\Filament\Resources\FleetVehicleResource\Pages;

use App\Filament\Resources\FleetVehicleResource;
use Filament\Resources\Pages\CreateRecord;

class CreateFleetVehicle extends CreateRecord
{
    use \App\Filament\Concerns\MasterDataBreadcrumbs;

    protected static string $resource = FleetVehicleResource::class;
}
