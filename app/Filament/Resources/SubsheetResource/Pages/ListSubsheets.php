<?php

namespace App\Filament\Resources\SubsheetResource\Pages;

use App\Filament\Resources\SubsheetResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSubsheets extends ListRecords
{
    protected static string $resource = SubsheetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
