<?php

namespace App\Filament\Resources\SaLocationResource\Pages;

use App\Filament\Resources\SaLocationResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSaLocation extends CreateRecord
{
    use \App\Filament\Concerns\MasterDataBreadcrumbs;

    protected static string $resource = SaLocationResource::class;
}
