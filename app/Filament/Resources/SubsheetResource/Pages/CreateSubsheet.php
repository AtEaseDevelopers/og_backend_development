<?php

namespace App\Filament\Resources\SubsheetResource\Pages;

use App\Domains\Dispatch\Actions\CreateSubsheet as CreateSubsheetAction;
use App\Domains\Dispatch\Models\DeliveryOrder;
use App\Filament\Resources\SubsheetResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateSubsheet extends CreateRecord
{
    protected static string $resource = SubsheetResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $do = DeliveryOrder::query()->findOrFail($data['delivery_order_id']);

        return app(CreateSubsheetAction::class)->execute($do, $data);
    }
}
