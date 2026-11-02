<?php

namespace App\Filament\Resources\LorryProductResource\Pages;

use App\Filament\Resources\LorryProductResource;
use App\Filament\Resources\UomResource\Pages\CreateUom;

class CreateCharteredLorry extends CreateUom
{
    use \App\Filament\Concerns\MasterDataBreadcrumbs;

    protected static string $resource = LorryProductResource::class;
}
