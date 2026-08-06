<?php

namespace App\Filament\Resources\ConsignmentNoteResource\Pages;

use App\Domains\Consignment\Models\ConsignmentNote;
use App\Enums\CsnStatus;
use App\Filament\Resources\ConsignmentNoteResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Throwable;

class ViewConsignmentNote extends ViewRecord
{
    protected static string $resource = ConsignmentNoteResource::class;

    protected function getHeaderActions(): array
    {
        /** @var ConsignmentNote $record */
        $record = $this->getRecord();

        return [
            Actions\EditAction::make(),
            Actions\Action::make('assignLorry')
                ->label('Assign to Lorry')
                ->icon('heroicon-o-truck')
                ->visible(fn () => ! $record->deliveryOrder()->exists()
                    && $record->status !== CsnStatus::Cancelled
                    && $record->canAssignToLorry())
                ->form(ConsignmentNoteResource::assignLorryFormSchema())
                ->action(function (array $data) use ($record) {
                    try {
                        ConsignmentNoteResource::runAssignAndSubsheets($record, $data);
                    } catch (Throwable $e) {
                        Notification::make()->title($e->getMessage())->danger()->send();
                    }
                }),
            Actions\Action::make('addSubsheets')
                ->label('Add Subsheets')
                ->icon('heroicon-o-document-duplicate')
                ->color('warning')
                ->visible(fn () => $record->deliveryOrder?->job_sheet_id
                    && $record->status !== CsnStatus::Cancelled)
                ->form([
                    \Filament\Forms\Components\Select::make('sub_lorry_ids')
                        ->label('Lorries for subsheets')
                        ->options(fn () => ConsignmentNoteResource::lorryOptions(
                            excludeIds: array_filter([(int) $record->deliveryOrder?->lorry_id])
                        ))
                        ->multiple()
                        ->required()
                        ->searchable(),
                    ...ConsignmentNoteResource::subsheetOptionFields(),
                ])
                ->action(function (array $data) use ($record) {
                    try {
                        $created = ConsignmentNoteResource::createSubsheetsForLorries(
                            $record->fresh(['deliveryOrder']),
                            collect($data['sub_lorry_ids'] ?? []),
                            $data
                        );

                        Notification::make()
                            ->title($created ? "{$created} subsheet(s) created" : 'No subsheets created')
                            ->success()
                            ->send();
                    } catch (Throwable $e) {
                        Notification::make()->title($e->getMessage())->danger()->send();
                    }
                }),
        ];
    }
}
