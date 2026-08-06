<?php

namespace App\Filament\Resources\CommissionBatchResource\Pages;

use App\Domains\Commission\Actions\ConfirmCommissionBatch;
use App\Domains\Commission\Actions\GenerateCommissionPurchaseOrders;
use App\Filament\Resources\CommissionBatchResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Throwable;

class ViewCommissionBatch extends ViewRecord
{
    protected static string $resource = CommissionBatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('confirm')
                ->visible(fn () => $this->record->status === 'draft')
                ->requiresConfirmation()
                ->action(function () {
                    try {
                        app(ConfirmCommissionBatch::class)->execute($this->record, auth()->user());
                        Notification::make()->title('Confirmed')->success()->send();
                        $this->refreshFormData(['status', 'confirmed_at', 'confirmed_by']);
                    } catch (Throwable $e) {
                        Notification::make()->title($e->getMessage())->danger()->send();
                    }
                }),
            Actions\Action::make('generate_po')
                ->label('Generate PO/PI')
                ->visible(fn () => in_array($this->record->status, ['confirmed', 'po_generated'], true))
                ->action(function () {
                    try {
                        $pos = app(GenerateCommissionPurchaseOrders::class)->execute($this->record);
                        Notification::make()->title($pos->count().' PO/PI created')->success()->send();
                    } catch (Throwable $e) {
                        Notification::make()->title($e->getMessage())->danger()->send();
                    }
                }),
        ];
    }
}
