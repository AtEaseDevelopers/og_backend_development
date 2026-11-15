<?php

namespace App\Filament\Resources\JobSheetResource\Pages;

use App\Filament\Resources\JobSheetResource;
use Filament\Resources\Pages\EditRecord;

class EditJobSheet extends EditRecord
{
    use \App\Filament\Concerns\FormActionsInHeader;

    protected static string $resource = JobSheetResource::class;
}
