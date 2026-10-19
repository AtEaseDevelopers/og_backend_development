<?php

namespace App\Filament\Resources;

use App\Domains\Quotation\Actions\CreateOrderFromQuote;
use App\Domains\Quotation\Models\Quote;
use App\Filament\Pages\CreateQuotation;
use App\Filament\Pages\OrderDetail;
use App\Filament\Resources\QuoteResource\Pages;
use App\Support\CurrentCompany;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Throwable;

/**
 * Operations → Quotation (user, 20 Oct 2026): quotations on their own table. Created with Create quotation
 * (the Create order page). "Accept by customer" records who accepted it and makes the order (the Order column shows
 * it); "Duplicate" copies a quotation into a new one to change.
 */
class QuoteResource extends Resource
{
    protected static ?string $model = Quote::class;

    protected static bool $isScopedToTenant = false;

    protected static ?string $slug = 'quotations';

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Operations';

    protected static ?string $navigationLabel = 'Quotation';

    protected static ?string $modelLabel = 'quotation';

    // above Orders
    protected static ?int $navigationSort = -1;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['customer:id,code,company_name', 'lead:id,name', 'orderEnquiry:id,order_number'])
            ->when(CurrentCompany::id(), fn (Builder $q, $id) => $q->where('company_id', $id));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('number')->label('Quotation no.')->weight('semibold')->sortable(),
                Tables\Columns\TextColumn::make('party')
                    ->label('Customer / lead')
                    ->state(fn (Quote $record) => $record->customer
                        ? trim(($record->customer->code ? $record->customer->code.' — ' : '').$record->customer->company_name)
                        : $record->lead?->name)
                    ->description(fn (Quote $record) => $record->customer ? null : 'Lead')
                    ->wrap(),
                Tables\Columns\TextColumn::make('form.customer_pic_name')->label('PIC')->placeholder('—'),
                Tables\Columns\TextColumn::make('quote_date')->label('Quotation date')->date('d/m/Y')->sortable(),
                Tables\Columns\TextColumn::make('records')
                    ->label('Consignor & consignee')
                    ->state(fn (Quote $record) => count((array) $record->pairs))
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('total_amount')->label('Total')->money('MYR')->alignEnd()->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (Quote $record) => $record->statusLabel())
                    ->color(fn (string $state) => $state === Quote::ORDERED ? 'success' : 'warning')
                    ->description(fn (Quote $record) => filled($record->accepted_by) ? 'Accepted by '.$record->accepted_by : null),
                // the order made from the quotation
                Tables\Columns\TextColumn::make('orderEnquiry.order_number')
                    ->label('Order')
                    ->placeholder('—')
                    ->url(fn (Quote $record) => $record->orderEnquiry ? OrderDetail::urlFor('enquiry', $record->orderEnquiry->id) : null)
                    ->color('primary')
                    ->weight('medium'),
            ])
            ->defaultSort('number', 'desc')
            ->recordUrl(fn (Quote $record) => $record->isPending()
                ? CreateQuotation::getUrl(['quote' => $record->id])
                : ($record->orderEnquiry ? OrderDetail::urlFor('enquiry', $record->orderEnquiry->id) : null))
            ->actions([
                // the customer agreed: who accepted it, then the order is made from it
                Tables\Actions\Action::make('accept')
                    ->label('Accept by customer')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn (Quote $record) => $record->isPending())
                    ->modalHeading(fn (Quote $record) => 'Customer accepted quotation '.$record->number)
                    ->modalDescription('The quotation becomes an order with its consignor & consignee, products and prices; a lead becomes a customer. The quoted prices are kept as the customer\'s special prices.')
                    ->form([
                        Forms\Components\TextInput::make('accepted_by')
                            ->label('Accepted by (customer contact)')
                            ->default(fn (Quote $record) => $record->form['customer_pic_name'] ?? null)
                            ->required()
                            ->maxLength(255),
                    ])
                    ->modalSubmitActionLabel('Accept & create order')
                    ->action(fn (Quote $record, array $data) => static::createOrders(collect([$record]), $data['accepted_by'] ?? null)),
                Tables\Actions\Action::make('duplicate')
                    ->label('Duplicate')
                    ->icon('heroicon-o-document-duplicate')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalHeading(fn (Quote $record) => 'Duplicate quotation '.$record->number)
                    ->modalDescription('A new quotation with the same customer / lead, consignor & consignee, products and prices, dated today. It opens to be changed (e.g. another lead).')
                    ->modalSubmitActionLabel('Duplicate')
                    ->action(function (Quote $record, $livewire): void {
                        $copy = $record->duplicate(auth()->id());
                        Notification::make()->title('Quotation '.$copy->number.' created from '.$record->number)->success()->send();
                        $livewire->redirect(CreateQuotation::getUrl(['quote' => $copy->id]), navigate: false);
                    }),
                // the quotation in the earlier quotation PDF layout (QuotePdfController)
                Tables\Actions\Action::make('pdf')
                    ->label('PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('gray')
                    ->url(fn (Quote $record) => static::pdfUrl($record))
                    ->openUrlInNewTab(),
                Tables\Actions\Action::make('edit')
                    ->label('Edit')
                    ->icon('heroicon-o-pencil-square')
                    ->url(fn (Quote $record) => CreateQuotation::getUrl(['quote' => $record->id]))
                    ->visible(fn (Quote $record) => $record->isPending()),
                Tables\Actions\Action::make('order')
                    ->label('View order')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (Quote $record) => $record->orderEnquiry ? OrderDetail::urlFor('enquiry', $record->orderEnquiry->id) : null)
                    ->visible(fn (Quote $record) => ! $record->isPending() && $record->orderEnquiry !== null),
                Tables\Actions\DeleteAction::make()->visible(fn (Quote $record) => $record->isPending()),
            ])
            ->checkIfRecordIsSelectableUsing(fn (Quote $record) => $record->isPending())
            ->bulkActions([
                // the customer agreed: each ticked quotation becomes an order (its products, details and prices)
                Tables\Actions\BulkAction::make('createOrder')
                    ->label('Create order')
                    ->icon('heroicon-o-shopping-cart')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->modalHeading('Customer accepted the ticked quotations')
                    ->modalDescription('Each quotation becomes an order with its consignor & consignee, products and prices; a lead becomes a customer. The quoted prices are kept as the customer\'s special prices.')
                    ->form([
                        Forms\Components\TextInput::make('accepted_by')->label('Accepted by (customer contact)')->required()->maxLength(255),
                    ])
                    ->modalSubmitActionLabel('Accept & create order')
                    ->action(fn (Collection $records, array $data) => static::createOrders($records, $data['accepted_by'] ?? null))
                    ->deselectRecordsAfterCompletion(),
            ]);
    }

    public static function pdfUrl(Quote $record): string
    {
        return route('filament.admin.quotes.pdf', ['tenant' => Filament::getTenant(), 'quote' => $record]);
    }

    /** Each quotation becomes an order (accepted by the customer contact given). */
    public static function createOrders(\Illuminate\Support\Collection $records, ?string $acceptedBy): void
    {
        $done = [];
        $failed = [];

        foreach ($records as $quote) {
            try {
                $result = app(CreateOrderFromQuote::class)->execute($quote, auth()->user(), $acceptedBy);
                $done[] = $quote->number.' → order '.$result['enquiry']->orderNumber();
            } catch (Throwable $e) {
                $failed[] = $quote->number.': '.$e->getMessage();
            }
        }

        Notification::make()
            ->title(count($done).' order(s) created')
            ->body(implode("\n", array_merge($done, $failed)))
            ->{$failed === [] ? 'success' : 'warning'}()
            ->persistent()
            ->send();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListQuotes::route('/'),
        ];
    }
}
