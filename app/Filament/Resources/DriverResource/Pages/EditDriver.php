<?php

namespace App\Filament\Resources\DriverResource\Pages;

use App\Filament\Resources\DriverResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDriver extends EditRecord
{
    use \App\Filament\Concerns\MasterDataBreadcrumbs;

    protected static string $resource = DriverResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['login_email'] = \App\Models\User::query()->where('driver_id', $this->getRecord()->id)->value('email');

        return $data;
    }

    protected function afterSave(): void
    {
        $driver = $this->getRecord();
        DriverResource::syncLorry($driver);
        DriverResource::syncLogin($driver, $this->data['login_email'] ?? null, $this->data['login_password'] ?? null);
    }

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
