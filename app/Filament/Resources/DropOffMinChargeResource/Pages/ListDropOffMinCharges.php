<?php

namespace App\Filament\Resources\DropOffMinChargeResource\Pages;

use App\Filament\Resources\DropOffMinChargeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDropOffMinCharges extends ListRecords
{
    protected static string $resource = DropOffMinChargeResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
