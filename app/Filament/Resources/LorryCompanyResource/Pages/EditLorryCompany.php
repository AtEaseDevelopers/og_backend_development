<?php

namespace App\Filament\Resources\LorryCompanyResource\Pages;

use App\Filament\Resources\LorryCompanyResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditLorryCompany extends EditRecord
{
    protected static string $resource = LorryCompanyResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
