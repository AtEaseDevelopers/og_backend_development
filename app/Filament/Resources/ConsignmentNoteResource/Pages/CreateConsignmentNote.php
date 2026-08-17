<?php

namespace App\Filament\Resources\ConsignmentNoteResource\Pages;

use App\Domains\Consignment\Models\ConsignmentNote;
use App\Filament\Resources\ConsignmentNoteResource;
use App\Filament\Resources\ConsignmentNoteResource\Schemas\ConsignmentNoteForm;
use App\Support\CsnDocumentNumbers;
use App\Support\CurrentCompany;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateConsignmentNote extends CreateRecord
{
    protected static string $resource = ConsignmentNoteResource::class;

    /** @var list<array<string, mixed>> */
    protected array $pendingLines = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $matrixPayload = ConsignmentNoteForm::matrixPayloadFromForm($this->data);
        $this->pendingLines = $matrixPayload['lines'];
        $data['transport_charges'] = $matrixPayload['transport_charges'];

        $data = ConsignmentNoteForm::applyPersistedTotals($data);

        $data['company_id'] = CurrentCompany::id() ?? $data['company_id'] ?? null;
        $data['source_branch_id'] = CurrentCompany::branchId() ?? $data['source_branch_id'] ?? null;

        abort_unless($data['company_id'] && $data['source_branch_id'], 422, 'Select a company first.');

        $data['issued_at'] = $data['issued_at'] ?? now()->toDateString();

        $data = app(CsnDocumentNumbers::class)->assign($data, (int) $data['source_branch_id']);

        $data['qr_token'] = (string) Str::uuid();
        $data['tracking_token'] = Str::random(40);
        $data['created_by'] = auth()->id();

        return $data;
    }

    protected function afterCreate(): void
    {
        /** @var ConsignmentNote $record */
        $record = $this->record;

        foreach ($this->pendingLines as $line) {
            $record->lines()->create($line);
        }
    }
}
