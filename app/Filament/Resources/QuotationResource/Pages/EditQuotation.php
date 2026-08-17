<?php

namespace App\Filament\Resources\QuotationResource\Pages;

use App\Domains\Quotation\Models\Quotation;
use App\Filament\Resources\QuotationResource;
use App\Filament\Resources\QuotationResource\Schemas\QuotationForm;
use App\Support\QuotationMatrix;
use Filament\Actions;
use Filament\Facades\Filament;
use Filament\Resources\Pages\EditRecord;

class EditQuotation extends EditRecord
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

    protected function getHeaderActions(): array
    {
        /** @var Quotation $record */
        $record = $this->getRecord();

        return [
            Actions\Action::make('previewPdf')
                ->label('Preview PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->url(fn (): string => route('filament.admin.quotations.pdf', [
                    'tenant' => Filament::getTenant(),
                    'quotation' => $record,
                ]))
                ->openUrlInNewTab(),
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Quotation $record */
        $record = $this->getRecord();
        $record->loadMissing(['customer.pics', 'destinations', 'lines', 'toLocation', 'fromLocation', 'creator']);
        $customer = $record->customer;
        $matrixState = app(QuotationMatrix::class)->toFormState($record);

        return array_merge($data, $matrixState, [
            'customer_code_display' => $customer?->code,
            'customer_name_display' => $customer?->company_name,
            'customer_email_display' => $customer?->email,
            'customer_phone_display' => $customer?->phone,
            'customer_address' => $record->customer_address ?: $customer?->address,
            'consignor_brn' => $record->consignor_brn ?: $customer?->brn,
            'quoted_at' => $record->quoted_at ?? $record->created_at?->toDateString(),
            'history_destination' => $record->consignee_name
                ?: $record->toLocation?->name
                ?: collect($matrixState['matrix_columns'] ?? [])->first(),
        ]);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->matrixColumns = $data['matrix_columns'] ?? ['Seremban', 'Melaka', 'Johor'];
        $this->matrixRows = $data['matrix_rows'] ?? [];

        unset($data['matrix_columns'], $data['matrix_rows']);

        $data['subtotal'] = (float) $this->record->subtotal;
        $data['total_amount'] = (float) $this->record->subtotal + (float) ($data['tax_amount'] ?? 0);

        return $data;
    }

    protected function afterSave(): void
    {
        app(QuotationMatrix::class)->sync(
            $this->record,
            $this->matrixColumns,
            $this->matrixRows,
        );

        $this->record->refresh();
    }
}
