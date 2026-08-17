<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Filament\Resources\CustomerResource;
use App\Filament\Resources\CustomerResource\Pages\Concerns\CreatesCustomerPortalUser;
use App\Filament\Resources\CustomerResource\Pages\Concerns\SyncsCustomerFormData;
use Filament\Resources\Pages\CreateRecord;

class CreateCustomer extends CreateRecord
{
    use CreatesCustomerPortalUser;
    use SyncsCustomerFormData;

    protected static string $resource = CustomerResource::class;

    protected function afterCreate(): void
    {
        $this->afterSave();
        $this->createPortalUser($this->getRecord());
    }
}
