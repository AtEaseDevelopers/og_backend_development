<?php

namespace App\Filament\Resources\ConsignmentNoteResource\Pages;

use App\Domains\Consignment\Models\ConsignmentNote;
use App\Filament\Resources\ConsignmentNoteResource;
use App\Filament\Resources\ConsignmentNoteResource\Schemas\ConsignmentNoteForm;
use App\Support\CsnTransportMatrix;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Throwable;

class EditConsignmentNote extends EditRecord
{
    protected static string $resource = ConsignmentNoteResource::class;

    /** @var list<array<string, mixed>> */
    protected array $pendingLines = [];

    /** @var array<string, mixed> */
    protected array $pendingAdditionalTask = [];

    protected function getHeaderActions(): array
    {
        return [Actions\ViewAction::make(), Actions\DeleteAction::make()];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var ConsignmentNote $record */
        $record = $this->getRecord();

        return array_merge($data, app(CsnTransportMatrix::class)->toFormState($record));
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $matrixPayload = ConsignmentNoteForm::matrixPayloadFromForm($this->data);
        $this->pendingLines = $matrixPayload['lines'];
        $data['transport_charges'] = $matrixPayload['transport_charges'];

        $this->pendingAdditionalTask = array_merge(
            ['sub_lorry_ids' => $this->data['sub_lorry_ids'] ?? []],
            ConsignmentNoteResource::additionalTaskPayload($this->data),
        );

        return ConsignmentNoteForm::applyPersistedTotals($data);
    }

    protected function afterSave(): void
    {
        /** @var ConsignmentNote $record */
        $record = $this->record;

        $record->lines()->delete();

        foreach ($this->pendingLines as $line) {
            $record->lines()->create($line);
        }

        if (empty($this->pendingAdditionalTask['needs_additional_task'])) {
            return;
        }

        $lorryIds = collect($this->pendingAdditionalTask['sub_lorry_ids'] ?? [])->filter();

        if ($lorryIds->isEmpty()) {
            return;
        }

        try {
            $created = ConsignmentNoteResource::createSubsheetsForLorries(
                $record->fresh(['deliveryOrder']),
                $lorryIds,
                $this->pendingAdditionalTask,
            );

            if ($created > 0) {
                Notification::make()
                    ->title("{$created} additional task(s) assigned")
                    ->success()
                    ->send();
            }
        } catch (Throwable $e) {
            Notification::make()
                ->title($e->getMessage())
                ->warning()
                ->send();
        }
    }
}
