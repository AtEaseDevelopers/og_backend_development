<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    use \App\Filament\Concerns\MasterDataBreadcrumbs;

    protected static string $resource = UserResource::class;
}
