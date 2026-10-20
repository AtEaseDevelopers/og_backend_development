<?php

namespace App\Filament\Resources\ConsignorConsigneeResource\Pages;

use App\Filament\Resources\ConsignorConsigneeResource;
use App\Support\CurrentCompany;
use Filament\Resources\Pages\CreateRecord;

class CreateConsignorConsignee extends CreateRecord
{
    protected static string $resource = ConsignorConsigneeResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['company_id'] ??= CurrentCompany::id();

        return $data;
    }
}
