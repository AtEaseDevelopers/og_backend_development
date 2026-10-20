<?php

namespace App\Filament\Resources\UomResource\Pages;

use App\Filament\Resources\UomResource;
use App\Support\UomPriceRows;
use Filament\Resources\Pages\CreateRecord;

class CreateUom extends CreateRecord
{
    protected static string $resource = UomResource::class;

    /** The default price rows from the form, saved after the product. */
    protected array $priceRows = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->priceRows = array_values($data['price_rows'] ?? []);
        unset($data['price_rows']);
        $data = static::getResource()::productData($data);

        $data['code'] = static::getResource()::uniqueCode((string) $data['name']);

        return $data;
    }

    protected function afterCreate(): void
    {
        UomPriceRows::sync($this->getRecord(), $this->priceRows);
        static::getResource()::productSaved($this->getRecord());
    }
}
