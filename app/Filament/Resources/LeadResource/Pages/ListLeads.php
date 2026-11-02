<?php

namespace App\Filament\Resources\LeadResource\Pages;

use App\Filament\Resources\LeadResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListLeads extends ListRecords
{
    use \App\Filament\Concerns\MasterDataBreadcrumbs;

    protected static string $resource = LeadResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()->label('New lead')];
    }
}
