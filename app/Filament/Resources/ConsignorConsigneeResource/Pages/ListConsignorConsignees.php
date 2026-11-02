<?php

namespace App\Filament\Resources\ConsignorConsigneeResource\Pages;

use App\Filament\Resources\ConsignorConsigneeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListConsignorConsignees extends ListRecords
{
    use \App\Filament\Concerns\MasterDataBreadcrumbs;

    protected static string $resource = ConsignorConsigneeResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
