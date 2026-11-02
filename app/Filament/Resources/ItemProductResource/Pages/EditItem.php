<?php

namespace App\Filament\Resources\ItemProductResource\Pages;

use App\Filament\Resources\ItemProductResource;
use App\Filament\Resources\UomResource\Pages\EditUom;

class EditItem extends EditUom
{
    use \App\Filament\Concerns\MasterDataBreadcrumbs;

    protected static string $resource = ItemProductResource::class;
}
