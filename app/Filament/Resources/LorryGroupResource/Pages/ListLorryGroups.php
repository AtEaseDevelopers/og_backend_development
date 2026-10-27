<?php

namespace App\Filament\Resources\LorryGroupResource\Pages;

use App\Filament\Resources\LorryGroupResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListLorryGroups extends ListRecords
{
    protected static string $resource = LorryGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
