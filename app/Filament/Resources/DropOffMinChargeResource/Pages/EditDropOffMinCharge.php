<?php

namespace App\Filament\Resources\DropOffMinChargeResource\Pages;

use App\Filament\Resources\DropOffMinChargeResource;
use Filament\Resources\Pages\EditRecord;

class EditDropOffMinCharge extends EditRecord
{
    use \App\Filament\Concerns\MasterDataBreadcrumbs;

    protected static string $resource = DropOffMinChargeResource::class;
}
