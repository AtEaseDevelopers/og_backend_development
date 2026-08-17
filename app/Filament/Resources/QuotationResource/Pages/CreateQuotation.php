<?php

namespace App\Filament\Resources\QuotationResource\Pages;

use App\Domains\MasterData\Models\Branch;
use App\Enums\DocumentType;
use App\Filament\Resources\QuotationResource;
use App\Filament\Resources\QuotationResource\Schemas\QuotationForm;
use App\Support\QuotationMatrix;
use App\Services\DocumentNumberingService;
use Filament\Resources\Pages\CreateRecord;

class CreateQuotation extends CreateRecord
{
    protected static string $resource = QuotationResource::class;

    /** @var list<string> */
    protected array $matrixColumns = [];

    /** @var list<array<string, mixed>> */
    protected array $matrixRows = [];

    public function form(\Filament\Forms\Form $form): \Filament\Forms\Form
    {
        return QuotationForm::configure($form);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $branch = Branch::query()->findOrFail($data['branch_id']);

        $this->matrixColumns = $data['matrix_columns'] ?? ['Seremban', 'Melaka', 'Johor'];
        $this->matrixRows = $data['matrix_rows'] ?? [];

        unset($data['matrix_columns'], $data['matrix_rows']);

        $data['number'] = app(DocumentNumberingService::class)->next($branch, DocumentType::Quotation);
        $data['created_by'] = auth()->id();
        $data['subtotal'] = 0;
        $data['total_amount'] = (float) ($data['tax_amount'] ?? 0);

        if (empty($data['title'])) {
            $data['title'] = 'Quotation Of Transport Charges';
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        app(QuotationMatrix::class)->sync(
            $this->record,
            $this->matrixColumns,
            $this->matrixRows,
        );

        $this->record->refresh();
    }
}
