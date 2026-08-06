<?php

namespace App\Filament\Resources\JobSheetResource\Pages;

use App\Filament\Resources\JobSheetResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewJobSheet extends ViewRecord
{
    protected static string $resource = JobSheetResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\EditAction::make()];
    }
}
