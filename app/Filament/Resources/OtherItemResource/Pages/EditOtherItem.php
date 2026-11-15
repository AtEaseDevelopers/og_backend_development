<?php

namespace App\Filament\Resources\OtherItemResource\Pages;

use App\Filament\Resources\OtherItemResource;
use Filament\Resources\Pages\EditRecord;

class EditOtherItem extends EditRecord
{
    use \App\Filament\Concerns\FormActionsInHeader;
    use \App\Filament\Concerns\MasterDataBreadcrumbs;

    protected static string $resource = OtherItemResource::class;

    protected function getRedirectUrl(): string
    {
        return OtherItemResource::getUrl('index');
    }
}
