<?php

namespace App\Filament\Resources\VehicleMaintenanceResource\Pages;

use App\Filament\Resources\VehicleMaintenanceResource;
use Filament\Resources\Pages\ManageRecords;
use App\Filament\Concerns\HasExcelColumnFilters;

class ManageVehicleMaintenance extends ManageRecords
{
    use HasExcelColumnFilters;

    protected static string $resource = VehicleMaintenanceResource::class;
}
