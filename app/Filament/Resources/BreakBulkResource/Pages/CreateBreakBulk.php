<?php

namespace App\Filament\Resources\BreakBulkResource\Pages;

use App\Domains\Delivery\Actions\CreateBreakBulk as CreateBreakBulkAction;
use App\Domains\Delivery\Models\BreakBulk;
use App\Domains\Dispatch\Models\DeliveryOrder;
use App\Filament\Resources\BreakBulkResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateBreakBulk extends CreateRecord
{
    protected static string $resource = BreakBulkResource::class;

    public function getTitle(): string
    {
        return 'Create Break-Bulk Record';
    }

    public function getSubheading(): ?string
    {
        return 'Manually create a Break-Bulk record for reassignment at an intermediate location.';
    }

    protected function handleRecordCreation(array $data): Model
    {
        $do = DeliveryOrder::query()->findOrFail($data['delivery_order_id']);

        return app(CreateBreakBulkAction::class)->execute($do, $data, null, auth()->user());
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->title('Break-Bulk created')
            ->body('Assign a replacement driver or lorry on the details page.')
            ->success();
    }

    protected function getRedirectUrl(): string
    {
        /** @var BreakBulk $record */
        $record = $this->getRecord();

        return BreakBulkResource::getUrl('view', ['record' => $record]);
    }
}
