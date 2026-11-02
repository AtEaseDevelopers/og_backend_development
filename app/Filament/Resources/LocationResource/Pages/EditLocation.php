<?php

namespace App\Filament\Resources\LocationResource\Pages;

use App\Filament\Resources\LocationResource;
use Filament\Resources\Pages\EditRecord;

class EditLocation extends EditRecord
{
    use \App\Filament\Concerns\MasterDataBreadcrumbs;

    protected static string $resource = LocationResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
