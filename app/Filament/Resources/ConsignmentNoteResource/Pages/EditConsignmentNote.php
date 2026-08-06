<?php

namespace App\Filament\Resources\ConsignmentNoteResource\Pages;

use App\Filament\Resources\ConsignmentNoteResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditConsignmentNote extends EditRecord
{
    protected static string $resource = ConsignmentNoteResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\ViewAction::make(), Actions\DeleteAction::make()];
    }
}
