<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Domains\MasterData\Models\Branch;
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

        $branchId = $this->form->getState()['branch_id'] ?? null;

        if (! $branchId) {
            return;
        }

        $branch = Branch::query()->find($branchId);

        if ($branch) {
            $this->record->assignToBranch($branch, isDefault: true);
        }
    }
}
