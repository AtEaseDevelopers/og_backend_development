<?php

namespace App\Filament\Resources\BranchResource\Pages;

use App\Filament\Resources\BranchResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBranch extends CreateRecord
{
    use \App\Filament\Concerns\MasterDataBreadcrumbs;

    protected static string $resource = BranchResource::class;
}
