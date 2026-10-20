<?php

namespace App\Filament\Resources\UomResource\Pages;

use App\Filament\Resources\UomResource;
use App\Support\UomPriceRows;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditUom extends EditRecord
{
    protected static string $resource = UomResource::class;

    /** The default price rows from the form, saved after the product. */
    protected array $priceRows = [];

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['price_rows'] = UomPriceRows::fromTiers(
            $this->getRecord()->rateTiers()->whereNull('customer_code')->with('location:id,name')->get()
        );

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->priceRows = array_values($data['price_rows'] ?? []);
        unset($data['price_rows']);

        return static::getResource()::productData($data);
    }

    protected function afterSave(): void
    {
        UomPriceRows::sync($this->getRecord(), $this->priceRows);
        static::getResource()::productSaved($this->getRecord());
    }
}
