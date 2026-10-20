<?php

namespace App\Filament\Resources\ItemProductResource\Pages;

use App\Filament\Resources\ItemProductResource;
use App\Filament\Resources\UomResource\Pages\ListUoms;

class ListItems extends ListUoms
{
    protected static string $resource = ItemProductResource::class;
}
