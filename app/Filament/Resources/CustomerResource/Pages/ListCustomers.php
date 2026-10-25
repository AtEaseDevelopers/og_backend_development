<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Filament\Resources\CustomerResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use App\Filament\Concerns\HasExcelColumnFilters;

class ListCustomers extends ListRecords
{
    use HasExcelColumnFilters;

    protected static string $resource = CustomerResource::class;

    // no "Import customer list" button (user, 25 Oct 2026): the client's customer lists are entered by a query run
    // with CustomerInformationImporter instead
    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
