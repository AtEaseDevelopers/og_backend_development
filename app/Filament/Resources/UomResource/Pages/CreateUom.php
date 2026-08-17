<?php

namespace App\Filament\Resources\UomResource\Pages;

use App\Filament\Resources\UomResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateUom extends CreateRecord
{
    protected static string $resource = UomResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (blank($data['code'] ?? null) && filled($data['name'] ?? null)) {
            $data['code'] = Str::upper(Str::limit(Str::slug($data['name'], '-'), 50, ''));
        }

        return $data;
    }
}
