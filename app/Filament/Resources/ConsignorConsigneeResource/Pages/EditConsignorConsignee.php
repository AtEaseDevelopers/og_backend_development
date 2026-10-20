<?php

namespace App\Filament\Resources\ConsignorConsigneeResource\Pages;

use App\Filament\Resources\ConsignorConsigneeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditConsignorConsignee extends EditRecord
{
    protected static string $resource = ConsignorConsigneeResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
