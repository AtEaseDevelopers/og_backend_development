<?php

namespace App\Filament\Resources\LorryResource\Pages;

use App\Filament\Resources\LorryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListLorries extends ListRecords
{
    protected static string $resource = LorryResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
