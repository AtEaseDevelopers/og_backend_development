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
