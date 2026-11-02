<?php

namespace App\Filament\Resources\DriverResource\Pages;

use App\Filament\Resources\DriverResource;
use Filament\Resources\Pages\CreateRecord;

class CreateDriver extends CreateRecord
{
    use \App\Filament\Concerns\MasterDataBreadcrumbs;

    protected static string $resource = DriverResource::class;

    protected function afterCreate(): void
    {
        DriverResource::syncLorry($this->getRecord());
    }
}
