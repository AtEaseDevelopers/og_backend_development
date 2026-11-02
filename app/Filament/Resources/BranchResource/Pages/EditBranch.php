<?php

namespace App\Filament\Resources\BranchResource\Pages;

use App\Filament\Resources\BranchResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBranch extends EditRecord
{
    use \App\Filament\Concerns\MasterDataBreadcrumbs;

    protected static string $resource = BranchResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\ViewAction::make(), Actions\DeleteAction::make()];
    }
}
