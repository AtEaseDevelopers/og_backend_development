<?php

namespace App\Filament\Resources\ItemProductResource\Pages;

use App\Filament\Resources\ItemProductResource;
use App\Filament\Resources\UomResource\Pages\ViewUom;

class ViewItem extends ViewUom
{
    use \App\Filament\Concerns\MasterDataBreadcrumbs;

    protected static string $resource = ItemProductResource::class;
}
