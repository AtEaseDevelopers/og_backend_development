<?php

namespace App\Filament\Resources\LorryProductResource\Pages;

use App\Filament\Resources\LorryProductResource;
use App\Filament\Resources\UomResource\Pages\ListUoms;

class ListLorries extends ListUoms
{
    protected static string $resource = LorryProductResource::class;
}
