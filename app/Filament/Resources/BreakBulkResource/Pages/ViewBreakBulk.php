<?php

namespace App\Filament\Resources\BreakBulkResource\Pages;

use App\Domains\Delivery\Actions\AssignBreakBulkContinuation;
use App\Domains\MasterData\Models\Driver;
use App\Domains\MasterData\Models\Lorry;
use App\Filament\Resources\BreakBulkResource;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Throwable;

class ViewBreakBulk extends ViewRecord
{
    protected static string $resource = BreakBulkResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('assign')
                ->visible(fn () => $this->record->status === 'active')
                ->form([
                    Forms\Components\Select::make('replacement_lorry_id')
                        ->options(fn () => Lorry::query()->where('is_active', true)->pluck('registration_no', 'id'))
                        ->searchable(),
                    Forms\Components\Select::make('replacement_driver_id')
                        ->options(fn () => Driver::query()->where('is_active', true)->pluck('name', 'id'))
                        ->searchable(),
                    Forms\Components\DatePicker::make('operating_date')->default(now()),
                ])
                ->action(function (array $data) {
                    try {
                        app(AssignBreakBulkContinuation::class)->execute($this->record, $data, auth()->user());
                        Notification::make()->title('Assigned')->success()->send();
                        $this->refreshFormData(['status', 'handover_status', 'replacement_driver_id', 'replacement_lorry_id']);
                    } catch (Throwable $e) {
                        Notification::make()->title($e->getMessage())->danger()->send();
                    }
                }),
        ];
    }
}
