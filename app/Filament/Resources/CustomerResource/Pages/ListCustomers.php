<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Filament\Resources\CustomerResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use App\Filament\Concerns\HasExcelColumnFilters;

class ListCustomers extends ListRecords
{
    use \App\Filament\Concerns\MasterDataBreadcrumbs;

    use HasExcelColumnFilters;

    protected static string $resource = CustomerResource::class;

    // no "Import customer list" button (user, 25 Oct 2026): the client's customer lists are entered by a query run
    // with CustomerInformationImporter instead
    protected function getHeaderActions(): array
    {
        return [
            // the customers' Special Price workbook (user, 4 Nov 2026): checked on upload, then replace or add
            \App\Filament\Actions\ImportPriceListAction::make('importSpecialPrice')
                ->label('Import special price')
                ->modalHeading('Import special price')
                ->modalDescription('Upload the O&G Customer Pricing Setup — Special Price workbook (one sheet per customer, named by its debtor code). The file is checked first; then choose whether it replaces the current special prices of the customers in the file or only adds to them. A price agreed in an accepted quotation always stays.')
                ->visible(fn () => \App\Filament\Resources\UomResource::canCreate()),
            Actions\CreateAction::make(),
        ];
    }
}
