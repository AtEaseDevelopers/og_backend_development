<?php

namespace App\Filament\Resources\SaLocationResource\Pages;

use App\Filament\Resources\SaLocationResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSaLocation extends EditRecord
{
    protected static string $resource = SaLocationResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
