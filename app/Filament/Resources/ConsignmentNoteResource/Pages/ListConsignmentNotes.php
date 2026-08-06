<?php

namespace App\Filament\Resources\ConsignmentNoteResource\Pages;

use App\Filament\Resources\ConsignmentNoteResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListConsignmentNotes extends ListRecords
{
    protected static string $resource = ConsignmentNoteResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
