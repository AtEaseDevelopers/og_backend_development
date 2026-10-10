<?php

namespace App\Filament\Resources;

use App\Domains\Billing\Models\Invoice;
use App\Domains\Billing\Models\Payment;
use App\Domains\MasterData\Models\Customer;
use App\Enums\InvoiceStatus;
use App\Support\CurrentCompany;
use App\Filament\Resources\PaymentResource\Pages;
use App\Support\PaymentListingData;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static ?string $tenantOwnershipRelationshipName = 'company';

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Billing';

    protected static ?string $navigationLabel = 'Payments & Receipts';

    protected static ?int $navigationSort = 20;

    /**
     * Create Payment (invoice payment): the branch is the one being viewed (no field); a customer narrows the
     * invoices to their unpaid ones; several invoices can be paid at once (the amount starts as their total
     * outstanding and is split over them, oldest first, on save — see CreatePayment). The CSN field is gone: each
     * invoice brings its own CSN and order, so the order's paid amount / status follow the payment.
     */
    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('customer_id')
                ->label('Customer')
                ->options(fn () => Customer::query()
                    ->when(CurrentCompany::id(), fn ($q, $id) => $q->where('company_id', $id))
                    ->orderBy('company_name')
                    ->pluck('company_name', 'id'))
                ->searchable()
                ->live()
                ->afterStateUpdated(function (Forms\Set $set, Forms\Get $get): void {
                    // keep only the invoices of the customer picked
                    $keep = array_values(array_intersect(array_map('strval', (array) $get('invoice_ids')), array_map('strval', array_keys(static::invoiceOptions($get('customer_id'))))));
                    $set('invoice_ids', $keep);
                    $set('amount', static::outstandingTotal($keep) ?: null);
                }),
            Forms\Components\Select::make('invoice_ids')
                ->label('Invoices')
                ->helperText('Unpaid invoices (with the amount still outstanding). Pick one or more; the amount is split over them, oldest first.')
                ->options(fn (Forms\Get $get) => static::invoiceOptions($get('customer_id')))
                ->multiple()
                ->searchable()
                ->live()
                ->afterStateUpdated(fn ($state, Forms\Set $set) => $set('amount', static::outstandingTotal((array) $state) ?: null)),
            Forms\Components\TextInput::make('amount')
                ->numeric()
                ->minValue(0.01)
                ->prefix('RM')
                ->required()
                ->helperText(fn (Forms\Get $get) => ($total = static::outstandingTotal((array) $get('invoice_ids'))) > 0
                    ? 'Outstanding on the invoices picked: RM '.number_format($total, 2)
                    : null),
            Forms\Components\Select::make('method')
                ->options([
                    'cash' => 'Cash',
                    'ewallet' => 'eWallet',
                    'bank_transfer' => 'Bank Transfer',
                    'online' => 'Online Payment',
                    'counter' => 'Pay at Counter',
                    'cod' => 'COD',
                    'credit' => 'Credit',
                ])
                ->required(),
            Forms\Components\TextInput::make('reference'),
            Forms\Components\Textarea::make('remarks'),
        ]);
    }

    /**
     * Unpaid invoices of the company (of the customer when one is picked), oldest first:
     * "INV-0001 · KL-QT-0001 · Demo Trading · outstanding RM 120.00".
     *
     * @return array<int, string>
     */
    public static function invoiceOptions(mixed $customerId = null): array
    {
        return Invoice::query()
            ->with(['customer:id,company_name', 'quotation:id,number'])
            ->withSum(['payments as paid_sum' => fn ($q) => $q->where('status', 'completed')], 'amount')
            ->when(CurrentCompany::id(), fn ($q, $id) => $q->where('company_id', $id))
            ->when(filled($customerId), fn ($q) => $q->where('customer_id', $customerId))
            ->whereNotIn('status', [InvoiceStatus::Paid->value, InvoiceStatus::Cancelled->value])
            ->orderBy('invoice_date')
            ->orderBy('id')
            ->get()
            ->filter(fn (Invoice $invoice) => static::outstanding($invoice) > 0.004)
            ->mapWithKeys(fn (Invoice $invoice) => [$invoice->id => collect([
                $invoice->number,
                $invoice->quotation?->number,
                $invoice->customer?->company_name,
                'outstanding RM '.number_format(static::outstanding($invoice), 2),
            ])->filter()->implode(' · ')])
            ->all();
    }

    /** What is still to be paid on an invoice (its total less its completed payments). */
    public static function outstanding(Invoice $invoice): float
    {
        $paid = $invoice->paid_sum ?? $invoice->payments()->where('status', 'completed')->sum('amount');

        return max(0, round((float) $invoice->total_amount - (float) $paid, 2));
    }

    /** @param  array<int, int|string>  $invoiceIds */
    public static function outstandingTotal(array $invoiceIds): float
    {
        $ids = array_values(array_filter(array_map('intval', $invoiceIds)));

        return $ids === [] ? 0.0 : round(Invoice::query()->whereIn('id', $ids)->get()->sum(fn (Invoice $invoice) => static::outstanding($invoice)), 2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('Payment Number')
                    ->sortable()
                    ->formatStateUsing(fn ($state, Payment $record): string => PaymentListingData::paymentNumber($record))
                    ->weight('medium'),
                Tables\Columns\TextColumn::make('customer.company_name')
                    ->label('Customer')
                    ->default('—'),
                Tables\Columns\TextColumn::make('consignmentNote.number')
                    ->label('Related CSN')
                    ->default('—'),
                Tables\Columns\TextColumn::make('method')
                    ->label('Type')
                    ->formatStateUsing(fn ($state, Payment $record): string => PaymentListingData::typeLabel($record)),
                Tables\Columns\TextColumn::make('amount')
                    ->label('Payment')
                    ->money('MYR')
                    ->alignRight()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn ($state, Payment $record): string => PaymentListingData::statusLabel($record))
                    ->color(fn ($state, Payment $record): string => PaymentListingData::statusColor($record)),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(10)
            ->filters([])
            ->actions([])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPayments::route('/'),
            'create' => Pages\CreatePayment::route('/create'),
        ];
    }
}
