<?php

namespace App\Filament\Resources\BreakBulkResource\Pages;

use App\Domains\Delivery\Actions\CreateBreakBulk as CreateBreakBulkAction;
use App\Domains\Dispatch\Models\DeliveryOrder;
use App\Filament\Resources\BreakBulkResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateBreakBulk extends CreateRecord
{
    protected static string $resource = BreakBulkResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $do = DeliveryOrder::query()->findOrFail($data['delivery_order_id']);

        return app(CreateBreakBulkAction::class)->execute($do, $data, null, auth()->user());
    }
}
