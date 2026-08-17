<?php

namespace App\Filament\Resources\ConsignmentNoteResource\Pages;

use App\Filament\Resources\ConsignmentNoteResource;
use App\Filament\Resources\ConsignmentNoteResource\Schemas\ConsignmentNoteForm;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditConsignmentNote extends EditRecord
{
    protected static string $resource = ConsignmentNoteResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\ViewAction::make(), Actions\DeleteAction::make()];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return ConsignmentNoteForm::applyPersistedTotals($data);
    }
}
