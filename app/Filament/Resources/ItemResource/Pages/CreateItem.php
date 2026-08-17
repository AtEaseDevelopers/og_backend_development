<?php

namespace App\Filament\Resources\ItemResource\Pages;

use App\Domains\MasterData\Models\ItemCategory;
use App\Filament\Resources\ItemResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateItem extends CreateRecord
{
    protected static string $resource = ItemResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (blank($data['code'] ?? null) && filled($data['name'] ?? null)) {
            $data['code'] = Str::upper(Str::limit(Str::slug($data['name'], '-'), 50, ''));
        }

        $data['item_category_id'] ??= ItemCategory::query()->where('name', 'Transport Items')->value('id');

        return $data;
    }
}
