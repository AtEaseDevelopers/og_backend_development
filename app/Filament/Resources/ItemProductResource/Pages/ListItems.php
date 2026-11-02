<?php

namespace App\Filament\Resources\ItemProductResource\Pages;

use App\Filament\Resources\ItemProductResource;
use App\Filament\Resources\UomResource\Pages\ListUoms;

class ListItems extends ListUoms
{
    use \App\Filament\Concerns\MasterDataBreadcrumbs;

    protected static string $resource = ItemProductResource::class;
}
