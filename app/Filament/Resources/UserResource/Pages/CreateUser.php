<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    use \App\Filament\Concerns\FormActionsInHeader;

    use \App\Filament\Concerns\MasterDataBreadcrumbs;

    protected static string $resource = UserResource::class;

    /** The password typed for a new user is also kept as the encrypted copy shown on the edit page. */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (filled($this->data['password'] ?? null)) {
            $data['password_readable'] = (string) $this->data['password'];
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        UserResource::syncStaffRole($this->record, $this->data['role_id'] ?? null);

        // read from the page's data: the field is not a column, so the form's saved state leaves it out
        UserResource::syncBranches($this->record, (array) ($this->data['branch_ids'] ?? []));
    }
}
