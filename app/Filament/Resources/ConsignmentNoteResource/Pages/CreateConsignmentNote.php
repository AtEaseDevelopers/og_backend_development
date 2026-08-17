<?php

namespace App\Filament\Resources\ConsignmentNoteResource\Pages;

use App\Domains\Consignment\Models\ConsignmentNote;
use App\Enums\DocumentType;
use App\Filament\Resources\ConsignmentNoteResource;
use App\Filament\Resources\ConsignmentNoteResource\Schemas\ConsignmentNoteForm;
use App\Services\DocumentNumberingService;
use App\Support\CurrentCompany;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;
use Throwable;

class CreateConsignmentNote extends CreateRecord
{
    protected static string $resource = ConsignmentNoteResource::class;

    /** @var array<string, mixed> */
    protected array $pendingAssign = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data = ConsignmentNoteForm::applyPersistedTotals($data);

        $data['company_id'] = CurrentCompany::id() ?? $data['company_id'] ?? null;
        $data['source_branch_id'] = CurrentCompany::branchId() ?? $data['source_branch_id'] ?? null;

        abort_unless($data['company_id'] && $data['source_branch_id'], 422, 'Select a company first.');

        $this->pendingAssign = [
            'lorry_id' => $this->data['assign_lorry_id'] ?? null,
            'driver_id' => $this->data['assign_driver_id'] ?? null,
            'sub_lorry_ids' => $this->data['assign_sub_lorry_ids'] ?? [],
            'operating_date' => $this->data['assign_operating_date'] ?? now()->toDateString(),
            'task_type' => 'transfer',
        ];

        $data['number'] = app(DocumentNumberingService::class)->next(
            (int) $data['source_branch_id'],
            DocumentType::Csn
        );
        $data['qr_token'] = (string) Str::uuid();
        $data['tracking_token'] = Str::random(40);
        $data['created_by'] = auth()->id();

        $lines = $this->data['lines'] ?? [];
        foreach ($lines as &$line) {
            $line['unit_price'] = 0;
            $line['line_total'] = 0;
        }
        $data['lines'] = $lines;

        $data['issued_at'] = $data['issued_at'] ?? now()->toDateString();

        return $data;
    }

    protected function afterCreate(): void
    {
        if (empty($this->pendingAssign['lorry_id'])) {
            return;
        }

        /** @var ConsignmentNote $record */
        $record = $this->record;

        try {
            ConsignmentNoteResource::runAssignAndSubsheets($record, $this->pendingAssign);
        } catch (Throwable $e) {
            Notification::make()
                ->title('CSN created, but assign failed')
                ->body($e->getMessage())
                ->warning()
                ->send();
        }
    }
}
