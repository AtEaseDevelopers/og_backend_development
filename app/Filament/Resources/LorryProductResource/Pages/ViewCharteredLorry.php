<?php

namespace App\Filament\Resources\LorryProductResource\Pages;

use App\Filament\Resources\LorryProductResource;
use App\Filament\Resources\UomResource\Pages\ViewUom;

class ViewCharteredLorry extends ViewUom
{
    use \App\Filament\Concerns\MasterDataBreadcrumbs;

    protected static string $resource = LorryProductResource::class;
}
