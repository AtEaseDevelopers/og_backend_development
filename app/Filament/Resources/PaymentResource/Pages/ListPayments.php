<?php

namespace App\Filament\Resources\PaymentResource\Pages;

use App\Filament\Pages\CashBillCalculator;
use App\Filament\Resources\PaymentResource;
use App\Support\PaymentListingData;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ListPayments extends ListRecords
{
    protected static string $resource = PaymentResource::class;

    protected static string $view = 'filament.resources.payment-resource.pages.list-payments';

    public ?string $filterSearch = null;

    public ?string $filterType = null;

    public ?string $filterStatus = null;

    public function getHeading(): string
    {
        return 'Payment Listing';
    }

    public function getSubheading(): ?string
    {
        return null;
    }

    public function applyFilters(): void
    {
        $this->resetTable();
    }

    public function resetFilters(): void
    {
        $this->filterSearch = null;
        $this->filterType = null;
        $this->filterStatus = null;
        $this->resetTable();
    }

    /** @return array<string, string> */
    public function typeFilterOptions(): array
    {
        return PaymentListingData::typeFilterOptions();
    }

    /** @return array<string, string> */
    public function statusFilterOptions(): array
    {
        return PaymentListingData::statusFilterOptions();
    }

    public function getFilteredPaymentCount(): int
    {
        return (int) $this->getPaymentListingQuery()->count();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('createInvoicePayment')
                ->label('Create Invoice Payment')
                ->icon('heroicon-o-plus')
                ->url(fn (): string => PaymentResource::getUrl('create')),
            Actions\Action::make('createCashBillPayment')
                ->label('Create Cash Bill Payment')
                ->icon('heroicon-o-plus')
                ->url(fn (): string => CashBillCalculator::getUrl()),
        ];
    }

    protected function getPaymentListingQuery(): Builder
    {
        return $this->applyPaymentFilters(
            PaymentResource::getEloquentQuery()
                ->with([
                    'customer',
                    'consignmentNote',
                    'invoice',
                    'receipt',
                ])
        );
    }

    protected function applyPaymentFilters(Builder $query): Builder
    {
        return $query
            ->when(filled($this->filterSearch), function (Builder $builder): void {
                $needle = trim((string) $this->filterSearch);

                $builder->where(function (Builder $q) use ($needle): void {
                    $q->whereHas('customer', fn (Builder $customer) => $customer->where('company_name', 'like', '%'.$needle.'%'))
                        ->orWhereHas('consignmentNote', fn (Builder $csn) => $csn->where('number', 'like', '%'.$needle.'%'))
                        ->orWhereHas('receipt', fn (Builder $receipt) => $receipt->where('number', 'like', '%'.$needle.'%'))
                        ->orWhereHas('invoice', fn (Builder $invoice) => $invoice->where('number', 'like', '%'.$needle.'%'));

                    if (ctype_digit($needle)) {
                        $q->orWhere('id', (int) $needle);
                    }
                });
            })
            ->when(filled($this->filterStatus), fn (Builder $builder) => $builder->where('status', $this->filterStatus))
            ->when($this->filterType === 'cash_bill', function (Builder $builder): void {
                $builder->whereNull('invoice_id')
                    ->where(function (Builder $q): void {
                        $q->whereHas('consignmentNote', fn (Builder $csn) => $csn->where('billing_type', 'cash_bill'))
                            ->orWhere(function (Builder $inner): void {
                                $inner->whereNull('consignment_note_id')->where('method', '!=', 'cod');
                            });
                    });
            })
            ->when($this->filterType === 'cod', function (Builder $builder): void {
                $builder->where(function (Builder $q): void {
                    $q->where('method', 'cod')
                        ->orWhereHas('consignmentNote', fn (Builder $csn) => $csn->where('billing_type', 'cod'));
                });
            })
            ->when($this->filterType === 'term', fn (Builder $builder) => $builder->where(function (Builder $q): void {
                $q->whereNotNull('invoice_id')
                    ->orWhereHas('consignmentNote', fn (Builder $csn) => $csn->where('billing_type', 'term'));
            }));
    }

    protected function getTableQuery(): Builder
    {
        return $this->getPaymentListingQuery();
    }

    public function table(Table $table): Table
    {
        return parent::table($table)
            ->searchable(false)
            ->filters([])
            ->defaultSort('created_at', 'desc');
    }
}
