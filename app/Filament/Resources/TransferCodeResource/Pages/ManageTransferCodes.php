<?php

namespace App\Filament\Resources\TransferCodeResource\Pages;

use App\Filament\Resources\TransferCodeResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageTransferCodes extends ManageRecords
{
    protected static string $resource = TransferCodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
