<?php

namespace App\Filament\Resources\LorryResource\Pages;

use App\Filament\Resources\LorryResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateLorry extends CreateRecord
{
    protected static string $resource = LorryResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (blank($data['code'] ?? null) && filled($data['name'] ?? null)) {
            $data['code'] = Str::upper(Str::limit(Str::slug($data['name'], '-'), 50, ''));
        }

        return $data;
    }
}
