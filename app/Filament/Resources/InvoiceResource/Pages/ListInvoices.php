<?php

namespace App\Filament\Resources\InvoiceResource\Pages;

use App\Domains\Billing\Actions\ConsolidateTermInvoices;
use App\Domains\MasterData\Models\Branch;
use App\Domains\Consignment\Models\ConsignmentNote;
use App\Domains\MasterData\Models\Customer;
use App\Enums\CsnBillingType;
use App\Support\CurrentCompany;
use App\Filament\Resources\InvoiceResource;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use App\Filament\Concerns\HasExcelColumnFilters;

class ListInvoices extends ListRecords
{
    use HasExcelColumnFilters;

    protected static string $resource = InvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('consolidateTerm')
                ->label('Month-end Term Billing')
                ->icon('heroicon-o-calendar')
                ->form([
                    Forms\Components\TextInput::make('billing_month')
                        ->label('Billing month (YYYY-MM)')
                        ->default(now()->format('Y-m'))
                        ->required(),
                    Forms\Components\Select::make('branch_id')
                        ->label('Branch (optional)')
                        ->options(Branch::query()->pluck('name', 'id'))
                        ->searchable(),
                    // only these customers' delivered Term CSNs (blank = every customer)
                    Forms\Components\Select::make('customer_ids')
                        ->label('Customers (optional)')
                        ->helperText('Leave blank to bill every customer.')
                        // credit customers, and any customer with Term-billed CSNs
                        ->options(fn () => Customer::query()
                            ->where(fn ($q) => $q->where('is_credit', true)
                                ->orWhereIn('id', ConsignmentNote::query()->where('billing_type', CsnBillingType::Term)->select('customer_id')))
                            ->when(CurrentCompany::id(), fn ($q, $id) => $q->where('company_id', $id))
                            ->orderBy('company_name')
                            ->get()
                            ->mapWithKeys(fn (Customer $c) => [$c->id => trim(($c->code ? $c->code.' — ' : '').$c->company_name)]))
                        ->multiple()
                        ->searchable(),
                ])
                ->action(function (array $data) {
                    $invoices = app(ConsolidateTermInvoices::class)->execute(
                        $data['billing_month'],
                        filled($data['branch_id'] ?? null) ? (int) $data['branch_id'] : null,
                        $data['customer_ids'] ?? [],
                    );
                    Notification::make()
                        ->title('Generated '.$invoices->count().' term invoice(s)')
                        ->success()
                        ->send();
                }),
        ];
    }
}
