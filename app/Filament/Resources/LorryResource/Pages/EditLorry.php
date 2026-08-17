<?php

namespace App\Filament\Resources\LorryResource\Pages;

use App\Filament\Resources\LorryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditLorry extends EditRecord
{
    protected static string $resource = LorryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
