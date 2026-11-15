<?php

namespace App\Filament\Resources\LocationResource\Pages;

use App\Filament\Resources\LocationResource;
use Filament\Resources\Pages\CreateRecord;

class CreateLocation extends CreateRecord
{
    use \App\Filament\Concerns\FormActionsInHeader;

    use \App\Filament\Concerns\MasterDataBreadcrumbs;

    protected static string $resource = LocationResource::class;

    /** A deleted area of the same name is brought back with the new details (the name is unique in the table). */
    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        return \App\Domains\MasterData\Models\Location::restoreOrCreate(trim((string) $data['name']), \Illuminate\Support\Arr::except($data, ['name']));
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
