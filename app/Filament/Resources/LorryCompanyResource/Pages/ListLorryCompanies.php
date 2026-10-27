<?php

namespace App\Filament\Resources\LorryCompanyResource\Pages;

use App\Filament\Resources\LorryCompanyResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListLorryCompanies extends ListRecords
{
    protected static string $resource = LorryCompanyResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
