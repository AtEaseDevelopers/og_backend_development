<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Filament\Resources\CustomerResource;
use App\Filament\Resources\CustomerResource\Pages\Concerns\SyncsCustomerFormData;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCustomer extends EditRecord
{
    use SyncsCustomerFormData;

    protected static string $resource = CustomerResource::class;

    // no Delete (user, 25 Oct 2026): set a customer inactive from the customer list instead
    protected function getHeaderActions(): array
    {
        return [Actions\ViewAction::make()];
    }
}
