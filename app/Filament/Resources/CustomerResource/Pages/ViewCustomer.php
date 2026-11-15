<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Filament\Actions\ImportPriceListAction;
use App\Filament\Resources\CustomerResource;
use App\Filament\Resources\UomResource;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\URL;

class ViewCustomer extends ViewRecord
{
    protected static string $resource = CustomerResource::class;

    // each card has its own Edit (user, 25 Oct 2026); the status (active / inactive) is changed in the Account card or
    // on the edit page, no separate button. An inactive customer is not offered on Create order, Create quotation or the
    // CSN form.
    protected function getHeaderActions(): array
    {
        return [
            // the Special Price workbook can be imported from here too (user, 2 Nov 2026)
            ImportPriceListAction::make()->visible(fn () => UomResource::canCreate()),
            // the customer's accepted quotation as a PDF, for the customer to view (client, 16 Nov 2026): its items are
            // the customer's special prices; the latest one, or picked when there are several
            Actions\Action::make('exportQuotationPdf')
                ->label('Export quotation (PDF)')
                ->icon('heroicon-o-document-arrow-down')
                ->color('gray')
                ->visible(fn () => $this->acceptedQuotes()->isNotEmpty())
                ->form(fn () => $this->acceptedQuotes()->count() > 1 ? [
                    Forms\Components\Select::make('quote_id')->label('Quotation')
                        ->options($this->acceptedQuotes()->mapWithKeys(fn ($q) => [$q->id => $q->number.' · '.\Illuminate\Support\Carbon::parse($q->quote_date)->format('d/m/Y')])->all())
                        ->default($this->acceptedQuotes()->first()->id)
                        ->required(),
                ] : [])
                ->modalHeading('Export quotation (PDF)')
                ->modalSubmitActionLabel('Open PDF')
                ->action(function (array $data, $livewire): void {
                    $quote = $this->acceptedQuotes()->firstWhere('id', (int) ($data['quote_id'] ?? 0)) ?? $this->acceptedQuotes()->first();
                    $livewire->js('window.open('.json_encode(\App\Filament\Resources\QuoteResource::pdfUrl($quote)).', "_blank")');
                }),
            Actions\Action::make('portalOrderLink')
                ->label('Portal ordering link')
                ->icon('heroicon-o-link')
                ->color('gray')
                ->modalHeading('Customer portal ordering link')
                ->modalDescription('Send this link to the customer. After signing in with a login approved for this company, it opens the order form for this account.')
                ->fillForm(fn () => ['url' => URL::signedRoute('portal.customer-order-link', ['customer' => $this->record->getKey()])])
                ->form([
                    Forms\Components\TextInput::make('url')->label('Link')->readOnly()
                        ->extraInputAttributes(['onclick' => 'this.select()']),
                ])
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Close'),
        ];
    }

    /** The customer's accepted quotations, the latest first. */
    protected function acceptedQuotes(): \Illuminate\Support\Collection
    {
        return $this->acceptedQuotesCache ??= \App\Domains\Quotation\Models\Quote::query()
            ->where('customer_id', $this->record->getKey())
            ->where('status', \App\Domains\Quotation\Models\Quote::ACCEPTED)
            ->orderByDesc('quote_date')->orderByDesc('id')
            ->get(['id', 'number', 'quote_date', 'company_id', 'branch_id']);
    }

    private ?\Illuminate\Support\Collection $acceptedQuotesCache = null;

    // no "View …" title on the customer page (user, 28 Oct 2026), but the breadcrumbs stay (user, 4 Nov 2026):
    // Master Data › Customers › the company name
    public function getBreadcrumbs(): array
    {
        try {
            return [
                \App\Filament\Pages\MasterDataHub::getUrl() => 'Master Data',
                CustomerResource::getUrl('index') => 'Customers',
                '#' => (string) ($this->record->company_name ?: $this->record->code),
            ];
        } catch (\Throwable) {
            return [];
        }
    }

    public function getHeading(): string
    {
        return '';
    }
}
