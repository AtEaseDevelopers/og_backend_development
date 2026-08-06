<?php

namespace App\Filament\Resources\BreakBulkResource\Pages;

use App\Filament\Resources\BreakBulkResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBreakBulks extends ListRecords
{
    protected static string $resource = BreakBulkResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
