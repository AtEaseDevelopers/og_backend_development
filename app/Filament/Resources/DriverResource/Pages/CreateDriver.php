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
        $driver = $this->getRecord();
        DriverResource::syncLorry($driver);
        DriverResource::syncLogin($driver, $this->data['login_email'] ?? null, $this->data['login_password'] ?? null);
    }
}
