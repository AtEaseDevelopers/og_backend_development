<?php

namespace App\Filament\Resources\LorryGroupResource\Pages;

use App\Filament\Resources\LorryGroupResource;
use Filament\Resources\Pages\CreateRecord;

class CreateLorryGroup extends CreateRecord
{
    use \App\Filament\Concerns\MasterDataBreadcrumbs;

    protected static string $resource = LorryGroupResource::class;
}
