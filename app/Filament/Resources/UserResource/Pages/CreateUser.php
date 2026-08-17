<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Domains\MasterData\Models\Branch;
use App\Filament\Resources\UserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function afterCreate(): void
    {
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
